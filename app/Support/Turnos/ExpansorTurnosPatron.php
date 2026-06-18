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

    /**
     * Continúa el patrón fijo respetando la frecuencia semanal por semana calendario.
     *
     * @param  array<int, array{dia_semana: int|string, hora_inicio: string}>  $horarios
     * @param  array<int, Carbon|string>  $turnosExistentesOriginales
     * @return array{turnos: array<int, Carbon>, semanas: int}
     */
    public function continuarDesdeUltimoOriginal(
        Carbon $ultimoOriginal,
        array $horarios,
        int $cantidadSesiones,
        int $frecuenciaSemanal,
        array $turnosExistentesOriginales = []
    ): array {
        $ultimoOriginal = $ultimoOriginal->copy();
        $horariosPreparados = $this->prepararHorariosPacienteFijo($horarios);

        if ($horariosPreparados === []) {
            return ['turnos' => [], 'semanas' => 0];
        }

        $existentesPorSemana = $this->contarTurnosPorSemana($turnosExistentesOriginales);
        $nuevosPorSemana = [];

        $lunesInicio = $ultimoOriginal->copy()->startOfWeek(Carbon::MONDAY);
        $semanaOffset = 0;
        $turnosSolicitados = [];
        $ultimaSemanaUsada = 0;
        $maxSemanas = (int) ceil($cantidadSesiones / max(1, $frecuenciaSemanal)) + 3;

        while (count($turnosSolicitados) < $cantidadSesiones && $semanaOffset <= $maxSemanas) {
            $lunesSemana = $lunesInicio->copy()->addWeeks($semanaOffset);

            foreach ($horariosPreparados as $horario) {
                if (count($turnosSolicitados) >= $cantidadSesiones) {
                    break 2;
                }

                $fechaTurno = $lunesSemana->copy()
                    ->dayOfWeek($horario['dia'])
                    ->setTimeFromTimeString($horario['hora']);

                if ($fechaTurno->lte($ultimoOriginal)) {
                    continue;
                }

                $semanaKey = $this->claveSemana($fechaTurno);
                $ocupacionSemanal = ($existentesPorSemana[$semanaKey] ?? 0)
                    + ($nuevosPorSemana[$semanaKey] ?? 0);

                if ($ocupacionSemanal >= $frecuenciaSemanal) {
                    continue;
                }

                $turnosSolicitados[] = $fechaTurno;
                $nuevosPorSemana[$semanaKey] = ($nuevosPorSemana[$semanaKey] ?? 0) + 1;
                $ultimaSemanaUsada = $semanaOffset;
            }

            $semanaOffset++;
        }

        return [
            'turnos' => $turnosSolicitados,
            'semanas' => max(1, $ultimaSemanaUsada + 1),
        ];
    }

    /**
     * @param  array<int, array{dia_semana: int|string, hora_inicio: string}>  $horarios
     * @return array<int, array{dia: int, hora: string}>
     */
    private function prepararHorariosPacienteFijo(array $horarios): array
    {
        return collect($horarios)
            ->sortBy(fn (array $horario) => sprintf(
                '%02d_%s',
                (int) $horario['dia_semana'],
                $horario['hora_inicio']
            ))
            ->values()
            ->map(fn (array $horario) => [
                'dia' => (int) $horario['dia_semana'],
                'hora' => str_replace('hs', '', $horario['hora_inicio']),
            ])
            ->all();
    }

    /**
     * @param  array<int, Carbon|string>  $turnos
     * @return array<string, int>
     */
    private function contarTurnosPorSemana(array $turnos): array
    {
        $conteo = [];

        foreach ($turnos as $turno) {
            $fecha = $turno instanceof Carbon ? $turno->copy() : Carbon::parse($turno);
            $semanaKey = $this->claveSemana($fecha);
            $conteo[$semanaKey] = ($conteo[$semanaKey] ?? 0) + 1;
        }

        return $conteo;
    }

    private function claveSemana(Carbon $fecha): string
    {
        return $fecha->copy()->startOfWeek(Carbon::MONDAY)->format('Y-m-d');
    }
}
