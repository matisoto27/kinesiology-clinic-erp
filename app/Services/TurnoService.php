<?php

namespace App\Services;

use App\Exceptions\ReglaNegocioException;
use App\Models\Actividad;
use App\Models\ActividadPaciente;
use App\Models\Turno;
use App\Support\Turnos\AsignacionTurno;
use App\Support\Turnos\ExpansorTurnosPatron;
use App\Support\Turnos\ResultadoPreparacionTurnos;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class TurnoService
{
    public function __construct(
        private ExpansorTurnosPatron $expansorTurnosPatron,
    ) {}

    /**
     * Expande un patrón semanal y asigna cupo con reemplazo si hace falta.
     *
     * @param  array<int, array{dia_semana: string, hora_inicio: string}>  $patron
     */
    public function prepararDesdePatron(
        Actividad $actividad,
        int $idPaciente,
        Carbon $fechaAncla,
        array $patron,
        int $cantidadSesiones,
        int $frecuenciaSemanal
    ): ResultadoPreparacionTurnos {
        $expansion = $this->expansorTurnosPatron->expandir(
            $fechaAncla,
            $patron,
            $cantidadSesiones,
            $frecuenciaSemanal
        );

        if ($expansion['turnos'] === []) {
            throw new ReglaNegocioException('No se pudieron calcular turnos para la inscripción.');
        }

        return $this->asignarConReemplazo($actividad, $idPaciente, $expansion['turnos']);
    }

    /**
     * Expande un patrón semanal sin buscar reemplazos (Gym/Pilates fijo).
     *
     * @param  array<int, array{dia_semana: string, hora_inicio: string}>  $patron
     */
    public function prepararDesdePatronSinReemplazo(
        Carbon $fechaAncla,
        array $patron,
        int $cantidadSesiones,
        int $frecuenciaSemanal
    ): ResultadoPreparacionTurnos {
        $expansion = $this->expansorTurnosPatron->expandir(
            $fechaAncla,
            $patron,
            $cantidadSesiones,
            $frecuenciaSemanal
        );

        return $this->prepararExactosDesdeCarbons($expansion['turnos']);
    }

    /**
     * @param  list<string>  $fechasHora
     */
    public function prepararExactos(array $fechasHora): ResultadoPreparacionTurnos
    {
        sort($fechasHora);

        return ResultadoPreparacionTurnos::desdeFechasExactas($fechasHora);
    }

    /**
     * @param  list<Carbon>  $turnosSolicitados
     */
    private function prepararExactosDesdeCarbons(array $turnosSolicitados): ResultadoPreparacionTurnos
    {
        if ($turnosSolicitados === []) {
            throw new ReglaNegocioException('No se pudieron calcular turnos para la inscripción.');
        }

        foreach ($turnosSolicitados as $turno) {
            if ($turno->isPast()) {
                throw new ReglaNegocioException(
                    'Alguno de los turnos de la inscripción ya quedó en el pasado. Vuelva a seleccionar la fecha de inicio.'
                );
            }
        }

        return ResultadoPreparacionTurnos::desdeFechasExactas(
            array_map(fn (Carbon $turno) => $turno->toDateTimeString(), $turnosSolicitados)
        );
    }

    /**
     * @param  list<Carbon>  $turnosSolicitados
     */
    private function asignarConReemplazo(
        Actividad $actividad,
        int $idPaciente,
        array $turnosSolicitados
    ): ResultadoPreparacionTurnos {
        $primerTurno = $turnosSolicitados[0];
        $ultimoTurno = $turnosSolicitados[array_key_last($turnosSolicitados)];

        $comienzo = $primerTurno->copy()->startOfWeek(Carbon::MONDAY)->startOfDay();
        $fin = $ultimoTurno->copy()->endOfWeek(Carbon::FRIDAY)->endOfDay();

        $fechasDisponibles = array_flip($actividad->turnosDisponibles($idPaciente, $comienzo, $fin));

        $asignaciones = [];
        $turnosAsignados = [];
        $turnosSolicitadosStr = array_map(fn ($t) => $t->toDateTimeString(), $turnosSolicitados);

        foreach ($turnosSolicitados as $i => $turno) {
            $solicitado = $turnosSolicitadosStr[$i];
            unset($turnosSolicitadosStr[$i]);

            $asignado = $solicitado;

            if ($turno->isPast() || !isset($fechasDisponibles[$solicitado])) {
                $fechasRestringidas = array_flip(array_merge($turnosAsignados, $turnosSolicitadosStr));
                $asignado = $actividad->buscarReemplazoTurno($turno, $fechasDisponibles, $fechasRestringidas);

                if (!$asignado) {
                    throw new ReglaNegocioException('No hay suficientes turnos disponibles para cubrir la cantidad de turnos solicitada.');
                }
            }

            $turnosAsignados[] = $asignado;
            $asignaciones[] = new AsignacionTurno($solicitado, $asignado);
        }

        return new ResultadoPreparacionTurnos($asignaciones);
    }

    public function validarCuposTurnosCasuales(
        Actividad $actividad,
        int $idPaciente,
        Carbon $comienzo,
        Carbon $fin,
        array $fechasHoraSolicitadas
    ): void {
        if ($fechasHoraSolicitadas === []) {
            throw new ReglaNegocioException('Debe seleccionar al menos un turno.');
        }

        $disponibles = array_flip($actividad->turnosDisponibles($idPaciente, $comienzo, $fin, false));
        $fechasVistas = [];

        foreach ($fechasHoraSolicitadas as $fechaHora) {
            $instante = Carbon::parse($fechaHora);
            $fecha = $instante->toDateString();
            $slot = $instante->toDateTimeString();

            if (isset($fechasVistas[$fecha])) {
                throw new ReglaNegocioException('No puede seleccionar más de un turno por día.');
            }

            if (!isset($disponibles[$slot])) {
                throw new ReglaNegocioException('Uno o más horarios seleccionados ya no tienen cupo disponible.');
            }

            $fechasVistas[$fecha] = true;
        }
    }

    public function reprogramar(Turno $turnoOriginal, Carbon $nuevaFechaHora, ?int $idActividadDestino = null): Turno
    {
        if (str_contains($turnoOriginal->estado, 'Presente')) {
            throw new ReglaNegocioException('No se puede reprogramar un turno donde el paciente ya ha asistido.');
        }

        if (!$turnoOriginal->esAusenteAviso()) {
            throw new ReglaNegocioException('El turno debe marcarse como Ausente avisó antes de asignar una nueva fecha.');
        }

        $this->asegurarFechaReprogramacionValida($turnoOriginal->fecha_hora, $nuevaFechaHora);

        return DB::transaction(function () use ($turnoOriginal, $nuevaFechaHora, $idActividadDestino) {
            $turno = Turno::lockForUpdate()->findOrFail($turnoOriginal->id);
            $turno->load([
                'turnoRecuperacion',
                'actividadPaciente.actividad',
                'actividadPaciente.actPacDual.actividad',
            ]);

            if ($turno->esReprogramado() || $turno->turnoRecuperacion) {
                throw new ReglaNegocioException('Este turno ya fue reprogramado.');
            }

            $inscripcionOrigen = $turno->actividadPaciente;
            $inscripcionDestino = $this->resolverInscripcionDestino(
                $inscripcionOrigen,
                $idActividadDestino ?? (int) $inscripcionOrigen->id_actividad
            );

            if ((int) $inscripcionDestino->id !== (int) $inscripcionOrigen->id) {
                ActividadPaciente::lockForUpdate()->findOrFail($inscripcionDestino->id);
            }

            $this->asegurarSlotDisponible(
                $inscripcionDestino->actividad,
                $inscripcionOrigen->id_paciente,
                $nuevaFechaHora
            );

            return Turno::create([
                'id_act_pac' => $inscripcionDestino->id,
                'fecha_hora' => $nuevaFechaHora,
                'id_turno_original' => $turno->id,
            ]);
        });
    }

    public function cancelarReprogramacion(Turno $turnoOriginal): void
    {
        DB::transaction(function () use ($turnoOriginal) {
            $original = Turno::lockForUpdate()->findOrFail($turnoOriginal->id);
            $original->load('turnoRecuperacion');

            if (!$original->puedeCancelarReprogramacion()) {
                throw new ReglaNegocioException('No se puede cancelar la reprogramación de este turno.');
            }

            if ($original->turnoRecuperacion) {
                $recuperacion = Turno::lockForUpdate()->findOrFail($original->turnoRecuperacion->id);
                $recuperacion->notas()->delete();
                $recuperacion->delete();
            }

            $original->update(['estado' => 'Ausente']);
        });
    }

    public function corregirFecha(Turno $turno, Carbon $nuevaFechaHora): Turno
    {
        return DB::transaction(function () use ($turno, $nuevaFechaHora) {
            $vigente = $this->bloquearSinAsistencia(
                $turno,
                'No se puede corregir un turno donde el paciente ya ha asistido.'
            );
            $vigente->load(['actividadPaciente.actividad', 'turnoRecuperacion']);

            if ($vigente->actividadPaciente->actividad->esActividadGeneral()) {
                throw new ReglaNegocioException('La corrección simple de fecha solo aplica a turnos de kinesiología.');
            }

            if ($vigente->esAusenteAviso()) {
                throw new ReglaNegocioException('Este turno ya fue marcado como Ausente Avisó.');
            }

            if ($vigente->turnoRecuperacion) {
                throw new ReglaNegocioException('Este turno ya fue reprogramado.');
            }

            $this->asegurarFechaReprogramacionValida($vigente->fecha_hora, $nuevaFechaHora);

            $vigente->update(['fecha_hora' => $nuevaFechaHora]);

            return $vigente->fresh();
        });
    }

    public function moverReprogramado(Turno $reprogramado, Carbon $nuevaFechaHora, int $idActividadDestino): Turno
    {
        return DB::transaction(function () use ($reprogramado, $nuevaFechaHora, $idActividadDestino) {
            $turno = $this->bloquearSinAsistencia(
                $reprogramado,
                'No se puede mover un turno donde el paciente ya ha asistido.'
            );
            $turno->load([
                'actividadPaciente.actividad',
                'actividadPaciente.actPacDual.actividad',
            ]);

            if (!$turno->esReprogramado()) {
                throw new ReglaNegocioException('Solo se puede mover un turno reprogramado.');
            }

            if (!$turno->actividadPaciente->actividad->esActividadGeneral()) {
                throw new ReglaNegocioException('Solo se puede mover un turno reprogramado de Gimnasio o Pilates.');
            }

            $turno->loadMissing('turnoOriginal');
            $original = $turno->turnoOriginal;

            $fechaReferencia = $original?->fecha_hora ?? $turno->fecha_hora;
            $this->asegurarFechaReprogramacionValida($fechaReferencia, $nuevaFechaHora);

            if ($original && $nuevaFechaHora->equalTo($original->fecha_hora)) {
                throw new ReglaNegocioException(
                    'No se puede mover el turno reprogramado a la fecha del turno original. Para volver a esa fecha, cancelá la reprogramación.'
                );
            }

            $inscripcionOrigen = $turno->actividadPaciente;
            $inscripcionDestino = $this->resolverInscripcionDestino(
                $inscripcionOrigen,
                $idActividadDestino
            );

            if ((int) $inscripcionDestino->id !== (int) $inscripcionOrigen->id) {
                ActividadPaciente::lockForUpdate()->findOrFail($inscripcionDestino->id);
            }

            $this->asegurarSlotDisponible(
                $inscripcionDestino->actividad,
                $inscripcionOrigen->id_paciente,
                $nuevaFechaHora,
                $turno->id
            );

            $turno->update([
                'id_act_pac' => $inscripcionDestino->id,
                'fecha_hora' => $nuevaFechaHora,
            ]);

            return $turno->fresh();
        });
    }

    private function resolverInscripcionDestino(
        ActividadPaciente $origen,
        int $idActividadDestino
    ): ActividadPaciente {
        if ((int) $origen->id_actividad === $idActividadDestino) {
            return $origen;
        }

        $generales = [Actividad::GIMNASIO, Actividad::PILATES];

        if (
            !in_array((int) $origen->id_actividad, $generales, true)
            || !in_array($idActividadDestino, $generales, true)
        ) {
            throw new ReglaNegocioException('Solo se puede cambiar un turno entre Gimnasio y Pilates.');
        }

        if (!$origen->esDualOperativo()) {
            throw new ReglaNegocioException('Solo se puede cambiar de actividad en una inscripción dual activa.');
        }

        $par = $origen->actPacDual;

        if ((int) $par->id_actividad !== $idActividadDestino) {
            throw new ReglaNegocioException('La actividad destino no corresponde al par dual.');
        }

        return $par;
    }

    private function bloquearSinAsistencia(Turno $turno, string $mensajePresente): Turno
    {
        $bloqueado = Turno::lockForUpdate()->findOrFail($turno->id);

        if (str_contains($bloqueado->estado, 'Presente')) {
            throw new ReglaNegocioException($mensajePresente);
        }

        return $bloqueado;
    }

    private function asegurarFechaReprogramacionValida(Carbon $fechaTurno, Carbon $nuevaFechaHora): void
    {
        $inicioSemanaTurno = $fechaTurno->copy()->startOfWeek(Carbon::MONDAY)->startOfDay();
        $finVentana = $fechaTurno->copy()->startOfWeek(Carbon::MONDAY)->addWeeks(2)->addDays(4)->endOfDay();

        if ($nuevaFechaHora->lt($inicioSemanaTurno)) {
            throw new ReglaNegocioException('No se puede reprogramar a una fecha anterior a la semana del turno.');
        }

        if ($nuevaFechaHora->gt($finVentana)) {
            throw new ReglaNegocioException('No se puede reprogramar más allá del viernes de la segunda semana siguiente.');
        }

        if ($nuevaFechaHora->isPast()) {
            throw new ReglaNegocioException('No se puede reprogramar a una fecha que ya pasó.');
        }
    }

    private function asegurarSlotDisponible(
        Actividad $actividad,
        ?int $idPaciente,
        Carbon $nuevaFechaHora,
        ?int $idTurnoAIgnorar = null
    ): void {
        $comienzo = $nuevaFechaHora->copy()->startOfDay();
        $fin = $nuevaFechaHora->copy()->endOfDay();
        $slot = $nuevaFechaHora->toDateTimeString();

        if ($idTurnoAIgnorar === null) {
            $disponibles = array_flip($actividad->turnosDisponibles($idPaciente, $comienzo, $fin));

            if (!isset($disponibles[$slot])) {
                throw new ReglaNegocioException('El horario seleccionado ya no tiene cupo disponible.');
            }

            return;
        }

        $disponibles = array_flip($actividad->turnosDisponibles(null, $comienzo, $fin));

        if (!isset($disponibles[$slot])) {
            throw new ReglaNegocioException('El horario seleccionado ya no tiene cupo disponible.');
        }

        if (
            $idPaciente !== null
            && $this->pacienteTieneSolapeEnSlot($idPaciente, $nuevaFechaHora, $idTurnoAIgnorar)
        ) {
            throw new ReglaNegocioException('El horario seleccionado ya no tiene cupo disponible.');
        }
    }

    private function pacienteTieneSolapeEnSlot(int $idPaciente, Carbon $nuevaFechaHora, int $idTurnoAIgnorar): bool
    {
        $inicio = $nuevaFechaHora->timestamp;
        $fin = $inicio + 3600;

        return Turno::conActPac()
            ->delPaciente($idPaciente, true)
            ->activosParaCupo()
            ->entreFechas(
                $nuevaFechaHora->copy()->startOfDay()->toDateTimeString(),
                $nuevaFechaHora->copy()->endOfDay()->toDateTimeString()
            )
            ->where('turnos.id', '!=', $idTurnoAIgnorar)
            ->get()
            ->contains(function (Turno $turno) use ($inicio, $fin) {
                $existenteInicio = $turno->fecha_hora->timestamp;
                $existenteFin = $existenteInicio + 3600;

                return $inicio < $existenteFin && $fin > $existenteInicio;
            });
    }
}
