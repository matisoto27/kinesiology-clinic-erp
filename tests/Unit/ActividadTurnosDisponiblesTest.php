<?php

namespace Tests\Unit;

use App\Models\Actividad;
use App\Models\ActividadPaciente;
use App\Models\Horario;
use App\Models\Paciente;
use App\Models\Turno;
use App\Services\TurnoService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class ActividadTurnosDisponiblesTest extends TestCase
{
    use RefreshDatabase;

    private TurnoService $turnoService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->turnoService = app(TurnoService::class);
        Config::set('app.max_turnos_gimnasio', 1);
        Config::set('app.max_turnos_pilates', 1);
        Config::set('app.max_turnos_convencional', 1);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_tras_reprogramar_libera_cupo_en_fecha_original_y_ocupa_la_nueva(): void
    {
        Carbon::setTestNow('2026-06-01 08:00:00');

        $gimnasio = $this->prepararGimnasioConHorario('10:00:00');
        $paciente = $this->crearPaciente();
        $otroPaciente = $this->crearPaciente();

        $actPac = $this->crearInscripcion($paciente, Actividad::GIMNASIO);
        $turnoOriginal = Turno::create([
            'id_act_pac' => $actPac->id,
            'nro_turno' => 1,
            'fecha_hora' => '2026-06-03 10:00:00',
            'estado' => 'Ausente',
        ]);

        $comienzo = Carbon::parse('2026-06-01')->startOfDay();
        $fin = Carbon::parse('2026-06-05')->endOfDay();

        $antes = $gimnasio->turnosDisponibles($otroPaciente->id, $comienzo, $fin);
        $this->assertNotContains('2026-06-03 10:00:00', $antes);

        $this->turnoService->reprogramar($turnoOriginal, Carbon::parse('2026-06-04 10:00:00'));

        $despues = $gimnasio->turnosDisponibles($otroPaciente->id, $comienzo, $fin);
        $this->assertContains('2026-06-03 10:00:00', $despues);
        $this->assertNotContains('2026-06-04 10:00:00', $despues);
    }

    public function test_ausente_aviso_libera_cupo_sin_necesidad_de_reprogramar(): void
    {
        Carbon::setTestNow('2026-06-01 08:00:00');

        $gimnasio = $this->prepararGimnasioConHorario('10:00:00');
        $paciente = $this->crearPaciente();
        $otroPaciente = $this->crearPaciente();

        $actPac = $this->crearInscripcion($paciente, Actividad::GIMNASIO);
        Turno::create([
            'id_act_pac' => $actPac->id,
            'nro_turno' => 1,
            'fecha_hora' => '2026-06-03 10:00:00',
            'estado' => 'Ausente avisó',
        ]);

        $disponibles = $gimnasio->turnosDisponibles(
            $otroPaciente->id,
            Carbon::parse('2026-06-01')->startOfDay(),
            Carbon::parse('2026-06-05')->endOfDay()
        );

        $this->assertContains('2026-06-03 10:00:00', $disponibles);
    }

    public function test_ausente_sin_aviso_sigue_ocupando_cupo(): void
    {
        Carbon::setTestNow('2026-06-01 08:00:00');

        $gimnasio = $this->prepararGimnasioConHorario('10:00:00');
        $paciente = $this->crearPaciente();
        $otroPaciente = $this->crearPaciente();

        $actPac = $this->crearInscripcion($paciente, Actividad::GIMNASIO);
        Turno::create([
            'id_act_pac' => $actPac->id,
            'nro_turno' => 1,
            'fecha_hora' => '2026-06-03 10:00:00',
            'estado' => 'Ausente',
        ]);

        $disponibles = $gimnasio->turnosDisponibles(
            $otroPaciente->id,
            Carbon::parse('2026-06-01')->startOfDay(),
            Carbon::parse('2026-06-05')->endOfDay()
        );

        $this->assertNotContains('2026-06-03 10:00:00', $disponibles);
    }

    public function test_paciente_no_queda_bloqueado_en_fecha_original_tras_reprogramar(): void
    {
        Carbon::setTestNow('2026-06-01 08:00:00');

        $gimnasio = $this->prepararGimnasioConHorario('10:00:00');
        Config::set('app.max_turnos_gimnasio', 8);

        $paciente = $this->crearPaciente();
        $actPac = $this->crearInscripcion($paciente, Actividad::GIMNASIO);
        $turnoOriginal = Turno::create([
            'id_act_pac' => $actPac->id,
            'nro_turno' => 1,
            'fecha_hora' => '2026-06-03 10:00:00',
            'estado' => 'Ausente',
        ]);

        $this->turnoService->reprogramar($turnoOriginal, Carbon::parse('2026-06-04 10:00:00'));

        $disponibles = $gimnasio->turnosDisponibles(
            $paciente->id,
            Carbon::parse('2026-06-01')->startOfDay(),
            Carbon::parse('2026-06-05')->endOfDay()
        );

        $this->assertContains('2026-06-03 10:00:00', $disponibles);
        $this->assertNotContains('2026-06-04 10:00:00', $disponibles);
    }

    public function test_reprogramacion_kinesiologia_libera_slot_original(): void
    {
        Carbon::setTestNow('2026-06-01 08:00:00');

        $kine = Actividad::findOrFail(Actividad::QUIROPRAXIA);
        $horario = Horario::create([
            'hora_inicio' => '10:00:00',
            'franja' => 'M',
        ]);
        $kine->horarios()->attach($horario->id);

        $paciente = $this->crearPaciente();
        $otroPaciente = $this->crearPaciente();
        $actPac = $this->crearInscripcion($paciente, Actividad::QUIROPRAXIA);

        $turnoOriginal = Turno::create([
            'id_act_pac' => $actPac->id,
            'nro_turno' => 1,
            'fecha_hora' => '2026-06-03 10:00:00',
            'estado' => 'Ausente',
        ]);

        $comienzo = Carbon::parse('2026-06-01')->startOfDay();
        $fin = Carbon::parse('2026-06-05')->endOfDay();

        $this->assertNotContains(
            '2026-06-03 10:00:00',
            $kine->turnosDisponibles($otroPaciente->id, $comienzo, $fin)
        );

        $this->turnoService->reprogramar($turnoOriginal, Carbon::parse('2026-06-04 10:00:00'));

        $despues = $kine->turnosDisponibles($otroPaciente->id, $comienzo, $fin);
        $this->assertContains('2026-06-03 10:00:00', $despues);
        $this->assertNotContains('2026-06-04 10:00:00', $despues);
    }

    private function prepararGimnasioConHorario(string $horaInicio): Actividad
    {
        $gimnasio = Actividad::findOrFail(Actividad::GIMNASIO);
        $horario = Horario::create([
            'hora_inicio' => $horaInicio,
            'franja' => 'M',
        ]);
        $gimnasio->horarios()->attach($horario->id);

        return $gimnasio->fresh(['horarios']);
    }

    private function crearPaciente(): Paciente
    {
        return Paciente::create([
            'dni' => fake()->unique()->numerify('########'),
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

    private function crearInscripcion(Paciente $paciente, int $idActividad): ActividadPaciente
    {
        return ActividadPaciente::create([
            'id_actividad' => $idActividad,
            'id_paciente' => $paciente->id,
            'cant_sesiones' => 4,
            'es_fijo' => false,
            'total_a_pagar' => 0,
            'plan_dual_pendiente' => false,
        ]);
    }
}
