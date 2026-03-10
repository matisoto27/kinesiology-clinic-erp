<?php

namespace App\Http\Controllers;

use App\Http\Resources\PacienteResource;
use App\Models\Paciente;
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

    public function inicio()
    {
        $consultaPacientes = Paciente::query()
            ->select(['id', 'dni', 'nombre', 'apellido', 'fecha_nac', 'domicilio', 'telefono', 'profesion', 'actividad_fisica', 'es_adulto_mayor', 'vive_con', 'created_at'])
            ->with(['contactosEmergencia:id,nombre,telefono,vinculo,id_paciente', 'sintomasActivos:id,nombre', 'patologias:id,nombre'])
            ->latest()
            ->paginate(10);

        $pacientes = PacienteResource::collection($consultaPacientes);
        return view('pacientes.inicio', compact('pacientes'));
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
