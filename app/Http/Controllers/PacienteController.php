<?php

namespace App\Http\Controllers;

use App\Models\Paciente;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class PacienteController extends Controller
{
    public function buscarPorNombre(Request $request)
    {
        $nombre = trim($request->input('consulta', ''));

        try {
            if (strlen($nombre) < 2) {
                return response()->json(['pacientes' => []], 200);
            }

            $consultaPacientes = Paciente::select('id', 'nombre', 'apellido')
                ->buscarPorApNom($nombre)
                ->orderBy('apellido')
                ->orderBy('nombre')
                ->limit(10);

            if ($request->boolean('incluir_obra')) {
                $consultaPacientes->with(['afiliacionVigente' => function ($consulta) {
                    $consulta->select('obras_sociales.id', 'obras_sociales.nombre');
                }]);
            }

            $pacientes = $consultaPacientes->get();

            return response()->json(['pacientes' => $pacientes], 200);

        } catch (Throwable $ex) {
            Log::error('[PacienteController@buscarPorNombre]', [
                'consulta' => $request->input('consulta', ''),
                'excepción' => $ex->getMessage()
            ]);

            return response()->json(['error' => 'Falla interna del servidor. Por favor, inténtelo de nuevo más tarde.'], 500);
        }
    }

    public function eliminar(Paciente $paciente)
    {
        $paciente->delete();

        return redirect()->back()->with('exito', 'El paciente ha sido eliminado correctamente.');
    }
}
