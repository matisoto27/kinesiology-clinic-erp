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
    public function crear($id = null)
    {
        try {
            $pendientesDePago = ActividadPaciente::with(['actividad', 'paciente'])
                ->withSum('pagos', 'monto')
                ->sinPagar()
                ->get();

            $profesionales = Profesional::activo()
                ->orderBy('apellido')
                ->orderBy('nombre')
                ->get(['id', 'nombre', 'apellido']);

            return view('pagos.crear', compact('pendientesDePago', 'profesionales', 'id'));

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

        $inscripcion = ActividadPaciente::with('actividad')
            ->withSum('pagos', 'monto')
            ->find($validados['id_act_pac']);
        $deudaActual = (float) $inscripcion->deuda;

        if ($validados['monto'] > $deudaActual) {
            throw ValidationException::withMessages([
                'monto' => ["El monto ingresado ($" . number_format($validados['monto'], 2) . ") supera la deuda actual ($" . number_format($deudaActual, 2) . ")."]
            ]);
        }

        DB::beginTransaction();

        try {
            Pago::create($validados);
            $inscripcion->loadSum('pagos', 'monto'); // Luego de crear el pago, la deuda va a disminuir

            if ($inscripcion->deuda <= 0) {
                $inscripcion->update(['pago_completado' => true]);
            }

            DB::commit();
            return redirect()->route('movimientos')->with('exito', '¡El pago ha sido registrado con éxito!');

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
