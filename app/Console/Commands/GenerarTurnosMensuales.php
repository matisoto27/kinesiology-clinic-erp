<?php

namespace App\Console\Commands;

use App\Models\PacienteFijo;
use App\Services\RenovacionInscripcionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class GenerarTurnosMensuales extends Command
{
    protected $signature = 'app:generar-turnos-mensuales {--id_paciente_fijo=}';

    protected $description = 'Genera la próxima inscripción de pacientes fijos dentro de la ventana de anticipación configurada respecto al fin del ciclo (primer turno + 4 semanas).';

    public function handle(RenovacionInscripcionService $renovacionInscripcionService): void
    {
        $consulta = PacienteFijo::query()
            ->select('id', 'id_paciente')
            ->whereHas('paciente')
            ->with([
                'horarios:id,id_paciente_fijo,id_actividad,dia_semana,hora_inicio',
                'paciente:id,es_gympass',
            ]);

        if ($id = $this->option('id_paciente_fijo')) {
            $consulta->where('id', $id);
        }

        foreach ($consulta->get() as $pacFijo) {
            try {
                $renovacionInscripcionService->renovarSiCorresponde($pacFijo);
            } catch (Throwable $ex) {
                Log::error('[(Command) GenerarTurnosMensuales@handle] Ocurrió un error inesperado al intentar generar los turnos mensuales de las inscripciones fijas.', [
                    'excepción' => $ex->getMessage(),
                    'id_paciente_fijo' => $pacFijo->id,
                    'id_paciente' => $pacFijo->id_paciente,
                ]);

                if ($this->option('id_paciente_fijo')) {
                    throw $ex;
                }
            }
        }
    }
}
