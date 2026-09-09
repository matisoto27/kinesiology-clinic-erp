<?php

namespace Tests\Unit;

use App\Support\Turnos\AsignacionTurno;
use App\Support\Turnos\ResultadoPreparacionTurnos;
use Tests\TestCase;

class ResultadoPreparacionTurnosTest extends TestCase
{
    public function test_desde_fechas_exactas_no_reporta_reemplazos(): void
    {
        $resultado = ResultadoPreparacionTurnos::desdeFechasExactas([
            '2026-06-03 18:00:00',
            '2026-06-10 18:00:00',
        ]);

        $this->assertFalse($resultado->huboReemplazos());
        $this->assertSame([], $resultado->reemplazos());
        $this->assertSame([
            ['fecha_hora' => '2026-06-03 18:00:00'],
            ['fecha_hora' => '2026-06-10 18:00:00'],
        ], $resultado->paraPersistir());
    }

    public function test_reemplazos_solo_incluye_asignaciones_distintas(): void
    {
        $resultado = new ResultadoPreparacionTurnos([
            new AsignacionTurno('2026-06-03 18:00:00', '2026-06-03 18:00:00'),
            new AsignacionTurno('2026-06-10 18:00:00', '2026-06-11 17:00:00'),
        ]);

        $this->assertTrue($resultado->huboReemplazos());
        $this->assertSame([
            [
                'solicitado' => '2026-06-10 18:00:00',
                'asignado' => '2026-06-11 17:00:00',
            ],
        ], $resultado->reemplazos());
        $this->assertSame([
            ['fecha_hora' => '2026-06-03 18:00:00'],
            ['fecha_hora' => '2026-06-11 17:00:00'],
        ], $resultado->paraPersistir());
    }

    public function test_asignacion_turno_detecta_reemplazo(): void
    {
        $this->assertFalse((new AsignacionTurno('a', 'a'))->fueReemplazado());
        $this->assertTrue((new AsignacionTurno('a', 'b'))->fueReemplazado());
    }
}
