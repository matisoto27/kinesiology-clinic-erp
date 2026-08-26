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
use Livewire\Livewire;
use Tests\TestCase;

class TurnoCancelarReprogramacionTest extends TestCase
{
    use RefreshDatabase;

    private TurnoService $turnoService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->turnoService = app(TurnoService::class);
        Carbon::setTestNow('2026-06-01 08:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_cancela_reprogramacion_de_gimnasio(): void
    {
        $this->asociarHora(Actividad::GIMNASIO, '10:00:00');

        $inscripcion = $this->crearInscripcion(Actividad::GIMNASIO);
        $original = $this->crearTurno($inscripcion, '2026-05-30 10:00:00', 'Ausente avisó');
        $recuperacion = Turno::create([
            'id_act_pac' => $inscripcion->id,
            'fecha_hora' => '2026-06-04 10:00:00',
            'estado' => 'Ausente',
            'id_turno_original' => $original->id,
        ]);

        $this->turnoService->cancelarReprogramacion($original);

        $this->assertDatabaseMissing('turnos', ['id' => $recuperacion->id]);
        $this->assertSame('Ausente', $original->fresh()->getRawOriginal('estado'));
    }

    public function test_cancela_reprogramacion_de_kinesiologia(): void
    {
        $inscripcion = $this->crearInscripcion(Actividad::QUIROPRAXIA);
        $original = $this->crearTurno($inscripcion, '2026-05-30 10:00:00', 'Ausente avisó');
        $recuperacion = Turno::create([
            'id_act_pac' => $inscripcion->id,
            'fecha_hora' => '2026-06-04 10:00:00',
            'estado' => 'Ausente',
            'id_turno_original' => $original->id,
        ]);

        $this->turnoService->cancelarReprogramacion($original);

        $this->assertDatabaseMissing('turnos', ['id' => $recuperacion->id]);
        $this->assertSame('Ausente', $original->fresh()->getRawOriginal('estado'));
    }

    public function test_rechaza_si_la_recuperacion_ya_paso(): void
    {
        Carbon::setTestNow('2026-06-06 08:00:00');

        $inscripcion = $this->crearInscripcion(Actividad::PILATES);
        $original = $this->crearTurno($inscripcion, '2026-06-03 10:00:00', 'Ausente avisó');
        Turno::create([
            'id_act_pac' => $inscripcion->id,
            'fecha_hora' => '2026-06-04 10:00:00',
            'estado' => 'Ausente',
            'id_turno_original' => $original->id,
        ]);

        $this->expectException(ReglaNegocioException::class);
        $this->expectExceptionMessage('No se puede cancelar la reprogramación de este turno.');

        $this->turnoService->cancelarReprogramacion($original);
    }

    public function test_rechaza_si_la_recuperacion_tiene_asistencia(): void
    {
        $inscripcion = $this->crearInscripcion(Actividad::PILATES);
        $original = $this->crearTurno($inscripcion, '2026-06-03 10:00:00', 'Ausente avisó');
        Turno::create([
            'id_act_pac' => $inscripcion->id,
            'fecha_hora' => '2026-06-10 10:00:00',
            'estado' => 'Presente',
            'id_turno_original' => $original->id,
        ]);

        $this->expectException(ReglaNegocioException::class);
        $this->expectExceptionMessage('No se puede cancelar la reprogramación de este turno.');

        $this->turnoService->cancelarReprogramacion($original);
    }

    public function test_quita_ausente_aviso_sin_recuperacion_si_es_futuro(): void
    {
        $inscripcion = $this->crearInscripcion(Actividad::PILATES);
        $original = $this->crearTurno($inscripcion, '2026-06-10 10:00:00', 'Ausente avisó');

        $this->turnoService->cancelarReprogramacion($original);

        $this->assertSame('Ausente', $original->fresh()->getRawOriginal('estado'));
    }

    public function test_rechaza_quitar_ausente_aviso_sin_recuperacion_si_ya_paso(): void
    {
        Carbon::setTestNow('2026-06-06 08:00:00');

        $inscripcion = $this->crearInscripcion(Actividad::PILATES);
        $original = $this->crearTurno($inscripcion, '2026-06-03 10:00:00', 'Ausente avisó');

        $this->expectException(ReglaNegocioException::class);
        $this->expectExceptionMessage('No se puede cancelar la reprogramación de este turno.');

        $this->turnoService->cancelarReprogramacion($original);
    }

    public function test_rechaza_si_el_turno_no_esta_en_ausente_aviso(): void
    {
        $inscripcion = $this->crearInscripcion(Actividad::PILATES);
        $original = $this->crearTurno($inscripcion, '2026-06-10 10:00:00', 'Ausente');

        $this->expectException(ReglaNegocioException::class);
        $this->expectExceptionMessage('No se puede cancelar la reprogramación de este turno.');

        $this->turnoService->cancelarReprogramacion($original);
    }

    public function test_livewire_muestra_cruz_solo_cuando_puede_cancelar(): void
    {
        $this->asociarHora(Actividad::GIMNASIO, '10:00:00');

        $inscripcion = $this->crearInscripcion(Actividad::GIMNASIO);
        $cancelable = $this->crearTurno($inscripcion, '2026-05-30 10:00:00', 'Ausente avisó');
        Turno::create([
            'id_act_pac' => $inscripcion->id,
            'fecha_hora' => '2026-06-04 10:00:00',
            'estado' => 'Ausente',
            'id_turno_original' => $cancelable->id,
        ]);

        $aaPasadoSinRecuperacion = $this->crearTurno($inscripcion, '2026-05-29 10:00:00', 'Ausente avisó');
        $aaFuturoSinRecuperacion = $this->crearTurno($inscripcion, '2026-06-05 10:00:00', 'Ausente avisó');

        Livewire::test('turnos.inicio', ['actividades' => Actividad::all()])
            ->assertSeeHtml('wire:click="cancelarReprogramacion(' . $cancelable->id . ')"')
            ->assertDontSeeHtml('wire:click="cancelarReprogramacion(' . $aaPasadoSinRecuperacion->id . ')"')
            ->assertSeeHtml('wire:click="cancelarReprogramacion(' . $aaFuturoSinRecuperacion->id . ')"');
    }

    public function test_livewire_quita_ausente_aviso_sin_recuperacion(): void
    {
        $inscripcion = $this->crearInscripcion(Actividad::PILATES);
        $original = $this->crearTurno($inscripcion, '2026-06-10 10:00:00', 'Ausente avisó');

        Livewire::test('turnos.inicio', ['actividades' => Actividad::all()])
            ->call('cancelarReprogramacion', $original->id);

        $this->assertSame('Ausente', $original->fresh()->getRawOriginal('estado'));
    }

    public function test_livewire_cancela_reprogramacion(): void
    {
        $this->asociarHora(Actividad::GIMNASIO, '10:00:00');

        $inscripcion = $this->crearInscripcion(Actividad::GIMNASIO);
        $original = $this->crearTurno($inscripcion, '2026-05-30 10:00:00', 'Ausente avisó');
        $recuperacion = Turno::create([
            'id_act_pac' => $inscripcion->id,
            'fecha_hora' => '2026-06-04 10:00:00',
            'estado' => 'Ausente',
            'id_turno_original' => $original->id,
        ]);

        Livewire::test('turnos.inicio', ['actividades' => Actividad::all()])
            ->call('cancelarReprogramacion', $original->id);

        $this->assertDatabaseMissing('turnos', ['id' => $recuperacion->id]);
        $this->assertSame('Ausente', $original->fresh()->getRawOriginal('estado'));
    }

    private function crearInscripcion(int $idActividad): ActividadPaciente
    {
        return ActividadPaciente::create([
            'id_actividad' => $idActividad,
            'id_paciente' => $this->crearPaciente()->id,
            'cant_sesiones' => 4,
            'total_a_pagar' => 0,
        ]);
    }

    private function crearTurno(ActividadPaciente $inscripcion, string $fechaHora, string $estado): Turno
    {
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
