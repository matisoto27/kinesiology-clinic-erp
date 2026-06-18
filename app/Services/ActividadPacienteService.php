<?php

namespace App\Services;

use App\Models\Actividad;
use App\Models\ActividadCombo;
use App\Models\ActividadPaciente;
use App\Models\Paciente;
use App\Support\Registros\DeteccionRegistroDuplicado;
use App\Support\Registros\ModalidadRegistro;
use App\Support\Turnos\ExpansorTurnosPatron;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class ActividadPacienteService
{
    public function __construct(
        private TurnoService $turnoService,
        private ExpansorTurnosPatron $expansorTurnosPatron
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

                $validados = $this->determinarTotal($validados);

                $datosInscripcion = [
                    'id_actividad' => $validados['id_actividad'],
                    'id_paciente' => $validados['id_paciente'],
                    'fecha_comienzo' => $ahora,
                    'cant_sesiones' => $validados['cant_sesiones'],
                    'es_fijo' => false,
                    'total_a_pagar' => $validados['total_a_pagar'],
                    'pago_completado' => $esConOrden,
                    'fecha_emision_ord' => $validados['fecha_emision_ord'] ?? null,
                ];

                $actividadPaciente = ActividadPaciente::create($datosInscripcion);

                $turnosParaInsertar = $validados['autogenerados']
                    ? $this->prepararTurnosAutomaticos($validados)
                    : $this->turnoService->prepararTurnosManuales($validados['turnos']);

                $actividadPaciente->turnos()->createMany($turnosParaInsertar);

                return $actividadPaciente;
            });
        } catch (Throwable $th) {
            Log::error('[ActividadPacienteService@registrar] Error al registrar la inscripción del paciente', [
                'excepción' => $th->getMessage(),
            ]);

            if ($th instanceof QueryException && DeteccionRegistroDuplicado::esDuplicado($th)) {
                throw new Exception(DeteccionRegistroDuplicado::MENSAJE, previous: $th);
            }

            throw $th;
        }
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
}
