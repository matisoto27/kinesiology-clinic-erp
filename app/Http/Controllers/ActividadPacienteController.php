<?php

namespace App\Http\Controllers;

use App\Http\Requests\AlmacenarTurnoRequest;
use App\Models\Actividad;
use App\Models\ActividadCombo;
use App\Models\ActividadPaciente;
use App\Models\Turno;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class ActividadPacienteController extends Controller
{
    public function crearGeneral()
    {
        return view('actividades-pacientes.general.crear');
    }

    public function crearKinesiologiaConOrden()
    {
        return view('actividades-pacientes.kinesiologia.con-orden.crear');
    }

    public function crearKinesiologiaSinOrden()
    {
        return view('actividades-pacientes.kinesiologia.sin-orden.crear');
    }

    public function almacenar(AlmacenarTurnoRequest $request)
    {
        $esConOrden = !$request->has('total_a_pagar') || $request->has('mes') || $request->has('dia');
        $ahora = Carbon::now();

        DB::beginTransaction();

        try {
            $validados = $request->validated();

            if ($esConOrden) {
                $validados['cant_sesiones'] = $validados['sesiones_cubiertas'];
                $validados['total_a_pagar'] = ActividadCombo::calcularTotalAPagar($validados['id_actividad'], $validados['sesiones_cubiertas']);
                $validados['fecha_emision_ord'] = Carbon::create($ahora->year, $validados['mes'], $validados['dia']);
            }

            $validados['fecha_comienzo'] = $ahora;
            $validados['es_fijo'] = false;
            $validados['pago_completado'] = $esConOrden;

            $actividadPaciente = ActividadPaciente::create($validados);

            $turnosParaInsertar = $validados['autogenerados']
                ? $this->prepararTurnosAutomaticos($ahora, $validados, $actividadPaciente)
                : $this->prepararTurnosManuales($validados['turnos'], $actividadPaciente);

            if (!empty($turnosParaInsertar)) {
                Turno::insert($turnosParaInsertar);
            }

            DB::commit();
            return response()->noContent();

        } catch (Throwable $ex) {

            $mensajeError = $ex->getMessage();

            Log::error('[ActividadPacienteController@crear] Error al registrar los turnos del paciente', ['exception' => $mensajeError]);

            DB::rollBack();
            return response()->json([
                'error' => $mensajeError
            ], 500);
        }
    }

    private function prepararTurnosAutomaticos(Carbon $ahora, array $validados, ActividadPaciente $actividadPaciente): array
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

        $actividad = Actividad::find($validados['id_actividad']);
        $comienzo = $fechaBase->copy()->startOfWeek()->startOfDay();
        $fin = $fechaBase->copy()->addWeeks($semanasNecesarias - 1)->addDays(4)->endOfDay();

        $fechasDisponibles = array_flip($actividad->turnosDisponibles($validados['id_paciente'], $comienzo, $fin));

        $turnosValidados = [];
        $turnosParaInsertar = [];
        $turnosSolicitadosStr = array_map(fn($t) => $t->toDateTimeString(), $turnosSolicitados);

        foreach ($turnosSolicitados as $i => $turno) {
            $turnoStr = $turnosSolicitadosStr[$i];
            unset($turnosSolicitadosStr[$i]);

            if ($turno->isPast() || !isset($fechasDisponibles[$turnoStr])) {
                $fechasRestringidas = array_flip(array_merge($turnosValidados, $turnosSolicitadosStr));
                $turnoStr = $actividad->buscarReemplazoTurno($turno, $fechasDisponibles, $fechasRestringidas);

                if (!$turnoStr) {
                    throw new Exception('No hay suficientes turnos disponibles para cubrir la cantidad de turnos solicitada.');
                }
            }

            $turnosValidados[] = $turnoStr;
            
            $turnosParaInsertar[] = [
                'id_act_pac' => $actividadPaciente->id,
                'fecha_hora' => $turnoStr,
                'asiste' => false
            ];
        }

        return collect($turnosParaInsertar)
            ->sortBy('fecha_hora')
            ->values()
            ->map(fn($turno, $indice) => array_merge($turno, ['nro_turno' => $indice + 1]))
            ->toArray();
    }

    private function prepararTurnosManuales(array $turnos, ActividadPaciente $actividadPaciente): array
    {
        return collect($turnos)
            ->sort()
            ->values()
            ->map(function (string $fecha, int $indice) use ($actividadPaciente) {
                return [
                    'id_act_pac' => $actividadPaciente->id,
                    'nro_turno'  => $indice + 1,
                    'fecha_hora' => $fecha,
                    'asiste'     => false
                ];
            })
            ->toArray();
    }
}
