<?php

namespace App\Models;

use Exception;
use Illuminate\Database\Eloquent\Model;

class PrecioMensual extends Model
{
    protected $table = 'precios_mensuales';

    protected $fillable = [
        'frecuencia_semanal',
        'fecha_desde',
        'valor',
    ];

    protected $casts = [
        'frecuencia_semanal' => 'integer',
        'fecha_desde' => 'date',
        'valor' => 'decimal:2',
    ];

    public static function obtenerVigentePorFrecuencia(int $frecuenciaSemanal): float
    {
        $precio = self::query()
            ->where('frecuencia_semanal', $frecuenciaSemanal)
            ->where('fecha_desde', '<=', now())
            ->orderByDesc('fecha_desde')
            ->first();

        if (!$precio) {
            throw new Exception("No existe un precio mensual vigente para frecuencia x{$frecuenciaSemanal}.");
        }

        return (float) $precio->valor;
    }
}
