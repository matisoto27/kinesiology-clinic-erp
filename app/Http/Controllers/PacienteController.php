<?php

namespace App\Http\Controllers;

use App\Models\Paciente;
use App\Services\PlanDualService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class PacienteController extends Controller
{
    public function obtenerActividadesGeneralesSinSuscripcion(int $id)
    {
        try {

            $paciente = Paciente::findOrFail($id);

            $actividades = $paciente->obtenerActividadesGeneralesSinSuscripcion();

            return response()->json($actividades);

        } catch (ModelNotFoundException $ex) {

            Log::info('[PacienteController@obtenerActividadesGeneralesSinSuscripcion] Paciente no encontrado', [
                'id_paciente' => $id,
                'excepcion' => $ex->getMessage()
            ]);

            return response()->json([
                'error' => 'Paciente no encontrado.'
            ], 404);

        } catch (Throwable $ex) {

            Log::error('[PacienteController@obtenerActividadesGeneralesSinSuscripcion] Error al obtener las actividades sin suscripción', [
                'id_paciente' => $id,
                'excepcion' => $ex->getMessage()
            ]);

            return response()->json([
                'error' => 'Se ha producido un error inesperado al obtener las actividades sin suscripción.'
            ], 500);
        }
    }

    public function obtenerInscripcionDualPendiente(int $id, PlanDualService $planDualService)
    {
        try {
            Paciente::findOrFail($id);

            $pendiente = $planDualService->obtenerPendiente($id);

            return response()->json([
                'plan_dual_pendiente' => $pendiente
            ]);

        } catch (ModelNotFoundException $ex) {
            return response()->json(['error' => 'Paciente no encontrado.'], 404);
        } catch (Throwable $ex) {

            Log::error('[PacienteController@obtenerInscripcionDualPendiente] Error al obtener inscripción dual pendiente.', [
                'id_paciente' => $id,
                'excepcion' => $ex->getMessage(),
            ]);

            return response()->json(['error' => 'Error al obtener inscripción dual pendiente.'], 500);
        }
    }

    public function obtenerPreviewInscripcionDual(int $id, Request $request, PlanDualService $planDualService)
    {
        try {
            Paciente::findOrFail($id);

            $validados = $request->validate([
                'frecuencia_segunda' => 'required|integer|min:1|max:4',
            ]);

            $primeraInscripcion = $planDualService->obtenerDualPendiente($id);

            if (!$primeraInscripcion) {
                return response()->json(['error' => 'No existe una inscripción dual pendiente para este paciente.'], 422);
            }

            $frecuenciaSegunda = (int) $validados['frecuencia_segunda'];
            $permitidas = $planDualService->frecuenciasPermitidasSegundaInscripcion($primeraInscripcion);

            if (!in_array($frecuenciaSegunda, $permitidas, true)) {
                return response()->json(['error' => 'Frecuencia no válida para completar el plan dual.'], 422);
            }

            return response()->json(
                $planDualService->previewPrecioSegundaVisita(
                    $primeraInscripcion->frecuenciaSemanal(),
                    $frecuenciaSegunda
                )
            );

        } catch (ModelNotFoundException $ex) {
            return response()->json(['error' => 'Paciente no encontrado.'], 404);
        } catch (Throwable $ex) {
            Log::error('[PacienteController@previewPlanDual] Error al calcular preview del plan dual', [
                'id_paciente' => $id,
                'excepcion' => $ex->getMessage(),
            ]);

            return response()->json(['error' => $ex->getMessage()], 422);
        }
    }

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
