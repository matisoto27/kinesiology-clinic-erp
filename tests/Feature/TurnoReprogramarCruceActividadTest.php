<?php

namespace Tests\Feature;

use App\Exceptions\ReglaNegocioException;
use App\Models\Actividad;
use App\Models\ActividadPaciente;
use App\Models\Horario;
use App\Models\Paciente;
use App\Models\Turno;
use App\Services\TurnoService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Livewire\Livewire;
use Tests\TestCase;

class TurnoReprogramarCruceActividadTest extends TestCase
{
    use RefreshDatabase;

    private const ORIGINAL = '2026-06-05 10:00:00';

    private const DESTINO = '2026-06-04 10:00:00';

    private TurnoService $turnoService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->turnoService = app(TurnoService::class);
        Config::set('app.max_turnos_gimnasio', 8);
        Config::set('app.max_turnos_pilates', 6);
        Carbon::setTestNow('2026-06-01 08:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_dual_operativo_crea_el_turno_reprogramado_en_el_par(): void
    {
        ['pilates' => $pilates, 'gimnasio' => $gimnasio, 'turno' => $turno] = $this->crearDualConTurnoPilates();

        $reprogramado = $this->turnoService->reprogramar(
            $turno,
            Carbon::parse(self::DESTINO),
            Actividad::GIMNASIO
        );

        $this->assertSame($gimnasio->id, $reprogramado->id_act_pac);
        $this->assertSame($turno->id, $reprogramado->id_turno_original);
        $this->assertSame($pilates->id, $turno->fresh()->id_act_pac);
        $this->assertSame(self::DESTINO, $reprogramado->fecha_hora->format('Y-m-d H:i:s'));
    }

    public function test_misma_actividad_deja_el_turno_reprogramado_en_el_origen(): void
    {
        ['pilates' => $pilates, 'turno' => $turno] = $this->crearDualConTurnoPilates();

        $reprogramado = $this->turnoService->reprogramar(
            $turno,
            Carbon::parse(self::DESTINO)
        );

        $this->assertSame($pilates->id, $reprogramado->id_act_pac);
        $this->assertSame($turno->id, $reprogramado->id_turno_original);
    }

    public function test_simple_no_puede_cruzar_de_actividad(): void
    {
        $this->asociarHora(Actividad::PILATES, '10:00:00');
        $this->asociarHora(Actividad::GIMNASIO, '10:00:00');

        $inscripcion = $this->crearInscripcion(Actividad::PILATES, cantSesiones: 8);
        $turno = $this->crearTurnoAa($inscripcion, self::ORIGINAL);

        $this->expectException(ReglaNegocioException::class);
        $this->expectExceptionMessage('Solo se puede cambiar de actividad en una inscripción dual activa.');

        $this->turnoService->reprogramar(
            $turno,
            Carbon::parse(self::DESTINO),
            Actividad::GIMNASIO
        );
    }

    public function test_pata_cerrada_no_puede_cruzar(): void
    {
        ['gimnasio' => $gimnasio, 'turno' => $turno] = $this->crearDualConTurnoPilates();
        $gimnasio->update(['cant_sesiones' => 0]);

        $this->expectException(ReglaNegocioException::class);
        $this->expectExceptionMessage('Solo se puede cambiar de actividad en una inscripción dual activa.');

        $this->turnoService->reprogramar(
            $turno->fresh(['actividadPaciente.actPacDual']),
            Carbon::parse(self::DESTINO),
            Actividad::GIMNASIO
        );
    }

    public function test_origen_cerrado_no_puede_cruzar(): void
    {
        ['pilates' => $pilates, 'turno' => $turno] = $this->crearDualConTurnoPilates();
        $pilates->update(['cant_sesiones' => 0]);

        $this->expectException(ReglaNegocioException::class);
        $this->expectExceptionMessage('Solo se puede cambiar de actividad en una inscripción dual activa.');

        $this->turnoService->reprogramar(
            $turno->fresh(['actividadPaciente.actPacDual']),
            Carbon::parse(self::DESTINO),
            Actividad::GIMNASIO
        );
    }

    public function test_rechaza_si_el_cupo_de_la_destino_esta_lleno(): void
    {
        Config::set('app.max_turnos_gimnasio', 1);

        ['turno' => $turno] = $this->crearDualConTurnoPilates();

        $otro = $this->crearInscripcion(Actividad::GIMNASIO, cantSesiones: 8);
        $this->crearTurnoAa($otro, self::DESTINO, estado: 'Ausente');

        $this->expectException(ReglaNegocioException::class);
        $this->expectExceptionMessage('El horario seleccionado ya no tiene cupo disponible.');

        $this->turnoService->reprogramar(
            $turno,
            Carbon::parse(self::DESTINO),
            Actividad::GIMNASIO
        );
    }

    public function test_livewire_muestra_selector_solo_en_dual_operativo(): void
    {
        ['turno' => $turnoDual] = $this->crearDualConTurnoPilates();

        Livewire::test('turnos.inicio', ['actividades' => Actividad::all()])
            ->call('abrirModal', $turnoDual->id)
            ->call('marcarAusenteAviso')
            ->assertSee('Actividad del nuevo turno');

        $simple = $this->crearTurnoAa(
            $this->crearInscripcion(Actividad::PILATES, cantSesiones: 8),
            '2026-06-03 10:00:00'
        );

        Livewire::test('turnos.inicio', ['actividades' => Actividad::all()])
            ->call('abrirModal', $simple->id)
            ->call('marcarAusenteAviso')
            ->assertDontSee('Actividad del nuevo turno');
    }

    public function test_livewire_cruzar_a_gimnasio_deja_el_turno_reprogramado_en_el_par(): void
    {
        ['gimnasio' => $gimnasio, 'turno' => $turno] = $this->crearDualConTurnoPilates();

        Livewire::test('turnos.inicio', ['actividades' => Actividad::all()])
            ->call('abrirModal', $turno->id)
            ->call('marcarAusenteAviso')
            ->set('idActividadDestino', Actividad::GIMNASIO)
            ->set('fechaSeleccionada', '2026-06-04')
            ->set('horaSeleccionada', '10:00:00')
            ->call('actualizar');

        $this->assertTrue(
            Turno::query()
                ->where('id_act_pac', $gimnasio->id)
                ->where('id_turno_original', $turno->id)
                ->where('fecha_hora', self::DESTINO)
                ->exists()
        );
    }

    /**
     * @return array{pilates: ActividadPaciente, gimnasio: ActividadPaciente, turno: Turno}
     */
    private function crearDualConTurnoPilates(): array
    {
        $this->asociarHora(Actividad::GIMNASIO, '10:00:00');
        $this->asociarHora(Actividad::PILATES, '10:00:00');

        $paciente = $this->crearPaciente();
        $gimnasio = $this->crearInscripcion(Actividad::GIMNASIO, cantSesiones: 8, paciente: $paciente, total: 30000);
        $pilates = $this->crearInscripcion(Actividad::PILATES, cantSesiones: 4, paciente: $paciente, total: 0);

        $gimnasio->update([
            'id_act_pac_dual' => $pilates->id,
            'frecuencia_total_dual' => 3,
        ]);
        $pilates->update([
            'id_act_pac_dual' => $gimnasio->id,
            'frecuencia_total_dual' => 3,
            'pago_completado' => true,
        ]);

        $turno = $this->crearTurnoAa($pilates, self::ORIGINAL);

        return [
            'pilates' => $pilates->fresh(),
            'gimnasio' => $gimnasio->fresh(),
            'turno' => $turno->fresh(),
        ];
    }

    private function crearInscripcion(
        int $idActividad,
        int $cantSesiones,
        ?Paciente $paciente = null,
        float $total = 10000
    ): ActividadPaciente {
        return ActividadPaciente::create([
            'id_actividad' => $idActividad,
            'id_paciente' => ($paciente ?? $this->crearPaciente())->id,
            'cant_sesiones' => $cantSesiones,
            'total_a_pagar' => $total,
            'pago_completado' => $total <= 0,
        ]);
    }

    private function crearTurnoAa(
        ActividadPaciente $inscripcion,
        string $fechaHora,
        string $estado = 'Ausente avisó'
    ): Turno {
        return Turno::create([
            'id_act_pac' => $inscripcion->id,
            'fecha_hora' => $fechaHora,
            'estado' => $estado,
        ]);
    }

    private function asociarHora(int $idActividad, string $hora): void
    {
        $actividad = Actividad::findOrFail($idActividad);
        $horario = Horario::create([
            'hora_inicio' => $hora,
            'franja' => 'M',
        ]);
        $actividad->horarios()->attach($horario->id);
    }

    private function crearPaciente(): Paciente
    {
        return Paciente::create([
            'dni' => (string) random_int(10000000, 99999999),
            'nombre' => 'Nombre',
            'apellido' => 'Apellido',
            'fecha_nac' => '1990-01-01',
            'domicilio' => 'Calle 123',
            'telefono' => '1111111111',
            'profesion' => 'Profesion',
            'actividad_fisica' => 'Ninguna',
            'es_adulto_mayor' => false,
        ]);
    }
}
