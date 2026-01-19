<?php

namespace App\Http\Controllers;

use App\Models\ActividadCombo;
use App\Models\ActividadPaciente;
use App\Models\Pago;
use App\Models\Profesional;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class PagoController extends Controller
{
    public function crear(Request $request)
    {
        try {
            $idActPac = $request->query('id_act_pac');

            $pendientesDePago = ActividadPaciente::with(['actividad', 'paciente'])
                ->withSum('pagos', 'monto')
                ->sinPagar()
                ->get()
                ->map(function ($inscripcion) {
                    if ($inscripcion->actividad->id_tipo_actividad === 2) {
                        $sesionesRestantes = max(0, $inscripcion->cant_sesiones - ($inscripcion->sesiones_cubiertas ?? 0));

                        if ($sesionesRestantes > 0) {
                            $nuevoTotal = ActividadCombo::calcularTotalAPagar($inscripcion->id_actividad, $sesionesRestantes, $inscripcion->actividad->nombre);
                        } else {
                            $nuevoTotal = 0;
                        }

                        $inscripcion->total_a_pagar = $nuevoTotal;
                    }
                    return $inscripcion;
                });
            $profesionales = Profesional::activo()->orderBy('apellido')->get(['id', 'nombre', 'apellido']);

            return view('pagos.crear', compact('pendientesDePago', 'profesionales', 'idActPac'));

        } catch (Throwable $ex) {
            return redirect()->route('inicio')->with('error', $ex->getMessage());
        }
    }

    public function almacenar(Request $request)
    {
        $validados = $request->validate([
            'id_act_pac' => 'required|integer|exists:actividades_pacientes,id',
            'metodo' => 'required|string|in:Efectivo,Transferencia',
            'monto' => 'required|numeric|gt:0',
            'id_profesional' => 'required|integer|exists:profesionales,id'
        ], [], [
            'id_act_pac' => 'inscripción',
            'id_profesional' => 'profesional',
            'metodo' => 'método de pago',
            'monto' => 'monto'
        ]);

        $inscripcion = ActividadPaciente::withSum('pagos', 'monto')->find($validados['id_act_pac']);
        $deudaTotal = $inscripcion->total_a_pagar - ($inscripcion->pagos_sum_monto ?? 0);

        if ($validados['monto'] > $deudaTotal) {
            throw ValidationException::withMessages([
                'monto' => ["El monto ingresado ($" . number_format($validados['monto'], 2) . ") supera la deuda actual ($" . number_format($deudaTotal, 2) . ")."]
            ]);
        }

        DB::beginTransaction();

        try {
            Pago::create($validados);

            $totalPagado = $inscripcion->pagos()->sum('monto');

            if ($totalPagado >= $inscripcion->total_a_pagar) {
                $inscripcion->update(['pago_completado' => true]);
            }

            DB::commit();
            return redirect()->route('inicio')->with('exito', '¡El pago ha sido registrado con éxito!');

        } catch (Throwable $ex) {
            $mensajeError = $ex->getMessage();

            DB::rollBack();
            Log::error('[PagoController@almacenar] Error al almacenar el pago', [
                'id_act_pac' => $request->id_act_pac,
                'excepción' => $mensajeError
            ]);

            return back()->withErrors(['error' => $mensajeError])->withInput();
        }
    }
}
