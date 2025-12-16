<?php

namespace App\Models;

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
}
