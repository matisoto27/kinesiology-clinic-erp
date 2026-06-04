<?php

namespace Tests\Unit;

use App\Models\Actividad;
use App\Models\ActividadPaciente;
use App\Models\Paciente;
use App\Models\TipoActividad;
use App\Models\Turno;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActividadPacienteScopeTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_con_ultimo_turno_vigente_incluye_inscripciones_con_ultimo_turno_en_el_futuro(): void
    {
        Carbon::setTestNow('2026-06-04 10:00:00');

        $actPac = $this->crearInscripcionConUltimoTurno('2026-06-15 09:00:00');

        $resultado = ActividadPaciente::conUltimoTurnoVigente()->pluck('id');

        $this->assertTrue($resultado->contains($actPac->id));
    }

    public function test_con_ultimo_turno_vigente_excluye_inscripciones_con_ultimo_turno_en_el_pasado(): void
    {
        Carbon::setTestNow('2026-06-04 10:00:00');

        $actPac = $this->crearInscripcionConUltimoTurno('2026-04-29 09:00:00');

        $resultado = ActividadPaciente::conUltimoTurnoVigente()->pluck('id');

        $this->assertFalse($resultado->contains($actPac->id));
    }

    public function test_con_ultimo_turno_vigente_excluye_inscripciones_cuyo_ultimo_turno_ya_paso_hoy(): void
    {
        Carbon::setTestNow('2026-06-04 15:00:00');

        $actPac = $this->crearInscripcionConUltimoTurno('2026-06-04 09:00:00');

        $resultado = ActividadPaciente::conUltimoTurnoVigente()->pluck('id');

        $this->assertFalse($resultado->contains($actPac->id));
    }

    private function crearInscripcionConUltimoTurno(string $fechaUltimoTurno): ActividadPaciente
    {
        $tipo = TipoActividad::create(['descripcion' => 'General']);

        $actividad = Actividad::create([
            'nombre' => 'Gimnasio test ' . uniqid(),
            'id_tipo_actividad' => $tipo->id,
        ]);

        $paciente = Paciente::create([
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

        $actPac = ActividadPaciente::create([
            'id_actividad' => $actividad->id,
            'id_paciente' => $paciente->id,
            'fecha_comienzo' => '2026-04-01',
            'cant_sesiones' => 4,
            'es_fijo' => false,
            'total_a_pagar' => 1000,
        ]);

        Turno::create([
            'id_act_pac' => $actPac->id,
            'nro_turno' => 1,
            'fecha_hora' => '2026-04-08 09:00:00',
        ]);

        Turno::create([
            'id_act_pac' => $actPac->id,
            'nro_turno' => 2,
            'fecha_hora' => $fechaUltimoTurno,
        ]);

        return $actPac;
    }
}
