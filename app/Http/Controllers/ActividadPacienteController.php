<?php

namespace App\Http\Controllers;

use App\Http\Requests\AlmacenarTurnoRequest;
use App\Services\ActividadPacienteService;
use App\Services\PlanDualService;

class ActividadPacienteController extends Controller
{
    public function store(
        AlmacenarTurnoRequest $request,
        ActividadPacienteService $service,
        PlanDualService $planDualService
    ) {
        $actividadPaciente = $service->registrar($request->validated());

        $payload = ['id' => $actividadPaciente->id];

        if ($actividadPaciente->plan_dual_pendiente) {
            $payload['plan_dual_pendiente'] = $planDualService->obtenerPendiente((int) $actividadPaciente->id_paciente);
        } elseif ($actividadPaciente->esDualCompleto()) {
            $payload['plan_dual_completado'] = true;
        }

        return response()->json($payload);
    }
}
