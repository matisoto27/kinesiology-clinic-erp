<?php

namespace App\Http\Controllers;

use App\Models\Actividad;
use App\Models\Combo;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class ActividadController extends Controller
{
    public function inicio(Request $request)
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
            $actividad = Actividad::with(['actividadCombos' => function ($consulta) {
                $consulta->with(['combo', 'precioVigente'])
                    ->where('id_combo', '!=', Combo::CLASE_PRUEBA)
                    ->when(request()->boolean('con_precio'), fn($sc) => $sc->whereHas('precios'))
                    ->activo();
            }])->findOrFail($id);

            $combos = $actividad->actividadCombos->map(function ($actividadCombo) {
                return [
                    'id_actividad_combo' => $actividadCombo->id,
                    'nombre' => $actividadCombo->combo->nombre,
                    'cantidad_sesiones' => $actividadCombo->combo->cantidad_sesiones,
                    'precio_vigente' => (float) ($actividadCombo->precioVigente->valor ?? 0)
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
        $request->merge(['id_actividad' => $id]);

        $validados = $request->validate([
            'id_actividad' => ['required', 'integer', 'exists:actividades,id'],
            'id_paciente' => ['required', 'integer', 'exists:pacientes,id'],
            'fecha_comienzo' => ['required', 'date'],
            'fecha_fin' => ['required', 'date']
        ]);

        try {
            $fechaComienzo = Carbon::parse($validados['fecha_comienzo']);
            $fechaFin = Carbon::parse($validados['fecha_fin']);

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
