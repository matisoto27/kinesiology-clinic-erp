<?php

namespace App\Console\Commands;

use App\Models\Actividad;
use App\Models\ActividadPaciente;
use App\Models\PacienteFijo;
use App\Models\PrecioMensual;
use App\Services\TurnoService;
use App\Support\Turnos\ExpansorTurnosPatron;
use Carbon\Carbon;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class GenerarTurnosMensuales extends Command
{
    protected $signature = 'app:generar-turnos-mensuales {--id_paciente_fijo=}';

    protected $description = 'Genera turnos mensuales para pacientes fijos cuando faltan menos de 30 días de cobertura.';

    public function handle(
        TurnoService $turnoService,
        ExpansorTurnosPatron $expansorTurnosPatron
    ): void {
        $consulta = PacienteFijo::query()
            ->select('id', 'id_paciente')
            ->with(['horarios:id,id_paciente_fijo,id_actividad,dia_semana,hora_inicio']);

        if ($id = $this->option('id_paciente_fijo')) {
            $consulta->where('id', $id);
        }

        foreach ($consulta->get() as $pacFijo) {
            $horariosPorActividad = $pacFijo->horarios->groupBy(fn ($horario) => (int) $horario->id_actividad);

            if ($horariosPorActividad->count() > 1) {
                $this->procesarPatronDual($pacFijo, $horariosPorActividad, $turnoService, $expansorTurnosPatron);
                continue;
            }

            foreach ($horariosPorActividad as $idActividad => $horarios) {
                $this->procesarPacienteFijoSimple($pacFijo, (int) $idActividad, $horarios, $turnoService, $expansorTurnosPatron);
            }
        }
    }

    private function procesarPacienteFijoSimple(
        PacienteFijo $pacFijo,
        int $idActividad,
        Collection $horarios,
        TurnoService $turnoService,
        ExpansorTurnosPatron $expansorTurnosPatron
    ): void {
        $actPac = $this->obtenerUltimaInscripcion($idActividad, $pacFijo->id_paciente);

        if (!$actPac) {
            return;
        }

        $ahora = Carbon::now();
        $fechaAnticipacion = $ahora->copy()->addDays(30);
        $fechaObjetivo = $ahora->copy()->addDays(60);

        $fechaReferencia = $actPac->ultimoTurno
            ? $actPac->ultimoTurno->fecha_hora->copy()
            : $ahora->copy();

        if ($fechaReferencia->greaterThan($fechaAnticipacion)) {
            return;
        }

        $horariosPaciente = $this->formatearHorarios($horarios);

        while ($fechaReferencia->lessThan($fechaObjetivo)) {
            try {
                DB::transaction(function () use (
                    &$actPac,
                    &$fechaReferencia,
                    $idActividad,
                    $pacFijo,
                    $turnoService,
                    $expansorTurnosPatron,
                    $horariosPaciente
                ) {
                    $nuevoActPac = $this->renovarInscripcionSimple(
                        $actPac,
                        $pacFijo->id_paciente,
                        $fechaReferencia,
                        $horariosPaciente,
                        $turnoService,
                        $expansorTurnosPatron
                    );

                    $ultimoTurnoCreado = $nuevoActPac->turnos()->orderByDesc('fecha_hora')->first();
                    $fechaReferencia = $ultimoTurnoCreado->fecha_hora->copy();
                    $nuevoActPac->setRelation('actividad', $actPac->actividad);
                    $actPac = $nuevoActPac;
                });
            } catch (Throwable $ex) {
                $this->registrarError($ex, $idActividad, $pacFijo->id_paciente);
                break;
            }
        }
    }

    private function procesarPatronDual(
        PacienteFijo $pacFijo,
        Collection $horariosPorActividad,
        TurnoService $turnoService,
        ExpansorTurnosPatron $expansorTurnosPatron
    ): void {
        $horariosGym = $horariosPorActividad->get(Actividad::GIMNASIO);
        $horariosPilates = $horariosPorActividad->get(Actividad::PILATES);

        if (!$horariosGym || !$horariosPilates || $horariosPorActividad->count() > 2) {
            Log::error('[(Command) GenerarTurnosMensuales@procesarPatronDual] Combinación de actividades no soportada por el plan dual actual.', [
                'id_paciente_fijo' => $pacFijo->id,
                'id_paciente' => $pacFijo->id_paciente,
                'actividades' => $horariosPorActividad->keys()->all(),
            ]);

            return;
        }

        $actPacGym = $this->obtenerUltimaInscripcionDual(Actividad::GIMNASIO, $pacFijo->id_paciente);
        $actPacPilates = $this->obtenerUltimaInscripcionDual(Actividad::PILATES, $pacFijo->id_paciente);

        if (!$actPacGym || !$actPacPilates) {
            return;
        }

        $ahora = Carbon::now();
        $fechaAnticipacion = $ahora->copy()->addDays(30);
        $fechaObjetivo = $ahora->copy()->addDays(60);

        $fechaReferenciaGym = $actPacGym->ultimoTurno
            ? $actPacGym->ultimoTurno->fecha_hora->copy()
            : $ahora->copy();
        $fechaReferenciaPilates = $actPacPilates->ultimoTurno
            ? $actPacPilates->ultimoTurno->fecha_hora->copy()
            : $ahora->copy();

        if ($fechaReferenciaGym->greaterThan($fechaAnticipacion) && $fechaReferenciaPilates->greaterThan($fechaAnticipacion)) {
            return;
        }

        $horariosGymFormateados = $this->formatearHorarios($horariosGym);
        $horariosPilatesFormateados = $this->formatearHorarios($horariosPilates);

        while (min($fechaReferenciaGym->timestamp, $fechaReferenciaPilates->timestamp) < $fechaObjetivo->timestamp) {
            try {
                DB::transaction(function () use (
                    &$actPacGym,
                    &$actPacPilates,
                    &$fechaReferenciaGym,
                    &$fechaReferenciaPilates,
                    $turnoService,
                    $expansorTurnosPatron,
                    $horariosGymFormateados,
                    $horariosPilatesFormateados
                ) {
                    [$nuevoGym, $nuevoPilates] = $this->renovarInscripcionesDual(
                        $actPacGym,
                        $actPacPilates,
                        $actPacGym->id_paciente,
                        $fechaReferenciaGym,
                        $fechaReferenciaPilates,
                        $horariosGymFormateados,
                        $horariosPilatesFormateados,
                        $turnoService,
                        $expansorTurnosPatron
                    );

                    $ultimoGym = $nuevoGym->turnos()->orderByDesc('fecha_hora')->first();
                    $ultimoPilates = $nuevoPilates->turnos()->orderByDesc('fecha_hora')->first();

                    $fechaReferenciaGym = $ultimoGym->fecha_hora->copy();
                    $fechaReferenciaPilates = $ultimoPilates->fecha_hora->copy();

                    $nuevoGym->setRelation('actividad', $actPacGym->actividad);
                    $nuevoPilates->setRelation('actividad', $actPacPilates->actividad);
                    $actPacGym = $nuevoGym;
                    $actPacPilates = $nuevoPilates;
                });
            } catch (Throwable $ex) {
                $this->registrarError($ex, Actividad::GIMNASIO, $pacFijo->id_paciente);
                break;
            }
        }
    }

    private function obtenerUltimaInscripcion(int $idActividad, int $idPaciente): ?ActividadPaciente
    {
        return ActividadPaciente::query()
            ->select('id', 'id_actividad', 'id_paciente', 'cant_sesiones', 'frecuencia_total_dual')
            ->with([
                'actividad:id,nombre,id_tipo_actividad',
                'ultimoTurno:turnos.id_act_pac,turnos.fecha_hora',
            ])
            ->where('id_actividad', $idActividad)
            ->where('id_paciente', $idPaciente)
            ->latest('id')
            ->first();
    }

    private function obtenerUltimaInscripcionDual(int $idActividad, int $idPaciente): ?ActividadPaciente
    {
        return ActividadPaciente::query()
            ->select('id', 'id_actividad', 'id_paciente', 'cant_sesiones', 'frecuencia_total_dual')
            ->with([
                'actividad:id,nombre,id_tipo_actividad',
                'ultimoTurno:turnos.id_act_pac,turnos.fecha_hora',
            ])
            ->where('id_actividad', $idActividad)
            ->where('id_paciente', $idPaciente)
            ->dualCompleto()
            ->latest('id')
            ->first();
    }

    private function formatearHorarios(Collection $horarios): array
    {
        return $horarios
            ->map(fn ($horario) => [
                'dia_semana' => $horario->dia_semana,
                'hora_inicio' => $horario->hora_inicio,
            ])
            ->values()
            ->all();
    }

    private function renovarInscripcionSimple(
        ActividadPaciente $actPac,
        int $idPaciente,
        Carbon $fechaReferencia,
        array $horariosPaciente,
        TurnoService $turnoService,
        ExpansorTurnosPatron $expansorTurnosPatron
    ): ActividadPaciente {
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

        $totalAPagar = PrecioMensual::obtenerVigentePorFrecuencia($frecuenciaSemanal);

        $nuevoActPac = ActividadPaciente::create([
            'id_actividad' => $actPac->id_actividad,
            'id_paciente' => $idPaciente,
            'cant_sesiones' => $cantidadSesiones,
            'es_fijo' => true,
            'total_a_pagar' => $totalAPagar,
        ]);
        $nuevoActPac->turnos()->createMany($turnosValidados);

        return $nuevoActPac;
    }

    /**
     * @return array{0: ActividadPaciente, 1: ActividadPaciente}
     */
    private function renovarInscripcionesDual(
        ActividadPaciente $actPacGym,
        ActividadPaciente $actPacPilates,
        int $idPaciente,
        Carbon $fechaReferenciaGym,
        Carbon $fechaReferenciaPilates,
        array $horariosGym,
        array $horariosPilates,
        TurnoService $turnoService,
        ExpansorTurnosPatron $expansorTurnosPatron
    ): array {
        $frecuenciaTotal = (int) $actPacGym->frecuencia_total_dual;

        if ($frecuenciaTotal < 1) {
            throw new Exception('La inscripción dual no tiene una frecuencia total válida para renovar.');
        }

        $precioPlan = PrecioMensual::obtenerVigentePorFrecuencia($frecuenciaTotal);

        $nuevoGym = $this->renovarInscripcionDualPorActividad(
            $actPacGym,
            $idPaciente,
            $fechaReferenciaGym,
            $horariosGym,
            $turnoService,
            $expansorTurnosPatron,
            $precioPlan
        );

        $nuevoPilates = $this->renovarInscripcionDualPorActividad(
            $actPacPilates,
            $idPaciente,
            $fechaReferenciaPilates,
            $horariosPilates,
            $turnoService,
            $expansorTurnosPatron,
            0
        );

        $nuevoGym->update([
            'frecuencia_total_dual' => $frecuenciaTotal,
            'id_act_pac_dual' => $nuevoPilates->id,
            'plan_dual_pendiente' => false,
        ]);

        $nuevoPilates->update([
            'frecuencia_total_dual' => $frecuenciaTotal,
            'id_act_pac_dual' => $nuevoGym->id,
            'plan_dual_pendiente' => false,
        ]);

        return [$nuevoGym->fresh(['turnos']), $nuevoPilates->fresh(['turnos'])];
    }

    private function renovarInscripcionDualPorActividad(
        ActividadPaciente $actPac,
        int $idPaciente,
        Carbon $fechaReferencia,
        array $horariosPaciente,
        TurnoService $turnoService,
        ExpansorTurnosPatron $expansorTurnosPatron,
        float $totalAPagar
    ): ActividadPaciente {
        $actPac->loadMissing('actividad');

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
            throw new Exception('No se pudieron calcular turnos para continuar el patrón dual del paciente fijo.');
        }

        $turnosValidados = $turnoService->prepararFechas(
            $actPac->actividad,
            $idPaciente,
            $expansion['turnos'],
            $expansion['semanas']
        );

        $nuevoActPac = ActividadPaciente::create([
            'id_actividad' => $actPac->id_actividad,
            'id_paciente' => $idPaciente,
            'cant_sesiones' => $cantidadSesiones,
            'es_fijo' => true,
            'total_a_pagar' => $totalAPagar,
            'pago_completado' => $totalAPagar <= 0, // La 2da inscripcion del par dual tendrá pago_completado = true
            'plan_dual_pendiente' => false,
        ]);
        $nuevoActPac->turnos()->createMany($turnosValidados);

        return $nuevoActPac;
    }

    private function registrarError(Throwable $ex, int $idActividad, int $idPaciente): void
    {
        Log::error('[(Command) GenerarTurnosMensuales@handle] Ocurrió un error inesperado al intentar generar los turnos mensuales de las inscripciones fijas.', [
            'excepción' => $ex->getMessage(),
            'id_actividad' => $idActividad,
            'id_paciente' => $idPaciente,
        ]);

        if ($this->option('id_paciente_fijo')) {
            throw $ex;
        }
    }
}
