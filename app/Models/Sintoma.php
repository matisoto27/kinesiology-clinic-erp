<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Sintoma extends Model
{
    protected $table = 'sintomas';

    public $timestamps = false;

    protected $fillable = [
        'id_tipo',
        'nro_sintoma',
        'nombre',
        'activo'
    ];

    protected $casts = [
        'activo' => 'boolean'
    ];
}
