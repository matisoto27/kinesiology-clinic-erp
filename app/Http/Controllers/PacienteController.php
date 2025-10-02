<?php

namespace App\Http\Controllers;

use App\Models\Paciente;
use App\Models\SintomaPaciente;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class PacienteController extends Controller
{
    public function crearPaciente(Request $request)
    {
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

            DB::beginTransaction();

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
        } catch (Exception $ex) {
            DB::rollBack();
            Log::error('Error al registrar paciente', ['exception' => $ex]);
            return back()
                ->with('error', 'Se ha producido un error inesperado.')
                ->withInput();
        }
    }

    public function paginaInicio()
    {
        $pacientes = Paciente::all();
        return view('pacientes.inicio', compact('pacientes'));
    }

    public function paginaCrear()
    {
        return view('pacientes.crear');
    }

    public function buscarPorApellidoNombre(Request $request)
    {
        $query = $request->input('query');

        if (strlen($query) < 2) {
            return response()->json([]);
        }

        $pacientes = Paciente::select('id', 'nombre', 'apellido')->where('nombre', 'like', "$query%")
            ->limit(10)
            ->orderBy('apellido')
            ->orderBy('nombre')
            ->get();

        return response()->json($pacientes);
    }
}
