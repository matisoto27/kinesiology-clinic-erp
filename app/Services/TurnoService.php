<?php

namespace App\Services;

use App\Models\Actividad;
use Exception;

class TurnoService
{
    public function prepararFechas(Actividad $actividad, int $idPaciente, array $turnosSolicitados, int $semanasNecesarias): array
    {
        $primerTurno = $turnosSolicitados[0];

        $comienzo = $primerTurno->copy()->startOfWeek()->startOfDay();
        $fin = $primerTurno->copy()->addWeeks($semanasNecesarias - 1)->addDays(4)->endOfDay();

        $fechasDisponibles = array_flip($actividad->turnosDisponibles($idPaciente, $comienzo, $fin));

        $turnosValidados = [];
        $turnosSolicitadosStr = array_map(fn($t) => $t->toDateTimeString(), $turnosSolicitados);

        foreach ($turnosSolicitados as $i => $turno) {
            $turnoStr = $turnosSolicitadosStr[$i];
            unset($turnosSolicitadosStr[$i]);

            if ($turno->isPast() || !isset($fechasDisponibles[$turnoStr])) {
                $fechasRestringidas = array_flip(array_merge($turnosValidados, $turnosSolicitadosStr));
                $turnoStr = $actividad->buscarReemplazoTurno($turno, $fechasDisponibles, $fechasRestringidas);

                if (!$turnoStr) {
                    throw new Exception('No hay suficientes turnos disponibles para cubrir la cantidad de turnos solicitada.');
                }
            }

            $turnosValidados[] = $turnoStr;
        }

        sort($turnosValidados);

        return array_map(function ($fecha, $indice) {
            return [
                'nro_turno'  => $indice + 1,
                'fecha_hora' => $fecha
            ];
        }, $turnosValidados, array_keys($turnosValidados));
    }

    public function prepararTurnosManuales(array $turnos): array
    {
        sort($turnos);

        return array_map(function ($fecha, $indice) {
            return [
                'nro_turno'  => $indice + 1,
                'fecha_hora' => $fecha
            ];
        }, $turnos, array_keys($turnos));
    }
}
