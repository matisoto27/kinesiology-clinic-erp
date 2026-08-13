<?php

namespace App\Services;

use App\Models\Actividad;
use App\Models\ActividadPaciente;
use App\Models\PacienteFijo;
use App\Models\Turno;
use App\Support\Registros\ResultadoActualizacionHorarioFijo;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class HorarioPacienteFijoService
{
    private const SEMANAS_CICLO = 4;

    /**
     * @param  array<int, array{id_actividad: int, dia_semana: string, hora_inicio: string}>  $horariosNuevos
     */
    public function tieneCambiosEfectivos(int $idPacienteFijo, array $horariosNuevos, Carbon $fechaCorte): bool
    {
        $pacienteFijo = PacienteFijo::findOrFail($idPacienteFijo);

        if (!$pacienteFijo->estaCursandoInscripcion()) {
            return false;
        }

        $plan = $this->calcularPlan($idPacienteFijo, $horariosNuevos, $fechaCorte);

        return !$plan['sin_cambios_efectivos'];
    }

    /**
     * @param  array<int, array{id_actividad: int, dia_semana: string, hora_inicio: string}>  $horariosNuevos
     */
    public function previsualizar(int $idPacienteFijo, array $horariosNuevos, Carbon $fechaCorte): ResultadoActualizacionHorarioFijo
    {
        $this->asegurarPuedeEditar($idPacienteFijo);

        $plan = $this->calcularPlan($idPacienteFijo, $horariosNuevos, $fechaCorte);

        return $this->resultadoDesdePlan($plan, persistido: false);
    }

    /**
     * @param  array<int, array{id_actividad: int, dia_semana: string, hora_inicio: string}>  $horariosNuevos
     */
    public function actualizar(int $idPacienteFijo, array $horariosNuevos, Carbon $fechaCorte): ResultadoActualizacionHorarioFijo
    {
        try {
            return DB::transaction(function () use ($idPacienteFijo, $horariosNuevos, $fechaCorte) {
                $this->asegurarPuedeEditar($idPacienteFijo);

                $plan = $this->calcularPlan($idPacienteFijo, $horariosNuevos, $fechaCorte);

                if (!$plan['sin_cambios_efectivos']) {
                    $this->aplicarPlan($plan);
                }

                return $this->resultadoDesdePlan($plan, persistido: true);
            });
        } catch (Throwable $th) {
            Log::error('[HorarioPacienteFijoService@actualizar] Error al actualizar horarios del paciente fijo', [
                'excepción' => $th->getMessage(),
                'id_paciente_fijo' => $idPacienteFijo,
            ]);

            throw $th;
        }
    }

    /**
     * Si aplicar el cambio "desde ya" rompería la semana calendario en curso, el
     * backend aplica desde el lunes siguiente. La UI debe forzar esa opción.
     *
     * Se pospone cuando:
     * - pasados inmutables + slots del patrón nuevo en el resto de la semana
     *   superarían la frecuencia operativa nueva, o
     * - el patrón nuevo incluye un día+hora de esta semana ya pasado (respecto
     *   del corte) que no coincide con un turno existente.
     *
     * @param  array<int, array{id_actividad: int, dia_semana: string, hora_inicio: string}>  $horariosNuevos
     */
    public function debeForzarSemanaSiguiente(int $idPacienteFijo, array $horariosNuevos = []): bool
    {
        $pacienteFijo = PacienteFijo::findOrFail($idPacienteFijo);

        if (!$pacienteFijo->estaCursandoInscripcion()) {
            return false;
        }

        $horariosNormalizados = $this->normalizarHorariosEntrada($horariosNuevos);

        if ($horariosNormalizados->isEmpty()) {
            return false;
        }

        $fechaCorte = Carbon::now();
        $nuevosPorActividad = $horariosNormalizados->groupBy('id_actividad');
        $inscripciones = $this->obtenerInscripcionesVigentes($pacienteFijo->id_paciente, $fechaCorte);
        $fechaCorteEfectiva = $this->resolverFechaCorteEfectiva(
            $inscripciones,
            $nuevosPorActividad,
            $fechaCorte,
            $horariosNormalizados->count()
        );

        return !$fechaCorteEfectiva->isSameDay($fechaCorte);
    }

    private function asegurarPuedeEditar(int $idPacienteFijo): void
    {
        $pacienteFijo = PacienteFijo::findOrFail($idPacienteFijo);

        if (!$pacienteFijo->estaCursandoInscripcion()) {
            throw new Exception(
                'No se pueden editar los horarios fijos porque el paciente no está cursando una inscripción. Eliminá el registro de fijo y creá la inscripción nuevamente.'
            );
        }
    }

    /**
     * Reconcilia el ciclo en curso contra el nuevo patrón fijo.
     *
     * Ventana pagada: [ancla, ancla + 4 semanas). Cupo de turnos compartido del ciclo.
     *
     * Contrato (no baja al reducir frecuencia operativa):
     * - Dual: frecuencia_total_dual = max(actual, frecuencia_nueva).
     *   cant_sesiones = reparto del pool (dual×4) por actividad.
     *   Al operar al tope contratado se redistribuye (freq_act×4; actividad fuera → 0).
     *   Por debajo del tope se congelan los cant (no bajan).
     * - Simple: cant_sesiones = marca de agua del ciclo (freq_contractual×4).
     *
     * El corte efectivo se pospone al lunes siguiente (equivale a "desde la semana
     * que viene") cuando aplicar el corte solicitado rompería la semana calendario
     * en curso, ya sea porque:
     * - los turnos pasados inmutables + los slots del patrón nuevo en el resto de
     *   esa semana superarían la frecuencia operativa nueva (ej. Lun/Mié → Jue/Vie
     *   un martes dejaría Lun+Jue+Vie = 3 con freq 2), o
     * - el patrón nuevo incluye un día+hora de esta semana anterior al corte que
     *   no coincide con ningún turno ya existente (ese slot quedaría perdido).
     * No se pospone solo por existir turnos pasados: aumentar la frecuencia con
     * slots aún futuros de esa semana (ej. sumar miércoles un martes) aplica ya.
     *
     * @param  array<int, array{id_actividad: int, dia_semana: string, hora_inicio: string}>  $horariosNuevos
     * @return array<string, mixed>
     */
    private function calcularPlan(int $idPacienteFijo, array $horariosNuevos, Carbon $fechaCorte): array
    {
        $pacienteFijo = PacienteFijo::with(['horarios', 'paciente'])->findOrFail($idPacienteFijo);
        $fechaCorteSolicitada = $fechaCorte->copy();

        $horariosNuevosNormalizados = $this->normalizarHorariosEntrada($horariosNuevos);
        $frecuenciaNueva = $horariosNuevosNormalizados->count();

        if ($frecuenciaNueva < 1 || $frecuenciaNueva > 5) {
            throw new Exception('La frecuencia semanal debe estar entre 1 y 5.');
        }

        $nuevosPorActividad = $horariosNuevosNormalizados->groupBy('id_actividad');
        $actividadesInvalidas = $nuevosPorActividad->keys()->diff([Actividad::GIMNASIO, Actividad::PILATES]);

        if ($actividadesInvalidas->isNotEmpty() || $nuevosPorActividad->count() > 2) {
            throw new Exception('Solo se admiten horarios de Gimnasio y/o Pilates.');
        }

        $frecuenciaAnterior = $pacienteFijo->horarios->count();

        $inscripciones = $this->obtenerInscripcionesVigentes(
            $pacienteFijo->id_paciente,
            $fechaCorteSolicitada
        );
        $fechaCorte = $this->resolverFechaCorteEfectiva(
            $inscripciones,
            $nuevosPorActividad,
            $fechaCorteSolicitada,
            $frecuenciaNueva
        );

        $ventana = $this->resolverVentanaCiclo($inscripciones, $fechaCorte);
        $limiteExclusivo = $ventana['limite_exclusivo'];

        $lider = $this->resolverInscripcionLider($inscripciones);
        $frecuenciaContractual = max(
            $this->frecuenciaContractualActual($inscripciones, $lider),
            $frecuenciaNueva
        );
        $contratoCiclo = $frecuenciaContractual * self::SEMANAS_CICLO;

        $pasadosPorActividad = $this->contarPasadosPorActividad($inscripciones, $fechaCorte);
        $pasadosTotales = (int) array_sum($pasadosPorActividad);
        $presupuestoFuturo = max(0, $contratoCiclo - $pasadosTotales);

        $turnosACrear = collect();
        $turnosAEliminar = collect();
        $cambiosInscripciones = [];

        // Actividades que salen del patrón: solo historial (pasados); futuros fuera.
        foreach ($inscripciones as $idActividad => $inscripcion) {
            if ($nuevosPorActividad->has($idActividad)) {
                continue;
            }

            $idActividad = (int) $idActividad;
            $turnos = $inscripcion->turnos()->whereNull('id_turno_original')->orderBy('fecha_hora')->get();
            $futuros = $turnos->filter(fn (Turno $t) => $t->fecha_hora->gte($fechaCorte));
            $pasados = (int) ($pasadosPorActividad[$idActividad] ?? 0);
            $tuvoPasados = $pasados > 0;

            foreach ($futuros as $turno) {
                $turnosAEliminar->push([
                    'id' => $turno->id,
                    'fecha_hora' => $turno->fecha_hora->toDateTimeString(),
                    'id_actividad' => $idActividad,
                    'nombre_actividad' => $inscripcion->actividad->nombre ?? '',
                ]);
            }

            $cambiosInscripciones[$idActividad] = [
                'inscripcion' => $inscripcion,
                'crear' => false,
                'cant_sesiones' => 0,
                'fechas_nuevas' => [],
                'eliminar_completa' => !$tuvoPasados,
                'cerrar' => $tuvoPasados,
                'turnos_a_conservar' => $pasados,
            ];
        }

        // Actividades del patrón nuevo: reconciliar contra la ventana y el cupo compartido.
        $propuestas = [];

        foreach ($nuevosPorActividad as $idActividad => $horariosAct) {
            $idActividad = (int) $idActividad;
            $frecuenciaAct = $horariosAct->count();
            $inscripcion = $inscripciones->get($idActividad);
            $esNueva = $inscripcion === null;

            $turnos = $esNueva
                ? collect()
                : $inscripcion->turnos()->whereNull('id_turno_original')->orderBy('fecha_hora')->get();

            $pasados = (int) ($pasadosPorActividad[$idActividad] ?? 0);
            $futuros = $turnos->filter(fn (Turno $t) => $t->fecha_hora->gte($fechaCorte));

            $deseados = $this->slotsDeseadosEnVentana(
                $horariosAct,
                $fechaCorte,
                $limiteExclusivo
            );

            $clave = fn (Carbon $fecha): string => $fecha->format('Y-m-d H:i');
            $clavesDeseadas = $deseados->mapWithKeys(fn (Carbon $f) => [$clave($f) => $f]);
            $clavesFuturas = $futuros->mapWithKeys(fn (Turno $t) => [$clave($t->fecha_hora) => $t]);

            $conservarCandidatos = $futuros
                ->filter(fn (Turno $t) => $clavesDeseadas->has($clave($t->fecha_hora)))
                ->sortBy(fn (Turno $t) => $t->fecha_hora->timestamp)
                ->values();

            $eliminarForzados = $futuros
                ->reject(fn (Turno $t) => $clavesDeseadas->has($clave($t->fecha_hora)))
                ->values();

            $crearCandidatos = $deseados
                ->reject(fn (Carbon $fecha) => $clavesFuturas->has($clave($fecha)))
                ->sortBy(fn (Carbon $fecha) => $fecha->timestamp)
                ->values();

            $nombreActividad = $inscripcion?->actividad?->nombre
                ?? Actividad::find($idActividad)?->nombre
                ?? '';

            $propuestas[$idActividad] = [
                'inscripcion' => $inscripcion,
                'crear' => $esNueva,
                'frecuencia_act' => $frecuenciaAct,
                'pasados' => $pasados,
                'nombre_actividad' => $nombreActividad,
                'conservar_candidatos' => $conservarCandidatos,
                'eliminar_forzados' => $eliminarForzados,
                'crear_candidatos' => $crearCandidatos,
            ];
        }

        $asignacion = $this->asignarCupoCompartidoCiclo($propuestas, $presupuestoFuturo);

        foreach ($propuestas as $idActividad => $propuesta) {
            $idActividad = (int) $idActividad;
            $resultadoAct = $asignacion[$idActividad];

            foreach ($propuesta['eliminar_forzados']->concat($resultadoAct['eliminar_por_cupo']) as $turno) {
                $turnosAEliminar->push([
                    'id' => $turno->id,
                    'fecha_hora' => $turno->fecha_hora->toDateTimeString(),
                    'id_actividad' => $idActividad,
                    'nombre_actividad' => $propuesta['nombre_actividad'],
                ]);
            }

            $fechasNuevas = $resultadoAct['crear']->all();

            foreach ($fechasNuevas as $fecha) {
                $turnosACrear->push([
                    'fecha_hora' => $fecha->toDateTimeString(),
                    'id_actividad' => $idActividad,
                    'nombre_actividad' => $propuesta['nombre_actividad'],
                ]);
            }

            $turnosFinales = $propuesta['pasados']
                + $resultadoAct['conservar']->count()
                + count($fechasNuevas);

            $cambiosInscripciones[$idActividad] = [
                'inscripcion' => $propuesta['inscripcion'],
                'crear' => $propuesta['crear'],
                'frecuencia_act' => $propuesta['frecuencia_act'],
                'cant_sesiones' => 0,
                'fechas_nuevas' => $fechasNuevas,
                'eliminar_completa' => false,
                'turnos_a_conservar' => $turnosFinales,
            ];
        }

        $esDualFinal = $this->esDualFinal($cambiosInscripciones);
        $this->asignarCantSesionesNominal(
            $cambiosInscripciones,
            $frecuenciaNueva,
            $frecuenciaContractual,
            $esDualFinal
        );

        $precio = $this->calcularPrecio(
            $inscripciones,
            $frecuenciaNueva,
            $turnosACrear
        );

        $sinCambiosEfectivos = $turnosACrear->isEmpty() && $turnosAEliminar->isEmpty();

        return [
            'paciente_fijo' => $pacienteFijo,
            'horarios_nuevos' => $horariosNuevosNormalizados,
            'frecuencia_anterior' => $frecuenciaAnterior,
            'frecuencia_nueva' => $frecuenciaNueva,
            'frecuencia_contractual' => $frecuenciaContractual,
            'hubo_primer_turno' => true,
            'fecha_corte' => $fechaCorte,
            'es_dual_nuevo' => $esDualFinal,
            'contrato_ciclo' => $contratoCiclo,
            'turnos_a_crear' => $turnosACrear->values(),
            'turnos_a_eliminar' => $turnosAEliminar->unique('id')->values(),
            'cambios_inscripciones' => $cambiosInscripciones,
            'precio' => $precio,
            'inscripciones' => $inscripciones,
            'sin_cambios_efectivos' => $sinCambiosEfectivos,
        ];
    }

    /**
     * Asigna cant_sesiones como cupo nominal contratado (freq×4), no como conteo de turnos.
     *
     * Dual al tope contratado: redistribuye el pool según el patrón (freq_act×4; fuera del patrón → 0).
     * Dual por debajo del tope: congela cant de actividades activas; cerradas → 0.
     * Simple: marca de agua del ciclo (no baja).
     *
     * @param  array<int, array<string, mixed>>  $cambios
     */
    private function asignarCantSesionesNominal(
        array &$cambios,
        int $frecuenciaNueva,
        int $frecuenciaContractual,
        bool $esDualFinal
    ): void {
        $pool = $frecuenciaContractual * self::SEMANAS_CICLO;

        if (!$esDualFinal) {
            foreach ($cambios as $idActividad => $cambio) {
                if ($cambio['eliminar_completa'] ?? false) {
                    continue;
                }

                if ($cambio['cerrar'] ?? false) {
                    $cambios[$idActividad]['cant_sesiones'] = 0;
                    continue;
                }

                $previa = (int) ($cambio['inscripcion']?->cant_sesiones ?? 0);
                $cambios[$idActividad]['cant_sesiones'] = max($previa, $pool);
            }

            return;
        }

        $operandoAlTope = $frecuenciaNueva >= $frecuenciaContractual;

        if ($operandoAlTope) {
            foreach ($cambios as $idActividad => $cambio) {
                if ($cambio['eliminar_completa'] ?? false) {
                    continue;
                }

                if ($cambio['cerrar'] ?? false) {
                    $cambios[$idActividad]['cant_sesiones'] = 0;
                    continue;
                }

                $freqAct = (int) ($cambio['frecuencia_act'] ?? 0);
                $cambios[$idActividad]['cant_sesiones'] = $freqAct * self::SEMANAS_CICLO;
            }

            return;
        }

        foreach ($cambios as $idActividad => $cambio) {
            if ($cambio['eliminar_completa'] ?? false) {
                continue;
            }

            if ($cambio['cerrar'] ?? false) {
                $cambios[$idActividad]['cant_sesiones'] = 0;
                continue;
            }

            $previa = (int) ($cambio['inscripcion']?->cant_sesiones ?? 0);
            $freqAct = (int) ($cambio['frecuencia_act'] ?? 0);

            // Congelar: no bajar. Alta nueva en ciclo reducido: arranca en freq_act×4.
            $cambios[$idActividad]['cant_sesiones'] = $previa > 0
                ? $previa
                : $freqAct * self::SEMANAS_CICLO;
        }
    }

    /**
     * Frecuencia semanal ya contratada en el ciclo (marca de agua), sin mirar horarios operativos.
     *
     * @param  Collection<int, ActividadPaciente>  $inscripciones
     */
    private function frecuenciaContractualActual(
        Collection $inscripciones,
        ?ActividadPaciente $lider
    ): int {
        foreach ($inscripciones as $inscripcion) {
            if ($inscripcion->frecuencia_total_dual) {
                return (int) $inscripcion->frecuencia_total_dual;
            }
        }

        if ($lider && (int) $lider->cant_sesiones > 0) {
            return max(1, intdiv((int) $lider->cant_sesiones, self::SEMANAS_CICLO));
        }

        $maxCant = (int) $inscripciones->max(fn (ActividadPaciente $i) => (int) $i->cant_sesiones);

        return $maxCant > 0
            ? max(1, intdiv($maxCant, self::SEMANAS_CICLO))
            : 0;
    }

    /**
     * @param  array<int, array<string, mixed>>  $cambios
     */
    private function esDualFinal(array $cambios): bool
    {
        $ids = collect($cambios)
            ->reject(fn (array $cambio) => $cambio['eliminar_completa'] ?? false)
            ->keys()
            ->map(fn ($id) => (int) $id);

        return $ids->contains(Actividad::GIMNASIO) && $ids->contains(Actividad::PILATES);
    }

    /**
     * Reparte el cupo futuro del ciclo entre actividades del patrón.
     * Prioridad: conservar turnos que ya matchean el patrón; el resto del cupo crea.
     * Si no entra todo, se pierden primero las creaciones y luego los conservados más lejanos.
     *
     * @param  array<int, array<string, mixed>>  $propuestas
     * @return array<int, array{conservar: Collection<int, Turno>, crear: Collection<int, Carbon>, eliminar_por_cupo: Collection<int, Turno>}>
     */
    private function asignarCupoCompartidoCiclo(array $propuestas, int $presupuestoFuturo): array
    {
        $conservarGlobal = collect();
        $crearGlobal = collect();

        foreach ($propuestas as $idActividad => $propuesta) {
            foreach ($propuesta['conservar_candidatos'] as $turno) {
                $conservarGlobal->push([
                    'id_actividad' => (int) $idActividad,
                    'turno' => $turno,
                    'fecha' => $turno->fecha_hora->copy(),
                ]);
            }

            foreach ($propuesta['crear_candidatos'] as $fecha) {
                $crearGlobal->push([
                    'id_actividad' => (int) $idActividad,
                    'fecha' => $fecha->copy(),
                ]);
            }
        }

        $conservarGlobal = $conservarGlobal->sortBy(fn (array $item) => $item['fecha']->timestamp)->values();
        $crearGlobal = $crearGlobal->sortBy(fn (array $item) => $item['fecha']->timestamp)->values();

        $conservarAceptados = $conservarGlobal->take($presupuestoFuturo)->values();
        $conservarRechazados = $conservarGlobal->slice($presupuestoFuturo)->values();
        $presupuestoCrear = max(0, $presupuestoFuturo - $conservarAceptados->count());
        $crearAceptados = $crearGlobal->take($presupuestoCrear)->values();

        $resultado = [];

        foreach (array_keys($propuestas) as $idActividad) {
            $idActividad = (int) $idActividad;

            $resultado[$idActividad] = [
                'conservar' => $conservarAceptados
                    ->where('id_actividad', $idActividad)
                    ->pluck('turno')
                    ->values(),
                'crear' => $crearAceptados
                    ->where('id_actividad', $idActividad)
                    ->pluck('fecha')
                    ->values(),
                'eliminar_por_cupo' => $conservarRechazados
                    ->where('id_actividad', $idActividad)
                    ->pluck('turno')
                    ->values(),
            ];
        }

        return $resultado;
    }

    /**
     * @param  Collection<int, ActividadPaciente>  $inscripciones
     * @return array<int, int>
     */
    private function contarPasadosPorActividad(Collection $inscripciones, Carbon $fechaCorte): array
    {
        $resultado = [];

        foreach ($inscripciones as $idActividad => $inscripcion) {
            $resultado[(int) $idActividad] = $inscripcion->turnos()
                ->whereNull('id_turno_original')
                ->where('fecha_hora', '<', $fechaCorte)
                ->count();
        }

        return $resultado;
    }

    /**
     * Ventana pagada del ciclo: [ancla, ancla + 4 semanas).
     *
     * Ancla = día del primer turno original entre las inscripciones del ciclo
     * (vigentes + pares dual en ambos sentidos). No se recalcula por cambios de horario.
     *
     * @param  Collection<int, ActividadPaciente>  $inscripciones
     * @return array{ancla: Carbon, limite_exclusivo: Carbon}
     */
    private function resolverVentanaCiclo(
        Collection $inscripciones,
        Carbon $fechaCorte
    ): array {
        $idsCiclo = $this->idsInscripcionesDelCiclo($inscripciones);

        $primeraFecha = null;

        if ($idsCiclo !== []) {
            $primeraFecha = Turno::query()
                ->whereIn('id_act_pac', $idsCiclo)
                ->whereNull('id_turno_original')
                ->orderBy('fecha_hora')
                ->value('fecha_hora');
        }

        // Fallback: si no hay turnos aún, la ventana arranca en la fecha de corte.
        $ancla = $primeraFecha
            ? Carbon::parse($primeraFecha)->startOfDay()
            : $fechaCorte->copy()->startOfDay();

        return [
            'ancla' => $ancla,
            'limite_exclusivo' => $ancla->copy()->addWeeks(self::SEMANAS_CICLO),
        ];
    }

    /**
     * Inscripciones que definen el ciclo actual: vigentes + duales vinculados.
     *
     * @param  Collection<int, ActividadPaciente>  $inscripciones
     * @return list<int>
     */
    private function idsInscripcionesDelCiclo(Collection $inscripciones): array
    {
        $ids = $inscripciones
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        foreach ($inscripciones as $inscripcion) {
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

            $ids = array_merge($ids, $inversos);
        }

        return array_values(array_unique($ids));
    }

    /**
     * El corte solicitado se pospone al lunes siguiente (no se toca el resto de
     * la semana en curso) si aplicarlo hoy rompería esa semana calendario:
     * - pasados inmutables + slots del patrón nuevo en el resto de la semana
     *   > frecuencia operativa nueva, o
     * - el patrón nuevo tiene, en [inicioSemana, fechaCorte), un slot que no
     *   coincide con ningún turno ya existente (día+hora ya pasado).
     *
     * @param  Collection<int, ActividadPaciente>  $inscripciones
     * @param  Collection<int, Collection<int, array{dia_semana_int: int, hora_inicio: string}>>  $nuevosPorActividad
     */
    private function resolverFechaCorteEfectiva(
        Collection $inscripciones,
        Collection $nuevosPorActividad,
        Carbon $fechaCorte,
        int $frecuenciaNueva
    ): Carbon {
        $inicioSemana = $fechaCorte->copy()->startOfWeek(Carbon::MONDAY);

        if ($this->semanaSuperaFrecuenciaOperativa(
            $inscripciones,
            $nuevosPorActividad,
            $inicioSemana,
            $fechaCorte,
            $frecuenciaNueva
        )) {
            return $inicioSemana->copy()->addWeek()->startOfDay();
        }

        if ($this->tieneSlotNuevoPerdidoEnSemana($inscripciones, $nuevosPorActividad, $inicioSemana, $fechaCorte)) {
            return $inicioSemana->copy()->addWeek()->startOfDay();
        }

        return $fechaCorte->copy();
    }

    /**
     * Simula el resultado de aplicar el corte esta semana: turnos pasados
     * inmutables + slots del patrón nuevo desde el corte hasta el domingo.
     * Si eso supera la frecuencia operativa nueva, hay que posponer.
     *
     * @param  Collection<int, ActividadPaciente>  $inscripciones
     * @param  Collection<int, Collection<int, array{dia_semana_int: int, hora_inicio: string}>>  $nuevosPorActividad
     */
    private function semanaSuperaFrecuenciaOperativa(
        Collection $inscripciones,
        Collection $nuevosPorActividad,
        Carbon $inicioSemana,
        Carbon $fechaCorte,
        int $frecuenciaNueva
    ): bool {
        $finSemana = $inicioSemana->copy()->addWeek();
        $idsCiclo = $this->idsInscripcionesDelCiclo($inscripciones);

        $pasadosEnSemana = 0;

        if ($idsCiclo !== []) {
            $pasadosEnSemana = Turno::query()
                ->whereIn('id_act_pac', $idsCiclo)
                ->whereNull('id_turno_original')
                ->where('fecha_hora', '>=', $inicioSemana)
                ->where('fecha_hora', '<', $fechaCorte)
                ->count();
        }

        $slotsNuevosEnRestoSemana = 0;

        foreach ($nuevosPorActividad as $horariosAct) {
            foreach ($horariosAct as $horario) {
                $fecha = $inicioSemana->copy()
                    ->dayOfWeek((int) $horario['dia_semana_int'])
                    ->setTimeFromTimeString($horario['hora_inicio']);

                if ($fecha->gte($fechaCorte) && $fecha->lt($finSemana)) {
                    $slotsNuevosEnRestoSemana++;
                }
            }
        }

        return ($pasadosEnSemana + $slotsNuevosEnRestoSemana) > $frecuenciaNueva;
    }

    /**
     * Detecta si el patrón nuevo tiene un slot en [inicioSemana, fechaCorte)
     * que no coincide con ningún turno ya existente (actividad nueva o
     * continuada). Ese día+hora ya pasó respecto del corte y no podría crearse.
     *
     * @param  Collection<int, ActividadPaciente>  $inscripciones
     * @param  Collection<int, Collection<int, array{dia_semana_int: int, hora_inicio: string}>>  $nuevosPorActividad
     */
    private function tieneSlotNuevoPerdidoEnSemana(
        Collection $inscripciones,
        Collection $nuevosPorActividad,
        Carbon $inicioSemana,
        Carbon $fechaCorte
    ): bool {
        foreach ($nuevosPorActividad as $idActividad => $horariosAct) {
            $idActividad = (int) $idActividad;
            $inscripcion = $inscripciones->get($idActividad);

            foreach ($horariosAct as $horario) {
                $fecha = $inicioSemana->copy()
                    ->dayOfWeek((int) $horario['dia_semana_int'])
                    ->setTimeFromTimeString($horario['hora_inicio']);

                if ($fecha->lt($inicioSemana) || !$fecha->lt($fechaCorte)) {
                    continue;
                }

                if (!$inscripcion) {
                    return true;
                }

                $yaExiste = Turno::query()
                    ->where('id_act_pac', $inscripcion->id)
                    ->whereNull('id_turno_original')
                    ->where('fecha_hora', $fecha)
                    ->exists();

                if (!$yaExiste) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Slots del patrón en [fechaCorte, limiteExclusivo).
     *
     * @param  Collection<int, array{dia_semana_int: int, hora_inicio: string}>  $horariosAct
     * @return Collection<int, Carbon>
     */
    private function slotsDeseadosEnVentana(
        Collection $horariosAct,
        Carbon $fechaCorte,
        Carbon $limiteExclusivo
    ): Collection {
        $patron = $horariosAct
            ->sortBy(fn (array $h) => sprintf('%02d_%s', $h['dia_semana_int'], $h['hora_inicio']))
            ->values();

        $lunesInicio = $fechaCorte->copy()->startOfWeek(Carbon::MONDAY);
        $lunesFin = $limiteExclusivo->copy()->subSecond()->startOfWeek(Carbon::MONDAY);
        $deseados = collect();

        for ($lunes = $lunesInicio->copy(); $lunes->lte($lunesFin); $lunes->addWeek()) {
            foreach ($patron as $horario) {
                $fecha = $lunes->copy()
                    ->dayOfWeek((int) $horario['dia_semana_int'])
                    ->setTimeFromTimeString($horario['hora_inicio']);

                if ($fecha->lt($fechaCorte) || !$fecha->lt($limiteExclusivo)) {
                    continue;
                }

                $deseados->push($fecha);
            }
        }

        return $deseados->values();
    }

    /**
     * @param  array<string, mixed>  $plan
     */
    private function aplicarPlan(array $plan): void
    {
        $pacienteFijo = $plan['paciente_fijo'];
        $cambios = $plan['cambios_inscripciones'];
        $precio = $plan['precio'];
        $esDual = $plan['es_dual_nuevo'];

        foreach ($plan['turnos_a_eliminar'] as $turnoInfo) {
            $turno = Turno::find($turnoInfo['id']);

            if (!$turno) {
                continue;
            }

            $turno->turnoRecuperacion?->delete();
            $turno->delete();
        }

        $inscripcionesPorActividad = collect();

        foreach ($cambios as $idActividad => $cambio) {
            $idActividad = (int) $idActividad;

            if ($cambio['eliminar_completa'] ?? false) {
                $inscripcion = $cambio['inscripcion'];
                $parId = $inscripcion->id_act_pac_dual;

                if ($parId) {
                    $par = ActividadPaciente::find($parId);
                    $totalAMover = (float) $inscripcion->total_a_pagar;

                    $inscripcion->update([
                        'id_act_pac_dual' => null,
                        'frecuencia_total_dual' => null,
                        'total_a_pagar' => 0,
                    ]);

                    if ($par && $totalAMover > 0) {
                        $par->update([
                            'id_act_pac_dual' => null,
                            'frecuencia_total_dual' => null,
                            'total_a_pagar' => (float) $par->total_a_pagar + $totalAMover,
                            'pago_completado' => false,
                        ]);
                    } elseif ($par) {
                        $par->update([
                            'id_act_pac_dual' => null,
                            'frecuencia_total_dual' => null,
                        ]);
                    }
                }

                $inscripcion->turnos()->delete();
                $inscripcion->delete();
                continue;
            }

            if ($cambio['cerrar'] ?? false) {
                $inscripcion = $cambio['inscripcion'];
                $inscripcion->update([
                    'cant_sesiones' => $cambio['cant_sesiones'],
                ]);
                $inscripcionesPorActividad->put($idActividad, $inscripcion->fresh(['turnos', 'actividad']));
                continue;
            }

            if ($cambio['crear']) {
                $inscripcion = ActividadPaciente::create([
                    'id_actividad' => $idActividad,
                    'id_paciente' => $pacienteFijo->id_paciente,
                    'cant_sesiones' => $cambio['cant_sesiones'],
                    'total_a_pagar' => 0,
                    'pago_completado' => true,
                ]);
            } else {
                $inscripcion = $cambio['inscripcion'];
                $inscripcion->update([
                    'cant_sesiones' => $cambio['cant_sesiones'],
                ]);
            }

            $paraInsertar = collect($cambio['fechas_nuevas'])
                ->map(fn ($fecha) => [
                    'fecha_hora' => $fecha instanceof Carbon ? $fecha->toDateTimeString() : $fecha,
                ])
                ->all();

            if ($paraInsertar !== []) {
                $inscripcion->turnos()->createMany($paraInsertar);
            }

            $inscripcionesPorActividad->put($idActividad, $inscripcion->fresh(['turnos', 'actividad']));
        }

        if ($esDual && $inscripcionesPorActividad->count() === 2) {
            $freqDual = (int) $plan['frecuencia_contractual'];
            $primera = $inscripcionesPorActividad->sortBy('id')->first();
            $segunda = $inscripcionesPorActividad->first(
                fn (ActividadPaciente $i) => (int) $i->id !== (int) $primera->id
            );

            $primera->update([
                'frecuencia_total_dual' => $freqDual,
                'id_act_pac_dual' => $segunda->id,
                'total_a_pagar' => $precio['total_nuevo'],
                'pago_completado' => $precio['total_nuevo'] <= 0,
            ]);
            $segunda->update([
                'frecuencia_total_dual' => $freqDual,
                'id_act_pac_dual' => $primera->id,
                'total_a_pagar' => 0,
                'pago_completado' => true,
            ]);
        } elseif ($inscripcionesPorActividad->count() === 1) {
            $unica = $inscripcionesPorActividad->first();
            $unica->update([
                'frecuencia_total_dual' => null,
                'id_act_pac_dual' => null,
                'total_a_pagar' => $precio['total_nuevo'],
                'pago_completado' => $precio['total_nuevo'] <= 0,
            ]);
        }

        $pacienteFijo->horarios()->delete();
        $pacienteFijo->horarios()->createMany(
            $plan['horarios_nuevos']
                ->map(fn (array $h) => [
                    'id_actividad' => $h['id_actividad'],
                    'dia_semana' => $h['dia_semana_int'],
                    'hora_inicio' => $h['hora_inicio'],
                ])
                ->all()
        );
    }

    /**
     * Cobro solo si la frecuencia pedida supera la marca de agua contractual del ciclo.
     * Bajar o volver a la frecuencia ya contratada no genera cargo.
     *
     * @param  Collection<int, ActividadPaciente>  $inscripciones
     * @return array{total_anterior: float, total_nuevo: float, cargo_extra: float, precio_por_turno: float}
     */
    private function calcularPrecio(
        Collection $inscripciones,
        int $frecuenciaNueva,
        Collection $turnosACrear
    ): array {
        $liderActual = $this->resolverInscripcionLider($inscripciones, $inscripciones->count() === 2);
        $totalAnterior = $liderActual ? (float) $liderActual->total_a_pagar : 0.0;

        if ($totalAnterior <= 0) {
            $totalAnterior = (float) $inscripciones->sum('total_a_pagar');
        }

        $cantContrato = $this->cantSesionesContrato($inscripciones, $liderActual);
        $precioPorTurno = $cantContrato > 0 ? $totalAnterior / $cantContrato : 0;
        $sesionesContratoNuevo = $frecuenciaNueva * self::SEMANAS_CICLO;

        if ($sesionesContratoNuevo <= $cantContrato) {
            return [
                'total_anterior' => $totalAnterior,
                'total_nuevo' => $totalAnterior,
                'cargo_extra' => 0.0,
                'precio_por_turno' => round($precioPorTurno, 2),
            ];
        }

        $sesionesExtraContrato = $sesionesContratoNuevo - $cantContrato;
        $turnosCobrables = min($turnosACrear->count(), $sesionesExtraContrato);
        $cargoExtra = round($precioPorTurno * $turnosCobrables, 2);

        return [
            'total_anterior' => $totalAnterior,
            'total_nuevo' => round($totalAnterior + $cargoExtra, 2),
            'cargo_extra' => $cargoExtra,
            'precio_por_turno' => round($precioPorTurno, 2),
        ];
    }

    private function cantSesionesContrato(Collection $inscripciones, ?ActividadPaciente $lider): int
    {
        foreach ($inscripciones as $inscripcion) {
            if ($inscripcion->frecuencia_total_dual) {
                return (int) $inscripcion->frecuencia_total_dual * self::SEMANAS_CICLO;
            }
        }

        if ($lider) {
            return (int) $lider->cant_sesiones;
        }

        return (int) $inscripciones->sum('cant_sesiones');
    }

    /**
     * Líder de cobro: la primera inscripción creada (menor id).
     *
     * @param  Collection<int, ActividadPaciente>  $inscripciones
     */
    private function resolverInscripcionLider(Collection $inscripciones, bool $esDual = false): ?ActividadPaciente
    {
        if ($inscripciones->isEmpty()) {
            return null;
        }

        return $inscripciones->sortBy('id')->first();
    }

    /**
     * @return Collection<int, ActividadPaciente> keyed by id_actividad
     */
    private function obtenerInscripcionesVigentes(int $idPaciente, Carbon $fechaCorte): Collection
    {
        $resultado = collect();

        foreach ([Actividad::GIMNASIO, Actividad::PILATES] as $idActividad) {
            $inscripcion = ActividadPaciente::query()
                ->with(['actividad:id,nombre', 'primerTurno', 'ultimoTurno'])
                ->where('id_paciente', $idPaciente)
                ->where('id_actividad', $idActividad)
                ->whereHas('turnos', fn ($q) => $q
                    ->whereNull('id_turno_original')
                    ->where('fecha_hora', '>=', $fechaCorte))
                ->orderBy('id')
                ->first();

            if (!$inscripcion) {
                $inscripcion = ActividadPaciente::query()
                    ->with(['actividad:id,nombre', 'primerTurno', 'ultimoTurno'])
                    ->where('id_paciente', $idPaciente)
                    ->where('id_actividad', $idActividad)
                    ->latest('id')
                    ->first();
            }

            if ($inscripcion) {
                $resultado->put($idActividad, $inscripcion);
            }
        }

        return $resultado;
    }

    /**
     * @param  array<int, array{id_actividad: int, dia_semana: string, hora_inicio: string}>  $horarios
     */
    private function normalizarHorariosEntrada(array $horarios): Collection
    {
        return collect($horarios)
            ->map(function (array $h) {
                $hora = $h['hora_inicio'];

                if (strlen($hora) === 5) {
                    $hora .= ':00';
                }

                return [
                    'id_actividad' => (int) $h['id_actividad'],
                    'dia_semana' => $h['dia_semana'],
                    'dia_semana_int' => Actividad::diaSemanaAEntero($h['dia_semana']),
                    'hora_inicio' => $hora,
                ];
            })
            ->sortBy(fn (array $h) => sprintf('%d_%02d_%s', $h['id_actividad'], $h['dia_semana_int'], $h['hora_inicio']))
            ->values();
    }

    /**
     * @param  array<string, mixed>  $plan
     */
    private function resultadoDesdePlan(array $plan, bool $persistido): ResultadoActualizacionHorarioFijo
    {
        $pacienteFijo = $persistido
            ? $plan['paciente_fijo']->fresh(['horarios.actividad', 'paciente'])
            : $plan['paciente_fijo'];

        $inscripciones = $persistido
            ? $this->obtenerInscripcionesVigentes($pacienteFijo->id_paciente, $plan['fecha_corte'])
            : $plan['inscripciones'];

        $lider = $this->resolverInscripcionLider($inscripciones, $plan['es_dual_nuevo']);

        return new ResultadoActualizacionHorarioFijo(
            pacienteFijo: $pacienteFijo,
            frecuenciaAnterior: $plan['frecuencia_anterior'],
            frecuenciaNueva: $plan['frecuencia_nueva'],
            totalAnterior: $plan['precio']['total_anterior'],
            totalNuevo: $plan['precio']['total_nuevo'],
            cargoExtra: $plan['precio']['cargo_extra'],
            huboPrimerTurno: $plan['hubo_primer_turno'],
            turnosACrear: $plan['turnos_a_crear']
                ->map(fn ($t) => [
                    'fecha_hora' => $t['fecha_hora'],
                    'id_actividad' => $t['id_actividad'],
                    'nombre_actividad' => $t['nombre_actividad'],
                ])
                ->all(),
            turnosAEliminar: $plan['turnos_a_eliminar']
                ->map(fn ($t) => [
                    'fecha_hora' => $t['fecha_hora'],
                    'id_actividad' => $t['id_actividad'],
                    'nombre_actividad' => $t['nombre_actividad'],
                ])
                ->all(),
            inscripciones: $inscripciones->values(),
            idInscripcionParaCobro: $lider && $plan['precio']['cargo_extra'] > 0 ? $lider->id : null,
            sinCambiosEfectivos: $plan['sin_cambios_efectivos'],
        );
    }
}
