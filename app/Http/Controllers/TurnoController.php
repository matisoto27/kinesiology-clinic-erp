<?php

namespace App\Http\Controllers;

use App\Models\Paciente;
use App\Models\Turno;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;

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

    public function calendario(Request $request)
    {
        // Parámetro
        $cantSemanas = (int) $request->input('semana', 0);

        // Obtener el comienzo de la semana actual
        $fecha = Carbon::now()->startOfWeek();

        // Avanzar o retroceder semanas
        $fecha = $fecha->addWeeks($cantSemanas);

        $diaInicio = $fecha->copy(); // Lunes
        $diaFin = $diaInicio->copy()->addDays(4); // Viernes

        $consulta = Turno::with(['actividadPaciente.actividad', 'actividadPaciente.paciente'])->whereBetween('fecha_hora', [$diaInicio, $diaFin]);

        // Actividad 1: Gimnasio
        // Actividad 2: Kinesiología
        // Actividad 3: Pilates

        // Franja horaria 1: Turno mañana
        // Franja horaria 2: Turno tarde

        $idActividad = request('actividad', 0);
        $nroHorario = request('horario', 0);
        $horarios = [];
        $horariosDisponibles = [
            '1_1' => ['08:00', '08:30', '09:00', '09:30', '10:00', '10:30', '11:00'],
            '1_2' => ['16:00', '16:30', '17:00', '17:30', '18:00', '18:30', '19:00'],
            '2_1' => ['08:00', '08:30', '09:00', '09:30', '10:00', '10:30', '11:00', '11:30'],
            '2_2' => ['16:00', '16:30', '17:00', '17:30', '18:00', '18:30', '19:00', '19:30'],
            '3_1' => ['08:00', '09:00', '10:00', '11:00'],
            '3_2' => ['16:00', '17:00', '18:00', '19:00'],
        ];

        if (in_array($idActividad, [1,2,3]) && in_array($nroHorario, [1,2])) {

            $clave = "{$idActividad}_{$nroHorario}";
            $horarios = $horariosDisponibles[$clave] ?? [];

            $consulta->whereHas('actividadPaciente', fn($q) => $q->where('id_actividad', $idActividad));

            if ($nroHorario == 1) {
                $consulta->whereTime('fecha_hora', '>=', '08:00')->whereTime('fecha_hora', '<', '12:00');
            } else if ($nroHorario == 2) {
                $consulta->whereTime('fecha_hora', '>=', '16:00')->whereTime('fecha_hora', '<', '20:00');
            }

        } else if (in_array($idActividad, [1,2,3])) {

            foreach ($horariosDisponibles as $clave => $lista) {
                if (str_starts_with($clave, "{$idActividad}_")) {
                    $horarios = array_merge($horarios, $lista);
                }
            }

            $horarios = array_unique($horarios);
            sort($horarios);

            $consulta->whereHas('actividadPaciente', fn($q) => $q->where('id_actividad', $idActividad));

        } else if (in_array($nroHorario, [1,2])) {

            foreach ($horariosDisponibles as $clave => $lista) {
                if (str_ends_with($clave, "_{$nroHorario}")) {
                    $horarios = array_merge($horarios, $lista);
                }
            }

            $horarios = array_unique($horarios);
            sort($horarios);

            if ($nroHorario == 1) {
                $consulta->whereTime('fecha_hora', '>=', '08:00')->whereTime('fecha_hora', '<', '12:00');
            } else if ($nroHorario == 2) {
                $consulta->whereTime('fecha_hora', '>=', '16:00')->whereTime('fecha_hora', '<', '20:00');
            }
        } else {
            $horarios = ['08:00', '08:30', '09:00', '09:30', '10:00', '10:30', '11:00', '11:30', '16:00', '16:30', '17:00', '17:30', '18:00', '18:30', '19:00', '19:30'];
        }

        $turnos = $consulta->get();

        // Agrupar turnos por día y hora
        $turnosPorDia = [];
        foreach (range(0, 4) as $diaSemana) {
            $dia = $diaInicio->copy()->addDays($diaSemana)->format('Y-m-d');
            $turnosPorDia[$dia] = [];
            foreach ($horarios as $horaInicio) {
                $turnosPorDia[$dia][$horaInicio] = $turnos->filter(function ($turno) use ($dia, $horaInicio) {
                    return $turno->fecha_hora->format('Y-m-d') === $dia && $turno->fecha_hora->format('H:i') === $horaInicio;
                });
            }
        }

        return view('turnos.calendario', compact('turnosPorDia', 'diaInicio', 'diaFin', 'horarios', 'cantSemanas'));
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
