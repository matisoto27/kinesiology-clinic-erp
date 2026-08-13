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
    private const DIAS_ANTICIPACION = 7;

    private const SEMANAS_CICLO = 4;

    protected $signature = 'app:generar-turnos-mensuales {--id_paciente_fijo=}';

    protected $description = 'Genera la próxima inscripción de pacientes fijos cuando faltan 7 días o menos para el fin del ciclo (primer turno + 4 semanas).';

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
            $horariosPorActividad = $pacFijo->horarios->groupBy(
                fn ($horario) => (int) $horario->id_actividad
            );

            if ($horariosPorActividad->count() > 1) {
                $this->procesarPatronDual($pacFijo, $horariosPorActividad, $turnoService, $expansorTurnosPatron);
                continue;
            }

            foreach ($horariosPorActividad as $idActividad => $horarios) {
                $this->procesarPacienteFijoSimple(
                    $pacFijo,
                    (int) $idActividad,
                    $horarios,
                    $turnoService,
                    $expansorTurnosPatron
                );
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

        if (!$actPac?->primerTurno) {
            return;
        }

        $anclaCiclo = $actPac->primerTurno->fecha_hora->copy();
        $inicioProximoCiclo = $this->inicioProximoCiclo($anclaCiclo);

        if (!$this->debeRenovar($inicioProximoCiclo)) {
            return;
        }

        $horariosPaciente = $this->formatearHorariosParaExpansion($horarios);

        try {
            DB::transaction(function () use (
                $actPac,
                $inicioProximoCiclo,
                $pacFijo,
                $turnoService,
                $expansorTurnosPatron,
                $horariosPaciente
            ) {
                $this->crearInscripcionDesdeAncla(
                    $actPac,
                    $pacFijo->id_paciente,
                    $inicioProximoCiclo,
                    $horariosPaciente,
                    $turnoService,
                    $expansorTurnosPatron,
                    PrecioMensual::obtenerVigentePorFrecuencia(count($horariosPaciente))
                );
            });
        } catch (Throwable $ex) {
            $this->registrarError($ex, $idActividad, $pacFijo->id_paciente);
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
            Log::error('[(Command) GenerarTurnosMensuales@procesarPatronDual] Combinación de actividades inválida.', [
                'id_paciente_fijo' => $pacFijo->id,
                'id_paciente' => $pacFijo->id_paciente,
                'actividades' => $horariosPorActividad->keys()->all(),
            ]);

            return;
        }

        $actPacGym = $this->obtenerUltimaInscripcion(Actividad::GIMNASIO, $pacFijo->id_paciente, esDual: true);
        $actPacPilates = $this->obtenerUltimaInscripcion(Actividad::PILATES, $pacFijo->id_paciente, esDual: true);

        if (!$actPacGym?->primerTurno || !$actPacPilates?->primerTurno) {
            return;
        }

        $anclaCiclo = $actPacGym->primerTurno->fecha_hora->lt($actPacPilates->primerTurno->fecha_hora)
            ? $actPacGym->primerTurno->fecha_hora->copy()
            : $actPacPilates->primerTurno->fecha_hora->copy();

        $inicioProximoCiclo = $this->inicioProximoCiclo($anclaCiclo);

        if (!$this->debeRenovar($inicioProximoCiclo)) {
            return;
        }

        $horariosGymFormateados = $this->formatearHorariosParaExpansion($horariosGym);
        $horariosPilatesFormateados = $this->formatearHorariosParaExpansion($horariosPilates);
        $frecuenciaTotal = count($horariosGymFormateados) + count($horariosPilatesFormateados);

        try {
            DB::transaction(function () use (
                $actPacGym,
                $actPacPilates,
                $inicioProximoCiclo,
                $turnoService,
                $expansorTurnosPatron,
                $horariosGymFormateados,
                $horariosPilatesFormateados,
                $frecuenciaTotal
            ) {
                $precioPlan = PrecioMensual::obtenerVigentePorFrecuencia($frecuenciaTotal);

                $nuevoGym = $this->crearInscripcionDesdeAncla(
                    $actPacGym,
                    $actPacGym->id_paciente,
                    $inicioProximoCiclo,
                    $horariosGymFormateados,
                    $turnoService,
                    $expansorTurnosPatron,
                    $precioPlan
                );

                $nuevoPilates = $this->crearInscripcionDesdeAncla(
                    $actPacPilates,
                    $actPacPilates->id_paciente,
                    $inicioProximoCiclo,
                    $horariosPilatesFormateados,
                    $turnoService,
                    $expansorTurnosPatron,
                    0.0
                );

                $nuevoGym->update([
                    'frecuencia_total_dual' => $frecuenciaTotal,
                    'id_act_pac_dual' => $nuevoPilates->id,
                ]);

                $nuevoPilates->update([
                    'frecuencia_total_dual' => $frecuenciaTotal,
                    'id_act_pac_dual' => $nuevoGym->id,
                    'pago_completado' => true,
                ]);
            });
        } catch (Throwable $ex) {
            $this->registrarError($ex, Actividad::GIMNASIO, $pacFijo->id_paciente);
        }
    }

    private function inicioProximoCiclo(Carbon $primerTurnoOriginal): Carbon
    {
        return $primerTurnoOriginal->copy()->startOfDay()->addWeeks(self::SEMANAS_CICLO);
    }

    private function debeRenovar(Carbon $inicioProximoCiclo): bool
    {
        $limiteAnticipacion = Carbon::now()->addDays(self::DIAS_ANTICIPACION);

        return $inicioProximoCiclo->lte($limiteAnticipacion);
    }

    private function obtenerUltimaInscripcion(
        int $idActividad,
        int $idPaciente,
        bool $esDual = false
    ): ?ActividadPaciente {
        return ActividadPaciente::query()
            ->select('id', 'id_actividad', 'id_paciente', 'cant_sesiones', 'frecuencia_total_dual')
            ->with([
                'actividad:id,nombre,id_tipo_actividad',
                'primerTurno:turnos.id,turnos.id_act_pac,turnos.fecha_hora,turnos.id_turno_original',
            ])
            ->where('id_actividad', $idActividad)
            ->where('id_paciente', $idPaciente)
            ->when($esDual, fn ($q) => $q->dualCompleto())
            ->latest('id')
            ->first();
    }

    /**
     * @return list<array{dia_semana: string, hora_inicio: string}>
     */
    private function formatearHorariosParaExpansion(Collection $horarios): array
    {
        return $horarios
            ->map(fn ($horario) => [
                'dia_semana' => Actividad::enteroADiaSemana((int) $horario->dia_semana),
                'hora_inicio' => (string) $horario->hora_inicio,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  list<array{dia_semana: string, hora_inicio: string}>  $horariosPaciente
     */
    private function crearInscripcionDesdeAncla(
        ActividadPaciente $actPacOrigen,
        int $idPaciente,
        Carbon $anclaProximoCiclo,
        array $horariosPaciente,
        TurnoService $turnoService,
        ExpansorTurnosPatron $expansorTurnosPatron,
        float $totalAPagar
    ): ActividadPaciente {
        $actPacOrigen->loadMissing('actividad');

        $frecuenciaSemanal = count($horariosPaciente);
        $cantidadSesiones = $frecuenciaSemanal * self::SEMANAS_CICLO;

        if ($frecuenciaSemanal < 1) {
            throw new Exception('No hay horarios fijos para generar la próxima inscripción.');
        }

        $expansion = $expansorTurnosPatron->expandir(
            $anclaProximoCiclo,
            $horariosPaciente,
            $cantidadSesiones,
            $frecuenciaSemanal
        );

        if ($expansion['turnos'] === []) {
            throw new Exception('No se pudieron calcular turnos para el próximo ciclo del paciente fijo.');
        }

        $turnosValidados = $turnoService->prepararFechas(
            $actPacOrigen->actividad,
            $idPaciente,
            $expansion['turnos'],
            $expansion['semanas']
        );

        $nuevoActPac = ActividadPaciente::create([
            'id_actividad' => $actPacOrigen->id_actividad,
            'id_paciente' => $idPaciente,
            'cant_sesiones' => $cantidadSesiones,
            'total_a_pagar' => $totalAPagar,
            'pago_completado' => $totalAPagar <= 0, // La 2da inscripcion del par dual tendrá pago_completado = true
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
