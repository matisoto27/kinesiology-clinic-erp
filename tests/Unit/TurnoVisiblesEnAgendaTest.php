<?php

namespace Tests\Unit;

use App\Models\Actividad;
use App\Models\ActividadPaciente;
use App\Models\Paciente;
use App\Models\Turno;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TurnoVisiblesEnAgendaTest extends TestCase
{
    use RefreshDatabase;

    public function test_oculta_turnos_de_kinesiologia_sustituidos_por_reprogramacion(): void
    {
        $paciente = $this->crearPaciente();
        $actPac = $this->crearInscripcion($paciente, Actividad::QUIROPRAXIA);

        $original = Turno::create([
            'id_act_pac' => $actPac->id,
            'fecha_hora' => '2026-06-03 10:00:00',
            'estado' => 'Ausente',
        ]);

        $vigente = Turno::create([
            'id_act_pac' => $actPac->id,
            'fecha_hora' => '2026-06-04 10:00:00',
            'estado' => 'Ausente',
            'id_turno_original' => $original->id,
        ]);

        $ids = Turno::visiblesEnAgenda()->pluck('id');

        $this->assertFalse($ids->contains($original->id));
        $this->assertTrue($ids->contains($vigente->id));
    }

    public function test_conserva_turnos_generales_reprogramados_y_sus_recuperaciones(): void
    {
        $paciente = $this->crearPaciente();
        $actPac = $this->crearInscripcion($paciente, Actividad::GIMNASIO);

        $original = Turno::create([
            'id_act_pac' => $actPac->id,
            'fecha_hora' => '2026-06-03 10:00:00',
            'estado' => 'Ausente avisó',
        ]);

        $recuperacion = Turno::create([
            'id_act_pac' => $actPac->id,
            'fecha_hora' => '2026-06-04 10:00:00',
            'estado' => 'Ausente',
            'id_turno_original' => $original->id,
        ]);

        $ids = Turno::visiblesEnAgenda()->pluck('id');

        $this->assertTrue($ids->contains($original->id));
        $this->assertTrue($ids->contains($recuperacion->id));
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
