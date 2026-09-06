<?php

namespace Tests\Feature;

use App\Models\Actividad;
use App\Models\ActividadPaciente;
use App\Models\Paciente;
use App\Models\PacienteFijo;
use App\Models\Turno;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PacientesFijosInicioTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_ids_pacientes_cursando_incluye_solo_los_que_tienen_turnos_pasados_y_futuros(): void
    {
        Carbon::setTestNow('2026-06-15 10:00:00');

        $pacienteCursando = $this->crearPaciente();
        $inscripcionCursando = $this->crearInscripcion($pacienteCursando);
        Turno::create(['id_act_pac' => $inscripcionCursando->id, 'fecha_hora' => '2026-06-01 10:00:00']);
        Turno::create(['id_act_pac' => $inscripcionCursando->id, 'fecha_hora' => '2026-06-22 10:00:00']);
        $pacienteFijoCursando = PacienteFijo::create(['id_paciente' => $pacienteCursando->id]);

        $pacienteNoCursando = $this->crearPaciente();
        $inscripcionFutura = $this->crearInscripcion($pacienteNoCursando);
        Turno::create(['id_act_pac' => $inscripcionFutura->id, 'fecha_hora' => '2026-07-01 10:00:00']);
        Turno::create(['id_act_pac' => $inscripcionFutura->id, 'fecha_hora' => '2026-07-08 10:00:00']);
        $pacienteFijoNoCursando = PacienteFijo::create(['id_paciente' => $pacienteNoCursando->id]);

        $componente = Livewire::test('pacientes-fijos.inicio');
        $ids = $componente->instance()->idsPacientesCursandoInscripcion();

        $this->assertContains($pacienteCursando->id, $ids);
        $this->assertNotContains($pacienteNoCursando->id, $ids);

        $this->assertTrue($pacienteFijoCursando->fresh()->estaCursandoInscripcion());
        $this->assertFalse($pacienteFijoNoCursando->fresh()->estaCursandoInscripcion());
    }

    public function test_listado_muestra_editar_solo_para_pacientes_que_estan_cursando(): void
    {
        Carbon::setTestNow('2026-06-15 10:00:00');

        $pacienteCursando = $this->crearPaciente();
        $inscripcionCursando = $this->crearInscripcion($pacienteCursando);
        Turno::create(['id_act_pac' => $inscripcionCursando->id, 'fecha_hora' => '2026-06-01 10:00:00']);
        Turno::create(['id_act_pac' => $inscripcionCursando->id, 'fecha_hora' => '2026-06-22 10:00:00']);
        $pacienteFijoCursando = PacienteFijo::create(['id_paciente' => $pacienteCursando->id]);

        $pacienteNoCursando = $this->crearPaciente();
        $inscripcionFutura = $this->crearInscripcion($pacienteNoCursando);
        Turno::create(['id_act_pac' => $inscripcionFutura->id, 'fecha_hora' => '2026-07-01 10:00:00']);
        Turno::create(['id_act_pac' => $inscripcionFutura->id, 'fecha_hora' => '2026-07-08 10:00:00']);
        PacienteFijo::create(['id_paciente' => $pacienteNoCursando->id]);

        Livewire::test('pacientes-fijos.inicio')
            ->assertSee(route('pacientes-fijos.editar', ['id' => $pacienteFijoCursando->id]))
            ->assertSee('No se puede editar');
    }

    private function crearInscripcion(Paciente $paciente): ActividadPaciente
    {
        return ActividadPaciente::create([
            'id_actividad' => Actividad::GIMNASIO,
            'id_paciente' => $paciente->id,
            'cant_sesiones' => 8,
            'total_a_pagar' => 20000,
            'pago_completado' => false,
        ]);
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
}
