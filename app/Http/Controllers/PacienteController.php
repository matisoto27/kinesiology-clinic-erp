<?php

namespace App\Http\Controllers;

use App\Models\Paciente;
use App\Models\SintomaPaciente;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class PacienteController extends Controller
{
    public function paginaCrear()
    {
        return view('pacientes.crear');
    }

    public function crearPaciente(Request $request)
    {
        DB::beginTransaction();
        
        try {
            $datosValidados = $request->validate([
                'dni' => 'required|unique:pacientes,dni|numeric|digits_between:7,8',
                'nombre' => 'required|regex:/^[A-Za-záéíóúÁÉÍÓÚñÑ\s]+$/|max:30', // Permite espacios
                'apellido' => 'required|regex:/^[A-Za-záéíóúÁÉÍÓÚñÑ]+$/|max:30', // No permite espacios
                'fecha_nac' => 'required|date',
                'telefono' => 'required|numeric|digits_between:8,20',
                'sintomas' => 'array',
                'sintomas.*' => 'numeric|exists:sintomas,id'
            ], [
                'nombre.regex' => 'El nombre solo puede contener letras y espacios.',
                'apellido.regex' => 'El apellido solo puede contener letras.'
            ], [
                'fecha_nac' => 'fecha de nacimiento'
            ]);
            $datosValidados['fecha_ingreso'] = Carbon::now();
            $datosValidados['sesiones_a_favor'] = 0;
            $datosValidados['activo'] = 1;

            $sintomas = $datosValidados['sintomas'] ?? [];

            $paciente = Paciente::create($datosValidados);

            foreach ($sintomas as $idSintoma) {
                SintomaPaciente::create([
                    'id_sintoma' => $idSintoma,
                    'id_paciente' => $paciente->id,
                    'fecha_desde' => Carbon::now()
                ]);
            }

            DB::commit();

            return redirect()->route('pacientes.paginaCrear')->with([
                'titulo' => 'Paciente registrado',
                'mensaje' => '¡El paciente ha sido registrado con éxito!'
            ]);

        } catch (ValidationException $ex) {
            throw $ex;
        } catch (Throwable $ex) {
            DB::rollBack();
            Log::error('Error al registrar el paciente', ['excepcion' => $ex->getMessage()]);
            return back()
                ->with('error', 'Se ha producido un error inesperado al registrar el paciente.')
                ->withInput();
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

    public function paginaInicio()
    {
        $pacientes = Paciente::all();
        return view('pacientes.inicio', compact('pacientes'));
    }

    public function buscarPorApellidoNombre(Request $request)
    {
        try {

            $nombre = $request->input('query');

            if (strlen($nombre) < 2) {
                return response()->json([
                    'pacientes' => []
                ], 200);
            }

            $pacientes = Paciente::select('id', 'nombre', 'apellido')->where('nombre', 'like', "$nombre%")
                ->limit(10)
                ->orderBy('apellido')
                ->orderBy('nombre')
                ->get();

            return response()->json([
                'pacientes' => $pacientes
            ], 200);

        } catch (Throwable $ex) {

            Log::error('[PacienteController@buscarPorApellidoNombre]', [
                'nombre' => $nombre,
                'excepcion' => $ex->getMessage()
            ]);

            return response()->json([
                'error' => 'Falla interna del servidor. Por favor, inténtelo de nuevo más tarde.'
            ], 500);
        }
    }
}
