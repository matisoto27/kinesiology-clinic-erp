<?php

namespace Tests\Unit;

use App\Models\Actividad;
use App\Services\TurnoService;
use App\Support\Turnos\ExpansorTurnosPatron;
use Carbon\Carbon;
use Tests\TestCase;

class TurnoServiceTest extends TestCase
{
    public function test_preparar_fechas_incluye_semana_del_ultimo_turno_sin_reemplazar(): void
    {
        Carbon::setTestNow('2026-06-16 09:00:00');

        $expansion = (new ExpansorTurnosPatron())->expandir(
            Carbon::parse('2026-06-25'),
            [
                ['dia_semana' => 'Martes', 'hora_inicio' => '16:30:00'],
                ['dia_semana' => 'Jueves', 'hora_inicio' => '19:00:00'],
                ['dia_semana' => 'Viernes', 'hora_inicio' => '10:00:00'],
            ],
            12,
            3
        );

        $turnosDisponibles = array_map(
            fn (Carbon $turno) => $turno->toDateTimeString(),
            $expansion['turnos']
        );

        $actividad = $this->createMock(Actividad::class);
        $actividad->expects($this->once())
            ->method('turnosDisponibles')
            ->with(
                1,
                $this->callback(fn (Carbon $comienzo) => $comienzo->format('Y-m-d') === '2026-06-22'),
                $this->callback(fn (Carbon $fin) => $fin->format('Y-m-d H:i:s') === '2026-07-24 23:59:59')
            )
            ->willReturn($turnosDisponibles);

        $resultado = (new TurnoService())->prepararFechas(
            $actividad,
            1,
            $expansion['turnos'],
            $expansion['semanas']
        );

        $this->assertCount(12, $resultado);
        $this->assertSame('2026-07-21 16:30:00', $resultado[11]['fecha_hora']);

        Carbon::setTestNow();
    }
}
