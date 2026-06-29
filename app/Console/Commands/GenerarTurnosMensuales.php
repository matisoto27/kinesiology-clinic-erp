<?php

namespace App\Console\Commands;

use App\Models\Actividad;
use App\Models\ActividadPaciente;
use App\Models\PacienteFijo;
use App\Services\PlanDualService;
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
    protected $signature = 'app:generar-turnos-mensuales {--id_paciente_fijo=}';

    protected $description = 'Genera turnos mensuales para pacientes fijos cuando faltan menos de 30 días de cobertura.';

    public function handle(
        TurnoService $turnoService,
        ExpansorTurnosPatron $expansorTurnosPatron,
        PlanDualService $planDualService
    ): void {
        $consulta = PacienteFijo::query()
            ->select('id', 'id_actividad', 'id_paciente', 'id_pac_fijo_dual')
            ->with([
                'horarios:id_paciente_fijo,dia_semana,hora_inicio',
                'pacFijoDual:id,id_actividad,id_paciente,id_pac_fijo_dual',
                'pacFijoDual.horarios:id_paciente_fijo,dia_semana,hora_inicio',
            ]);

        if ($id = $this->option('id_paciente_fijo')) {
            $consulta->where('id', $id);
        } else {
            $consulta->principales();
        }

        foreach ($consulta->get() as $pacFijo) {
            if ($pacFijo->esDual() && $pacFijo->pacFijoDual) {
                $this->procesarParDual($pacFijo, $pacFijo->pacFijoDual, $turnoService, $expansorTurnosPatron, $planDualService);
                continue;
            }

            $this->procesarPacienteFijoSimple($pacFijo, $turnoService, $expansorTurnosPatron);
        }
    }

    private function procesarPacienteFijoSimple(
        PacienteFijo $pacFijo,
        TurnoService $turnoService,
        ExpansorTurnosPatron $expansorTurnosPatron
    ): void {
        $actPac = $this->obtenerUltimaInscripcion($pacFijo->id_actividad, $pacFijo->id_paciente);

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

        $horariosPaciente = $this->formatearHorariosPacienteFijo($pacFijo);

        while ($fechaReferencia->lessThan($fechaObjetivo)) {
            try {
                DB::transaction(function () use (
                    &$actPac,
                    &$fechaReferencia,
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
                $this->registrarError($ex, $pacFijo->id_actividad, $pacFijo->id_paciente);
                break;
            }
        }
    }

    private function procesarParDual(
        PacienteFijo $pacFijoA,
        PacienteFijo $pacFijoB,
        TurnoService $turnoService,
        ExpansorTurnosPatron $expansorTurnosPatron,
        PlanDualService $planDualService
    ): void {
        $pacFijoGym = (int) $pacFijoA->id_actividad === Actividad::GIMNASIO ? $pacFijoA : $pacFijoB;
        $pacFijoPilates = (int) $pacFijoGym->id === (int) $pacFijoA->id ? $pacFijoB : $pacFijoA;

        $actPacGym = $this->obtenerUltimaInscripcionDual(Actividad::GIMNASIO, $pacFijoGym->id_paciente);
        $actPacPilates = $this->obtenerUltimaInscripcionDual(Actividad::PILATES, $pacFijoPilates->id_paciente);

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

        $horariosGym = $this->formatearHorariosPacienteFijo($pacFijoGym);
        $horariosPilates = $this->formatearHorariosPacienteFijo($pacFijoPilates);

        while (min($fechaReferenciaGym->timestamp, $fechaReferenciaPilates->timestamp) < $fechaObjetivo->timestamp) {
            try {
                DB::transaction(function () use (
                    &$actPacGym,
                    &$actPacPilates,
                    &$fechaReferenciaGym,
                    &$fechaReferenciaPilates,
                    $pacFijoGym,
                    $turnoService,
                    $expansorTurnosPatron,
                    $planDualService,
                    $horariosGym,
                    $horariosPilates
                ) {
                    [$nuevoGym, $nuevoPilates] = $this->renovarInscripcionesDual(
                        $actPacGym,
                        $actPacPilates,
                        $pacFijoGym->id_paciente,
                        $fechaReferenciaGym,
                        $fechaReferenciaPilates,
                        $horariosGym,
                        $horariosPilates,
                        $turnoService,
                        $expansorTurnosPatron,
                        $planDualService
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
                $this->registrarError($ex, $pacFijoGym->id_actividad, $pacFijoGym->id_paciente);
                break;
            }
        }
    }

    private function obtenerUltimaInscripcion(int $idActividad, int $idPaciente): ?ActividadPaciente
    {
        return ActividadPaciente::query()
            ->select('id', 'id_actividad', 'id_paciente', 'cant_sesiones', 'frecuencia_total_dual')
            ->with([
                'actividad:id,nombre',
                'actividad.actividadCombos.precioVigente',
                'actividad.combos',
                'ultimoTurno:turnos.id_act_pac,turnos.fecha_hora',
            ])
            ->where('id_actividad', $idActividad)
            ->where('id_paciente', $idPaciente)
            ->latest('id')
            ->first();
    }

    private function obtenerUltimaInscripcionDual(int $idActividad, int $idPaciente): ?ActividadPaciente
    {
        // No carga los combos porque recibe el precio ya calculado por PlanDualService
        return ActividadPaciente::query()
            ->select('id', 'id_actividad', 'id_paciente', 'cant_sesiones', 'frecuencia_total_dual')
            ->with([
                'actividad:id,nombre',
                'ultimoTurno:turnos.id_act_pac,turnos.fecha_hora',
            ])
            ->where('id_actividad', $idActividad)
            ->where('id_paciente', $idPaciente)
            ->dualCompleto()
            ->latest('id')
            ->first();
    }

    private function formatearHorariosPacienteFijo(PacienteFijo $pacFijo): array
    {
        return $pacFijo->horarios
            ->map(fn ($horario) => [
                'dia_semana' => $horario->dia_semana,
                'hora_inicio' => $horario->hora_inicio,
            ])
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

        $combo = $actPac->actividad->combos
            ->where('cantidad_sesiones', $cantidadSesiones)
            ->first();

        if (!$combo) {
            throw new Exception("No existe un combo configurado para {$cantidadSesiones} sesiones mensuales en la actividad: {$actPac->nombre_actividad}");
        }

        $actCombo = $actPac->actividad->actividadCombos
            ->where('id_combo', $combo->id)
            ->first();

        if (!$actCombo || !$actCombo->precioVigente) {
            throw new Exception("El combo de {$cantidadSesiones} sesiones mensuales de la actividad {$actPac->nombre_actividad} no tiene un precio vigente definido.");
        }

        $nuevoActPac = ActividadPaciente::create([
            'id_actividad' => $actPac->id_actividad,
            'id_paciente' => $idPaciente,
            'fecha_comienzo' => $turnosValidados[0]['fecha_hora'],
            'cant_sesiones' => $cantidadSesiones,
            'es_fijo' => true,
            'total_a_pagar' => $actCombo->precioVigente->valor,
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
        ExpansorTurnosPatron $expansorTurnosPatron,
        PlanDualService $planDualService
    ): array {
        $frecuenciaGym = $actPacGym->frecuenciaSemanal();
        $frecuenciaPilates = $actPacPilates->frecuenciaSemanal();
        $frecuenciaTotal = (int) $actPacGym->frecuencia_total_dual;

        if ($frecuenciaTotal < 1) {
            throw new Exception('La inscripción dual no tiene una frecuencia total válida para renovar.');
        }

        $precioPlan = $planDualService->obtenerPrecioPlan($frecuenciaTotal);
        $totales = $planDualService->calcularTotalesProporcionales(
            $precioPlan,
            $frecuenciaGym,
            $frecuenciaPilates
        );

        $nuevoGym = $this->renovarInscripcionDualPorActividad(
            $actPacGym,
            $idPaciente,
            $fechaReferenciaGym,
            $horariosGym,
            $turnoService,
            $expansorTurnosPatron,
            $totales['total_primera']
        );

        $nuevoPilates = $this->renovarInscripcionDualPorActividad(
            $actPacPilates,
            $idPaciente,
            $fechaReferenciaPilates,
            $horariosPilates,
            $turnoService,
            $expansorTurnosPatron,
            $totales['total_segunda']
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
            'fecha_comienzo' => $turnosValidados[0]['fecha_hora'],
            'cant_sesiones' => $cantidadSesiones,
            'es_fijo' => true,
            'total_a_pagar' => $totalAPagar,
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
