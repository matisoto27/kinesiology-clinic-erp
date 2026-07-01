<?php

namespace App\Services;

use App\Models\Actividad;
use Carbon\Carbon;
use Exception;

class TurnoService
{
    public function prepararFechas(Actividad $actividad, int $idPaciente, array $turnosSolicitados, int $semanasNecesarias): array
    {
        $primerTurno = $turnosSolicitados[0];
        $ultimoTurno = $turnosSolicitados[array_key_last($turnosSolicitados)];

        $comienzo = $primerTurno->copy()->startOfWeek(Carbon::MONDAY)->startOfDay();
        $fin = $ultimoTurno->copy()->endOfWeek(Carbon::FRIDAY)->endOfDay();

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

    public function validarCuposTurnosCasuales(
        Actividad $actividad,
        int $idPaciente,
        Carbon $comienzo,
        Carbon $fin,
        array $fechasHoraSolicitadas
    ): void {
        if ($fechasHoraSolicitadas === []) {
            throw new Exception('Debe seleccionar al menos un turno.');
        }

        $disponibles = array_flip($actividad->turnosDisponibles($idPaciente, $comienzo, $fin, false));
        $fechasVistas = [];

        foreach ($fechasHoraSolicitadas as $fechaHora) {
            $instante = Carbon::parse($fechaHora);
            $fecha = $instante->toDateString();
            $slot = $instante->toDateTimeString();

            if (isset($fechasVistas[$fecha])) {
                throw new Exception('No puede seleccionar más de un turno por día.');
            }

            if (!isset($disponibles[$slot])) {
                throw new Exception('Uno o más horarios seleccionados ya no tienen cupo disponible.');
            }

            $fechasVistas[$fecha] = true;
        }
    }
}
