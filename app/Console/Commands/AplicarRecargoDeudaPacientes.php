<?php

namespace App\Console\Commands;

use App\Models\Actividad;
use App\Models\ActividadPaciente;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class AplicarRecargoDeudaPacientes extends Command
{
    private const DIAS_CORTESIA = 10;

    protected $signature = 'app:aplicar-recargo-deuda-pacientes';

    protected $description = 'Aplica recargo por mora a inscripciones mensuales gym/pilates impagas a los 10 días del primer turno.';

    public function handle(): int
    {
        $hoy = now()->startOfDay();
        $porcentaje = config('app.recargo_mora', 0.15);
        $porcentajeCien = round($porcentaje * 100, 2);

        $candidatos = ActividadPaciente::query()
            ->select([
                'id',
                'total_a_pagar',
                'id_act_pac_dual',
                'id_actividad',
            ])
            ->with([
                'primerTurno:id,turnos.id_act_pac,fecha_hora',
                'actPacDual:id,id_act_pac_dual',
                'actPacDual.primerTurno:id,turnos.id_act_pac,fecha_hora',
            ])
            ->where('pago_completado', false)
            ->whereNull('fecha_recargo')
            ->where('total_a_pagar', '>', 0)
            ->whereIn('id_actividad', [Actividad::GIMNASIO, Actividad::PILATES])
            ->get();

        $paresVistos = [];
        $cantidadProcesados = 0;

        foreach ($candidatos as $actPac) {
            $clavePar = $actPac->id_act_pac_dual
                ? min((int) $actPac->id, (int) $actPac->id_act_pac_dual)
                : (int) $actPac->id;

            if (isset($paresVistos[$clavePar])) {
                continue;
            }

            $paresVistos[$clavePar] = true;

            $fechaPrimerTurno = $this->fechaPrimerTurnoDelCiclo($actPac);

            if ($fechaPrimerTurno === null) {
                continue;
            }

            $finCortesia = $fechaPrimerTurno->copy()->addDays(self::DIAS_CORTESIA);

            if ($hoy->lessThanOrEqualTo($finCortesia)) {
                continue;
            }

            $montoRecargo = round((float) $actPac->total_a_pagar * $porcentaje, 2);
            $actPac->update([
                'fecha_recargo' => $hoy,
                'porcentaje_recargo' => $porcentajeCien,
                'monto_recargo' => $montoRecargo,
            ]);

            $cantidadProcesados++;
        }

        if ($cantidadProcesados > 0) {
            $mensaje = $cantidadProcesados === 1
                ? 'Se aplicó recargo a 1 inscripción mensual impaga.'
                : "Se aplicaron recargos a {$cantidadProcesados} inscripciones mensuales impagas.";

            Log::info($mensaje);
        }

        return self::SUCCESS;
    }

    private function fechaPrimerTurnoDelCiclo(ActividadPaciente $actPac): ?Carbon
    {
        $fechas = collect([
            $actPac->primerTurno?->fecha_hora,
            $actPac->actPacDual?->primerTurno?->fecha_hora,
        ])->filter();

        if ($fechas->isEmpty()) {
            return null;
        }

        return Carbon::parse($fechas->min())->startOfDay();
    }
}
