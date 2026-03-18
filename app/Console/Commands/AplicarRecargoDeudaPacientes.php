<?php

namespace App\Console\Commands;

use App\Models\Actividad;
use App\Models\ActividadPaciente;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class AplicarRecargoDeudaPacientes extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:aplicar-recargo-deuda-pacientes';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $hoy = now()->startOfDay();
        $diasDeCortesia = 10;
        $porcentaje = config('app.recargo_mora', 0.15);
        $porcentajeCien = round($porcentaje * 100, 2);

        $inscripcionesPilates = ActividadPaciente::select('id', 'total_a_pagar')
            ->with('ultimoTurno:id,turnos.id_act_pac,fecha_hora')
            ->where('pago_completado', false)
            ->whereNull('fecha_recargo')
            ->where('id_actividad', Actividad::PILATES)
            ->get();

        $cantidadProcesados = 0;

        foreach ($inscripcionesPilates as $actPac) {
            if (!$actPac->ultimoTurno) {
                continue;
            }

            $fechaUltimoTurno = $actPac->ultimoTurno->fecha_hora->startOfDay();
            $finCortesia = $fechaUltimoTurno->copy()->addDays($diasDeCortesia);

            if ($hoy->greaterThan($finCortesia)) {
                $montoRecargo = round($actPac->total_a_pagar * $porcentaje, 2);
                $actPac->update([
                    'fecha_recargo' => $hoy,
                    'porcentaje_recargo' => $porcentajeCien,
                    'monto_recargo' => $montoRecargo
                ]);

                $cantidadProcesados++;
            }
        }

        if ($cantidadProcesados > 0) {
            $mensaje = $cantidadProcesados === 1
                ? "Se aplicó recargo a 1 inscripción de Pilates."
                : "Se aplicaron recargos a {$cantidadProcesados} inscripciones de Pilates.";

            Log::info($mensaje);
        }
    }
}
