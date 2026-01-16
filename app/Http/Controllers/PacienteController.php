<?php

namespace App\Http\Controllers;

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

    public function almacenar(Request $request)
    {
        $validados = $request->validate([
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
            'dni' => 'DNI',
            'fecha_nac' => 'fecha de nacimiento'
        ]);
        $ahora = Carbon::now();

        DB::beginTransaction();

        try {
            $validados['fecha_ingreso'] = $ahora;
            $validados['sesiones_a_favor'] = 0;
            $validados['activo'] = 1;

            $sintomas = $validados['sintomas'] ?? [];

            $paciente = Paciente::create($validados);

            foreach ($sintomas as $idSintoma) {
                SintomaPaciente::create([
                    'id_sintoma' => $idSintoma,
                    'id_paciente' => $paciente->id,
                    'fecha_desde' => $ahora
                ]);
            }

            DB::commit();

            return redirect()->route('inicio')->with('exito', '¡El paciente ha sido registrado con éxito!');

        } catch (Throwable $ex) {

            $mensajeError = $ex->getMessage();

            DB::rollBack();
            Log::error('[PacienteController@almacenar] Error al registrar el paciente', ['excepción' => $mensajeError]);

            return back()->with('error', $mensajeError)->withInput();
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

            Log::error('[PacienteController@buscarPorNombre]', [
                'nombre' => $nombre,
                'excepcion' => $ex->getMessage()
            ]);

            return response()->json([
                'error' => 'Falla interna del servidor. Por favor, inténtelo de nuevo más tarde.'
            ], 500);
        }
    }
}
