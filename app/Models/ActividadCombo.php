<?php

namespace App\Models;

use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ActividadCombo extends Model
{
    protected $table = 'actividades_combos';

    public $timestamps = false;

    protected $fillable = [
        'id_actividad',
        'id_combo',
        'activo'
    ];

    protected $casts = [
        'activo' => 'boolean'
    ];

    function actividad(): BelongsTo
    {
        return $this->belongsTo(Actividad::class, 'id_actividad');
    }

    function combo(): BelongsTo
    {
        return $this->belongsTo(Combo::class, 'id_combo');
    }

    function precios(): HasMany
    {
        return $this->hasMany(Precio::class, 'id_actividad_combo');
    }

    public function scopeDeLaActividad(Builder $consulta, int $idActividad): Builder
    {
        return $consulta->where('actividades_combos.id_actividad', $idActividad);
    }

    public function scopeActivo(Builder $consulta): Builder
    {
        return $consulta->where('actividades_combos.activo', true);
    }

    public function scopeConCombo(Builder $consulta): Builder
    {
        return $consulta->join('combos', 'actividades_combos.id_combo', '=', 'combos.id');
    }

    function precioActual(): float
    {
        if ($this->relationLoaded('precios')) {
            $precio = $this->precios
                ->where('fecha_desde', '<=', now()->toDateTimeString())
                ->sortByDesc('fecha_desde')
                ->first();
        } else {
            $precio = $this->precios()
                ->where('fecha_desde', '<=', now())
                ->orderByDesc('fecha_desde')
                ->first();
        }

        return (float) ($precio->valor ?? 0);
    }

    public static function calcularTotalAPagar(int $idActividad, int $cantidadSesiones): float
    {
        $vinculos = self::activo()
            ->deLaActividad($idActividad)
            ->conCombo()
            ->with('precios')
            ->where('combos.es_mensual', false)
            ->select('actividades_combos.id', 'actividades_combos.id_actividad', 'combos.cantidad_sesiones as cantidad')
            ->get()
            ->sortByDesc('cantidad');

        $total = 0;
        $restante = $cantidadSesiones;

        foreach ($vinculos as $vinculo) {
            if ($restante >= $vinculo->cantidad) {
                $cantidadCombos = floor($restante / $vinculo->cantidad);
                $total += $cantidadCombos * $vinculo->precioActual();
                $restante -= $cantidadCombos * $vinculo->cantidad;
            }

            if ($restante === 0) break;
        }

        if ($restante > 0) {
            throw new Exception('La actividad no tiene un precio definido actualmente.');
        }

        return $total;
    }
}
