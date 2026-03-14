<?php

namespace App\Models;

use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

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

    protected function nombreActividad(): Attribute
    {
        return Attribute::make(
            get: fn() => $this->actividad->nombre
        );
    }

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

    function precioVigente(): HasOne
    {
        return $this->hasOne(Precio::class, 'id_actividad_combo')
            ->where('fecha_desde', '<=', now())
            ->latest('fecha_desde');
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

    public static function calcularTotalAPagar(int $idActividad, int $cantidadSesiones, ?string $nombreActividad = null): float
    {
        $vinculos = self::activo()
            ->deLaActividad($idActividad)
            ->conCombo()
            ->where('combos.es_mensual', false)
            ->whereHas('precioVigente')
            ->with('precioVigente')
            ->select('actividades_combos.id', 'actividades_combos.id_actividad', 'combos.cantidad_sesiones as cantidad')
            ->get()
            ->sortByDesc('cantidad');

        $tieneSesionIndividual = $vinculos->contains('cantidad', 1);
        if (!$tieneSesionIndividual) {
            $identificador = $nombreActividad ?? "con ID: $idActividad";
            throw new Exception('La actividad ' . $identificador . ' no tiene un precio definido para su sesión individual.');
        }

        $total = 0;
        $restante = $cantidadSesiones;

        foreach ($vinculos as $vinculo) {
            if ($restante >= $vinculo->cantidad) {
                $cantidadCombos = (int) floor($restante / $vinculo->cantidad);
                $total += $cantidadCombos * (float) $vinculo->precioVigente->valor;
                $restante -= $cantidadCombos * $vinculo->cantidad;
            }

            if ($restante === 0) break;
        }

        return $total;
    }
}
