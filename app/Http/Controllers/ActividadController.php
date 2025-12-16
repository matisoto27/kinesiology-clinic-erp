<?php

namespace App\Http\Controllers;

use App\Models\Actividad;
use App\Models\Turno;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Log;
use Throwable;

class ActividadController extends Controller
{
    public function obtenerCombos($id)
    {
        try {

            $actividad = Actividad::with([
                'actividadCombos' => function ($consulta) {

                    $consulta->where('activo', true);

                    if (request()->boolean('con_precio')) {
                        $consulta->whereHas('precios');
                    }

                    $consulta->with('combo');
                }
            ])->findOrFail($id);

            $combos = $actividad->actividadCombos->map(function ($actividadCombo) {
                return [
                    'id_actividad_combo' => $actividadCombo->id,
                    'nombre' => $actividadCombo->combo->nombre,
                    'cantidad_sesiones' => $actividadCombo->combo->cantidad_sesiones
                ];
            });

            return response()->json($combos);

        } catch (ModelNotFoundException $ex) {

            Log::info('[ActividadController@obtenerCombos] Actividad no encontrada', [
                'id_actividad' => $id,
                'excepción' => $ex->getMessage()
            ]);

            return response()->json([
                'error' => 'Actividad no encontrada.'
            ], 404);

        } catch (Throwable $ex) {

            Log::error('[ActividadController@obtenerCombos] Error al obtener los combos de la actividad', [
                'id_actividad' => $id,
                'excepción' => $ex->getMessage()
            ]);

            return response()->json([
                'error' => 'Se ha producido un error inesperado al obtener los combos de la actividad.'
            ], 500);
        }
    }

    public function obtenerTurnosDisponibles($id)
    {
        try {
            $actividad = Actividad::findOrFail($id);

            $horasInicio = $actividad->horarios()->pluck('hora_inicio');

            $turnosOcupados = Turno::whereHas('actividadPaciente', function($query) use ($id) {
                $query->where('id_actividad', $id);
            })
            ->where('fecha_hora', '>', now())
            ->select('fecha_hora')
            ->groupBy('fecha_hora')
            ->havingRaw('COUNT(*) >= 1')
            ->pluck('fecha_hora');

            $fechaComienzo = Carbon::today();
            $fechaFin = $fechaComienzo->copy()->addWeeks(4)->endOfWeek(Carbon::SUNDAY);
            $fechasPeriodo = CarbonPeriod::create($fechaComienzo, $fechaFin);

            $fechasHabiles = collect();
            foreach ($fechasPeriodo as $fecha) {
                if ($fecha->isWeekday()) {
                    $fechasHabiles->push($fecha->format('Y-m-d'));
                }
            }

            $turnosPosibles = collect();
            foreach ($fechasHabiles as $fecha) {
                foreach ($horasInicio as $hora) {

                    $fechaHora = Carbon::parse("$fecha $hora");

                    if ($fechaHora->isPast()) {
                        continue;
                    }

                    $turnosPosibles->push($fechaHora->format('Y-m-d H:i:s'));
                }
            }

            $turnosDisponibles = $turnosPosibles
                ->reject(fn($t) => in_array($t, $turnosOcupados->toArray()))
                ->values();

            return response()->json($turnosDisponibles);

        } catch (ModelNotFoundException $ex) {

            Log::info('[ActividadController@obtenerTurnosDisponibles] Actividad no encontrada', [
                'id_actividad' => $id,
                'excepción' => $ex->getMessage()
            ]);

            return response()->json([
                'error' => 'Actividad no encontrada.'
            ], 404);

        } catch (Throwable $ex) {

            Log::error('[ActividadController@obtenerTurnosDisponibles] Error al obtener los turnos disponibles', [
                'id_actividad' => $id,
                'excepción' => $ex->getMessage()
            ]);

            return response()->json([
                'error' => 'Se ha producido un error inesperado al obtener los turnos disponibles.'
            ], 500);
        }
    }
}
