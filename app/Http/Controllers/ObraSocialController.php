<?php

namespace App\Http\Controllers;

use App\Models\ObraSocial;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class ObraSocialController extends Controller
{
    public function buscarPorNombre(Request $request)
    {
        $nombre = $request->input('consulta', '');

        try {
            if (strlen($nombre) < 2) {
                return response()->json(['obras' => []], 200);
            }

            $obras = ObraSocial::select('id', 'nombre')
                ->where('activo', true)
                ->where('nombre', 'like', trim($nombre) . '%')
                ->orderBy('nombre')
                ->limit(10)
                ->get();

            return response()->json(['obras' => $obras], 200);

        } catch (Throwable $ex) {
            Log::error('[ObraSocialController@buscarPorNombre]', [
                'consulta' => $request->input('consulta', ''),
                'excepción' => $ex->getMessage()
            ]);

            return response()->json(['error' => 'Falla interna del servidor. Por favor, inténtelo de nuevo más tarde.'], 500);
        }
    }
}
