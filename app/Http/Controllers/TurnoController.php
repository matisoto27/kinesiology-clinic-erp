<?php

namespace App\Http\Controllers;

use App\Models\Paciente;
use App\Models\Turno;
use Carbon\Carbon;
use Exception;

class TurnoController extends Controller
{
    public function inicio(?int $idActividad = null, ?string $nombreApellidoPac = null)
    {
        return view('turnos.inicio', compact('idActividad', 'nombreApellidoPac'));
    }

    public function home()
    {
        $horaActual = Carbon::now();
        $limInferior = $horaActual->copy()->startOfHour()->subHour();
        $limSuperior = $horaActual->copy()->startOfHour()->addHours(2);

        $consulta = Turno::with(['actividadPaciente.actividad', 'actividadPaciente.paciente'])->whereBetween('fecha_hora', [$limInferior, $limSuperior]);

        if ($idActividad = request('id_actividad')) {
            $consulta->whereHas('actividadPaciente', fn($q) => $q->where('id_actividad', $idActividad));
        }

        $paciente = null;

        if ($idPaciente = request('id_paciente')) {
            if ($idPaciente > 0) {
                $consulta->whereHas('actividadPaciente', fn($q) => $q->where('id_paciente', $idPaciente));
                $paciente = Paciente::find($idPaciente);
            }
        }

        $turnos = $consulta->paginate(10)->withQueryString();

        return view('inicio', compact('turnos', 'paciente'));
    }

    public function calendario()
    {
        return view('turnos.calendario');
    }

    public function confirmarAsistencia($id)
    {
        try {

            $turno = Turno::find($id);
            if (!$turno) {
                return response()->json(['mensaje' => 'Turno no encontrado.'], 404);
            }

            if ($turno->asiste) {
                return response()->json(['mensaje' => 'La asistencia del turno ya se encuentra confirmada.'], 400);
            }

            $turno->asiste = true;
            $turno->save();

            return response()->json(['mensaje' => '¡Asistencia confirmada con éxito!'], 200);

        } catch (Exception $ex) {
            return response()->json(['mensaje' => $ex->getMessage()], 400);
        }
    }
}
