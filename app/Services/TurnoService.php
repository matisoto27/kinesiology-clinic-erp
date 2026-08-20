<?php

namespace App\Services;

use App\Exceptions\ReglaNegocioException;
use App\Models\Actividad;
use App\Models\ActividadPaciente;
use App\Models\Turno;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

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
                    throw new ReglaNegocioException('No hay suficientes turnos disponibles para cubrir la cantidad de turnos solicitada.');
                }
            }

            $turnosValidados[] = $turnoStr;
        }

        sort($turnosValidados);

        return array_map(fn ($fecha) => ['fecha_hora' => $fecha], $turnosValidados);
    }

    public function prepararTurnosManuales(array $turnos): array
    {
        sort($turnos);

        return array_map(fn ($fecha) => ['fecha_hora' => $fecha], $turnos);
    }

    public function validarCuposTurnosCasuales(
        Actividad $actividad,
        int $idPaciente,
        Carbon $comienzo,
        Carbon $fin,
        array $fechasHoraSolicitadas
    ): void {
        if ($fechasHoraSolicitadas === []) {
            throw new ReglaNegocioException('Debe seleccionar al menos un turno.');
        }

        $disponibles = array_flip($actividad->turnosDisponibles($idPaciente, $comienzo, $fin, false));
        $fechasVistas = [];

        foreach ($fechasHoraSolicitadas as $fechaHora) {
            $instante = Carbon::parse($fechaHora);
            $fecha = $instante->toDateString();
            $slot = $instante->toDateTimeString();

            if (isset($fechasVistas[$fecha])) {
                throw new ReglaNegocioException('No puede seleccionar más de un turno por día.');
            }

            if (!isset($disponibles[$slot])) {
                throw new ReglaNegocioException('Uno o más horarios seleccionados ya no tienen cupo disponible.');
            }

            $fechasVistas[$fecha] = true;
        }
    }

    public function reprogramar(Turno $turnoOriginal, Carbon $nuevaFechaHora, ?int $idActividadDestino = null): Turno
    {
        if (str_contains($turnoOriginal->estado, 'Presente')) {
            throw new ReglaNegocioException('No se puede reprogramar un turno donde el paciente ya ha asistido.');
        }

        if (!$turnoOriginal->esAusenteAviso()) {
            throw new ReglaNegocioException('El turno debe marcarse como Ausente avisó antes de asignar una nueva fecha.');
        }

        return DB::transaction(function () use ($turnoOriginal, $nuevaFechaHora, $idActividadDestino) {
            $turno = Turno::lockForUpdate()->findOrFail($turnoOriginal->id);
            $turno->load([
                'turnoRecuperacion',
                'actividadPaciente.actividad',
                'actividadPaciente.actPacDual.actividad',
            ]);

            if ($turno->esReprogramado() || $turno->turnoRecuperacion) {
                throw new ReglaNegocioException('Este turno ya fue reprogramado.');
            }

            $inscripcionOrigen = $turno->actividadPaciente;
            $inscripcionDestino = $this->resolverInscripcionDestino(
                $inscripcionOrigen,
                $idActividadDestino ?? (int) $inscripcionOrigen->id_actividad
            );

            if ((int) $inscripcionDestino->id !== (int) $inscripcionOrigen->id) {
                ActividadPaciente::lockForUpdate()->findOrFail($inscripcionDestino->id);
            }

            $this->asegurarSlotDisponible(
                $inscripcionDestino->actividad,
                $inscripcionOrigen->id_paciente,
                $nuevaFechaHora
            );

            return Turno::create([
                'id_act_pac' => $inscripcionDestino->id,
                'fecha_hora' => $nuevaFechaHora,
                'id_turno_original' => $turno->id,
            ]);
        });
    }

    private function resolverInscripcionDestino(
        ActividadPaciente $origen,
        int $idActividadDestino
    ): ActividadPaciente {
        if ((int) $origen->id_actividad === $idActividadDestino) {
            return $origen;
        }

        $generales = [Actividad::GIMNASIO, Actividad::PILATES];

        if (
            !in_array((int) $origen->id_actividad, $generales, true)
            || !in_array($idActividadDestino, $generales, true)
        ) {
            throw new ReglaNegocioException('Solo se puede cambiar un turno entre Gimnasio y Pilates.');
        }

        if (!$origen->esDualOperativo()) {
            throw new ReglaNegocioException('Solo se puede cambiar de actividad en una inscripción dual activa.');
        }

        $par = $origen->actPacDual;

        if ((int) $par->id_actividad !== $idActividadDestino) {
            throw new ReglaNegocioException('La actividad destino no corresponde al par dual.');
        }

        return $par;
    }

    private function asegurarSlotDisponible(
        Actividad $actividad,
        ?int $idPaciente,
        Carbon $nuevaFechaHora
    ): void {
        $disponibles = array_flip($actividad->turnosDisponibles(
            $idPaciente,
            $nuevaFechaHora->copy()->startOfDay(),
            $nuevaFechaHora->copy()->endOfDay()
        ));

        if (!isset($disponibles[$nuevaFechaHora->toDateTimeString()])) {
            throw new ReglaNegocioException('El horario seleccionado ya no tiene cupo disponible.');
        }
    }
}
