<?php

namespace App\Http\Controllers;

use App\Models\NotaTurno;
use App\Models\Turno;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;

class NotaTurnoController extends Controller
{
    public function almacenar(Request $request, $id)
    {
        try {
            $turno = Turno::find($id);
            if (!$turno) {
                return response()->json(['mensaje' => 'Turno no encontrado.'], 404);
            }

            $datosValidados = $request->validate(['contenidoNota' => 'required|string|max:255']);

            $nota = new NotaTurno();
            $nota->id_turno = $turno->id;
            $nota->fecha_realizada = Carbon::now();
            $nota->contenido = $datosValidados['contenidoNota'];
            $nota->save();

            return response()->json(['mensaje' => '¡Nota registrada con éxito!', 'nota' => $nota], 201);

        } catch (Exception $ex) {
            return response()->json(['mensaje' => $ex->getMessage()], 400);
        }
    }

    public function obtenerNotasDesdeTurno($id)
    {
        try {
            $turno = Turno::find($id);
            if (!$turno) {
                return response()->json(['mensaje' => 'Turno no encontrado.'], 404);
            }

            return response()->json($turno->notas);

        } catch (Exception $ex) {
            return response()->json(['mensaje' => $ex->getMessage()], 400);
        }
    }

    public function eliminar($id)
    {
        try {
            $nota = NotaTurno::find($id);
            if (!$nota) {
                return response()->json(['mensaje' => 'Nota no encontrada.'], 404);
            }

            $nota->delete();

            return response()->json(['mensaje' => '¡Nota eliminada con éxito!'], 200);

        } catch (Exception $ex) {
            return response()->json(['mensaje' => $ex->getMessage()], 400);
        }
    }
}
