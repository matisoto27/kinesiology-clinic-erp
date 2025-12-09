<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ComboActividad extends Model
{
    protected $table = 'combos';

    public $timestamps = false;

    protected $fillable = [
        'id_actividad',
        'nro_combo',
        'nombre',
        'cantidad_sesiones',
        'activo'
    ];

    protected $casts = [
        'activo' => 'boolean'
    ];

    function precios()
    {
        return $this->hasMany(PrecioCombo::class, 'id_combo');
    }

    function precioActual()
    {
        return optional(
            $this->precios()
                ->where('fecha_desde', '<=', now())
                ->orderByDesc('fecha_desde')
                ->first()
        )->valor ?? 0;
    }
}
