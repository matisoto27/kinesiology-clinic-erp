<?php

namespace App\Services;

use App\Models\Actividad;
use App\Models\ActividadCombo;
use App\Models\ActividadPaciente;
use App\Models\Paciente;
use App\Models\PacienteFijo;
use App\Models\PrecioMensual;
use App\Support\Registros\ModalidadRegistro;
use App\Support\Registros\ResultadoInscripcionGeneral;
use App\Support\Turnos\ExpansorTurnosPatron;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class ActividadPacienteService
{
    public const MENSAJE_PACIENTE_YA_FIJO = 'El paciente ya fue registrado como fijo previamente. Para modificar sus horarios, edite su registro existente en Inscripciones Mensuales.';

    public function __construct(
        private TurnoService $turnoService,
        private ExpansorTurnosPatron $expansorTurnosPatron,
    ) {}

    public function registrar(array $validados): ActividadPaciente
    {
        try {
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

                $actividadPaciente = $this->crearInscripcion($validados, $esConOrden);
                $this->persistirTurnos($actividadPaciente, $validados);

                return $actividadPaciente;
            });
        } catch (Throwable $th) {
            Log::error('[ActividadPacienteService@registrar] Error al registrar la inscripción del paciente', [
                'excepción' => $th->getMessage(),
            ]);

            throw $th;
        }
    }

    public function registrarInscripcionesGenerales(array $datos): ResultadoInscripcionGeneral
    {
        try {
            return DB::transaction(function () use ($datos) {
                $idPaciente = (int) $datos['id_paciente'];

                if (PacienteFijo::where('id_paciente', $idPaciente)->exists()) {
                    throw new Exception(self::MENSAJE_PACIENTE_YA_FIJO);
                }

                $horariosPorActividad = collect($datos['horarios'])
                    ->groupBy(fn (array $horario) => (int) $horario['id_actividad']);

                $actividadesInvalidas = $horariosPorActividad->keys()
                    ->diff([Actividad::GIMNASIO, Actividad::PILATES]);

                if ($horariosPorActividad->isEmpty() || $horariosPorActividad->count() > 2 || $actividadesInvalidas->isNotEmpty()) {
                    throw new Exception('Solo se admiten inscripciones de Gimnasio y/o Pilates.');
                }

                $this->asegurarCupoEstructural($datos['horarios']);

                $esDual = $horariosPorActividad->count() === 2;
                $fechaAncla = Carbon::parse($datos['fecha_ancla'])->startOfDay();
                $frecuenciaTotal = count($datos['horarios']);
                $precioMensual = PrecioMensual::obtenerVigentePorFrecuencia($frecuenciaTotal);

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

                    $expansion = $this->expansorTurnosPatron->expandir($fechaAncla, $patron, $cantSesiones, $frecuencia);

                    $actividadPaciente->turnos()->createMany(
                        $this->prepararTurnosGenerales($expansion['turnos'])
                    );

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
        } catch (Throwable $th) {
            Log::error('[ActividadPacienteService@registrarInscripcionesGenerales] Error al registrar las inscripciones generales del paciente', [
                'excepción' => $th->getMessage(),
            ]);

            throw $th;
        }
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

            throw new Exception(sprintf(
                'Sin cupo para %s %s %s. El horario dejó de estar disponible.',
                $actividad->nombre,
                $horario['dia_semana'],
                substr($horario['hora_inicio'], 0, 5)
            ));
        }
    }

    /**
     * @param  list<Carbon>  $turnosSolicitados
     * @return list<array{fecha_hora: string}>
     */
    private function prepararTurnosGenerales(array $turnosSolicitados): array
    {
        if ($turnosSolicitados === []) {
            throw new Exception('No se pudieron calcular turnos para la inscripción.');
        }

        foreach ($turnosSolicitados as $turno) {
            if ($turno->isPast()) {
                throw new Exception(
                    'Alguno de los turnos de la inscripción ya quedó en el pasado. Vuelva a seleccionar la fecha de inicio.'
                );
            }
        }

        return array_map(fn (Carbon $turno) => [
            'fecha_hora' => $turno->toDateTimeString(),
        ], $turnosSolicitados);
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

    private function persistirTurnos(ActividadPaciente $actividadPaciente, array $validados): void
    {
        $turnosParaInsertar = $validados['autogenerados']
            ? $this->prepararTurnosAutomaticos($validados)
            : $this->turnoService->prepararTurnosManuales($validados['turnos']);

        $actividadPaciente->turnos()->createMany($turnosParaInsertar);
    }

    private function enriquecerDatosConOrden(array $validados, Carbon $ahora): array
    {
        $paciente = Paciente::with('afiliacionVigente')->findOrFail($validados['id_paciente']);

        if (!$paciente->afiliacionVigente) {
            throw new Exception('El paciente seleccionado no posee una afiliación vigente a una obra social.');
        }

        $validados['cant_sesiones'] = (int) $validados['sesiones_cubiertas'];
        $validados['fecha_emision_ord'] = Carbon::create($ahora->year, $validados['mes'], $validados['dia']);

        return $validados;
    }

    private function prepararTurnosAutomaticos(array $validados): array
    {
        $cantidadSesiones = (int) ($validados['sesiones_cubiertas'] ?? $validados['cant_sesiones']);
        $frecuenciaSemanal = (int) $validados['frecuencia_semanal'];
        $fechaAncla = Carbon::parse($validados['fecha_ancla'])->startOfDay();

        $expansion = $this->expansorTurnosPatron->expandir(
            $fechaAncla,
            $validados['turnos'],
            $cantidadSesiones,
            $frecuenciaSemanal
        );

        return $this->turnoService->prepararFechas(
            Actividad::findOrFail($validados['id_actividad']),
            $validados['id_paciente'],
            $expansion['turnos'],
            $expansion['semanas']
        );
    }
}
