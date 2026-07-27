<?php

namespace App\Services;

use App\Models\Actividad;
use App\Models\ActividadCombo;
use App\Models\ActividadPaciente;
use App\Models\Paciente;
use App\Support\Registros\ModalidadRegistro;
use App\Support\Turnos\ExpansorTurnosPatron;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class ActividadPacienteService
{
    public const MENSAJE_INSCRIPCION_NO_DISPONIBLE = 'El paciente tiene una inscripción en curso para esta actividad.';

    public function __construct(
        private TurnoService $turnoService,
        private ExpansorTurnosPatron $expansorTurnosPatron,
        private PlanDualService $planDualService
    ) {}

    public function registrar(array $validados): ActividadPaciente
    {
        try {
            return DB::transaction(function () use ($validados) {
                $esPlanDual = !empty($validados['plan_dual'])
                    && Actividad::find((int) $validados['id_actividad'])?->esActividadGeneral() === true;

                if ($esPlanDual) {
                    return $this->registrarPlanDual($validados);
                }

                $esConOrden = ModalidadRegistro::esConOrden($validados);
                $ahora = Carbon::now();

                if ($esConOrden) {
                    $validados = $this->enriquecerDatosConOrden($validados, $ahora);
                }

                $validados = $this->determinarTotal($validados);

                $actividadPaciente = $this->crearInscripcion($validados, $ahora, $esConOrden);
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

    private function registrarPlanDual(array $validados): ActividadPaciente
    {
        $pendiente = $this->planDualService->obtenerDualPendiente((int) $validados['id_paciente']);

        if ($pendiente) {
            return $this->completarPlanDual($validados, $pendiente);
        }

        return $this->iniciarPlanDual($validados);
    }

    private function iniciarPlanDual(array $validados): ActividadPaciente
    {
        if ($this->planDualService->obtenerDualPendiente((int) $validados['id_paciente'])) {
            throw new Exception(PlanDualService::MENSAJE_DUAL_PENDIENTE_EXISTENTE);
        }

        $frecuencia = (int) $validados['frecuencia_semanal'];

        if ($frecuencia < 1 || $frecuencia > 4) {
            throw new Exception('La frecuencia semanal del plan dual debe estar entre 1 y 4.');
        }

        $paciente = Paciente::findOrFail((int) $validados['id_paciente']);

        if (!$this->planDualService->puedeIniciarPlanDual($paciente)) {
            throw new Exception(PlanDualService::MENSAJE_NO_PUEDE_INICIAR_PLAN_DUAL);
        }

        $ahora = Carbon::now();
        $validados['cant_sesiones'] = $frecuencia * 4;
        $validados['total_a_pagar'] = 0;

        $actividadPaciente = $this->crearInscripcion($validados, $ahora, false, [
            'plan_dual_pendiente' => true,
            'frecuencia_total_dual' => null,
            'id_act_pac_dual' => null,
        ]);

        $this->persistirTurnos($actividadPaciente, $validados);

        return $actividadPaciente->fresh(['turnos']);
    }

    private function completarPlanDual(array $validados, ActividadPaciente $pendiente): ActividadPaciente
    {
        $this->planDualService->validarSegundaInscripcion($pendiente, $validados);
        $frecuenciaPrimera = $pendiente->frecuenciaSemanal();
        $frecuenciaSegunda = (int) $validados['frecuencia_semanal'];

        $frecuenciaTotal = $frecuenciaPrimera + $frecuenciaSegunda;
        $precioPlan = $this->planDualService->obtenerPrecioPlan($frecuenciaTotal);

        $ahora = Carbon::now();
        $validados['cant_sesiones'] = $frecuenciaSegunda * 4;
        $validados['total_a_pagar'] = 0;

        $segundaInscripcion = $this->crearInscripcion($validados, $ahora, true, [
            'plan_dual_pendiente' => false,
            'frecuencia_total_dual' => $frecuenciaTotal,
            'id_act_pac_dual' => $pendiente->id,
        ]);

        $this->persistirTurnos($segundaInscripcion, $validados);

        $pendiente->update([
            'plan_dual_pendiente' => false,
            'frecuencia_total_dual' => $frecuenciaTotal,
            'id_act_pac_dual' => $segundaInscripcion->id,
            'total_a_pagar' => $precioPlan,
        ]);

        return $segundaInscripcion->fresh(['turnos']);
    }

    private function crearInscripcion(
        array $validados,
        Carbon $ahora,
        bool $pagoCompletado,
        array $datosDual = []
    ): ActividadPaciente {
        if (!empty($validados['id_paciente'])) {
            $this->assertPacienteRegularPuedeInscribirse(
                (int) $validados['id_paciente'],
                (int) $validados['id_actividad']
            );
        }

        return ActividadPaciente::create(array_merge([
            'id_actividad' => $validados['id_actividad'],
            'id_paciente' => $validados['id_paciente'],
            'cant_sesiones' => $validados['cant_sesiones'],
            'es_fijo' => false,
            'total_a_pagar' => $validados['total_a_pagar'],
            'pago_completado' => $pagoCompletado,
            'fecha_emision_ord' => $validados['fecha_emision_ord'] ?? null,
            'plan_dual_pendiente' => false,
            'frecuencia_total_dual' => null,
            'id_act_pac_dual' => null,
        ], $datosDual));
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

    private function determinarTotal(array $validados): array
    {
        if (ModalidadRegistro::debeUsarPrecioMensual($validados)) {
            $validados['total_a_pagar'] = ActividadCombo::obtenerPrecioMensual(
                (int) $validados['id_actividad_combo']
            );
        } else {
            $validados['total_a_pagar'] = ActividadCombo::calcularTotalAPagar(
                (int) $validados['id_actividad'],
                (int) $validados['cant_sesiones'],
                exigirComboExacto: ModalidadRegistro::esConOrden($validados)
            );
        }

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

    private function assertPacienteRegularPuedeInscribirse(int $idPaciente, int $idActividad): void
    {
        $actividad = Actividad::find($idActividad);

        if (!$actividad?->esActividadGeneral()) {
            return;
        }

        $paciente = Paciente::findOrFail($idPaciente);
        $disponibles = $this->planDualService->actividadesGeneralesDisponibles($paciente)->pluck('id');

        if (!$disponibles->contains($idActividad)) {
            throw new Exception(self::MENSAJE_INSCRIPCION_NO_DISPONIBLE);
        }
    }
}
