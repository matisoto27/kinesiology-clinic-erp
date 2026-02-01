<?php

namespace App\Http\Controllers;

use App\Models\ObraSocialPaciente;
use App\Models\Paciente;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class ObraSocialPacienteController extends Controller
{
    public function crear($id = null)
    {
        return view('obras-sociales-pacientes.crear', compact('id'));
    }

    public function almacenar(Request $request)
    {
        $validados = $request->validate([
            'id_obra_social' => 'required|integer|exists:obras_sociales,id',
            'id_paciente' => 'required|integer|exists:pacientes,id'
        ], [
            'id_obra_social.exists' => 'La obra social ingresada no existe.',
            'id_paciente.exists' => 'El paciente ingresado no existe.'
        ], [
            'id_obra_social' => 'obra social',
            'id_paciente' => 'paciente'
        ]);

        $paciente = Paciente::with('afiliacionVigente')->find($validados['id_paciente']);
        $afiliacionVigente = $paciente->afiliacionVigente;

        if ($afiliacionVigente && $afiliacionVigente->id_obra_social == $validados['id_obra_social']) {
            throw ValidationException::withMessages([
                'id_obra_social' => 'La obra social seleccionada coincide con la afiliación vigente del paciente.'
            ]);
        }

        $validados['fecha_desde'] = Carbon::now();

        DB::beginTransaction();

        try {
            if ($afiliacionVigente) {
                $paciente->afiliacionVigente->update(['fecha_hasta' => Carbon::now()]);
            }

            ObraSocialPaciente::create($validados);

            DB::commit();
            return redirect()->route('inicio')->with('exito', '¡La obra social del paciente ha sido actualizada con éxito!');

        } catch (Throwable $ex) {
            DB::rollBack();
            Log::error('[ObraSocialPacienteController@almacenar] Error al actualizar la obra social del paciente', [
                'id_obra_social' => $request->input('id_obra_social', ''),
                'id_paciente' => $request->input('id_paciente', ''),
                'excepción' => $ex->getMessage()
            ]);

            return back()->withErrors(['error' => 'Ocurrió un error inesperado al intentar actualizar la obra social del paciente.']);
        }
    }
}
