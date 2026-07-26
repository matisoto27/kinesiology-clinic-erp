<?php

namespace Tests\Unit;

use App\Models\Actividad;
use App\Models\ActividadPaciente;
use App\Models\Paciente;
use App\Models\Turno;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PacienteVentanaInscripcionTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_incluye_actividad_sin_historial_de_turnos(): void
    {
        Carbon::setTestNow('2026-06-01 10:00:00');

        $paciente = $this->crearPaciente();
        $actividad = $this->crearActividadGeneral();

        $ids = $paciente->obtenerActividadesGeneralesSinSuscripcion()->pluck('id');

        $this->assertTrue($ids->contains($actividad->id));
        $this->assertTrue($paciente->cumpleVentanaInscripcionGeneral($actividad->id));
    }

    public function test_excluye_actividad_con_ultimo_turno_mas_alla_de_siete_dias(): void
    {
        Carbon::setTestNow('2026-06-01 10:00:00');

        $paciente = $this->crearPaciente();
        $actividad = $this->crearActividadGeneral();

        $this->crearInscripcionConUltimoTurno($paciente, $actividad, '2026-06-20 10:00:00');

        $ids = $paciente->obtenerActividadesGeneralesSinSuscripcion()->pluck('id');

        $this->assertFalse($ids->contains($actividad->id));
        $this->assertFalse($paciente->cumpleVentanaInscripcionGeneral($actividad->id));
    }

    public function test_incluye_actividad_con_ultimo_turno_dentro_de_siete_dias(): void
    {
        Carbon::setTestNow('2026-06-01 10:00:00');

        $paciente = $this->crearPaciente();
        $actividad = $this->crearActividadGeneral();

        $this->crearInscripcionConUltimoTurno($paciente, $actividad, '2026-06-06 10:00:00');

        $ids = $paciente->obtenerActividadesGeneralesSinSuscripcion()->pluck('id');

        $this->assertTrue($ids->contains($actividad->id));
        $this->assertTrue($paciente->cumpleVentanaInscripcionGeneral($actividad->id));
    }

    public function test_incluye_actividad_cuyo_ultimo_turno_termino_hace_pocos_dias(): void
    {
        Carbon::setTestNow('2026-06-10 10:00:00');

        $paciente = $this->crearPaciente();
        $actividad = $this->crearActividadGeneral();

        $this->crearInscripcionConUltimoTurno($paciente, $actividad, '2026-06-05 10:00:00');

        $this->assertTrue($paciente->cumpleVentanaInscripcionGeneral($actividad->id));
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
            'profesion' => 'Profesión',
            'actividad_fisica' => 'Ninguna',
            'es_adulto_mayor' => false,
        ]);
    }

    private function crearActividadGeneral(): Actividad
    {
        return Actividad::create([
            'nombre' => 'ActGen T' . uniqid(),
            'id_tipo_actividad' => Actividad::TIPO_GENERAL,
        ]);
    }

    private function crearInscripcionConUltimoTurno(
        Paciente $paciente,
        Actividad $actividad,
        string $fechaUltimoTurno
    ): ActividadPaciente {
        $actPac = ActividadPaciente::create([
            'id_actividad' => $actividad->id,
            'id_paciente' => $paciente->id,
            'cant_sesiones' => 1,
            'es_fijo' => false,
            'total_a_pagar' => 1000,
        ]);

        Turno::create([
            'id_act_pac' => $actPac->id,
            'nro_turno' => 1,
            'fecha_hora' => $fechaUltimoTurno,
        ]);

        return $actPac;
    }
}
