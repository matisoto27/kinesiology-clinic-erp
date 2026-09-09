<?php

namespace Tests\Feature;

use App\Models\Actividad;
use App\Models\ActividadPaciente;
use App\Models\Horario;
use App\Models\Paciente;
use App\Models\Turno;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActividadTurnosDisponiblesEndpointTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_fecha_fin_sin_hora_no_ofrece_slot_ocupado_esa_tarde(): void
    {
        Carbon::setTestNow('2026-09-09 10:00:00');

        $quiropraxia = $this->prepararActividad(Actividad::QUIROPRAXIA, ['17:00:00', '18:00:00']);
        $consultante = $this->crearPaciente();
        $this->ocuparSlot(Actividad::ATM, '2026-09-09 17:00:00');

        $slots = $this->withSession(['autorizado' => true])
            ->getJson("/actividades/{$quiropraxia->id}/turnos-disponibles?".http_build_query([
                'id_paciente' => $consultante->id,
                'fecha_comienzo' => '2026-09-09',
                'fecha_fin' => '2026-09-09',
            ]))
            ->assertOk()
            ->json();

        $this->assertNotContains('2026-09-09 17:00:00', $slots);
        $this->assertContains('2026-09-09 18:00:00', $slots);
    }

    /**
     * @param  list<string>  $horasInicio
     */
    private function prepararActividad(int $idActividad, array $horasInicio): Actividad
    {
        $actividad = Actividad::findOrFail($idActividad);

        foreach ($horasInicio as $hora) {
            $horario = Horario::create([
                'hora_inicio' => $hora,
                'franja' => 'T',
            ]);
            $actividad->horarios()->attach($horario->id);
        }

        return $actividad->fresh(['horarios']);
    }

    private function ocuparSlot(int $idActividad, string $fechaHora): void
    {
        $inscripcion = ActividadPaciente::create([
            'id_actividad' => $idActividad,
            'id_paciente' => $this->crearPaciente()->id,
            'cant_sesiones' => 1,
            'total_a_pagar' => 0,
        ]);

        Turno::create([
            'id_act_pac' => $inscripcion->id,
            'fecha_hora' => $fechaHora,
            'estado' => 'Ausente',
        ]);
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
