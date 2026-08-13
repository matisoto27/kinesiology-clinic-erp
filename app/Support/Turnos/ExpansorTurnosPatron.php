<?php

namespace App\Support\Turnos;

use Carbon\Carbon;

class ExpansorTurnosPatron
{
    private const DIAS = [
        'Lunes' => Carbon::MONDAY,
        'Martes' => Carbon::TUESDAY,
        'Miércoles' => Carbon::WEDNESDAY,
        'Jueves' => Carbon::THURSDAY,
        'Viernes' => Carbon::FRIDAY,
    ];

    /**
     * @param  array<int, array{dia_semana: string, hora_inicio: string}>  $patron
     * @return array{turnos: array<int, Carbon>, semanas: int}
     */
    public function expandir(
        Carbon $fechaAncla,
        array $patron,
        int $cantidadSesiones,
        int $frecuenciaSemanal
    ): array {
        $fechaAncla = $fechaAncla->copy()->startOfDay();
        $lunesSemanaAncla = $fechaAncla->copy()->startOfWeek(Carbon::MONDAY);

        $turnosPreparados = collect($patron)->map(function (array $turno) {
            return [
                'dia' => self::DIAS[$turno['dia_semana']],
                'hora' => str_replace('hs', '', $turno['hora_inicio']),
            ];
        });

        $turnosSolicitados = [];
        $semana = 0;
        $ultimaSemanaUsada = 0;
        $maxSemanas = (int) ceil($cantidadSesiones / max(1, $frecuenciaSemanal)) + 2;

        while (count($turnosSolicitados) < $cantidadSesiones && $semana <= $maxSemanas) {
            $fechaSemana = $lunesSemanaAncla->copy()->addWeeks($semana);

            foreach ($turnosPreparados as $turno) {
                if (count($turnosSolicitados) >= $cantidadSesiones) {
                    break 2;
                }

                $fechaTurno = $fechaSemana->copy()
                    ->dayOfWeek($turno['dia'])
                    ->setTimeFromTimeString($turno['hora']);

                if ($fechaTurno->lt($fechaAncla)) {
                    continue;
                }

                $turnosSolicitados[] = $fechaTurno;
                $ultimaSemanaUsada = $semana;
            }

            $semana++;
        }

        return [
            'turnos' => $turnosSolicitados,
            'semanas' => max(1, $ultimaSemanaUsada + 1),
        ];
    }

}
