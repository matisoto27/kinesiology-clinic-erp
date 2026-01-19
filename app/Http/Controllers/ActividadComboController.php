<?php

namespace App\Http\Controllers;

use App\Models\ActividadCombo;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Log;
use Throwable;

class ActividadComboController extends Controller
{
    public function obtenerPrecioVigente($id)
    {
        try {
            $actividadCombo = ActividadCombo::with('precioVigente')->findOrFail($id);
            $valor = (float) ($actividadCombo->precioVigente->valor ?? 0);

            return response()->json($valor);

        } catch (ModelNotFoundException $ex) {

            Log::info('[ActividadComboController@obtenerPrecioVigente] Recurso no encontrado', [
                'id' => $id,
                'excepcion' => $ex->getMessage()
            ]);

            return response()->json([
                'error' => 'Recurso no encontrado.'
            ], 404);

        } catch (Throwable $ex) {

            Log::error('[ActividadComboController@obtenerPrecioVigente] Error al obtener el precio vigente del combo de la actividad', [
                'id' => $id,
                'excepcion' => $ex->getMessage()
            ]);

            return response()->json([
                'error' => 'Ocurrió un error al intentar obtener el precio vigente del combo.'
            ], 500);
        }
    }
}
