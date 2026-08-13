<?php

namespace Tests\Unit;

use App\Models\Actividad;
use App\Models\ActividadPaciente;
use App\Models\Paciente;
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

    public function test_par_dual_completo_distingue_primera_y_segunda_inscripcion(): void
    {
        [$primera, $segunda] = $this->crearParDualCompleto();

        $this->assertTrue($primera->esPrimeraDual());
        $this->assertFalse($primera->esSegundaDual());

        $this->assertTrue($segunda->esSegundaDual());
        $this->assertFalse($segunda->esPrimeraDual());
    }

    public function test_filtrar_proximas_pagables_deja_solo_la_inscripcion_mas_antigua_por_paciente_y_actividad(): void
    {
        $pilates = Actividad::create([
            'nombre' => 'Pil',
            'id_tipo_actividad' => Actividad::TIPO_GENERAL,
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

        $original = ActividadPaciente::create([
            'id_actividad' => $pilates->id,
            'id_paciente' => $paciente->id,
            'cant_sesiones' => 8,
            'total_a_pagar' => 20000,
        ]);

        $renovacion1 = ActividadPaciente::create([
            'id_actividad' => $pilates->id,
            'id_paciente' => $paciente->id,
            'cant_sesiones' => 8,
            'total_a_pagar' => 20000,
        ]);

        $renovacion2 = ActividadPaciente::create([
            'id_actividad' => $pilates->id,
            'id_paciente' => $paciente->id,
            'cant_sesiones' => 8,
            'total_a_pagar' => 20000,
        ]);

        $impagas = ActividadPaciente::withSum('pagos', 'monto')->sinPagar()->get();
        $pagables = ActividadPaciente::filtrarProximasPagables($impagas);

        $this->assertCount(1, $pagables->where('id_paciente', $paciente->id));
        $this->assertSame($original->id, $pagables->firstWhere('id_paciente', $paciente->id)?->id);
        $this->assertTrue($original->fresh()->esProximaPagable());
        $this->assertFalse($renovacion1->fresh()->esProximaPagable());
        $this->assertFalse($renovacion2->fresh()->esProximaPagable());
    }

    /**
     * @return array{0: ActividadPaciente, 1: ActividadPaciente}
     */
    private function crearParDualCompleto(): array
    {
        $gimnasio = Actividad::create([
            'nombre' => 'Gym',
            'id_tipo_actividad' => Actividad::TIPO_GENERAL,
        ]);

        $pilates = Actividad::create([
            'nombre' => 'Pil',
            'id_tipo_actividad' => Actividad::TIPO_GENERAL,
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

        $primera = ActividadPaciente::create([
            'id_actividad' => $gimnasio->id,
            'id_paciente' => $paciente->id,
            'cant_sesiones' => 8,
            'total_a_pagar' => 30000,
            'frecuencia_total_dual' => 3,
        ]);

        $segunda = ActividadPaciente::create([
            'id_actividad' => $pilates->id,
            'id_paciente' => $paciente->id,
            'cant_sesiones' => 4,
            'total_a_pagar' => 0,
            'pago_completado' => true,
            'frecuencia_total_dual' => 3,
        ]);

        $primera->update(['id_act_pac_dual' => $segunda->id]);
        $segunda->update(['id_act_pac_dual' => $primera->id]);

        return [$primera->fresh(), $segunda->fresh()];
    }

    private function crearInscripcionConUltimoTurno(string $fechaUltimoTurno): ActividadPaciente
    {
        $actividad = Actividad::create([
            'nombre' => 'Gimnasio T' . uniqid(),
            'id_tipo_actividad' => Actividad::TIPO_GENERAL,
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
            'cant_sesiones' => 4,
            'total_a_pagar' => 1000,
        ]);

        Turno::create([
            'id_act_pac' => $actPac->id,
            'fecha_hora' => '2026-04-08 09:00:00',
        ]);

        Turno::create([
            'id_act_pac' => $actPac->id,
            'fecha_hora' => $fechaUltimoTurno,
        ]);

        return $actPac;
    }
}
