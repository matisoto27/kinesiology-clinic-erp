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
use App\Support\Registros\ModalidadRegistro;
use App\Support\Registros\ResultadoInscripcionGeneral;
use App\Support\Registros\ResultadoRegistroActividadPaciente;
use App\Support\Turnos\ResultadoPreparacionTurnos;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ActividadPacienteService
{
    public const MENSAJE_PACIENTE_YA_FIJO = 'El paciente ya fue registrado como fijo previamente. Para modificar sus horarios, edite su registro existente en Inscripciones Mensuales.';

    public function __construct(
        private TurnoService $turnoService,
    ) {}

    public function registrar(array $validados): ResultadoRegistroActividadPaciente
    {
        return DB::transaction(function () use ($validados) {
            $esConOrden = ModalidadRegistro::esConOrden($validados);
            $ahora = Carbon::now();

            if ($esConOrden) {
                $validados = $this->enriquecerDatosConOrden($validados, $ahora);
            }

            $validados['total_a_pagar'] = ActividadCombo::calcularTotalAPagar(
                (int) $validados['id_actividad'],
                (int) $validados['cant_sesiones'],
                exigirComboExacto: $esConOrden
            );

            $preparacion = $this->prepararTurnos($validados);
            $turnos = $preparacion->paraPersistir();
            $this->asegurarCicloSinSolapamiento(
                (int) $validados['id_paciente'],
                (int) $validados['id_actividad'],
                $turnos
            );

            $actividadPaciente = $this->crearInscripcion($validados, $esConOrden);
            $actividadPaciente->turnos()->createMany($turnos);

            return new ResultadoRegistroActividadPaciente(
                inscripcion: $actividadPaciente->fresh(['turnos']),
                reemplazos: $preparacion->reemplazos(),
            );
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

    /**
     * Impide un ciclo paralelo de la misma actividad: los intervalos
     * [primer turno, último turno] (por día) no pueden solaparse.
     *
     * @param  list<array{fecha_hora: string}>  $turnos
     */
    private function asegurarCicloSinSolapamiento(int $idPaciente, int $idActividad, array $turnos): void
    {
        $rangoNuevo = $this->rangoDeTurnos($turnos);

        if ($rangoNuevo === null) {
            return;
        }

        [$inicioNuevo, $finNuevo] = $rangoNuevo;

        $ciclos = ActividadPaciente::query()
            ->where('id_paciente', $idPaciente)
            ->where('id_actividad', $idActividad)
            ->join('turnos', 'turnos.id_act_pac', '=', 'actividades_pacientes.id')
            ->whereNull('turnos.id_turno_original')
            ->groupBy('actividades_pacientes.id')
            ->select('actividades_pacientes.id')
            ->selectRaw('MIN(turnos.fecha_hora) as ciclo_inicio')
            ->selectRaw('MAX(turnos.fecha_hora) as ciclo_fin')
            ->orderBy('actividades_pacientes.id')
            ->get();

        foreach ($ciclos as $ciclo) {
            $inicio = Carbon::parse($ciclo->ciclo_inicio)->startOfDay();
            $fin = Carbon::parse($ciclo->ciclo_fin)->startOfDay();

            if ($inicioNuevo->gt($fin) || $finNuevo->lt($inicio)) {
                continue;
            }

            $nombreActividad = Actividad::query()->whereKey($idActividad)->value('nombre');

            throw new ReglaNegocioException(sprintf(
                'El paciente ya tiene una inscripción de %s con turnos entre el %s y el %s. Reprograme esos turnos o elimine esa inscripción antes de cargar otra.',
                $nombreActividad,
                $inicio->format('d/m/Y'),
                $fin->format('d/m/Y'),
            ));
        }
    }

    /**
     * @param  list<array{fecha_hora: string}>  $turnos
     * @return array{0: Carbon, 1: Carbon}|null
     */
    private function rangoDeTurnos(array $turnos): ?array
    {
        if ($turnos === []) {
            return null;
        }

        $inicio = null;
        $fin = null;

        foreach ($turnos as $turno) {
            $fecha = Carbon::parse($turno['fecha_hora'])->startOfDay();
            $inicio = $inicio === null || $fecha->lt($inicio) ? $fecha : $inicio;
            $fin = $fin === null || $fecha->gt($fin) ? $fecha : $fin;
        }

        return [$inicio, $fin];
    }

    private function enriquecerDatosConOrden(array $validados, Carbon $ahora): array
    {
        $paciente = Paciente::with('afiliacionVigente')->findOrFail($validados['id_paciente']);

        if (!$paciente->afiliacionVigente?->id_obra_social) {
            throw new ReglaNegocioException('El paciente seleccionado no posee una afiliación vigente a una obra social.');
        }

        $validados['cant_sesiones'] = (int) $validados['sesiones_cubiertas'];
        $validados['fecha_emision_ord'] = Carbon::create($ahora->year, $validados['mes'], $validados['dia']);

        return $validados;
    }
}
