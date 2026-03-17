<?php

namespace App\Http\Controllers;

use App\Http\Requests\AlmacenarTurnoRequest;
use App\Models\Actividad;
use App\Models\ActividadCombo;
use App\Models\ActividadPaciente;
use App\Models\Paciente;
use App\Services\TurnoService;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class ActividadPacienteController extends Controller
{
    public function almacenar(AlmacenarTurnoRequest $request, TurnoService $turnoService)
    {
        try {
            $idActPac = DB::transaction(function () use ($request, $turnoService) {
                $validados = $request->validated();
                $esConOrden = !$request->has('total_a_pagar') || $request->has('mes') || $request->has('dia');
                $ahora = Carbon::now();

                if ($esConOrden) {
                    $paciente = Paciente::with('afiliacionVigente')->findOrFail($validados['id_paciente']);
                    if (!$paciente->afiliacionVigente) {
                        throw new Exception('El paciente seleccionado no posee una afiliación vigente a una obra social.');
                    }

                    $cantidadSesiones = (int) $validados['sesiones_cubiertas'];

                    $validados['cant_sesiones'] = $cantidadSesiones;
                    $validados['total_a_pagar'] = ActividadCombo::calcularTotalAPagar($validados['id_actividad'], $cantidadSesiones);
                    $validados['fecha_emision_ord'] = Carbon::create($ahora->year, $validados['mes'], $validados['dia']);
                }

                $validados['fecha_comienzo'] = $ahora;
                $validados['es_fijo'] = false;
                $validados['pago_completado'] = $esConOrden;

                $actividadPaciente = ActividadPaciente::create($validados);

                $turnosParaInsertar = $validados['autogenerados']
                    ? $this->prepararTurnosAutomaticos($ahora, $validados, $turnoService)
                    : $turnoService->prepararTurnosManuales($validados['turnos']);

                $actividadPaciente->turnos()->createMany($turnosParaInsertar);

                return $actividadPaciente->id;
            });

            return response()->json(['id_act_pac' => $idActPac], 201);

        } catch (Throwable $th) {
            if ($th instanceof \Illuminate\Database\QueryException && $th->errorInfo[1] == 1062) {
                $mensajeError = "El paciente ya ha realizado una inscripción a esta actividad en la fecha de hoy.";
            } else {
                $mensajeError = $th->getMessage();
            }

            Log::error('[ActividadPacienteController@crear] Error al registrar los turnos del paciente', ['excepción' => $th->getMessage()]);
            return response()->json(['error' => $mensajeError], 500);
        }
    }

    private function prepararTurnosAutomaticos(Carbon $ahora, array $validados, TurnoService $turnoService): array
    {
        $dias = [
            'Lunes'     => 1,
            'Martes'    => 2,
            'Miércoles' => 3,
            'Jueves'    => 4,
            'Viernes'   => 5,
            'Sábado'    => 6,
            'Domingo'   => 7
        ];

        $cantidadSesiones = (int) ($validados['sesiones_cubiertas'] ?? $validados['cant_sesiones']);
        $frecuenciaSemanal = (int) $validados['frecuencia_semanal'];
        $semanasNecesarias = (int) ceil($cantidadSesiones / $frecuenciaSemanal);

        $fechaBase = (bool) $validados['desde_actual']
            ? $ahora->startOfWeek() // Lunes de la semana actual
            : $ahora->addWeek()->startOfWeek(); // Lunes de la semana siguiente

        $turnosSolicitados = [];

        $turnosPreparados = collect($validados['turnos'])->map(function($turno) use ($dias) {
            return [
                'dia' => $dias[$turno['dia_semana']],
                'hora' => str_replace('hs', '', $turno['hora_inicio'])
            ];
        });

        for ($semana = 0; $semana < $semanasNecesarias; $semana++) {
            $fechaSemana = $fechaBase->copy()->addWeeks($semana);

            foreach ($turnosPreparados as $turno) {
                if (count($turnosSolicitados) >= $cantidadSesiones) break 2;

                $turnosSolicitados[] = $fechaSemana->copy()
                    ->dayOfWeek($turno['dia'])
                    ->setTimeFromTimeString($turno['hora']);
            }
        }

        return $turnoService->prepararFechas(
            Actividad::find($validados['id_actividad']),
            $validados['id_paciente'],
            $turnosSolicitados,
            $semanasNecesarias
        );
    }
}
