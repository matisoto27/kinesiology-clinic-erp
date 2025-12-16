<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class Actividad extends Model
{
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

    public function actividadCombos(): HasMany
    {
        return $this->hasMany(ActividadCombo::class, 'id_actividad');
    }

    public function combos(): BelongsToMany
    {
        return $this->belongsToMany(Combo::class, 'actividades_combos', 'id_actividad', 'id_combo');
    }

    public function horarios(): BelongsToMany
    {
        return $this->belongsToMany(Horario::class, 'horarios_actividades', 'id_actividad', 'id_horario');
    }

    public function scopePorTipoDescripcion(Builder $consulta, string $descripcion): void
    {
        $consulta->whereHas('tipo', function (Builder $subconsulta) use ($descripcion) {
            $subconsulta->where('descripcion', $descripcion);
        });
    }

    public static function obtenerActividadesGenerales(): Collection
    {
        return self::porTipoDescripcion('General')->get();
    }

    public static function obtenerActividadesKinesiologia(): Collection
    {
        return self::porTipoDescripcion('Kinesiología')->get();
    }

    public function obtenerHorasDeInicio() {
        return $this->horarios()
            ->pluck('hora_inicio')
            ->sort()
            ->values();
    }
}
