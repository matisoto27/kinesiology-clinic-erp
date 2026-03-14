<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Combo extends Model
{
    public const CLASE_PRUEBA = 10;

    protected $table = 'combos';

    public $timestamps = false;

    protected $fillable = [
        'nombre',
        'cantidad_sesiones',
        'es_mensual'
    ];

    protected $casts = [
        'es_mensual' => 'boolean'
    ];
}
