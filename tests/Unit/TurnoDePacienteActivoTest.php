<?php

namespace Tests\Unit;

use App\Models\Actividad;
use App\Models\ActividadPaciente;
use App\Models\Paciente;
use App\Models\PacienteCasual;
use App\Models\Turno;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Un paciente (regular o casual) eliminado (soft delete) no debe seguir
 * ocupando cupo ni apareciendo en la agenda (calendario/principal).
 */
class TurnoDePacienteActivoTest extends TestCase
{
    use RefreshDatabase;

    public function test_activos_para_cupo_excluye_turnos_de_paciente_regular_eliminado(): void
    {
        $paciente = $this->crearPaciente();
        $actPac = $this->crearInscripcion($paciente, Actividad::KINESIOLOGIA_CONVENCIONAL);
        $turno = Turno::create([
            'id_act_pac' => $actPac->id,
            'fecha_hora' => '2026-06-16 10:00:00',
            'estado' => 'Ausente',
        ]);

        $this->assertTrue(Turno::activosParaCupo()->whereKey($turno->id)->exists());

        $paciente->delete();

        $this->assertFalse(Turno::activosParaCupo()->whereKey($turno->id)->exists());
    }

    public function test_visibles_en_agenda_excluye_turnos_de_paciente_regular_eliminado(): void
    {
        $paciente = $this->crearPaciente();
        $actPac = $this->crearInscripcion($paciente, Actividad::QUIROPRAXIA);
        $turno = Turno::create([
            'id_act_pac' => $actPac->id,
            'fecha_hora' => '2026-06-16 10:00:00',
            'estado' => 'Ausente',
        ]);

        $this->assertTrue(Turno::visiblesEnAgenda()->whereKey($turno->id)->exists());

        $paciente->delete();

        $this->assertFalse(Turno::visiblesEnAgenda()->whereKey($turno->id)->exists());
    }

    public function test_activos_para_cupo_excluye_turnos_de_paciente_casual_eliminado(): void
    {
        $casual = PacienteCasual::create([
            'nombre' => 'Nombre',
            'apellido' => 'Apellido',
            'telefono' => fake()->unique()->numerify('##########'),
        ]);

        $actPac = ActividadPaciente::create([
            'id_actividad' => Actividad::GIMNASIO,
            'id_paciente_casual' => $casual->id,
            'cant_sesiones' => 1,
            'total_a_pagar' => 0,
        ]);

        $turno = Turno::create([
            'id_act_pac' => $actPac->id,
            'fecha_hora' => '2026-06-16 10:00:00',
            'estado' => 'Ausente',
        ]);

        $this->assertTrue(Turno::activosParaCupo()->whereKey($turno->id)->exists());

        $casual->delete();

        $this->assertFalse(Turno::activosParaCupo()->whereKey($turno->id)->exists());
    }

    public function test_activos_para_cupo_conserva_turnos_de_paciente_activo(): void
    {
        $paciente = $this->crearPaciente();
        $actPac = $this->crearInscripcion($paciente, Actividad::KINESIOLOGIA_CONVENCIONAL);
        $turno = Turno::create([
            'id_act_pac' => $actPac->id,
            'fecha_hora' => '2026-06-16 10:00:00',
            'estado' => 'Ausente',
        ]);

        $this->assertTrue(Turno::activosParaCupo()->whereKey($turno->id)->exists());
        $this->assertTrue(Turno::visiblesEnAgenda()->whereKey($turno->id)->exists());
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
            'total_a_pagar' => 0,
        ]);
    }
}
