<?php

namespace App\Http\Controllers;

use App\Models\Actividad;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class ActividadController extends Controller
{
    public function index(Request $request)
    {
        $idTipoActividad = $request->query('id_tipo_actividad');

        $consulta = Actividad::query();

        if ($idTipoActividad) {
            $consulta->porTipo($idTipoActividad);
        }

        $actividades = $consulta->get();

        return response()->json($actividades);
    }

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
                    'cantidad_sesiones' => $actividadCombo->combo->cantidad_sesiones,
                    'precio_actual' => $actividadCombo->precioActual()
                ];
            });

            return response()->json($combos);

        } catch (ModelNotFoundException $ex) {

            Log::info('[ActividadController@obtenerCombos] Actividad no encontrada', [
                'id_actividad' => $id,
                'excepcion' => $ex->getMessage()
            ]);

            return response()->json([
                'error' => 'Actividad no encontrada.'
            ], 404);

        } catch (Throwable $ex) {

            Log::error('[ActividadController@obtenerCombos] Error al obtener los combos de la actividad', [
                'id_actividad' => $id,
                'excepcion' => $ex->getMessage()
            ]);

            return response()->json([
                'error' => 'Se ha producido un error inesperado al obtener los combos de la actividad.'
            ], 500);
        }
    }

    public function obtenerTurnosDisponibles(int $id, Request $request)
    {
        $actividad = Actividad::find($id);
        $ahora = Carbon::now();

        // REGLA DE NEGOCIO: Mínimo 1 hora antes del último turno para incluir la semana actual.
        $limite = $ahora->copy()->startOfWeek()->addDays(4);
        $actividad->esActividadGeneral() ? $limite->setTime(18, 00, 0) : $limite->setTime(18, 30, 0);
        $incluirSemanaActual = $ahora->lessThanOrEqualTo($limite);

        $validados = $request->validate([
            'id_paciente' => ['required', 'integer', 'exists:pacientes,id'],
            'cantidad_semanas' => ['required', 'integer', 'min:1']
        ]);

        try {
            $cantidadSemanas = (int) $validados['cantidad_semanas'];

            $fechaComienzo = $incluirSemanaActual
                ? $ahora->copy()->startOfWeek()
                : $ahora->copy()->next(Carbon::MONDAY);

            $semanasAdicionales = $incluirSemanaActual
                ? $cantidadSemanas
                : $cantidadSemanas - 1;

            $fechaFin = $fechaComienzo->copy()->addWeeks($semanasAdicionales)->endOfWeek(Carbon::FRIDAY);

            $turnosDisponibles = Actividad::findOrFail($id)->turnosDisponibles($validados['id_paciente'], $fechaComienzo, $fechaFin);

            return response()->json($turnosDisponibles);

        } catch (ModelNotFoundException $ex) {

            Log::info('[ActividadController@obtenerTurnosDisponibles] Actividad no encontrada', [
                'id_actividad' => $id,
                'excepcion' => $ex->getMessage()
            ]);

            return response()->json([
                'error' => 'Actividad no encontrada.'
            ], 404);

        } catch (Throwable $ex) {

            Log::error('[ActividadController@obtenerTurnosDisponibles] Error al obtener los turnos disponibles', [
                'id_actividad' => $id,
                'excepcion' => $ex->getMessage()
            ]);

            return response()->json([
                'error' => 'Se ha producido un error inesperado al obtener los turnos disponibles.'
            ], 500);
        }
    }
}
