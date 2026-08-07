<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Combo extends Model
{
    public const CLASE_PRUEBA = 5;

    protected $table = 'combos';

    public $timestamps = false;

    protected $fillable = [
        'nombre',
        'cantidad_sesiones',
    ];
}
