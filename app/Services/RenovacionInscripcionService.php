<?php

namespace App\Services;

use App\Models\Actividad;
use App\Models\ActividadPaciente;
use App\Models\NotaTurno;
use App\Models\PacienteFijo;
use App\Models\PrecioMensual;
use App\Models\Turno;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class RenovacionInscripcionService
{
    private const SEMANAS_CICLO = 4;

    public function __construct(
        private TurnoService $turnoService,
    ) {}

    /**
     * Renueva la inscripción del paciente fijo si el próximo ciclo cae en la ventana de anticipación.
     */
    public function renovarSiCorresponde(PacienteFijo $pacFijo): void
    {
        DB::transaction(function () use ($pacFijo) {
            $this->intentarRenovar($pacFijo->loadMissing(['horarios', 'paciente']), forzar: false);
        });
    }

    /**
     * Tras un cambio de patrón en el ciclo vigente: captura AA (+recuperación),
     * elimina inscripciones cuyo ciclo empieza en o después de $limiteExclusivoCicloVigente,
     * vuelve a crear la próxima si aplica, y reaplica AA/recuperaciones.
     */
    public function regenerarInscripcionesFuturas(
        PacienteFijo $pacFijo,
        Carbon $limiteExclusivoCicloVigente
    ): void {
        $pacFijo->loadMissing(['horarios', 'paciente']);

        $futuras = $this->obtenerInscripcionesFuturas(
            (int) $pacFijo->id_paciente,
            $limiteExclusivoCicloVigente
        );

        if ($futuras->isEmpty()) {
            return;
        }

        $snapshot = $this->capturarAusentesAviso($futuras);
        $this->eliminarInscripciones($futuras);
        $this->intentarRenovar($pacFijo->fresh(['horarios', 'paciente']), forzar: true);

        if ($snapshot !== []) {
            $this->reaplicarAusentesAviso((int) $pacFijo->id_paciente, $snapshot);
        }
    }

    private function intentarRenovar(PacienteFijo $pacFijo, bool $forzar): void
    {
        $horariosPorActividad = $pacFijo->horarios->groupBy(
            fn ($horario) => (int) $horario->id_actividad
        );

        if ($horariosPorActividad->isEmpty()) {
            return;
        }

        if ($horariosPorActividad->count() > 1) {
            $this->renovarPatronDual($pacFijo, $horariosPorActividad, $forzar);

            return;
        }

        foreach ($horariosPorActividad as $idActividad => $horarios) {
            $this->renovarPatronSimple(
                $pacFijo,
                (int) $idActividad,
                $horarios,
                $forzar
            );
        }
    }

    private function renovarPatronSimple(
        PacienteFijo $pacFijo,
        int $idActividad,
        Collection $horarios,
        bool $forzar
    ): void {
        $actPac = $this->obtenerUltimaInscripcion($idActividad, (int) $pacFijo->id_paciente);

        if (!$actPac?->primerTurno) {
            return;
        }

        $inicioProximoCiclo = $this->inicioProximoCiclo($actPac->primerTurno->fecha_hora->copy());

        if (!$forzar && !$this->debeRenovar($inicioProximoCiclo)) {
            return;
        }

        if ($this->yaExisteInscripcionEnAncla((int) $pacFijo->id_paciente, $idActividad, $inicioProximoCiclo)) {
            return;
        }

        $horariosPaciente = $this->formatearHorariosParaExpansion($horarios);

        $this->crearInscripcionDesdeAncla(
            $actPac,
            (int) $pacFijo->id_paciente,
            $inicioProximoCiclo,
            $horariosPaciente,
            PrecioMensual::obtenerVigenteParaPaciente($pacFijo->paciente, count($horariosPaciente))
        );
    }

    private function renovarPatronDual(
        PacienteFijo $pacFijo,
        Collection $horariosPorActividad,
        bool $forzar
    ): void {
        $horariosGym = $horariosPorActividad->get(Actividad::GIMNASIO);
        $horariosPilates = $horariosPorActividad->get(Actividad::PILATES);

        if (!$horariosGym || !$horariosPilates || $horariosPorActividad->count() > 2) {
            Log::error('[RenovacionInscripcionService@renovarPatronDual] Combinación de actividades inválida.', [
                'id_paciente_fijo' => $pacFijo->id,
                'id_paciente' => $pacFijo->id_paciente,
                'actividades' => $horariosPorActividad->keys()->all(),
            ]);

            return;
        }

        $actPacGym = $this->obtenerUltimaInscripcion(Actividad::GIMNASIO, (int) $pacFijo->id_paciente, esDual: true);
        $actPacPilates = $this->obtenerUltimaInscripcion(Actividad::PILATES, (int) $pacFijo->id_paciente, esDual: true);

        if (!$actPacGym?->primerTurno || !$actPacPilates?->primerTurno) {
            return;
        }

        $anclaCiclo = $actPacGym->primerTurno->fecha_hora->lt($actPacPilates->primerTurno->fecha_hora)
            ? $actPacGym->primerTurno->fecha_hora->copy()
            : $actPacPilates->primerTurno->fecha_hora->copy();

        $inicioProximoCiclo = $this->inicioProximoCiclo($anclaCiclo);

        if (!$forzar && !$this->debeRenovar($inicioProximoCiclo)) {
            return;
        }

        if (
            $this->yaExisteInscripcionEnAncla((int) $pacFijo->id_paciente, Actividad::GIMNASIO, $inicioProximoCiclo)
            || $this->yaExisteInscripcionEnAncla((int) $pacFijo->id_paciente, Actividad::PILATES, $inicioProximoCiclo)
        ) {
            return;
        }

        $horariosGymFormateados = $this->formatearHorariosParaExpansion($horariosGym);
        $horariosPilatesFormateados = $this->formatearHorariosParaExpansion($horariosPilates);
        $frecuenciaTotal = count($horariosGymFormateados) + count($horariosPilatesFormateados);
        $precioPlan = PrecioMensual::obtenerVigenteParaPaciente($pacFijo->paciente, $frecuenciaTotal);

        $nuevoGym = $this->crearInscripcionDesdeAncla(
            $actPacGym,
            (int) $actPacGym->id_paciente,
            $inicioProximoCiclo,
            $horariosGymFormateados,
            $precioPlan
        );

        $nuevoPilates = $this->crearInscripcionDesdeAncla(
            $actPacPilates,
            (int) $actPacPilates->id_paciente,
            $inicioProximoCiclo,
            $horariosPilatesFormateados,
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
    }

    /**
     * @return Collection<int, ActividadPaciente>
     */
    private function obtenerInscripcionesFuturas(int $idPaciente, Carbon $limiteExclusivo): Collection
    {
        $candidatas = ActividadPaciente::query()
            ->with(['primerTurno', 'actividad:id,nombre'])
            ->where('id_paciente', $idPaciente)
            ->whereIn('id_actividad', [Actividad::GIMNASIO, Actividad::PILATES])
            ->get();

        $futuras = $candidatas->filter(function (ActividadPaciente $inscripcion) use ($limiteExclusivo) {
            $primer = $inscripcion->primerTurno?->fecha_hora;

            return $primer !== null && $primer->gte($limiteExclusivo);
        });

        $ids = $futuras->pluck('id')->map(fn ($id) => (int) $id)->all();

        foreach ($futuras as $inscripcion) {
            if ($inscripcion->id_act_pac_dual) {
                $ids[] = (int) $inscripcion->id_act_pac_dual;
            }
        }

        if ($ids !== []) {
            $inversos = ActividadPaciente::query()
                ->whereIn('id_act_pac_dual', $ids)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();

            $ids = array_values(array_unique(array_merge($ids, $inversos)));
        }

        return $candidatas->whereIn('id', $ids)->values();
    }

    /**
     * @param  Collection<int, ActividadPaciente>  $inscripciones
     * @return list<array{
     *   original: array{id_actividad: int, fecha_hora: string},
     *   recuperacion: null|array{id_actividad: int, fecha_hora: string, estado: string}
     * }>
     */
    private function capturarAusentesAviso(Collection $inscripciones): array
    {
        if ($inscripciones->isEmpty()) {
            return [];
        }

        $snapshot = [];
        $ids = $inscripciones->pluck('id')->all();

        $turnosAa = Turno::query()
            ->with(['actividadPaciente:id,id_actividad', 'turnoRecuperacion.actividadPaciente:id,id_actividad'])
            ->whereIn('id_act_pac', $ids)
            ->whereNull('id_turno_original')
            ->where('estado', 'Ausente avisó')
            ->get();

        foreach ($turnosAa as $turno) {
            $idActividad = (int) ($turno->actividadPaciente?->id_actividad ?? 0);

            if ($idActividad < 1) {
                continue;
            }

            $item = [
                'original' => [
                    'id_actividad' => $idActividad,
                    'fecha_hora' => $turno->fecha_hora->toDateTimeString(),
                ],
                'recuperacion' => null,
            ];

            $recuperacion = $turno->turnoRecuperacion;

            if ($recuperacion) {
                $idActividadRec = (int) ($recuperacion->actividadPaciente?->id_actividad ?? 0);

                if ($idActividadRec > 0) {
                    $item['recuperacion'] = [
                        'id_actividad' => $idActividadRec,
                        'fecha_hora' => $recuperacion->fecha_hora->toDateTimeString(),
                        'estado' => (string) $recuperacion->getRawOriginal('estado'),
                    ];
                }
            }

            $snapshot[] = $item;
        }

        return $snapshot;
    }

    /**
     * @param  Collection<int, ActividadPaciente>  $inscripciones
     */
    private function eliminarInscripciones(Collection $inscripciones): void
    {
        $ids = $inscripciones->pluck('id')->map(fn ($id) => (int) $id)->all();

        if ($ids === []) {
            return;
        }

        $turnoIds = Turno::query()
            ->whereIn('id_act_pac', $ids)
            ->pluck('id')
            ->all();

        if ($turnoIds !== []) {
            NotaTurno::query()->whereIn('id_turno', $turnoIds)->delete();
        }

        foreach ($inscripciones as $inscripcion) {
            $inscripcion->update([
                'id_act_pac_dual' => null,
                'frecuencia_total_dual' => null,
            ]);
        }

        foreach ($inscripciones as $inscripcion) {
            $inscripcion->pagos()->delete();
            $inscripcion->delete();
        }
    }

    /**
     * @param  list<array{
     *   original: array{id_actividad: int, fecha_hora: string},
     *   recuperacion: null|array{id_actividad: int, fecha_hora: string, estado: string}
     * }>  $snapshot
     */
    private function reaplicarAusentesAviso(int $idPaciente, array $snapshot): void
    {
        foreach ($snapshot as $item) {
            try {
                $original = Turno::query()
                    ->whereNull('id_turno_original')
                    ->where('fecha_hora', $item['original']['fecha_hora'])
                    ->whereHas(
                        'actividadPaciente',
                        fn ($q) => $q
                            ->where('id_paciente', $idPaciente)
                            ->where('id_actividad', $item['original']['id_actividad'])
                    )
                    ->first();

                if (!$original) {
                    continue;
                }

                $original->update(['estado' => 'Ausente avisó']);

                $datosRec = $item['recuperacion'] ?? null;

                if ($datosRec === null) {
                    continue;
                }

                if ($original->turnoRecuperacion()->exists()) {
                    continue;
                }

                $idActPacDestino = $this->resolverIdActPacParaRecuperacion(
                    $original,
                    (int) $datosRec['id_actividad']
                );

                if ($idActPacDestino === null) {
                    continue;
                }

                $yaOcupado = Turno::query()
                    ->where('id_act_pac', $idActPacDestino)
                    ->where('fecha_hora', $datosRec['fecha_hora'])
                    ->exists();

                if ($yaOcupado) {
                    continue;
                }

                Turno::create([
                    'id_act_pac' => $idActPacDestino,
                    'fecha_hora' => $datosRec['fecha_hora'],
                    'estado' => $datosRec['estado'] ?: 'Ausente',
                    'id_turno_original' => $original->id,
                ]);
            } catch (Throwable $ex) {
                Log::warning('[RenovacionInscripcionService@reaplicarAusentesAviso] No se pudo reaplicar AA/recuperación.', [
                    'id_paciente' => $idPaciente,
                    'item' => $item,
                    'excepción' => $ex->getMessage(),
                ]);
            }
        }
    }

    private function resolverIdActPacParaRecuperacion(Turno $original, int $idActividadDestino): ?int
    {
        $inscripcionOrigen = $original->actividadPaciente;

        if (!$inscripcionOrigen) {
            return null;
        }

        if ((int) $inscripcionOrigen->id_actividad === $idActividadDestino) {
            return (int) $inscripcionOrigen->id;
        }

        $inscripcionOrigen->loadMissing('actPacDual:id,id_actividad,id_act_pac_dual');

        if (
            $inscripcionOrigen->id_act_pac_dual
            && (int) $inscripcionOrigen->actPacDual?->id_actividad === $idActividadDestino
        ) {
            return (int) $inscripcionOrigen->id_act_pac_dual;
        }

        $par = ActividadPaciente::query()
            ->where('id_paciente', $inscripcionOrigen->id_paciente)
            ->where('id_actividad', $idActividadDestino)
            ->where('id_act_pac_dual', $inscripcionOrigen->id)
            ->first();

        return $par?->id;
    }

    private function inicioProximoCiclo(Carbon $primerTurnoOriginal): Carbon
    {
        return $primerTurnoOriginal->copy()->startOfDay()->addWeeks(self::SEMANAS_CICLO);
    }

    private function debeRenovar(Carbon $inicioProximoCiclo): bool
    {
        $diasAnticipacion = (int) config('turnos.dias_anticipacion_renovacion');
        $limiteAnticipacion = Carbon::now()->addDays($diasAnticipacion);

        return $inicioProximoCiclo->lte($limiteAnticipacion);
    }

    private function yaExisteInscripcionEnAncla(int $idPaciente, int $idActividad, Carbon $ancla): bool
    {
        $anclaDia = $ancla->toDateString();

        return ActividadPaciente::query()
            ->where('id_paciente', $idPaciente)
            ->where('id_actividad', $idActividad)
            ->whereIn('id', function ($consulta) use ($anclaDia) {
                $consulta->select('id_act_pac')
                    ->from('turnos')
                    ->whereNull('id_turno_original')
                    ->groupBy('id_act_pac')
                    ->havingRaw('DATE(MIN(fecha_hora)) = ?', [$anclaDia]);
            })
            ->exists();
    }

    private function obtenerUltimaInscripcion(
        int $idActividad,
        int $idPaciente,
        bool $esDual = false
    ): ?ActividadPaciente {
        return ActividadPaciente::query()
            ->select('id', 'id_actividad', 'id_paciente', 'cant_sesiones', 'frecuencia_total_dual', 'id_act_pac_dual')
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
        float $totalAPagar
    ): ActividadPaciente {
        $actPacOrigen->loadMissing('actividad');

        $frecuenciaSemanal = count($horariosPaciente);
        $cantidadSesiones = $frecuenciaSemanal * self::SEMANAS_CICLO;

        if ($frecuenciaSemanal < 1) {
            throw new Exception('No hay horarios fijos para generar la próxima inscripción.');
        }

        $preparacion = $this->turnoService->prepararDesdePatron(
            $actPacOrigen->actividad,
            $idPaciente,
            $anclaProximoCiclo,
            $horariosPaciente,
            $cantidadSesiones,
            $frecuenciaSemanal
        );

        if ($preparacion->huboReemplazos()) {
            Log::info('[RenovacionInscripcionService] Se reasignaron turnos por falta de disponibilidad.', [
                'id_paciente' => $idPaciente,
                'id_actividad' => $actPacOrigen->id_actividad,
                'reemplazos' => $preparacion->reemplazos(),
            ]);
        }

        $nuevoActPac = ActividadPaciente::create([
            'id_actividad' => $actPacOrigen->id_actividad,
            'id_paciente' => $idPaciente,
            'cant_sesiones' => $cantidadSesiones,
            'total_a_pagar' => $totalAPagar,
            'pago_completado' => $totalAPagar <= 0,
        ]);
        $nuevoActPac->turnos()->createMany($preparacion->paraPersistir());

        return $nuevoActPac;
    }
}
