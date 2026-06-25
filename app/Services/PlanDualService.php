<?php

namespace App\Services;

use App\Models\Actividad;
use App\Models\ActividadCombo;
use App\Models\ActividadPaciente;
use Carbon\Carbon;
use Exception;

class PlanDualService
{
    public const MENSAJE_PRECIOS_NO_COINCIDEN = 'El precio del combo mensual x%s de Gimnasio y Pilates no coincide. Corregí los precios antes de registrar el plan dual.';

    public const MENSAJE_DUAL_PENDIENTE_EXISTENTE = 'El paciente ya tiene un plan dual pendiente de completar.';

    public const MENSAJE_COMPLETAR_PLAN_DUAL = 'El paciente tiene un plan dual pendiente. Debe completarlo marcando la opción Plan dual.';

    private const DIAS = [
        'Lunes' => Carbon::MONDAY,
        'Martes' => Carbon::TUESDAY,
        'Miércoles' => Carbon::WEDNESDAY,
        'Jueves' => Carbon::THURSDAY,
        'Viernes' => Carbon::FRIDAY,
    ];

    private const NOMBRES_DIA = [
        Carbon::MONDAY => 'Lunes',
        Carbon::TUESDAY => 'Martes',
        Carbon::WEDNESDAY => 'Miércoles',
        Carbon::THURSDAY => 'Jueves',
        Carbon::FRIDAY => 'Viernes',
    ];

    public function obtenerPendiente(int $idPaciente): ?array
    {
        $primeraInscripcion = $this->obtenerDualPendiente($idPaciente);

        if (!$primeraInscripcion) {
            return null;
        }

        $primeraInscripcion->loadMissing('turnos');

        $turnos = $primeraInscripcion->turnos
            ->sortBy('fecha_hora')
            ->values();
        $idActividadPrimera = (int) $primeraInscripcion->id_actividad;
        $frecuenciaPrimera = $primeraInscripcion->frecuenciaSemanal();
        $idActividadFaltante = $this->idActividadFaltante($idActividadPrimera);
        $frecuenciasSegunda = $this->frecuenciasPermitidasSegundaInscripcion($primeraInscripcion);

        return [
            'primera_inscripcion' => [
                'id' => $primeraInscripcion->id,
                'id_actividad' => $idActividadPrimera,
                'frecuencia' => $frecuenciaPrimera,
                'fecha_ancla' => $turnos->first()?->fecha_hora->toDateString(),
                'dias_semana' => $this->inferirDiasSemana($turnos),
                'turnos' => $turnos
                    ->map(fn ($turno) => $turno->fecha_hora->format('Y-m-d H:i:s'))
                    ->all(),
            ],
            'segunda_inscripcion' => [
                'actividad' => [
                    'id' => $idActividadFaltante,
                    'nombre' => Actividad::where('id', $idActividadFaltante)->value('nombre')
                ],
                'frecuencias_permitidas' => $frecuenciasSegunda
            ],
            'frecuencia_total_min' => $frecuenciasSegunda === [] ? null : $frecuenciaPrimera + min($frecuenciasSegunda),
            'frecuencia_total_max' => $frecuenciasSegunda === [] ? null : $frecuenciaPrimera + max($frecuenciasSegunda),
        ];
    }

    public function obtenerDualPendiente(int $idPaciente): ?ActividadPaciente
    {
        return ActividadPaciente::query()
            ->where('id_paciente', $idPaciente)
            ->where('plan_dual_pendiente', true)
            ->first();
    }

    public function idActividadFaltante(int $idActividadRegistrada): int
    {
        return $idActividadRegistrada === Actividad::GIMNASIO
            ? Actividad::PILATES
            : Actividad::GIMNASIO;
    }

    public function frecuenciasPermitidasSegundaInscripcion(ActividadPaciente $primeraInscripcion): array
    {
        $maxima = 5 - $primeraInscripcion->frecuenciaSemanal();

        return $maxima < 1 ? [] : range(1, $maxima);
    }

    public function previewPrecioSegundaVisita(int $frecuenciaPrimera, int $frecuenciaSegunda): array
    {
        $frecuenciaTotal = $frecuenciaPrimera + $frecuenciaSegunda;
        $precioPlan = $this->obtenerPrecioPlan($frecuenciaTotal);
        $totales = $this->calcularTotalesProporcionales($precioPlan, $frecuenciaPrimera, $frecuenciaSegunda);

        return [
            'frecuencia_total' => $frecuenciaTotal,
            'precio_plan' => $precioPlan,
            'total_primera' => $totales['total_primera'],
            'total_segunda' => $totales['total_segunda'],
        ];
    }

    public function obtenerPrecioPlan(int $frecuenciaTotal): float
    {
        $this->validarPreciosCoincidentes($frecuenciaTotal);

        return ActividadCombo::obtenerPrecioMensualPorFrecuencia(Actividad::GIMNASIO, $frecuenciaTotal);
    }

    public function validarPreciosCoincidentes(int $frecuenciaTotal): void
    {
        $precioGym = ActividadCombo::obtenerPrecioMensualPorFrecuencia(Actividad::GIMNASIO, $frecuenciaTotal);
        $precioPilates = ActividadCombo::obtenerPrecioMensualPorFrecuencia(Actividad::PILATES, $frecuenciaTotal);

        if (round($precioGym, 2) !== round($precioPilates, 2)) {
            throw new Exception(sprintf(self::MENSAJE_PRECIOS_NO_COINCIDEN, $frecuenciaTotal));
        }
    }

    public function calcularTotalesProporcionales(float $precioPlan, int $freqPrimera, int $freqSegunda): array
    {
        $frecuenciaTotal = $freqPrimera + $freqSegunda;

        if ($frecuenciaTotal < 1 || $frecuenciaTotal > 5) {
            throw new Exception('La frecuencia total del plan no tiene un valor válido.');
        }

        $totalPrimera = round($precioPlan * ($freqPrimera / $frecuenciaTotal), 2);

        return [
            'frecuencia_total' => $frecuenciaTotal,
            'total_primera' => $totalPrimera,
            'total_segunda' => round($precioPlan - $totalPrimera, 2),
        ];
    }

    public function validarSegundaInscripcion(ActividadPaciente $pendiente, array $validados): void
    {
        $idActividadFaltante = $this->idActividadFaltante((int) $pendiente->id_actividad);

        if ((int) $validados['id_actividad'] !== $idActividadFaltante) {
            throw new Exception('Debe registrar la actividad faltante del plan dual pendiente.');
        }

        $frecuencia = (int) $validados['frecuencia_semanal'];
        if (!in_array($frecuencia, $this->frecuenciasPermitidasSegundaInscripcion($pendiente), true)) {
            throw new Exception('La frecuencia seleccionada no es válida para completar el plan dual.');
        }

        if (!empty($validados['autogenerados'])) {
            $this->validarCalendarioAutomaticoSegunda($pendiente, $validados);
            return;
        }

        $this->validarTurnosManualesSegunda($pendiente, $validados);
    }

    private function validarCalendarioAutomaticoSegunda(ActividadPaciente $pendiente, array $validados): void
    {
        $diasPrimera = $this->diasSemanaPrimeraInscripcion($pendiente);
        $diasSegunda = collect($validados['turnos'])->pluck('dia_semana')->unique()->values()->all();
        $frecuenciaSegunda = (int) $validados['frecuencia_semanal'];

        if (count($diasSegunda) !== $frecuenciaSegunda) {
            throw new Exception('La cantidad de días elegidos no coincide con la frecuencia semanal de la segunda inscripción.');
        }

        if (array_intersect($diasPrimera, $diasSegunda) !== []) {
            throw new Exception('Los días de la segunda inscripción no pueden repetir días de la primera inscripción dual.');
        }

        $fechaAnclaSegunda = $validados['fecha_ancla'] ?? null;
        if (!$fechaAnclaSegunda || !$this->esFechaInicioSegundaValida($pendiente, $fechaAnclaSegunda, $diasSegunda)) {
            throw new Exception('La fecha de inicio de la segunda inscripción no respeta el patrón del plan dual.');
        }
    }

    private function esFechaInicioSegundaValida(ActividadPaciente $pendiente, string $fechaCandidata, array $diasSegunda): bool
    {
        $fechaAncla = $this->fechaAnclaPrimeraInscripcion($pendiente);
        if (!$fechaAncla) {
            return false;
        }

        $diasPrimera = $this->diasSemanaPrimeraInscripcion($pendiente);
        $diasCombinados = array_values(array_unique([...$diasPrimera, ...$diasSegunda]));

        $inicioSecuencia = min($fechaAncla, $fechaCandidata);
        $finHorizonte = $this->sumarDias($inicioSecuencia, 28);

        $todasLasFechas = [];
        $cursor = $inicioSecuencia;
        while ($cursor <= $finHorizonte) {
            $todasLasFechas[] = $cursor;
            $cursor = $this->sumarDias($cursor, 1);
        }

        $ocurrencias = array_values(array_filter(
            $todasLasFechas,
            fn (string $iso) => in_array($this->nombreDiaSemana(Carbon::parse($iso)), $diasCombinados, true)
        ));

        $cubiertas = array_map(function (string $iso) use ($diasPrimera, $diasSegunda, $fechaAncla, $fechaCandidata) {
            $dia = $this->nombreDiaSemana(Carbon::parse($iso));
            $porPrimera = in_array($dia, $diasPrimera, true) && $iso >= $fechaAncla;
            $porSegunda = in_array($dia, $diasSegunda, true) && $iso >= $fechaCandidata;

            return $porPrimera || $porSegunda;
        }, $ocurrencias);

        $primerCubierta = array_search(true, $cubiertas, true);
        if ($primerCubierta === false) {
            return false;
        }

        $ultimaCubierta = count($cubiertas) - 1;
        while ($ultimaCubierta >= 0 && !$cubiertas[$ultimaCubierta]) {
            $ultimaCubierta--;
        }

        for ($i = $primerCubierta; $i <= $ultimaCubierta; $i++) {
            if (!$cubiertas[$i]) {
                return false;
            }
        }

        return true;
    }

    private function sumarDias(string $fecha, int $dias): string
    {
        return Carbon::parse($fecha)->addDays($dias)->toDateString();
    }

    private function fechaAnclaPrimeraInscripcion(ActividadPaciente $pendiente): ?string
    {
        return $pendiente->turnos()
            ->orderBy('fecha_hora')
            ->first()
            ?->fecha_hora
            ->toDateString();
    }

    private function nombreDiaSemana(Carbon $fecha): ?string
    {
        return self::NOMBRES_DIA[$fecha->dayOfWeekIso] ?? null;
    }

    private function diasSemanaPrimeraInscripcion(ActividadPaciente $pendiente): array
    {
        return $this->inferirDiasSemana(
            $pendiente->turnos()
                ->select('fecha_hora')
                ->orderBy('fecha_hora')
                ->get()
        );
    }

    private function inferirDiasSemana($turnos): array
    {
        return $turnos
            ->pluck('fecha_hora')
            ->map(fn ($fecha) => $fecha->dayOfWeekIso)
            ->unique()
            ->sort()
            ->map(fn ($dia) => self::NOMBRES_DIA[$dia])
            ->values()
            ->all();
    }

    private function validarTurnosManualesSegunda(ActividadPaciente $pendiente, array $validados): void
    {
        $ocupadas = $this->fechasPrimeraInscripcion($pendiente);
        $recibidas = collect($validados['turnos'])
            ->map(fn (string $fechaHora) => Carbon::parse($fechaHora)->toDateString())
            ->all();

        if (array_intersect($ocupadas, $recibidas) !== []) {
            throw new Exception('Los turnos de la segunda inscripción no pueden ocupar fechas ya usadas por la primera inscripción dual.');
        }
    }

    private function fechasPrimeraInscripcion(ActividadPaciente $pendiente): array
    {
        return $pendiente->turnos()
            ->orderBy('fecha_hora')
            ->get()
            ->map(fn ($turno) => $turno->fecha_hora->toDateString())
            ->unique()
            ->values()
            ->all();
    }
}
