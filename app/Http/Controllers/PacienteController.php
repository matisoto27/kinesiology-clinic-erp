<?php

namespace App\Http\Controllers;

use App\Models\Paciente;
use App\Services\PacienteService;
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

            $pacientes = Paciente::select('id', 'nombre', 'apellido')
                ->buscarPorApNom($nombre)
                ->orderBy('apellido')
                ->orderBy('nombre')
                ->limit(10)
                ->get();

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
        try {
            $resultado = app(PacienteService::class)->eliminar($paciente);

            $mensaje = 'El paciente ha sido eliminado correctamente.';

            if ($resultado['eliminadas'] > 0) {
                $mensaje .= " Se liberaron {$resultado['eliminadas']} inscripción(es)/turno(s) futuros que tenía reservados.";
            }

            if ($resultado['conservadas'] > 0) {
                $mensaje .= ' Se conservaron inscripciones con historial de asistencia o pagos.';
            }

            return redirect()->back()->with('exito', $mensaje);
        } catch (Throwable $th) {
            Log::error('[PacienteController@eliminar] Error al eliminar el paciente.', [
                'id_paciente' => $paciente->id,
                'excepción' => $th->getMessage(),
            ]);

            return redirect()->back()->with('error', 'Ocurrió un error al intentar eliminar el paciente.');
        }
    }
}
