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

    public function test_continuar_desde_ultimo_original_genera_siguiente_slot_en_la_misma_semana(): void
    {
        $horarios = [
            ['dia_semana' => 2, 'hora_inicio' => '16:30:00'],
            ['dia_semana' => 4, 'hora_inicio' => '19:00:00'],
        ];

        $resultado = $this->expansor->continuarDesdeUltimoOriginal(
            Carbon::parse('2026-01-13 16:30:00'),
            $horarios,
            4,
            2,
            [Carbon::parse('2026-01-13 16:30:00')]
        );

        $this->assertSame(
            '2026-01-15 19:00:00',
            $resultado['turnos'][0]->format('Y-m-d H:i:s')
        );
        $this->assertSame(
            '2026-01-20 16:30:00',
            $resultado['turnos'][1]->format('Y-m-d H:i:s')
        );
    }

    public function test_continuar_desde_ultimo_original_pasa_a_la_semana_siguiente_tras_el_ultimo_slot(): void
    {
        $horarios = [
            ['dia_semana' => 2, 'hora_inicio' => '16:30:00'],
            ['dia_semana' => 4, 'hora_inicio' => '19:00:00'],
        ];

        $resultado = $this->expansor->continuarDesdeUltimoOriginal(
            Carbon::parse('2026-01-15 19:00:00'),
            $horarios,
            2,
            2,
            [
                Carbon::parse('2026-01-13 16:30:00'),
                Carbon::parse('2026-01-15 19:00:00'),
            ]
        );

        $this->assertCount(2, $resultado['turnos']);
        $this->assertSame(
            '2026-01-20 16:30:00',
            $resultado['turnos'][0]->format('Y-m-d H:i:s')
        );
        $this->assertSame(
            '2026-01-22 19:00:00',
            $resultado['turnos'][1]->format('Y-m-d H:i:s')
        );
    }

    public function test_continuar_desde_ultimo_original_f4_no_agrega_viernes_si_la_semana_ya_esta_llena(): void
    {
        $horarios = [
            ['dia_semana' => 1, 'hora_inicio' => '10:00:00'],
            ['dia_semana' => 2, 'hora_inicio' => '10:00:00'],
            ['dia_semana' => 3, 'hora_inicio' => '10:00:00'],
            ['dia_semana' => 5, 'hora_inicio' => '10:00:00'],
        ];

        $resultado = $this->expansor->continuarDesdeUltimoOriginal(
            Carbon::parse('2026-01-15 10:00:00'),
            $horarios,
            2,
            4,
            [
                Carbon::parse('2026-01-12 10:00:00'),
                Carbon::parse('2026-01-13 10:00:00'),
                Carbon::parse('2026-01-14 10:00:00'),
                Carbon::parse('2026-01-15 10:00:00'),
            ]
        );

        $this->assertSame(
            '2026-01-19 10:00:00',
            $resultado['turnos'][0]->format('Y-m-d H:i:s')
        );
        $this->assertSame(
            '2026-01-20 10:00:00',
            $resultado['turnos'][1]->format('Y-m-d H:i:s')
        );
    }

    public function test_continuar_desde_ultimo_original_f2_no_agrega_jueves_si_la_semana_ya_esta_llena(): void
    {
        $horarios = [
            ['dia_semana' => 1, 'hora_inicio' => '10:00:00'],
            ['dia_semana' => 4, 'hora_inicio' => '10:00:00'],
        ];

        $resultado = $this->expansor->continuarDesdeUltimoOriginal(
            Carbon::parse('2026-01-14 10:00:00'),
            $horarios,
            2,
            2,
            [
                Carbon::parse('2026-01-12 10:00:00'),
                Carbon::parse('2026-01-14 10:00:00'),
            ]
        );

        $this->assertSame(
            '2026-01-19 10:00:00',
            $resultado['turnos'][0]->format('Y-m-d H:i:s')
        );
        $this->assertSame(
            '2026-01-22 10:00:00',
            $resultado['turnos'][1]->format('Y-m-d H:i:s')
        );
    }

    public function test_continuar_desde_ultimo_original_respeta_cupo_restante_en_semana_de_transicion(): void
    {
        $horarios = [
            ['dia_semana' => 3, 'hora_inicio' => '10:00:00'],
            ['dia_semana' => 4, 'hora_inicio' => '10:00:00'],
        ];

        $resultado = $this->expansor->continuarDesdeUltimoOriginal(
            Carbon::parse('2026-03-10 10:00:00'),
            $horarios,
            2,
            2,
            [Carbon::parse('2026-03-10 10:00:00')]
        );

        $this->assertSame(
            '2026-03-11 10:00:00',
            $resultado['turnos'][0]->format('Y-m-d H:i:s')
        );
        $this->assertSame(
            '2026-03-18 10:00:00',
            $resultado['turnos'][1]->format('Y-m-d H:i:s')
        );
    }
}
