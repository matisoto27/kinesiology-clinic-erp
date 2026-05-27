<?php

namespace App\Services;

use App\Models\Actividad;
use App\Models\ActividadCombo;
use App\Models\ActividadPaciente;
use App\Models\Paciente;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class ActividadPacienteService
{
    public function __construct(
        private TurnoService $turnoService
    ) {}

    public function registrar(array $validados): ActividadPaciente
    {
        try {
            return DB::transaction(function () use ($validados) {
                $esConOrden = $this->esConOrden($validados);
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
                    ? $this->prepararTurnosAutomaticos($ahora, $validados)
                    : $this->turnoService->prepararTurnosManuales($validados['turnos']);

                $actividadPaciente->turnos()->createMany($turnosParaInsertar);

                return $actividadPaciente;
            });
        } catch (Throwable $th) {
            Log::error('[ActividadPacienteService@registrar] Error al registrar la inscripción del paciente', [
                'excepción' => $th->getMessage(),
            ]);

            if ($th instanceof QueryException && ($th->errorInfo[1] ?? null) == 1062) {
                throw new Exception('El paciente ya ha realizado una inscripción a esta actividad en la fecha de hoy.', previous: $th);
            }

            throw $th;
        }
    }

    private function esConOrden(array $validados): bool
    {
        return array_key_exists('sesiones_cubiertas', $validados)
            || array_key_exists('mes', $validados)
            || array_key_exists('dia', $validados);
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
        if (!$this->esConOrden($validados) && array_key_exists('id_actividad_combo', $validados)) {
            $validados['total_a_pagar'] = ActividadCombo::obtenerPrecioMensual(
                (int) $validados['id_actividad_combo']
            );
        } else {
            $validados['total_a_pagar'] = ActividadCombo::calcularTotalAPagar(
                (int) $validados['id_actividad'],
                (int) $validados['cant_sesiones'],
                exigirComboExacto: $this->esConOrden($validados)
            );
        }

        return $validados;
    }

    private function prepararTurnosAutomaticos(Carbon $ahora, array $validados): array
    {
        $dias = [
            'Lunes'     => 1,
            'Martes'    => 2,
            'Miércoles' => 3,
            'Jueves'    => 4,
            'Viernes'   => 5,
            'Sábado'    => 6,
            'Domingo'   => 7
        ];

        $cantidadSesiones = (int) ($validados['sesiones_cubiertas'] ?? $validados['cant_sesiones']);
        $frecuenciaSemanal = (int) $validados['frecuencia_semanal'];
        $semanasNecesarias = (int) ceil($cantidadSesiones / $frecuenciaSemanal);

        $fechaBase = $validados['desde_actual']
            ? $ahora->copy()->startOfWeek() // Lunes de la semana actual
            : $ahora->copy()->addWeek()->startOfWeek(); // Lunes de la semana siguiente

        $turnosSolicitados = [];

        $turnosPreparados = collect($validados['turnos'])->map(function ($turno) use ($dias) {
            return [
                'dia' => $dias[$turno['dia_semana']],
                'hora' => str_replace('hs', '', $turno['hora_inicio'])
            ];
        });

        for ($semana = 0; $semana < $semanasNecesarias; $semana++) {
            $fechaSemana = $fechaBase->copy()->addWeeks($semana);

            foreach ($turnosPreparados as $turno) {
                if (count($turnosSolicitados) >= $cantidadSesiones) {
                    break 2;
                }

                $turnosSolicitados[] = $fechaSemana->copy()
                    ->dayOfWeek($turno['dia'])
                    ->setTimeFromTimeString($turno['hora']);
            }
        }

        return $this->turnoService->prepararFechas(
            Actividad::findOrFail($validados['id_actividad']),
            $validados['id_paciente'],
            $turnosSolicitados,
            $semanasNecesarias
        );
    }
}
