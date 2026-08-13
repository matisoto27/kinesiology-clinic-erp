<?php

namespace App\Services;

use App\Models\Actividad;
use App\Models\ActividadPaciente;
use App\Models\PacienteFijo;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class PacienteFijoService
{
    /**
     * Da de baja el contrato permanente.
     * - Siempre elimina pacientes_fijos (+ horarios).
     * - Elimina inscripción futura (y su par dual) si no tiene pagos ni Presente.
     * - Elimina inscripción en curso solo si no tiene pagos ni Presente.
     * - Conserva historial.
     *
     * @return array{eliminadas: int, conservadas: int}
     */
    public function eliminar(PacienteFijo $pacienteFijo): array
    {
        try {
            return DB::transaction(function () use ($pacienteFijo) {
                $ahora = Carbon::now();
                $idPaciente = $pacienteFijo->id_paciente;

                $inscripciones = ActividadPaciente::query()
                    ->where('id_paciente', $idPaciente)
                    ->whereIn('id_actividad', [Actividad::GIMNASIO, Actividad::PILATES])
                    ->with(['primerTurno', 'ultimoTurno'])
                    ->withCount('pagos')
                    ->orderBy('id')
                    ->get();

                $eliminadas = 0;
                $conservadas = 0;
                $yaVistas = [];

                foreach ($inscripciones as $inscripcion) {
                    if (isset($yaVistas[$inscripcion->id])) {
                        continue;
                    }

                    $par = $inscripcion->id_act_pac_dual
                        ? $inscripciones->firstWhere('id', $inscripcion->id_act_pac_dual)
                        : null;

                    $yaVistas[$inscripcion->id] = true;
                    if ($par) {
                        $yaVistas[$par->id] = true;
                    }

                    $grupo = collect([$inscripcion, $par])->filter();

                    if ($this->grupoEsHistorial($grupo, $ahora)) {
                        $conservadas += $grupo->count();
                        continue;
                    }

                    if ($this->grupoNoEliminable($grupo)) {
                        $conservadas += $grupo->count();
                        continue;
                    }

                    // Futura o actual eliminable
                    foreach ($grupo as $item) {
                        $item->update(['id_act_pac_dual' => null]);
                    }
                    foreach ($grupo as $item) {
                        $item->delete();
                        $eliminadas++;
                    }
                }

                $pacienteFijo->delete();

                return compact('eliminadas', 'conservadas');
            });
        } catch (Throwable $th) {
            Log::error('[PacienteFijoService@eliminar] Error al dar de baja paciente fijo', [
                'excepción' => $th->getMessage(),
                'id_paciente_fijo' => $pacienteFijo->id,
            ]);

            throw $th;
        }
    }

    /**
     * @param  Collection<int, ActividadPaciente>  $grupo
     */
    private function grupoEsHistorial(Collection $grupo, Carbon $ahora): bool
    {
        return $grupo->every(function (ActividadPaciente $inscripcion) use ($ahora) {
            $ultimo = $inscripcion->ultimoTurno?->fecha_hora;

            return $ultimo !== null && $ultimo->lt($ahora);
        });
    }

    /**
     * @param  Collection<int, ActividadPaciente>  $grupo
     */
    private function grupoNoEliminable(Collection $grupo): bool
    {
        return $grupo->contains(function (ActividadPaciente $inscripcion) {
            if ($inscripcion->pagos_count > 0) {
                return true;
            }

            return $inscripcion->turnos()->where('estado', 'Presente')->exists();
        });
    }
}
