<?php

namespace App\Http\Controllers;

use App\Models\ComboActividad;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Log;

class ComboActividadController extends Controller
{
    public function obtenerPrecioActual($id)
    {
        try {

            $combo = ComboActividad::findOrFail($id);

            return response()->json($combo->precioActual());

        } catch (ModelNotFoundException $ex) {

            Log::info('[ComboActividadController@obtenerPrecioActual] Combo no encontrado', [
                'id_combo' => $id,
                'exception' => $ex->getMessage()
            ]);

            return response()->json([
                'error' => 'Combo no encontrado.'
            ], 404);
        }
    }
}
