<?php

namespace Tests\Feature;

use App\Models\Actividad;
use App\Models\ActividadPaciente;
use App\Models\Horario;
use App\Models\HorarioPacienteFijo;
use App\Models\Paciente;
use App\Models\PacienteFijo;
use App\Models\Turno;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Livewire\Livewire;
use Tests\TestCase;

class TurnosDisponiblesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('app.max_turnos_gimnasio', 8);
        Config::set('app.max_turnos_pilates', 6);
        Carbon::setTestNow('2026-08-17 10:00:00');
        Carbon::setLocale('es');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_slots_estructurales_incluye_ocupados_y_calcula_libres(): void
    {
        $gimnasio = $this->asociarHoras(Actividad::GIMNASIO, ['09:00:00', '09:30:00', '10:00:00', '11:00:00']);

        $this->ocuparFijos(Actividad::GIMNASIO, dia: 1, hora: '09:30:00', cantidad: 6);
        $this->ocuparFijos(Actividad::GIMNASIO, dia: 1, hora: '10:00:00', cantidad: 7);
        $this->ocuparFijos(Actividad::GIMNASIO, dia: 1, hora: '11:00:00', cantidad: 8);

        $libres = collect($gimnasio->slotsEstructurales())
            ->mapWithKeys(fn (array $slot) => [$slot['dia_semana'].'_'.$slot['hora'] => $slot['libres']]);

        $this->assertSame(8, $libres['Lunes_09:00']);
        $this->assertSame(2, $libres['Lunes_09:30']);
        $this->assertSame(1, $libres['Lunes_10:00']);
        $this->assertSame(0, $libres['Lunes_11:00']);
        $this->assertCount(20, $gimnasio->slotsEstructurales());
    }

    public function test_mensual_copia_semaforo_incluyendo_horarios_sin_cupo(): void
    {
        $this->asociarHoras(Actividad::GIMNASIO, ['09:00:00', '09:30:00', '10:00:00', '11:00:00']);

        $this->ocuparFijos(Actividad::GIMNASIO, dia: 1, hora: '09:30:00', cantidad: 6);
        $this->ocuparFijos(Actividad::GIMNASIO, dia: 1, hora: '10:00:00', cantidad: 7);
        $this->ocuparFijos(Actividad::GIMNASIO, dia: 1, hora: '11:00:00', cantidad: 8);

        $texto = $this->copiarMensaje([
            'modo' => 'mensual',
            'idActividad' => Actividad::GIMNASIO,
        ]);

        $this->assertStringContainsString('el cupo de cada uno', $texto);
        $this->assertStringContainsString('• 09:00 hs 🟢 8 lugares disponibles', $texto);
        $this->assertStringContainsString('• 09:30 hs 🟡 2 lugares disponibles', $texto);
        $this->assertStringContainsString('• 10:00 hs 🟡 1 lugar disponible', $texto);
        $this->assertStringContainsString('• 11:00 hs 🔴 Sin cupo', $texto);
        $this->assertStringNotContainsString('turnos disponibles para', $texto);
    }

    public function test_mensual_usa_fijos_y_ignora_turnos_puntuales(): void
    {
        $this->asociarHoras(Actividad::GIMNASIO, ['10:00:00']);
        $this->crearTurnosEnSlot(Actividad::GIMNASIO, '2026-08-18 10:00:00', 8);

        $texto = $this->copiarMensaje([
            'modo' => 'mensual',
            'idActividad' => Actividad::GIMNASIO,
        ]);

        $this->assertStringContainsString('• 10:00 hs 🟢 8 lugares disponibles', $texto);
        $this->assertStringNotContainsString('Sin cupo', $texto);
    }

    public function test_mensual_solo_en_punto_omite_media_hora(): void
    {
        $this->asociarHoras(Actividad::GIMNASIO, ['09:00:00', '09:30:00']);

        $texto = $this->copiarMensaje([
            'modo' => 'mensual',
            'idActividad' => Actividad::GIMNASIO,
            'soloEnPunto' => true,
        ]);

        $this->assertStringContainsString('• 09:00 hs', $texto);
        $this->assertStringNotContainsString('09:30', $texto);
    }

    public function test_mensual_filtra_actividades_y_rechaza_kine(): void
    {
        $this->asociarHoras(Actividad::QUIROPRAXIA, ['10:00:00']);

        $componente = $this->componente();

        $this->assertEqualsCanonicalizing(
            [Actividad::GIMNASIO, Actividad::PILATES],
            $componente->instance()->actividadesOpciones->pluck('id')->all()
        );

        $componente->set('modo', 'fechas');
        $this->assertTrue(
            $componente->instance()->actividadesOpciones->pluck('id')->contains(Actividad::QUIROPRAXIA)
        );

        $componente->set('idActividad', Actividad::QUIROPRAXIA);
        $componente->set('modo', 'mensual');

        $this->assertNull($componente->get('idActividad'));

        $componente
            ->set('idActividad', Actividad::QUIROPRAXIA)
            ->assertSet('idActividad', Actividad::QUIROPRAXIA)
            ->call('copiarTurnos')
            ->assertNotDispatched('copiar-portapapeles');
    }

    public function test_fechas_omite_slot_lleno_y_no_usa_semaforo(): void
    {
        $this->asociarHoras(Actividad::GIMNASIO, ['10:00:00', '11:00:00']);
        $this->crearTurnosEnSlot(Actividad::GIMNASIO, '2026-08-18 10:00:00', 8);

        $texto = $this->copiarMensaje([
            'modo' => 'fechas',
            'idActividad' => Actividad::GIMNASIO,
            'fechaDesde' => '2026-08-18',
            'fechaHasta' => '2026-08-18',
        ]);

        $this->assertStringContainsString('turnos disponibles para *Gimnasio*', $texto);
        $this->assertStringContainsString('• 11:00 hs', $texto);
        $this->assertStringNotContainsString('10:00', $texto);
        $this->assertStringNotContainsString('🟢', $texto);
        $this->assertStringNotContainsString('Sin cupo', $texto);
        $this->assertStringNotContainsString('el cupo de cada uno', $texto);
    }

    public function test_fechas_ignora_ocupacion_estructural(): void
    {
        $this->asociarHoras(Actividad::GIMNASIO, ['10:00:00']);
        $this->ocuparFijos(Actividad::GIMNASIO, dia: 2, hora: '10:00:00', cantidad: 8);

        $texto = $this->copiarMensaje([
            'modo' => 'fechas',
            'idActividad' => Actividad::GIMNASIO,
            'fechaDesde' => '2026-08-18',
            'fechaHasta' => '2026-08-18',
        ]);

        $this->assertStringContainsString('• 10:00 hs', $texto);
        $this->assertStringNotContainsString('Sin cupo', $texto);
    }

    public function test_fechas_rechaza_rango_invertido_y_mayor_a_cuatro_semanas(): void
    {
        $this->asociarHoras(Actividad::GIMNASIO, ['10:00:00']);

        $this->componente()
            ->set('modo', 'fechas')
            ->set('idActividad', Actividad::GIMNASIO)
            ->set('fechaDesde', '2026-08-20')
            ->set('fechaHasta', '2026-08-18')
            ->call('copiarTurnos')
            ->assertNotDispatched('copiar-portapapeles');

        $this->componente()
            ->set('modo', 'fechas')
            ->set('idActividad', Actividad::GIMNASIO)
            ->set('fechaDesde', '2026-08-17')
            ->set('fechaHasta', '2026-09-20')
            ->call('copiarTurnos')
            ->assertNotDispatched('copiar-portapapeles');
    }

    public function test_fechas_permite_kine_y_lista_todas_las_actividades(): void
    {
        $this->asociarHoras(Actividad::QUIROPRAXIA, ['10:00:00']);

        $componente = $this->componente()->set('modo', 'fechas');

        $this->assertTrue(
            $componente->instance()->actividadesOpciones->pluck('id')->contains(Actividad::QUIROPRAXIA)
        );

        $texto = $this->copiarMensaje([
            'modo' => 'fechas',
            'idActividad' => Actividad::QUIROPRAXIA,
            'fechaDesde' => '2026-08-18',
            'fechaHasta' => '2026-08-18',
        ]);

        $this->assertStringContainsString('turnos disponibles para *Quiropraxia*', $texto);
        $this->assertStringContainsString('• 10:00 hs', $texto);
    }

    public function test_fechas_solo_en_punto_omite_media_hora(): void
    {
        $this->asociarHoras(Actividad::GIMNASIO, ['10:00:00', '10:30:00']);

        $texto = $this->copiarMensaje([
            'modo' => 'fechas',
            'idActividad' => Actividad::GIMNASIO,
            'soloEnPunto' => true,
            'fechaDesde' => '2026-08-18',
            'fechaHasta' => '2026-08-18',
        ]);

        $this->assertStringContainsString('• 10:00 hs', $texto);
        $this->assertStringNotContainsString('10:30', $texto);
    }

    private function copiarMensaje(array $estado): string
    {
        $componente = $this->componente();

        foreach ($estado as $propiedad => $valor) {
            $componente->set($propiedad, $valor);
        }

        $texto = null;

        $componente
            ->call('copiarTurnos')
            ->assertDispatched('copiar-portapapeles', function (string $evento, array $params) use (&$texto) {
                $texto = $params['texto'] ?? null;

                return is_string($texto);
            });

        $this->assertIsString($texto);

        return $texto;
    }

    private function componente()
    {
        return Livewire::test('actividades.turnos-disponibles', [
            'actividades' => Actividad::all(),
        ]);
    }

    private function asociarHoras(int $idActividad, array $horas): Actividad
    {
        $actividad = Actividad::findOrFail($idActividad);

        foreach ($horas as $hora) {
            $horario = Horario::create([
                'hora_inicio' => $hora,
                'franja' => 'M',
            ]);
            $actividad->horarios()->attach($horario->id);
        }

        return $actividad->fresh(['horarios']);
    }

    private function ocuparFijos(int $idActividad, int $dia, string $hora, int $cantidad): void
    {
        for ($i = 0; $i < $cantidad; $i++) {
            $fijo = PacienteFijo::create(['id_paciente' => $this->crearPaciente()->id]);

            HorarioPacienteFijo::create([
                'id_paciente_fijo' => $fijo->id,
                'id_actividad' => $idActividad,
                'dia_semana' => $dia,
                'hora_inicio' => $hora,
            ]);
        }
    }

    private function crearTurnosEnSlot(int $idActividad, string $fechaHora, int $cantidad): void
    {
        for ($i = 0; $i < $cantidad; $i++) {
            $actPac = ActividadPaciente::create([
                'id_actividad' => $idActividad,
                'id_paciente' => $this->crearPaciente()->id,
                'cant_sesiones' => 1,
                'total_a_pagar' => 0,
            ]);

            Turno::create([
                'id_act_pac' => $actPac->id,
                'fecha_hora' => $fechaHora,
                'estado' => 'Ausente',
            ]);
        }
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
