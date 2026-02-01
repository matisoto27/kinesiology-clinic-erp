<?php

namespace App\Models;

use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;

class Actividad extends Model
{
    const TIPO_GENERAL = 1;
    const TIPO_KINESIOLOGIA = 2;

    protected $table = 'actividades';

    public $timestamps = false;

    protected $fillable = [
        'nombre',
        'id_tipo_actividad'
    ];

    public function tipo(): BelongsTo
    {
        return $this->belongsTo(TipoActividad::class, 'id_tipo_actividad');
    }

    public function combos(): BelongsToMany
    {
        return $this->belongsToMany(Combo::class, 'actividades_combos', 'id_actividad', 'id_combo')
            ->withPivot('activo')
            ->using(ActividadCombo::class);
    }

    public function horarios(): BelongsToMany
    {
        return $this->belongsToMany(Horario::class, 'horarios_actividades', 'id_actividad', 'id_horario');
    }

    public function esActividadGeneral(): bool
    {
        return (int) $this->id_tipo_actividad === self::TIPO_GENERAL;
    }

    public function scopePorTipo(Builder $consulta, int $idTipoActividad): Builder
    {
        return $consulta->where('id_tipo_actividad', $idTipoActividad);
    }

    public static function obtenerActividadesGenerales(): Collection
    {
        return self::porTipo(self::TIPO_GENERAL)->get();
    }

    public static function obtenerActividadesKinesiologia(): Collection
    {
        return self::porTipo(self::TIPO_KINESIOLOGIA)->get();
    }

    public function obtenerHorasDeInicio() {
        return $this->horarios()->orderBy('hora_inicio')->pluck('hora_inicio');
    }

    public function turnosDisponibles(int $idPaciente, Carbon $comienzo, Carbon $fin): array
    {
        if ($this->esActividadGeneral()) {

            $maximoTurnos = config('app.max_turnos_generales');

            $consulta = Turno::conActPac()
                ->deLaActividad($this->id)
                ->select('fecha_hora')
                ->entreFechas($comienzo, $fin)
                ->groupBy('fecha_hora')
                ->cantidadMayorIgualQue($maximoTurnos);

        } else {

            $maximoTurnos = config('app.max_turnos_kinesiologia');

            $consulta = Turno::conActPac()
                ->conActividad()
                ->deTipo(self::TIPO_KINESIOLOGIA)
                ->select('fecha_hora')
                ->entreFechas($comienzo, $fin)
                ->groupBy('fecha_hora');

            if ($this->nombre === 'Kinesiología') {
                $consulta->havingRaw("SUM(CASE WHEN actividades.nombre != 'Kinesiología' THEN 1 ELSE 0 END) > 0 OR COUNT(*) >= ?", [$maximoTurnos]);
            } else {
                $consulta->cantidadMayorIgualQue(1);
            }
        }

        $turnosSinCupo = $consulta->pluck('fecha_hora')
            ->map(fn($t) => $t->toDateTimeString())
            ->flip()
            ->toArray();

        $periodo = CarbonPeriod::create($comienzo, $fin);
        $horasInicio = $this->obtenerHorasDeInicio();

        $rangosOcupados = Turno::pacienteEntreFechas($idPaciente, $comienzo, $fin)
            ->map(fn ($t) => [
                'inicio' => $t->timestamp,
                'fin'    => $t->timestamp + 3600
            ])->toArray();

        $turnosDisponibles = [];

        foreach ($periodo as $fecha) {
            if ($fecha->isWeekEnd()) continue;

            $fechaStr = $fecha->format('Y-m-d');

            foreach ($horasInicio as $hora) {
                $turnoStr = $fechaStr . ' ' . $hora;

                if (isset($turnosSinCupo[$turnoStr])) continue;

                $turno = Carbon::parse($turnoStr);
                if ($turno->isPast()) continue;

                $inicio = $turno->timestamp;
                $fin = $inicio + 3600;

                $seSolapa = false;
                foreach ($rangosOcupados as $rango) {
                    if ($inicio < $rango['fin'] && $fin > $rango['inicio']) {
                        $seSolapa = true;
                        break;
                    }
                }

                if (!$seSolapa) {
                    $turnosDisponibles[] = $turnoStr;
                }
            }
        }

        return $turnosDisponibles;
    }

    public function buscarReemplazoTurno(Carbon $turnoOriginal, array $fechasDisponibles, array $fechasRestringidas): ?string
    {
        $comienzo = $turnoOriginal->copy()->startOfWeek();
        $fin = $comienzo->copy()->addDays(4);
        $periodo = CarbonPeriod::create($comienzo, $fin);
        $horasInicio = $this->obtenerHorasDeInicio();

        $turnosPosibles = [];

        foreach ($periodo as $fecha) {
            $fechaStr = $fecha->format('Y-m-d');

            foreach ($horasInicio as $hora) {
                $turnoStr = $fechaStr . ' ' . $hora;
                if (isset($fechasRestringidas[$turnoStr]) || !isset($fechasDisponibles[$turnoStr])) continue;

                $turno = Carbon::parse($turnoStr);
                if ($turno->isPast()) continue;

                $turnosPosibles[] = $turno;
            }
        }

        if (empty($turnosPosibles)) return null;

        // Pilates solo trabaja con horarios en punto (08:00:00, 09:00:00, etc).
        if ($this->nombre !== 'Pilates') {
            $turnosEnPunto = array_filter($turnosPosibles, fn($t) => $t->minute === 0);
            $turnosCandidatos = empty($turnosEnPunto) ? $turnosPosibles : $turnosEnPunto;
        } else {
            $turnosCandidatos = $turnosPosibles;
        }

        $turnosCandidatos = array_values($turnosCandidatos);
        $turnoOriginalTs = $turnoOriginal->timestamp;

        // De los turnos candidatos, elegir el más cercano a la fecha original
        usort($turnosCandidatos, fn($a, $b) =>
            abs($a->timestamp - $turnoOriginalTs) <=> abs($b->timestamp - $turnoOriginalTs)
        );

        return $turnosCandidatos[0]->toDateTimeString();
    }
}
