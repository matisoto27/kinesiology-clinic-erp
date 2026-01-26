<?php

namespace App\Http\Controllers;

use App\Http\Requests\AlmacenarPacienteRequest;
use App\Models\Paciente;
use App\Models\SintomaPaciente;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class PacienteController extends Controller
{
    public function crear()
    {
        return view('pacientes.crear');
    }

    public function almacenar(AlmacenarPacienteRequest $request)
    {
        $validados = $request->validated();

        DB::beginTransaction();

        try {
            $contactos = $validados['contactos'] ?? [];
            $sintomas = $validados['sintomas'] ?? [];

            $paciente = Paciente::create($validados);

            if (!empty($contactos)) {
                $paciente->contactosEmergencia()->createMany($contactos);
            }

            if (!empty($sintomas)) {
                $paciente->sintomas()->attach($sintomas, ['fecha_desde' => Carbon::now()]);
            }

            DB::commit();

            return redirect()->route('inicio')->with('exito', '¡El paciente ha sido registrado con éxito!');

        } catch (Throwable $ex) {
            DB::rollBack();
            Log::error('[PacienteController@almacenar] Error al crear el paciente', ['excepción' => $ex->getMessage()]);

            return back()->withErrors(['error' => 'Ocurrió un error inesperado al intentar registrar el paciente.']);
        }
    }

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
        $pacientes = Paciente::all();
        return view('pacientes.inicio', compact('pacientes'));
    }

    public function buscarPorNombre(Request $request)
    {
        $nombre = $request->input('consulta', '');

        try {
            if (strlen($nombre) < 2) {
                return response()->json(['pacientes' => []], 200);
            }

            $consultaPacientes = Paciente::select('id', 'nombre', 'apellido')
                ->where('nombre', 'like', "$nombre%")
                ->limit(10)
                ->orderBy('apellido')
                ->orderBy('nombre');

            if ($request->boolean('incluir_obra')) {
                $consultaPacientes->with([
                    'afiliacionVigente' => function($consulta) {
                        $consulta->select('id_obra_social', 'id_paciente');
                    }
                ]);
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
}
