<?php

namespace Tests\Unit;

use App\Exceptions\ReglaNegocioException;
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

        $resultado = (new TurnoService(new ExpansorTurnosPatron()))->prepararDesdePatron(
            $actividad,
            1,
            Carbon::parse('2026-06-25'),
            [
                ['dia_semana' => 'Martes', 'hora_inicio' => '16:30:00'],
                ['dia_semana' => 'Jueves', 'hora_inicio' => '19:00:00'],
                ['dia_semana' => 'Viernes', 'hora_inicio' => '10:00:00'],
            ],
            12,
            3
        )->paraPersistir();

        $this->assertCount(12, $resultado);
        $this->assertSame('2026-07-21 16:30:00', $resultado[11]['fecha_hora']);

        Carbon::setTestNow();
    }

    public function test_preparar_desde_patron_reporta_reemplazos(): void
    {
        Carbon::setTestNow('2026-06-01 08:00:00');

        $patron = [
            ['dia_semana' => 'Miércoles', 'hora_inicio' => '18:00:00'],
        ];
        $fechaAncla = Carbon::parse('2026-06-03');
        $solicitado = '2026-06-10 18:00:00';
        $asignado = '2026-06-11 17:00:00';

        $actividad = $this->createMock(Actividad::class);
        $actividad->expects($this->once())
            ->method('turnosDisponibles')
            ->willReturn([
                '2026-06-03 18:00:00',
                $asignado,
            ]);
        $actividad->expects($this->once())
            ->method('buscarReemplazoTurno')
            ->with(
                $this->callback(fn (Carbon $turno) => $turno->toDateTimeString() === $solicitado),
                $this->isType('array'),
                $this->isType('array')
            )
            ->willReturn($asignado);

        $resultado = (new TurnoService(new ExpansorTurnosPatron()))->prepararDesdePatron(
            $actividad,
            1,
            $fechaAncla,
            $patron,
            2,
            1
        );

        $this->assertTrue($resultado->huboReemplazos());
        $this->assertSame([
            [
                'solicitado' => $solicitado,
                'asignado' => $asignado,
            ],
        ], $resultado->reemplazos());
        $this->assertSame([
            ['fecha_hora' => '2026-06-03 18:00:00'],
            ['fecha_hora' => $asignado],
        ], $resultado->paraPersistir());

        Carbon::setTestNow();
    }

    public function test_preparar_desde_patron_sin_cupo_ni_reemplazo_falla(): void
    {
        Carbon::setTestNow('2026-06-01 08:00:00');

        $actividad = $this->createMock(Actividad::class);
        $actividad->method('turnosDisponibles')->willReturn([]);
        $actividad->method('buscarReemplazoTurno')->willReturn(null);

        $this->expectException(ReglaNegocioException::class);
        $this->expectExceptionMessage('No hay suficientes turnos disponibles para cubrir la cantidad de turnos solicitada.');

        (new TurnoService(new ExpansorTurnosPatron()))->prepararDesdePatron(
            $actividad,
            1,
            Carbon::parse('2026-06-03'),
            [['dia_semana' => 'Miércoles', 'hora_inicio' => '18:00:00']],
            2,
            1
        );
    }

    public function test_preparar_exactos_no_reporta_reemplazos(): void
    {
        $resultado = (new TurnoService(new ExpansorTurnosPatron()))->prepararExactos([
            '2026-06-10 18:00:00',
            '2026-06-03 18:00:00',
        ]);

        $this->assertFalse($resultado->huboReemplazos());
        $this->assertSame([], $resultado->reemplazos());
        $this->assertSame([
            ['fecha_hora' => '2026-06-03 18:00:00'],
            ['fecha_hora' => '2026-06-10 18:00:00'],
        ], $resultado->paraPersistir());
    }

    public function test_validar_cupos_turnos_casuales_acepta_horarios_disponibles(): void
    {
        $actividad = $this->createMock(Actividad::class);
        $actividad->expects($this->once())
            ->method('turnosDisponibles')
            ->with(5, $this->isInstanceOf(Carbon::class), $this->isInstanceOf(Carbon::class), false)
            ->willReturn([
                '2026-06-03 10:00:00',
                '2026-06-05 10:00:00',
            ]);

        (new TurnoService(new ExpansorTurnosPatron()))->validarCuposTurnosCasuales(
            $actividad,
            5,
            Carbon::parse('2026-06-02'),
            Carbon::parse('2026-06-06 23:59:59'),
            ['2026-06-03 10:00:00', '2026-06-05 10:00:00']
        );

        $this->assertTrue(true);
    }

    public function test_validar_cupos_turnos_casuales_rechaza_horario_sin_cupo(): void
    {
        $actividad = $this->createMock(Actividad::class);
        $actividad->method('turnosDisponibles')
            ->willReturn(['2026-06-03 10:00:00']);

        $this->expectException(ReglaNegocioException::class);
        $this->expectExceptionMessage('Uno o más horarios seleccionados ya no tienen cupo disponible.');

        (new TurnoService(new ExpansorTurnosPatron()))->validarCuposTurnosCasuales(
            $actividad,
            5,
            Carbon::parse('2026-06-02'),
            Carbon::parse('2026-06-06 23:59:59'),
            ['2026-06-05 10:00:00']
        );
    }

    public function test_validar_cupos_turnos_casuales_rechaza_fecha_repetida(): void
    {
        $actividad = $this->createMock(Actividad::class);
        $actividad->method('turnosDisponibles')
            ->willReturn([
                '2026-06-03 10:00:00',
                '2026-06-03 11:00:00',
            ]);

        $this->expectException(ReglaNegocioException::class);
        $this->expectExceptionMessage('No puede seleccionar más de un turno por día.');

        (new TurnoService(new ExpansorTurnosPatron()))->validarCuposTurnosCasuales(
            $actividad,
            5,
            Carbon::parse('2026-06-02'),
            Carbon::parse('2026-06-06 23:59:59'),
            ['2026-06-03 10:00:00', '2026-06-03 11:00:00']
        );
    }
}
