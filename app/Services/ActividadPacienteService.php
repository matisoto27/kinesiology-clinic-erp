<?php

namespace App\Services;

use App\Exceptions\ReglaNegocioException;
use App\Events\InscripcionGeneralRegistrada;
use App\Models\Actividad;
use App\Models\ActividadCombo;
use App\Models\ActividadPaciente;
use App\Models\Paciente;
use App\Models\PacienteFijo;
use App\Models\PrecioMensual;
use App\Models\Turno;
use App\Support\Registros\ModalidadRegistro;
use App\Support\Registros\ResultadoInscripcionGeneral;
use App\Support\Registros\ResultadoRegistroActividadPaciente;
use App\Support\Turnos\ResultadoPreparacionTurnos;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ActividadPacienteService
{
    public const MENSAJE_PACIENTE_YA_FIJO = 'El paciente ya fue registrado como fijo previamente. Para modificar sus horarios, edite su registro existente en Inscripciones Mensuales.';

    public const MENSAJE_KINESIO_SIN_ORDEN_CON_REGISTRO_EN_CURSO = 'El paciente tiene un registro de sesiones con orden médica en curso. Continuá ese registro en lugar de cargar kinesiología sin orden.';

    public const MENSAJE_KINESIO_PARTICULAR_EN_CURSO = 'El paciente ya tiene un registro de kinesiología sin orden en curso para esta actividad.';

    public function __construct(
        private TurnoService $turnoService,
    ) {}

    public function registrar(array $validados): ResultadoRegistroActividadPaciente
    {
        return DB::transaction(function () use ($validados) {
            $esConOrden = ModalidadRegistro::esConOrden($validados);
            $ahora = Carbon::now();
            $idPaciente = (int) $validados['id_paciente'];
            $idActividad = (int) $validados['id_actividad'];

            if (!$esConOrden) {
                $this->asegurarKinesioSinOrdenPermitido($idPaciente, $idActividad);
            }

            if ($esConOrden) {
                $validados = $this->enriquecerDatosConOrden($validados, $ahora);
            }

            $validados['total_a_pagar'] = ActividadCombo::calcularTotalAPagar(
                $idActividad,
                (int) $validados['cant_sesiones'],
                exigirComboExacto: $esConOrden
            );

            $preparacion = $this->prepararTurnos($validados);
            $turnos = $preparacion->paraPersistir();

            $actividadPaciente = $this->crearInscripcion($validados, $esConOrden);
            $actividadPaciente->turnos()->createMany($turnos);

            return new ResultadoRegistroActividadPaciente(
                inscripcion: $actividadPaciente->fresh(['turnos']),
                reemplazos: $preparacion->reemplazos(),
            );
        });
    }

    /**
     * Registros de sesiones de kinesio con orden médica aún en curso
     * (turnos efectivos < sesiones que cubre la orden).
     */
    public function kinesioConOrdenEnCurso(int $idPaciente): Collection
    {
        return ActividadPaciente::query()
            ->where('id_paciente', $idPaciente)
            ->where('id_actividad', Actividad::KINESIOLOGIA_CONVENCIONAL)
            ->whereIn('cant_sesiones', [5, 10])
            ->conOrdenMedica()
            ->with([
                'turnos' => fn ($q) => $q
                    ->whereNull('id_turno_original')
                    ->orderBy('fecha_hora'),
            ])
            ->withCount([
                'turnos as turnos_efectivos_count' => fn ($q) => $q->whereNull('id_turno_original'),
            ])
            ->orderByDesc('id')
            ->get()
            ->filter(fn (ActividadPaciente $registroSesiones) => $registroSesiones->turnos_efectivos_count < $registroSesiones->cant_sesiones)
            ->values();
    }

    /**
     * Particulares de kinesio aún en curso (sin orden, turnos efectivos < cant_sesiones).
     */
    public function kinesioParticularEnCurso(int $idPaciente, int $idActividad): Collection
    {
        return ActividadPaciente::query()
            ->where('id_paciente', $idPaciente)
            ->where('id_actividad', $idActividad)
            ->sinOrdenMedica()
            ->withCount([
                'turnos as turnos_efectivos_count' => fn ($q) => $q->whereNull('id_turno_original'),
            ])
            ->orderByDesc('id')
            ->get()
            ->filter(fn (ActividadPaciente $registro) => $registro->turnos_efectivos_count < (int) $registro->cant_sesiones)
            ->values();
    }

    /**
     * Abre un registro de sesiones de kinesio con orden y agenda el primer turno.
     * Si no hay fecha de emisión, se guarda como orden pendiente.
     */
    public function abrirKinesioConOrden(
        int $idPaciente,
        int $cantSesiones,
        string $fechaHora,
        ?string $fechaEmisionOrd = null,
    ): ActividadPaciente {
        return DB::transaction(function () use ($idPaciente, $cantSesiones, $fechaHora, $fechaEmisionOrd) {
            if (!in_array($cantSesiones, [5, 10], true)) {
                throw new ReglaNegocioException('La orden médica solo puede cubrir 5 o 10 sesiones.');
            }

            $this->asegurarAfiliacionObraSocial($idPaciente);

            if ($this->kinesioConOrdenEnCurso($idPaciente)->isNotEmpty()) {
                throw new ReglaNegocioException(
                    'El paciente ya tiene un registro de sesiones con orden médica en curso.'
                );
            }

            $this->asegurarSlotDisponible($idPaciente, $fechaHora);

            $fechaHoraNormalizada = Carbon::parse($fechaHora)->toDateTimeString();

            $totalAPagar = ActividadCombo::calcularTotalAPagar(
                Actividad::KINESIOLOGIA_CONVENCIONAL,
                $cantSesiones,
                exigirComboExacto: true
            );

            $registroSesiones = ActividadPaciente::create([
                'id_actividad' => Actividad::KINESIOLOGIA_CONVENCIONAL,
                'id_paciente' => $idPaciente,
                'cant_sesiones' => $cantSesiones,
                'total_a_pagar' => $totalAPagar,
                'pago_completado' => true,
                'fecha_emision_ord' => ActividadPaciente::normalizarFechaOrden($fechaEmisionOrd),
            ]);

            $registroSesiones->turnos()->create(['fecha_hora' => $fechaHoraNormalizada]);

            return $registroSesiones->fresh([
                'turnos' => fn ($q) => $q->whereNull('id_turno_original')->orderBy('fecha_hora'),
            ]);
        });
    }

    public function agregarTurnoAKinesioConOrden(ActividadPaciente $registroSesiones, string $fechaHora): Turno
    {
        return DB::transaction(function () use ($registroSesiones, $fechaHora) {
            $registroSesiones = ActividadPaciente::query()
                ->whereKey($registroSesiones->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ((int) $registroSesiones->id_actividad !== Actividad::KINESIOLOGIA_CONVENCIONAL) {
                throw new ReglaNegocioException('Solo se pueden agregar turnos a registros de Kinesiología Convencional.');
            }

            if (!$registroSesiones->tieneOrdenMedica()) {
                throw new ReglaNegocioException('El registro de sesiones seleccionado no está cubierto por orden médica.');
            }

            $efectivos = $registroSesiones->turnos()->whereNull('id_turno_original')->count();

            if ($efectivos >= (int) $registroSesiones->cant_sesiones) {
                throw new ReglaNegocioException('El registro ya tiene todas las sesiones de la orden agendadas.');
            }

            $this->asegurarSlotDisponible((int) $registroSesiones->id_paciente, $fechaHora);

            $fechaHoraNormalizada = Carbon::parse($fechaHora)->toDateTimeString();

            return $registroSesiones->turnos()->create(['fecha_hora' => $fechaHoraNormalizada]);
        });
    }

    public function cargarFechaOrdenMedica(ActividadPaciente $registroSesiones, string $fechaEmisionOrd): ActividadPaciente
    {
        $fecha = ActividadPaciente::normalizarFechaOrden($fechaEmisionOrd);

        if ($fecha === ActividadPaciente::FECHA_ORDEN_PENDIENTE) {
            throw new ReglaNegocioException('Debe ingresar una fecha de orden médica válida.');
        }

        $registroSesiones->update(['fecha_emision_ord' => $fecha]);

        return $registroSesiones->fresh();
    }

    /**
     * Convierte un particular de Kinesiología Convencional (sin pagos) en registro con orden.
     * Amplía cant_sesiones al cupo de la orden (5|10) si los turnos efectivos no lo exceden.
     */
    public function aplicarOrdenAParticular(
        ActividadPaciente $particular,
        int $cantSesionesOrden,
        string $fechaEmisionOrd,
    ): ActividadPaciente {
        return DB::transaction(function () use ($particular, $cantSesionesOrden, $fechaEmisionOrd) {
            $particular = ActividadPaciente::query()
                ->whereKey($particular->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ((int) $particular->id_actividad !== Actividad::KINESIOLOGIA_CONVENCIONAL) {
                throw new ReglaNegocioException(
                    'Solo se puede aplicar orden médica a registros de Kinesiología Convencional.'
                );
            }

            if ($particular->tieneOrdenMedica()) {
                throw new ReglaNegocioException('El registro seleccionado ya tiene orden médica.');
            }

            if ($particular->pagos()->exists()) {
                throw new ReglaNegocioException(
                    'Este registro ya tiene pagos. No se puede aplicar orden; dejalo como particular o abrí un registro con orden nuevo.'
                );
            }

            if (!in_array($cantSesionesOrden, [5, 10], true)) {
                throw new ReglaNegocioException('La orden médica solo puede cubrir 5 o 10 sesiones.');
            }

            $efectivos = $particular->turnos()->whereNull('id_turno_original')->count();

            if ($efectivos > $cantSesionesOrden) {
                throw new ReglaNegocioException(sprintf(
                    'El registro ya tiene %d turnos; una orden de %d sesiones no los cubre.',
                    $efectivos,
                    $cantSesionesOrden
                ));
            }

            if ($particular->id_paciente === null) {
                throw new ReglaNegocioException('El registro no está asociado a un paciente regular.');
            }

            $this->asegurarAfiliacionObraSocial((int) $particular->id_paciente);

            if ($this->kinesioConOrdenEnCurso((int) $particular->id_paciente)->isNotEmpty()) {
                throw new ReglaNegocioException(
                    'El paciente ya tiene un registro de sesiones con orden médica en curso.'
                );
            }

            $fecha = ActividadPaciente::normalizarFechaOrden($fechaEmisionOrd);

            if ($fecha === ActividadPaciente::FECHA_ORDEN_PENDIENTE) {
                throw new ReglaNegocioException('Debe ingresar una fecha de orden médica válida.');
            }

            $totalAPagar = ActividadCombo::calcularTotalAPagar(
                Actividad::KINESIOLOGIA_CONVENCIONAL,
                $cantSesionesOrden,
                exigirComboExacto: true
            );

            $particular->update([
                'cant_sesiones' => $cantSesionesOrden,
                'total_a_pagar' => $totalAPagar,
                'pago_completado' => true,
                'fecha_emision_ord' => $fecha,
            ]);

            return $particular->fresh([
                'turnos' => fn ($q) => $q->whereNull('id_turno_original')->orderBy('fecha_hora'),
            ]);
        });
    }

    public function registrarInscripcionesGenerales(array $datos): ResultadoInscripcionGeneral
    {
        return DB::transaction(function () use ($datos) {
            $idPaciente = (int) $datos['id_paciente'];

            if (PacienteFijo::where('id_paciente', $idPaciente)->exists()) {
                throw new ReglaNegocioException(self::MENSAJE_PACIENTE_YA_FIJO);
            }

            $horariosPorActividad = collect($datos['horarios'])
                ->groupBy(fn (array $horario) => (int) $horario['id_actividad']);

            $actividadesInvalidas = $horariosPorActividad->keys()
                ->diff([Actividad::GIMNASIO, Actividad::PILATES]);

            if ($horariosPorActividad->isEmpty() || $horariosPorActividad->count() > 2 || $actividadesInvalidas->isNotEmpty()) {
                throw new ReglaNegocioException('Solo se admiten inscripciones de Gimnasio y/o Pilates.');
            }

            $this->asegurarCupoEstructural($datos['horarios']);

            $esDual = $horariosPorActividad->count() === 2;
            $fechaAncla = Carbon::parse($datos['fecha_ancla'])->startOfDay();
            $frecuenciaTotal = count($datos['horarios']);
            $paciente = Paciente::query()->select('id', 'es_gympass')->findOrFail($idPaciente);
            $precioMensual = PrecioMensual::obtenerVigenteParaPaciente($paciente, $frecuenciaTotal);

            $inscripciones = collect();

            foreach ($horariosPorActividad as $idActividad => $horarios) {
                $idActividad = (int) $idActividad;
                $frecuencia = $horarios->count();
                $cantSesiones = $frecuencia * 4;

                $totalAPagar = $esDual
                    ? ($idActividad === Actividad::GIMNASIO ? $precioMensual : 0)
                    : $precioMensual;

                $actividadPaciente = ActividadPaciente::create([
                    'id_actividad' => $idActividad,
                    'id_paciente' => $idPaciente,
                    'cant_sesiones' => $cantSesiones,
                    'total_a_pagar' => $totalAPagar,
                    'pago_completado' => $totalAPagar <= 0,
                ]);

                $patron = $horarios
                    ->map(fn (array $horario) => [
                        'dia_semana' => $horario['dia_semana'],
                        'hora_inicio' => $horario['hora_inicio'],
                    ])
                    ->values()
                    ->all();

                $preparacion = $this->turnoService->prepararDesdePatronSinReemplazo(
                    $fechaAncla,
                    $patron,
                    $cantSesiones,
                    $frecuencia
                );

                $actividadPaciente->turnos()->createMany($preparacion->paraPersistir());

                $inscripciones->put($idActividad, $actividadPaciente);
            }

            if ($esDual) {
                $inscripcionGym = $inscripciones->get(Actividad::GIMNASIO);
                $inscripcionPilates = $inscripciones->get(Actividad::PILATES);

                $inscripcionGym->update([
                    'frecuencia_total_dual' => $frecuenciaTotal,
                    'id_act_pac_dual' => $inscripcionPilates->id,
                ]);
                $inscripcionPilates->update([
                    'frecuencia_total_dual' => $frecuenciaTotal,
                    'id_act_pac_dual' => $inscripcionGym->id,
                ]);
            }

            $pacienteFijo = PacienteFijo::create(['id_paciente' => $idPaciente]);
            $pacienteFijo->horarios()->createMany(
                collect($datos['horarios'])
                    ->map(fn (array $horario) => [
                        'id_actividad' => (int) $horario['id_actividad'],
                        'dia_semana' => Actividad::diaSemanaAEntero($horario['dia_semana']),
                        'hora_inicio' => $horario['hora_inicio'],
                    ])
                    ->all()
            );

            InscripcionGeneralRegistrada::dispatch($pacienteFijo->id);

            $inscripcionParaCobro = $esDual
                ? $inscripciones->get(Actividad::GIMNASIO)
                : $inscripciones->first();

            return new ResultadoInscripcionGeneral(
                inscripcionParaCobro: $inscripcionParaCobro->fresh(['turnos']),
                inscripciones: $inscripciones->values()->map(fn (ActividadPaciente $i) => $i->fresh(['turnos'])),
                pacienteFijo: $pacienteFijo->fresh(['horarios']),
                esDual: $esDual,
            );
        });
    }

    /**
     * @param  list<array{id_actividad: int, dia_semana: string, hora_inicio: string}>  $horarios
     */
    private function asegurarCupoEstructural(array $horarios): void
    {
        foreach ($horarios as $horario) {
            $actividad = Actividad::findOrFail((int) $horario['id_actividad']);

            if ($actividad->tieneCupoEstructural($horario['dia_semana'], $horario['hora_inicio'])) {
                continue;
            }

            throw new ReglaNegocioException(sprintf(
                'Sin cupo para %s %s %s. El horario dejó de estar disponible.',
                $actividad->nombre,
                $horario['dia_semana'],
                substr($horario['hora_inicio'], 0, 5)
            ));
        }
    }

    private function crearInscripcion(array $validados, bool $pagoCompletado): ActividadPaciente
    {
        return ActividadPaciente::create([
            'id_actividad' => $validados['id_actividad'],
            'id_paciente' => $validados['id_paciente'],
            'cant_sesiones' => $validados['cant_sesiones'],
            'total_a_pagar' => $validados['total_a_pagar'],
            'pago_completado' => $pagoCompletado,
            'fecha_emision_ord' => $validados['fecha_emision_ord'] ?? null,
        ]);
    }

    private function prepararTurnos(array $validados): ResultadoPreparacionTurnos
    {
        if (!$validados['autogenerados']) {
            return $this->turnoService->prepararExactos($validados['turnos']);
        }

        $cantidadSesiones = (int) ($validados['sesiones_cubiertas'] ?? $validados['cant_sesiones']);

        return $this->turnoService->prepararDesdePatron(
            Actividad::findOrFail($validados['id_actividad']),
            (int) $validados['id_paciente'],
            Carbon::parse($validados['fecha_ancla'])->startOfDay(),
            $validados['turnos'],
            $cantidadSesiones,
            (int) $validados['frecuencia_semanal']
        );
    }

    private function asegurarAfiliacionObraSocial(int $idPaciente): void
    {
        $paciente = Paciente::with('afiliacionVigente')->findOrFail($idPaciente);

        if (!$paciente->afiliacionVigente?->id_obra_social) {
            throw new ReglaNegocioException('El paciente seleccionado no posee una afiliación vigente a una obra social.');
        }
    }

    private function asegurarKinesioSinOrdenPermitido(int $idPaciente, int $idActividad): void
    {
        $actividad = Actividad::query()->find($idActividad);

        if ($actividad === null || (int) $actividad->id_tipo_actividad !== Actividad::TIPO_KINESIOLOGIA) {
            return;
        }

        if ($this->kinesioConOrdenEnCurso($idPaciente)->isNotEmpty()) {
            throw new ReglaNegocioException(self::MENSAJE_KINESIO_SIN_ORDEN_CON_REGISTRO_EN_CURSO);
        }

        if ($this->kinesioParticularEnCurso($idPaciente, $idActividad)->isNotEmpty()) {
            throw new ReglaNegocioException(self::MENSAJE_KINESIO_PARTICULAR_EN_CURSO);
        }
    }

    private function asegurarSlotDisponible(int $idPaciente, string $fechaHora): void
    {
        $instante = Carbon::parse($fechaHora);
        $actividad = Actividad::findOrFail(Actividad::KINESIOLOGIA_CONVENCIONAL);
        $disponibles = $actividad->turnosDisponibles(
            $idPaciente,
            $instante->copy()->startOfDay(),
            $instante->copy()->endOfDay()
        );

        if (!in_array($instante->toDateTimeString(), $disponibles, true)) {
            throw new ReglaNegocioException('El horario seleccionado no tiene cupo disponible.');
        }

        $this->asegurarSinOtroTurnoKinesioElMismoDia($idPaciente, $fechaHora);
    }

    private function asegurarSinOtroTurnoKinesioElMismoDia(int $idPaciente, string $fechaHora): void
    {
        $dia = Carbon::parse($fechaHora)->toDateString();

        $yaTieneTurno = Turno::query()
            ->whereNull('id_turno_original')
            ->whereDate('fecha_hora', $dia)
            ->whereHas(
                'actividadPaciente',
                fn ($q) => $q
                    ->where('id_paciente', $idPaciente)
                    ->whereHas(
                        'actividad',
                        fn ($actividad) => $actividad->where('id_tipo_actividad', Actividad::TIPO_KINESIOLOGIA)
                    )
            )
            ->exists();

        if ($yaTieneTurno) {
            throw new ReglaNegocioException('El paciente ya tiene un turno de kinesiología ese día.');
        }
    }

    private function enriquecerDatosConOrden(array $validados, Carbon $ahora): array
    {
        $this->asegurarAfiliacionObraSocial((int) $validados['id_paciente']);

        $validados['cant_sesiones'] = (int) $validados['sesiones_cubiertas'];
        $validados['fecha_emision_ord'] = Carbon::create($ahora->year, $validados['mes'], $validados['dia']);

        return $validados;
    }
}
