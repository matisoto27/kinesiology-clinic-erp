<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

class Actividad extends Model
{
    use SoftDeletes;

    protected $table = 'actividades';

    public $timestamps = false;

    protected $fillable = [
        'id_tipo_actividad',
        'nro_actividad',
        'nombre',
        'activo'
    ];

    protected $casts = [
        'activo' => 'boolean'
    ];

    public function tipo(): BelongsTo
    {
        return $this->belongsTo(TipoActividad::class, 'id_tipo_actividad');
    }

    public function scopeGeneral(Builder $consulta): void
    {
        $idTipoGeneral = TipoActividad::where('descripcion', 'General')->value('id');
        if ($idTipoGeneral) {
            $consulta->where('id_tipo_actividad', $idTipoGeneral);
        }
    }

    public function scopeActivo(Builder $consulta): void
    {
        $consulta->where('activo', 1);
    }

    public static function obtenerActividadesGenerales(): Collection
    {
        return self::general()->activo()->get();
    }

    public function combos()
    {
        return $this->hasMany(ComboActividad::class, 'id_actividad');
    }

    public function horarios()
    {
        return $this->belongsToMany(Horario::class, 'horarios_actividades', 'id_actividad', 'id_horario');
    }

    public function obtenerHorasDeInicio() {
        return $this->horarios()
                    ->pluck('hora_inicio')
                    ->sort()
                    ->values();
    }
}
