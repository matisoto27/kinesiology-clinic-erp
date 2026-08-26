<?php

namespace Tests\Unit;

use App\Models\Actividad;
use App\Models\ActividadPaciente;
use App\Models\HorarioPacienteFijo;
use App\Models\Paciente;
use App\Models\PacienteFijo;
use App\Models\Pago;
use App\Models\Profesional;
use App\Models\Turno;
use App\Services\PacienteService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Al eliminar (soft delete) un paciente, el servicio debe liberar el cupo que
 * estuviera reteniendo (turnos futuros sin historial real) y conservar todo
 * lo que sí tenga historial de asistencia o pagos.
 */
class PacienteServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-06-15 08:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_elimina_inscripcion_de_kinesiologia_totalmente_futura_sin_pagos(): void
    {
        $paciente = $this->crearPaciente();
        $actPac = $this->crearInscripcion($paciente, Actividad::KINESIOLOGIA_CONVENCIONAL);

        // 1 turno pasado (Ausente) + 9 futuros (Ausente), tal como el caso reportado.
        $this->crearTurno($actPac, '2026-06-10 10:00:00', 'Ausente');
        for ($i = 1; $i <= 9; $i++) {
            $this->crearTurno($actPac, Carbon::parse('2026-06-16 10:00:00')->addWeekdays($i)->toDateTimeString(), 'Ausente');
        }

        $resultado = app(PacienteService::class)->eliminar($paciente);

        $this->assertSame(1, $resultado['eliminadas']);
        $this->assertSame(0, $resultado['conservadas']);
        $this->assertSame(0, ActividadPaciente::where('id_paciente', $paciente->id)->count());
        $this->assertSame(0, Turno::where('id_act_pac', $actPac->id)->count());
        $this->assertTrue($paciente->fresh()->trashed());
    }

    public function test_conserva_inscripcion_con_pagos_aunque_tenga_turnos_futuros(): void
    {
        $paciente = $this->crearPaciente();
        $actPac = $this->crearInscripcion($paciente, Actividad::KINESIOLOGIA_CONVENCIONAL);

        $this->crearTurno($actPac, '2026-06-10 10:00:00', 'Ausente');
        $this->crearTurno($actPac, '2026-06-20 10:00:00', 'Ausente');

        Pago::create([
            'metodo' => 'Efectivo',
            'monto' => 5000,
            'es_copago' => false,
            'id_act_pac' => $actPac->id,
            'id_profesional' => $this->crearProfesional()->id,
        ]);

        $resultado = app(PacienteService::class)->eliminar($paciente);

        $this->assertSame(0, $resultado['eliminadas']);
        $this->assertSame(1, $resultado['conservadas']);
        $this->assertSame(1, ActividadPaciente::where('id_paciente', $paciente->id)->count());
        $this->assertSame(2, Turno::where('id_act_pac', $actPac->id)->count());
    }

    public function test_conserva_inscripcion_con_turno_presente_aunque_tenga_turnos_futuros(): void
    {
        $paciente = $this->crearPaciente();
        $actPac = $this->crearInscripcion($paciente, Actividad::KINESIOLOGIA_CONVENCIONAL);

        $this->crearTurno($actPac, '2026-06-10 10:00:00', 'Presente');
        $this->crearTurno($actPac, '2026-06-20 10:00:00', 'Ausente');

        $resultado = app(PacienteService::class)->eliminar($paciente);

        $this->assertSame(0, $resultado['eliminadas']);
        $this->assertSame(1, $resultado['conservadas']);
        $this->assertSame(2, Turno::where('id_act_pac', $actPac->id)->count());
    }

    public function test_conserva_inscripcion_totalmente_historica(): void
    {
        $paciente = $this->crearPaciente();
        $actPac = $this->crearInscripcion($paciente, Actividad::KINESIOLOGIA_CONVENCIONAL);

        $this->crearTurno($actPac, '2026-06-01 10:00:00', 'Ausente');
        $this->crearTurno($actPac, '2026-06-10 10:00:00', 'Ausente');

        $resultado = app(PacienteService::class)->eliminar($paciente);

        $this->assertSame(0, $resultado['eliminadas']);
        $this->assertSame(1, $resultado['conservadas']);
        $this->assertSame(2, Turno::where('id_act_pac', $actPac->id)->count());
    }

    public function test_elimina_contrato_de_fijo_y_libera_cupo_estructural(): void
    {
        $paciente = $this->crearPaciente();

        $pacienteFijo = PacienteFijo::create(['id_paciente' => $paciente->id]);
        HorarioPacienteFijo::create([
            'id_paciente_fijo' => $pacienteFijo->id,
            'id_actividad' => Actividad::PILATES,
            'dia_semana' => 1,
            'hora_inicio' => '10:00:00',
        ]);

        $actPac = ActividadPaciente::create([
            'id_actividad' => Actividad::PILATES,
            'id_paciente' => $paciente->id,
            'cant_sesiones' => 8,
            'total_a_pagar' => 0,
        ]);
        $this->crearTurno($actPac, '2026-06-16 10:00:00', 'Ausente');
        $this->crearTurno($actPac, '2026-06-18 10:00:00', 'Ausente');

        $resultado = app(PacienteService::class)->eliminar($paciente);

        $this->assertSame(1, $resultado['eliminadas']);
        $this->assertSame(0, PacienteFijo::where('id_paciente', $paciente->id)->count());
        $this->assertSame(0, HorarioPacienteFijo::where('id_paciente_fijo', $pacienteFijo->id)->count());
        $this->assertSame(0, ActividadPaciente::where('id_paciente', $paciente->id)->count());
    }

    public function test_paciente_fijo_de_pilates_con_inscripcion_individual_de_kinesio(): void
    {
        $paciente = $this->crearPaciente();

        $pacienteFijo = PacienteFijo::create(['id_paciente' => $paciente->id]);
        HorarioPacienteFijo::create([
            'id_paciente_fijo' => $pacienteFijo->id,
            'id_actividad' => Actividad::PILATES,
            'dia_semana' => 1,
            'hora_inicio' => '10:00:00',
        ]);

        $actPacFijo = ActividadPaciente::create([
            'id_actividad' => Actividad::PILATES,
            'id_paciente' => $paciente->id,
            'cant_sesiones' => 8,
            'total_a_pagar' => 0,
        ]);
        $this->crearTurno($actPacFijo, '2026-06-16 10:00:00', 'Ausente');

        $actPacKine = $this->crearInscripcion($paciente, Actividad::KINESIOLOGIA_CONVENCIONAL);
        $this->crearTurno($actPacKine, '2026-06-17 10:00:00', 'Ausente');

        $resultado = app(PacienteService::class)->eliminar($paciente);

        // Ambas inscripciones (fijo + individual) quedan libres de historial y se eliminan.
        $this->assertSame(2, $resultado['eliminadas']);
        $this->assertSame(0, ActividadPaciente::where('id_paciente', $paciente->id)->count());
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

    private function crearProfesional(): Profesional
    {
        return Profesional::create([
            'dni' => fake()->unique()->numerify('########'),
            'nombre' => 'Nombre',
            'apellido' => 'Apellido',
            'activo' => true,
        ]);
    }

    private function crearInscripcion(Paciente $paciente, int $idActividad): ActividadPaciente
    {
        return ActividadPaciente::create([
            'id_actividad' => $idActividad,
            'id_paciente' => $paciente->id,
            'cant_sesiones' => 10,
            'total_a_pagar' => 0,
        ]);
    }

    private function crearTurno(ActividadPaciente $actPac, string $fechaHora, string $estado): Turno
    {
        return Turno::create([
            'id_act_pac' => $actPac->id,
            'fecha_hora' => $fechaHora,
            'estado' => $estado,
        ]);
    }
}
