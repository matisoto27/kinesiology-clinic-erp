<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class Turno extends Model
{
    protected $table = 'turnos';

    public $timestamps = false;

    protected $fillable = [
        'id_act_pac',
        'nro_turno',
        'fecha_hora',
        'asiste'
    ];

    protected $casts = [
        'fecha_hora' => 'datetime',
        'asiste' => 'boolean'
    ];

    public function actividadPaciente()
    {
        return $this->belongsTo(ActividadPaciente::class, 'id_act_pac');
    }

    public function notas()
    {
        return $this->hasMany(NotaTurno::class, 'id_turno');
    }
    
    public static function estaOcupado(int $idActividad, string $fechaHora): bool
    {
        return self::where('fecha_hora', $fechaHora)
            ->whereHas('actividadPaciente', function ($query) use ($idActividad) {
                $query->where('id_actividad', $idActividad);
            })
            ->exists();
    }

    public static function yaPaso(string $fechaHora): bool
    {
        $fechaHoraTurno = Carbon::parse($fechaHora);
        return $fechaHoraTurno->isPast();
    }

    public static function buscarReemplazo(int $idActividad, Carbon $fechaHoraInicial, array $turnosAsignados): ?string
    {
        $actividad = Actividad::find($idActividad);
        if (!$actividad) return null;

        $horasDeInicio = $actividad->obtenerHorasDeInicio();

        $lunes = $fechaHoraInicial->copy()->startOfWeek(Carbon::MONDAY);
        $viernes = $lunes->copy()->addDays(4);

        $fechaBase = $lunes->copy();
        $ahora = Carbon::now();
        $turnosPosibles = collect();

        while ($fechaBase->lessThanOrEqualTo($viernes)) {

            foreach ($horasDeInicio as $horaStr) {

                $fechaHoraTurno = $fechaBase->copy()->setTimeFromTimeString($horaStr);
                if ($fechaHoraTurno->greaterThan($ahora)) {
                    $turnosPosibles->push($fechaHoraTurno->toDateTimeString());
                }
            }

            $fechaBase->addDay();
        }

        $fechasAsignadas = collect($turnosAsignados)->map(function ($fechaHoraStr) {
            return Carbon::parse($fechaHoraStr)->toDateString();
        })->unique()->toArray();

        $turnosDisponibles = [];

        foreach ($turnosPosibles as $fechaHoraStr) {

            $fechaStr = Carbon::parse($fechaHoraStr)->toDateString();

            if (self::estaOcupado($idActividad, $fechaHoraStr) || in_array($fechaStr, $fechasAsignadas))
                continue;

            $turnosDisponibles[] = $fechaHoraStr;
        }

        $turnoIdealStr = $fechaHoraInicial->toDateTimeString();

        $posteriores = array_filter($turnosDisponibles, fn($t) => $t > $turnoIdealStr);
        $anteriores = array_filter($turnosDisponibles, fn($t) => $t < $turnoIdealStr);

        

        // Gimnasio
        if ($idActividad === 1) {

            if (!empty($posteriores)) {

                $resultadoEnPunto = self::buscarTurnoEnPunto($posteriores);

                if ($resultadoEnPunto) {
                    return $resultadoEnPunto;
                }
            }

            if (!empty($anteriores)) {

                $resultadoEnPunto = self::buscarTurnoEnPunto($anteriores);

                if ($resultadoEnPunto) {
                    return $resultadoEnPunto;
                }
            }
        }

        if (!empty($posteriores)) {
            return array_values($posteriores)[0];
        }

        if (!empty($anteriores)) {
            // Ordenar los anteriores en orden descendente y devolver el más cercano
            rsort($anteriores);
            return $anteriores[0];
        }

        return null;
    }

    private static function buscarTurnoEnPunto($turnos) {
        return collect($turnos)->first(function ($turno) {
            return Carbon::parse($turno)->minute === 0;
        });
    }
}
