<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Sintoma extends Model
{
    protected $table = 'sintomas';

    public $timestamps = false;

    protected $fillable = [
        'nombre',
        'activo',
        'id_tipo'
    ];

    protected $casts = [
        'activo' => 'boolean'
    ];
}
