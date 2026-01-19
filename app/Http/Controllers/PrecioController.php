<?php

namespace App\Http\Controllers;

use App\Models\ActividadCombo;
use App\Models\Precio;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class PrecioController extends Controller
{
    public function crear($id = null)
    {
        $actividadesCombos = ActividadCombo::activo()
            ->with('actividad', 'combo', 'precioVigente')
            ->get()
            ->map(function ($actCom) {
                $actCom->precio_vigente = (float) ($actCom->precioVigente->valor ?? 0);
                return $actCom;
            });

        return view('precios.crear', compact('actividadesCombos', 'id'));
    }

    public function almacenar(Request $request)
    {
        $validados = $request->validate([
            'id_actividad_combo' => 'required|integer|exists:actividades_combos,id',
            'valor' => 'required|numeric|gt:0'
        ]);

        DB::beginTransaction();

        try {
            $validados['fecha_desde'] = Carbon::now();
            Precio::create($validados);

            DB::commit();

            return redirect()->route('inicio')->with('exito', '¡El precio ha sido actualizado con éxito!');

        } catch (Throwable $ex) {
            $mensajeError = $ex->getMessage();

            DB::rollBack();
            Log::error('[PrecioController@almacenar] Error al almacenar el precio', [
                'id_actividad_combo' => $request->id_actividad_combo,
                'excepción' => $mensajeError
            ]);

            return back()->withErrors(['error' => $mensajeError])->withInput();
        }
    }
}
