<?php

namespace App\Services;

use App\Models\Actividad;
use App\Models\ActividadPaciente;
use App\Models\Paciente;
use App\Models\PacienteFijo;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class PacienteService
{
    public function __construct(
        private PacienteFijoService $pacienteFijoService,
    ) {}

    /**
     * Da de baja (soft delete) al paciente y libera el cupo que estuviera reteniendo.
     * - Si tiene inscripción mensual (gimnasio/pilates), delega la limpieza en PacienteFijoService.
     * - Para el resto de las actividades (kinesiología, ATM, RPG, DLM, masajes, quiropraxia),
     *   elimina las inscripciones que aún no tienen historial real (sin turnos pasados,
     *   sin "Presente" y sin pagos), para no dejar turnos futuros ocupando la agenda.
     * - Conserva cualquier inscripción con historial de asistencia o pagos.
     *
     * @return array{eliminadas: int, conservadas: int}
     */
    public function eliminar(Paciente $paciente): array
    {
        try {
            return DB::transaction(function () use ($paciente) {
                $eliminadas = 0;
                $conservadas = 0;

                $pacienteFijo = PacienteFijo::where('id_paciente', $paciente->id)->first();

                if ($pacienteFijo) {
                    $resultadoFijo = $this->pacienteFijoService->eliminar($pacienteFijo);
                    $eliminadas += $resultadoFijo['eliminadas'];
                    $conservadas += $resultadoFijo['conservadas'];
                }

                $resultadoIndividual = $this->eliminarInscripcionesIndividuales($paciente->id);
                $eliminadas += $resultadoIndividual['eliminadas'];
                $conservadas += $resultadoIndividual['conservadas'];

                $paciente->delete();

                return compact('eliminadas', 'conservadas');
            });
        } catch (Throwable $th) {
            Log::error('[PacienteService@eliminar] Error al eliminar el paciente', [
                'excepción' => $th->getMessage(),
                'id_paciente' => $paciente->id,
            ]);

            throw $th;
        }
    }

    /**
     * @return array{eliminadas: int, conservadas: int}
     */
    private function eliminarInscripcionesIndividuales(int $idPaciente): array
    {
        $ahora = Carbon::now();

        // Gimnasio/Pilates se gestionan siempre vía inscripción mensual (PacienteFijoService);
        // acá solo se procesan las actividades individuales (kinesiología, ATM, RPG, DLM, masajes, quiropraxia).
        $inscripciones = ActividadPaciente::query()
            ->where('id_paciente', $idPaciente)
            ->whereNotIn('id_actividad', [Actividad::GIMNASIO, Actividad::PILATES])
            ->with(['ultimoTurno'])
            ->withCount('pagos')
            ->get();

        $eliminadas = 0;
        $conservadas = 0;

        foreach ($inscripciones as $inscripcion) {
            $ultimo = $inscripcion->ultimoTurno?->fecha_hora;
            $esHistorial = $ultimo !== null && $ultimo->lt($ahora);

            $noEliminable = $inscripcion->pagos_count > 0
                || $inscripcion->turnos()->where('estado', 'Presente')->exists();

            if ($esHistorial || $noEliminable) {
                $conservadas++;
                continue;
            }

            $inscripcion->delete();
            $eliminadas++;
        }

        return compact('eliminadas', 'conservadas');
    }
}
