<?php

namespace App\Http\Controllers;

use App\Models\ActividadCombo;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Log;
use Throwable;

class ActividadComboController extends Controller
{
    public function obtenerPrecioActual($id)
    {
        try {

            $actividadCombo = ActividadCombo::findOrFail($id);

            return response()->json($actividadCombo->precioActual());

        } catch (ModelNotFoundException $ex) {

            Log::info('[ActividadComboController@obtenerPrecioActual] Recurso no encontrado', [
                'id' => $id,
                'excepción' => $ex->getMessage()
            ]);

            return response()->json([
                'error' => 'Recurso no encontrado.'
            ], 404);

        } catch (Throwable $ex) {

            Log::error('[ActividadComboController@obtenerPrecioActual] Error al obtener el precio actual del combo de la actividad', [
                'id' => $id,
                'excepción' => $ex->getMessage()
            ]);

            return response()->json([
                'error' => 'Se ha producido un error inesperado al obtener el precio actual del combo de la actividad.'
            ], 500);
        }
    }
}
