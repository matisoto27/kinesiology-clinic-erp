<?php

namespace App\Services;

use App\Exceptions\ReglaNegocioException;
use App\Models\Actividad;
use App\Models\ActividadPaciente;
use App\Models\Turno;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class TurnoService
{
    public function prepararFechas(Actividad $actividad, int $idPaciente, array $turnosSolicitados, int $semanasNecesarias): array
    {
        $primerTurno = $turnosSolicitados[0];
        $ultimoTurno = $turnosSolicitados[array_key_last($turnosSolicitados)];

        $comienzo = $primerTurno->copy()->startOfWeek(Carbon::MONDAY)->startOfDay();
        $fin = $ultimoTurno->copy()->endOfWeek(Carbon::FRIDAY)->endOfDay();

        $fechasDisponibles = array_flip($actividad->turnosDisponibles($idPaciente, $comienzo, $fin));

        $turnosValidados = [];
        $turnosSolicitadosStr = array_map(fn($t) => $t->toDateTimeString(), $turnosSolicitados);

        foreach ($turnosSolicitados as $i => $turno) {
            $turnoStr = $turnosSolicitadosStr[$i];
            unset($turnosSolicitadosStr[$i]);

            if ($turno->isPast() || !isset($fechasDisponibles[$turnoStr])) {
                $fechasRestringidas = array_flip(array_merge($turnosValidados, $turnosSolicitadosStr));
                $turnoStr = $actividad->buscarReemplazoTurno($turno, $fechasDisponibles, $fechasRestringidas);

                if (!$turnoStr) {
                    throw new ReglaNegocioException('No hay suficientes turnos disponibles para cubrir la cantidad de turnos solicitada.');
                }
            }

            $turnosValidados[] = $turnoStr;
        }

        sort($turnosValidados);

        return array_map(fn ($fecha) => ['fecha_hora' => $fecha], $turnosValidados);
    }

    public function prepararTurnosManuales(array $turnos): array
    {
        sort($turnos);

        return array_map(fn ($fecha) => ['fecha_hora' => $fecha], $turnos);
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
            $fechaReferencia = $turno->turnoOriginal?->fecha_hora ?? $turno->fecha_hora;
            $this->asegurarFechaReprogramacionValida($fechaReferencia, $nuevaFechaHora);

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

        if ($nuevaFechaHora->lt($inicioSemanaTurno)) {
            throw new ReglaNegocioException('No se puede reprogramar a una fecha anterior a la semana del turno.');
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
