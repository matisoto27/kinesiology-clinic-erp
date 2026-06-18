<?php

namespace App\Console\Commands;

use App\Models\ActividadPaciente;
use App\Models\PacienteFijo;
use App\Services\TurnoService;
use App\Support\Turnos\ExpansorTurnosPatron;
use Carbon\Carbon;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class GenerarTurnosMensuales extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:generar-turnos-mensuales {--id_paciente_fijo=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Genera turnos mensuales para pacientes fijos cuando faltan menos de 30 días de cobertura.';

    public function handle(TurnoService $turnoService, ExpansorTurnosPatron $expansorTurnosPatron): void
    {
        $consulta = PacienteFijo::select('id', 'id_actividad', 'id_paciente')
            ->with('horarios:id_paciente_fijo,dia_semana,hora_inicio');

        if ($id = $this->option('id_paciente_fijo')) {
            $consulta->where('id', $id);
        }

        $pacientesFijos = $consulta->get();

        foreach ($pacientesFijos as $pacFijo) {
            $idActividad = $pacFijo->id_actividad;
            $idPaciente = $pacFijo->id_paciente;

            $actPac = ActividadPaciente::select('id', 'id_actividad', 'id_paciente', 'cant_sesiones')
                ->with([
                    'actividad:id,nombre',
                    'actividad.actividadCombos.precioVigente',
                    'actividad.combos',
                    'ultimoTurno:turnos.id_act_pac,turnos.fecha_hora'
                ])
                ->where('id_actividad', '=', $idActividad)
                ->where('id_paciente', '=', $idPaciente)
                ->latest('id')
                ->first();

            if (!$actPac) {
                continue;
            }

            $ahora = Carbon::now();
            $fechaAnticipacion = $ahora->copy()->addDays(30);
            $fechaObjetivo = $ahora->copy()->addDays(60);

            $fechaReferencia = $actPac->ultimoTurno
                ? $actPac->ultimoTurno->fecha_hora->copy()
                : $ahora->copy();

            if ($fechaReferencia->greaterThan($fechaAnticipacion)) {
                continue;
            }

            $horariosPaciente = $pacFijo->horarios
                ->map(fn ($horario) => [
                    'dia_semana' => $horario->dia_semana,
                    'hora_inicio' => $horario->hora_inicio,
                ])
                ->all();

            while ($fechaReferencia->lessThan($fechaObjetivo)) {
                DB::beginTransaction();

                try {
                    $cantidadSesiones = $actPac->cant_sesiones;
                    $frecuenciaSemanal = count($horariosPaciente);

                    $turnosExistentesOriginales = $actPac->turnos()
                        ->whereNull('id_turno_original')
                        ->pluck('fecha_hora')
                        ->all();

                    $expansion = $expansorTurnosPatron->continuarDesdeUltimoOriginal(
                        $fechaReferencia,
                        $horariosPaciente,
                        $cantidadSesiones,
                        $frecuenciaSemanal,
                        $turnosExistentesOriginales
                    );

                    if ($expansion['turnos'] === []) {
                        throw new Exception('No se pudieron calcular turnos para continuar el patrón del paciente fijo.');
                    }

                    $turnosValidados = $turnoService->prepararFechas(
                        $actPac->actividad,
                        $idPaciente,
                        $expansion['turnos'],
                        $expansion['semanas']
                    );

                    $combo = $actPac->actividad->combos
                        ->where('cantidad_sesiones', $cantidadSesiones)
                        ->first();

                    if (!$combo) {
                        throw new Exception("No existe un combo configurado para {$cantidadSesiones} sesiones mensuales en la actividad: " . $actPac->nombre_actividad);
                    }

                    $actCombo = $actPac->actividad->actividadCombos
                        ->where('id_combo', $combo->id)
                        ->first();

                    if (!$actCombo || !$actCombo->precioVigente) {
                        throw new Exception("El combo de {$cantidadSesiones} sesiones mensuales de la actividad " . $actPac->nombre_actividad . " no tiene un precio vigente definido.");
                    }

                    $nuevoActPac = ActividadPaciente::create([
                        'id_actividad' => $idActividad,
                        'id_paciente' => $idPaciente,
                        'fecha_comienzo' => $turnosValidados[0]['fecha_hora'],
                        'cant_sesiones' => $cantidadSesiones,
                        'es_fijo' => true,
                        'total_a_pagar' => $actCombo->precioVigente->valor
                    ]);
                    $nuevoActPac->turnos()->createMany($turnosValidados);

                    DB::commit();

                    $ultimoTurnoCreado = collect($turnosValidados)->last();
                    $fechaReferencia = Carbon::parse($ultimoTurnoCreado['fecha_hora']);

                    $nuevoActPac->setRelation('actividad', $actPac->actividad);
                    $actPac = $nuevoActPac;

                } catch (Throwable $ex) {
                    DB::rollBack();
                    Log::error('[(Command) GenerarTurnosMensuales@handle] Ocurrió un error inesperado al intentar generar los turnos mensuales de las inscripciones fijas.', [
                        'excepción' => $ex->getMessage(),
                        'id_actividad' => $idActividad,
                        'id_paciente' => $idPaciente
                    ]);

                    if ($this->option('id_paciente_fijo')) {
                        throw $ex;
                    }

                    break;
                }
            }
        }
    }
}
