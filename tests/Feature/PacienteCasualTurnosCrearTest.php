<?php

namespace Tests\Feature;

use App\Models\Actividad;
use App\Models\ActividadPaciente;
use App\Models\Horario;
use App\Models\PacienteCasual;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PacienteCasualTurnosCrearTest extends TestCase
{
    use RefreshDatabase;

    private const PRECIO_PRUEBA = 15000.0;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('app.max_turnos_gimnasio', 8);
        Config::set('app.max_turnos_pilates', 6);
        Config::set('precios.clase_prueba', self::PRECIO_PRUEBA);

        foreach ([Actividad::GIMNASIO, Actividad::PILATES] as $idActividad) {
            $horario = Horario::create(['hora_inicio' => '10:00:00', 'franja' => 'M']);
            Actividad::findOrFail($idActividad)->horarios()->attach($horario->id);
        }

        // Lunes, para asegurar días hábiles disponibles en la semana actual.
        Carbon::setTestNow('2026-06-01 08:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_arranca_en_modalidad_gympass_gimnasio_por_defecto(): void
    {
        Livewire::test('pacientes-casuales.turnos.crear')
            ->assertSet('modalidad', 'gympass_gimnasio');
    }

    public static function modalidadesGympass(): array
    {
        return [
            'gimnasio' => ['gympass_gimnasio', Actividad::GIMNASIO],
            'pilates' => ['gympass_pilates', Actividad::PILATES],
        ];
    }

    #[DataProvider('modalidadesGympass')]
    public function test_gympass_registra_turno_gratuito_para_la_actividad_elegida(string $modalidad, int $idActividadEsperada): void
    {
        $paciente = PacienteCasual::create([
            'nombre' => 'Ana',
            'apellido' => 'Gómez',
            'telefono' => '1111111111',
        ]);

        Livewire::test('pacientes-casuales.turnos.crear')
            ->set('idPacienteSeleccionado', $paciente->id)
            ->set('modalidad', $modalidad)
            ->set('turnosSeleccionados.0.fecha', '2026-06-01')
            ->set('turnosSeleccionados.0.hora', '10:00')
            ->call('almacenarTurnos')
            ->assertRedirect(route('actividades-pacientes.inicio'));

        $actPac = ActividadPaciente::where('id_paciente_casual', $paciente->id)->firstOrFail();

        $this->assertSame($idActividadEsperada, (int) $actPac->id_actividad);
        $this->assertSame(0.0, (float) $actPac->total_a_pagar);
        $this->assertTrue($actPac->pago_completado);
        $this->assertTrue($actPac->esGympass());
        $this->assertFalse($actPac->esPrueba());
    }

    public static function modalidadesPrueba(): array
    {
        return [
            'gimnasio' => ['prueba_gimnasio', Actividad::GIMNASIO],
            'pilates' => ['prueba_pilates', Actividad::PILATES],
        ];
    }

    #[DataProvider('modalidadesPrueba')]
    public function test_clase_de_prueba_registra_turno_pago_con_el_precio_de_config_para_cualquier_actividad(string $modalidad, int $idActividadEsperada): void
    {
        $paciente = PacienteCasual::create([
            'nombre' => 'Luis',
            'apellido' => 'Fernández',
            'telefono' => '2222222222',
        ]);

        Livewire::test('pacientes-casuales.turnos.crear')
            ->set('idPacienteSeleccionado', $paciente->id)
            ->set('modalidad', $modalidad)
            ->set('turnosSeleccionados.0.fecha', '2026-06-01')
            ->set('turnosSeleccionados.0.hora', '10:00')
            ->call('almacenarTurnos')
            ->assertRedirect(route('actividades-pacientes.inicio'));

        $actPac = ActividadPaciente::where('id_paciente_casual', $paciente->id)->firstOrFail();

        $this->assertSame($idActividadEsperada, (int) $actPac->id_actividad);
        $this->assertSame(self::PRECIO_PRUEBA, (float) $actPac->total_a_pagar);
        $this->assertFalse($actPac->pago_completado);
        $this->assertTrue($actPac->esPrueba());
        $this->assertFalse($actPac->esGympass());
    }

    public function test_clase_de_prueba_no_permite_agregar_mas_de_un_turno(): void
    {
        $paciente = PacienteCasual::create([
            'nombre' => 'Carla',
            'apellido' => 'Ruiz',
            'telefono' => '3333333333',
        ]);

        $componente = Livewire::test('pacientes-casuales.turnos.crear')
            ->set('idPacienteSeleccionado', $paciente->id)
            ->set('modalidad', 'prueba_pilates')
            ->call('agregarNuevoTurno');

        $this->assertCount(1, $componente->get('turnosSeleccionados'));
    }

    public function test_gympass_permite_agregar_un_turno_por_cada_dia_habil_disponible(): void
    {
        $paciente = PacienteCasual::create([
            'nombre' => 'Nico',
            'apellido' => 'Pereyra',
            'telefono' => '4444444444',
        ]);

        $componente = Livewire::test('pacientes-casuales.turnos.crear')
            ->set('idPacienteSeleccionado', $paciente->id)
            ->set('modalidad', 'gympass_gimnasio')
            ->call('agregarNuevoTurno')
            ->call('agregarNuevoTurno')
            ->call('agregarNuevoTurno')
            ->call('agregarNuevoTurno')
            ->call('agregarNuevoTurno');

        // Lunes a viernes con turno de 10:00 disponible = 5 días posibles.
        $this->assertCount(5, $componente->get('turnosSeleccionados'));
    }

    public function test_cambiar_de_modalidad_limpia_los_turnos_ya_seleccionados(): void
    {
        $paciente = PacienteCasual::create([
            'nombre' => 'Mario',
            'apellido' => 'Díaz',
            'telefono' => '5555555555',
        ]);

        Livewire::test('pacientes-casuales.turnos.crear')
            ->set('idPacienteSeleccionado', $paciente->id)
            ->set('modalidad', 'gympass_gimnasio')
            ->set('turnosSeleccionados.0.fecha', '2026-06-01')
            ->set('turnosSeleccionados.0.hora', '10:00')
            ->set('modalidad', 'gympass_pilates')
            ->assertSet('turnosSeleccionados.0.fecha', '')
            ->assertSet('turnosSeleccionados.0.hora', '');
    }
}
