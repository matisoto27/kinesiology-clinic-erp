<?php

namespace Tests\Unit;

use App\Support\Turnos\ExpansorTurnosPatron;
use Carbon\Carbon;
use Tests\TestCase;

class ExpansorTurnosPatronTest extends TestCase
{
    private ExpansorTurnosPatron $expansor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->expansor = new ExpansorTurnosPatron();
    }

    public function test_expandir_genera_cantidad_solicitada_desde_ancla(): void
    {
        $resultado = $this->expansor->expandir(
            Carbon::parse('2026-06-01'),
            [
                ['dia_semana' => 'Lunes', 'hora_inicio' => '10:00:00'],
                ['dia_semana' => 'Miércoles', 'hora_inicio' => '10:00:00'],
            ],
            4,
            2
        );

        $this->assertCount(4, $resultado['turnos']);
        $this->assertSame('2026-06-01 10:00:00', $resultado['turnos'][0]->format('Y-m-d H:i:s'));
        $this->assertSame('2026-06-03 10:00:00', $resultado['turnos'][1]->format('Y-m-d H:i:s'));
    }

    public function test_expandir_omite_turnos_anteriores_a_la_ancla_en_la_primera_semana(): void
    {
        $resultado = $this->expansor->expandir(
            Carbon::parse('2026-06-04'),
            [
                ['dia_semana' => 'Lunes', 'hora_inicio' => '10:00:00'],
                ['dia_semana' => 'Jueves', 'hora_inicio' => '10:00:00'],
            ],
            4,
            2
        );

        $fechas = array_map(
            fn (Carbon $fecha) => $fecha->format('Y-m-d'),
            $resultado['turnos']
        );

        $this->assertNotContains('2026-06-02', $fechas);
        $this->assertContains('2026-06-04', $fechas);
    }

    public function test_expandir_puede_usar_quinta_semana_si_la_primera_es_parcial(): void
    {
        $resultado = $this->expansor->expandir(
            Carbon::parse('2026-06-04'),
            [
                ['dia_semana' => 'Lunes', 'hora_inicio' => '10:00:00'],
                ['dia_semana' => 'Martes', 'hora_inicio' => '10:00:00'],
                ['dia_semana' => 'Miércoles', 'hora_inicio' => '10:00:00'],
                ['dia_semana' => 'Jueves', 'hora_inicio' => '10:00:00'],
            ],
            16,
            4
        );

        $this->assertCount(16, $resultado['turnos']);

        $ultimoTurno = $resultado['turnos'][15]->format('Y-m-d');

        $this->assertSame('2026-07-01', $ultimoTurno);
    }

    public function test_expandir_frecuencia_tres_ancla_jueves_25_usa_cinco_semanas(): void
    {
        $resultado = $this->expansor->expandir(
            Carbon::parse('2026-06-25'),
            [
                ['dia_semana' => 'Martes', 'hora_inicio' => '16:30:00'],
                ['dia_semana' => 'Jueves', 'hora_inicio' => '19:00:00'],
                ['dia_semana' => 'Viernes', 'hora_inicio' => '10:00:00'],
            ],
            12,
            3
        );

        $this->assertCount(12, $resultado['turnos']);
        $this->assertSame(5, $resultado['semanas']);
        $this->assertSame(
            '2026-07-21 16:30:00',
            $resultado['turnos'][11]->format('Y-m-d H:i:s')
        );
    }
}
