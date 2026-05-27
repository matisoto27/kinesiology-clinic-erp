<?php

namespace App\Http\Controllers;

use App\Http\Requests\AlmacenarTurnoRequest;
use App\Services\ActividadPacienteService;

class ActividadPacienteController extends Controller
{
    public function store(
        AlmacenarTurnoRequest $request,
        ActividadPacienteService $service
    ) {
        $actividadPaciente = $service->registrar($request->validated());

        return response()->json($actividadPaciente);
    }
}
