<?php

namespace Tests\Feature;

use App\Models\Actividad;
use App\Models\ActividadPaciente;
use App\Models\Paciente;
use App\Models\PacienteFijo;
use App\Models\Turno;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Regla de negocio: la card "Fechas de abono próximas" del dashboard debe informar, por paciente fijo:
 * - La fecha del primer turno de la inscripción ACTUAL, si la está cursando y todavía no la pagó.
 * - Si no, la fecha del primer turno de la PRÓXIMA inscripción, pero solo si cae esta semana o la siguiente
 *   (hasta el viernes de la semana siguiente inclusive).
 * - urgente = true cuando la fecha mostrada es hoy o anterior a hoy.
 */
class PrincipalProximosVencimientosFijosTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_marca_como_urgente_cuando_la_fecha_mostrada_es_hoy(): void
    {
        Carbon::setTestNow('2026-06-15 10:00:00');

        $paciente = $this->crearPaciente();
        PacienteFijo::create(['id_paciente' => $paciente->id]);

        $actual = $this->crearInscripcion($paciente, pagoCompletado: true);
        $this->crearTurno($actual, '2026-06-01 10:00:00');

        $proxima = $this->crearInscripcion($paciente, pagoCompletado: false);
        $this->crearTurno($proxima, '2026-06-15 10:00:00'); // hoy, dentro de la ventana

        $resultado = $this->obtenerVencimientos();

        $this->assertSame(1, $resultado->total());
        $this->assertSame('2026-06-15', $resultado->first()->fecha->format('Y-m-d'));
        $this->assertTrue($resultado->first()->urgente);
    }

    public function test_muestra_la_proxima_fecha_cuando_la_actual_ya_esta_pagada_y_la_proxima_cae_en_la_ventana(): void
    {
        Carbon::setTestNow('2026-06-15 10:00:00'); // lunes

        $paciente = $this->crearPaciente();
        PacienteFijo::create(['id_paciente' => $paciente->id]);

        $actual = $this->crearInscripcion($paciente, pagoCompletado: true);
        $this->crearTurno($actual, '2026-06-01 10:00:00');

        $proxima = $this->crearInscripcion($paciente, pagoCompletado: false);
        $this->crearTurno($proxima, '2026-06-26 10:00:00'); // viernes de la semana siguiente: dentro de la ventana

        $resultado = $this->obtenerVencimientos();

        $this->assertSame(1, $resultado->total());
        $this->assertSame('2026-06-26', $resultado->first()->fecha->format('Y-m-d'));
        $this->assertFalse($resultado->first()->urgente);
    }

    public function test_no_lista_al_paciente_cuando_la_proxima_inscripcion_cae_fuera_de_la_ventana(): void
    {
        Carbon::setTestNow('2026-06-15 10:00:00'); // lunes

        $paciente = $this->crearPaciente();
        PacienteFijo::create(['id_paciente' => $paciente->id]);

        $actual = $this->crearInscripcion($paciente, pagoCompletado: true);
        $this->crearTurno($actual, '2026-06-01 10:00:00');

        $proxima = $this->crearInscripcion($paciente, pagoCompletado: false);
        $this->crearTurno($proxima, '2026-06-29 10:00:00'); // lunes de la 3ra semana: fuera de la ventana

        $resultado = $this->obtenerVencimientos();

        $this->assertSame(0, $resultado->total());
        $this->assertCount(0, $resultado);
    }

    public function test_muestra_la_primera_inscripcion_cuando_el_paciente_todavia_no_comenzo_a_cursar(): void
    {
        Carbon::setTestNow('2026-06-15 10:00:00'); // lunes

        $paciente = $this->crearPaciente();
        PacienteFijo::create(['id_paciente' => $paciente->id]);

        $primera = $this->crearInscripcion($paciente, pagoCompletado: false);
        $this->crearTurno($primera, '2026-06-19 10:00:00'); // viernes de esta semana, aún no arrancó

        $resultado = $this->obtenerVencimientos();

        $this->assertSame(1, $resultado->total());
        $this->assertSame('2026-06-19', $resultado->first()->fecha->format('Y-m-d'));
        $this->assertFalse($resultado->first()->urgente);
    }

    public function test_inscripcion_dual_usa_el_menor_primer_turno_entre_el_par_como_fecha_del_ciclo(): void
    {
        Carbon::setTestNow('2026-06-15 10:00:00'); // lunes

        $paciente = $this->crearPaciente();
        PacienteFijo::create(['id_paciente' => $paciente->id]);

        $gym = $this->crearInscripcion($paciente, Actividad::GIMNASIO, pagoCompletado: false, totalAPagar: 30000);
        $this->crearTurno($gym, '2026-06-03 10:00:00');

        $pilates = $this->crearInscripcion($paciente, Actividad::PILATES, pagoCompletado: true, totalAPagar: 0);
        $this->crearTurno($pilates, '2026-06-01 10:00:00'); // anterior al de Gym: debe ser el que gane como ancla

        $gym->update(['frecuencia_total_dual' => 3, 'id_act_pac_dual' => $pilates->id]);
        $pilates->update(['frecuencia_total_dual' => 3, 'id_act_pac_dual' => $gym->id]);

        $resultado = $this->obtenerVencimientos();

        $this->assertSame(1, $resultado->total());
        $this->assertSame('2026-06-01', $resultado->first()->fecha->format('Y-m-d'));
        $this->assertTrue($resultado->first()->urgente);
    }

    public function test_no_incluye_pacientes_que_no_son_fijos(): void
    {
        Carbon::setTestNow('2026-06-15 10:00:00'); // lunes

        $paciente = $this->crearPaciente(); // sin registro en pacientes_fijos

        $inscripcion = $this->crearInscripcion($paciente, pagoCompletado: false);
        $this->crearTurno($inscripcion, '2026-06-01 10:00:00');

        $resultado = $this->obtenerVencimientos();

        $this->assertSame(0, $resultado->total());
        $this->assertCount(0, $resultado);
    }

    public function test_pagina_el_listado_sin_mezclarse_con_la_paginacion_de_turnos(): void
    {
        Carbon::setTestNow('2026-06-15 10:00:00');

        for ($i = 1; $i <= 9; $i++) {
            $paciente = $this->crearPaciente("Paciente{$i}");
            PacienteFijo::create(['id_paciente' => $paciente->id]);
            $inscripcion = $this->crearInscripcion($paciente, pagoCompletado: false);
            $this->crearTurno($inscripcion, '2026-06-01 10:00:00');
        }

        $primeraPagina = Livewire::test('principal', ['tiposActividad' => collect()])
            ->instance()
            ->proximosVencimientosFijos();

        $this->assertSame(9, $primeraPagina->total());
        $this->assertSame(8, $primeraPagina->perPage());
        $this->assertCount(8, $primeraPagina);

        $segundaPagina = Livewire::test('principal', ['tiposActividad' => collect()])
            ->call('gotoPage', 2, 'vencimientos')
            ->instance()
            ->proximosVencimientosFijos();

        $this->assertCount(1, $segundaPagina);
        $this->assertSame(2, $segundaPagina->currentPage());
    }

    private function obtenerVencimientos(): LengthAwarePaginator
    {
        return Livewire::test('principal', ['tiposActividad' => collect()])
            ->instance()
            ->proximosVencimientosFijos();
    }

    private function crearInscripcion(
        Paciente $paciente,
        int $idActividad = Actividad::GIMNASIO,
        bool $pagoCompletado = false,
        float $totalAPagar = 20000.00
    ): ActividadPaciente {
        return ActividadPaciente::create([
            'id_actividad' => $idActividad,
            'id_paciente' => $paciente->id,
            'cant_sesiones' => 8,
            'total_a_pagar' => $totalAPagar,
            'pago_completado' => $pagoCompletado,
        ]);
    }

    private function crearTurno(ActividadPaciente $inscripcion, string $fechaHora): Turno
    {
        return Turno::create([
            'id_act_pac' => $inscripcion->id,
            'fecha_hora' => $fechaHora,
        ]);
    }

    private function crearPaciente(string $nombre = 'Nombre'): Paciente
    {
        return Paciente::create([
            'dni' => fake()->unique()->numerify('########'),
            'nombre' => $nombre,
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
