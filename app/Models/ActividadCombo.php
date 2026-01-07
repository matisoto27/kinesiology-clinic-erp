<?php

namespace App\Models;

use Exception;
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

    function precioActual(): float
    {
        $precio = optional(
            $this->precios()
                ->where('fecha_desde', '<=', now())
                ->orderByDesc('fecha_desde')
                ->first()
        );

        return $precio->valor ?? 0;
    }

    public static function calcularTotalAPagar(int $idActividad, int $cantidadSesiones): float
    {
        $vinculoEspecifico = self::where('id_actividad', $idActividad)
            ->whereHas('combo', function($consulta) use ($cantidadSesiones) {
                $consulta->where('cantidad_sesiones', $cantidadSesiones)
                    ->where('es_mensual', false);
            })
            ->first();

        if ($vinculoEspecifico) {
            return $vinculoEspecifico->precioActual();
        }

        $vinculoIndividual = self::where('id_actividad', $idActividad)
            ->whereHas('combo', function($consulta) {
                $consulta->where('cantidad_sesiones', 1);
            })
            ->first();

        if (!$vinculoIndividual) {
            throw new Exception('La actividad no tiene un precio definido actualmente.');
        }

        return $vinculoIndividual->precioActual() * $cantidadSesiones;
    }
}
