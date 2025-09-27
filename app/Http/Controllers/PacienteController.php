<?php

namespace App\Http\Controllers;

use App\Models\Paciente;
use Illuminate\Http\Request;

class PacienteController extends Controller
{
    public function paginaInicio()
    {
        $pacientes = Paciente::all();
        return view('pacientes.inicio', compact('pacientes'));
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
