<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Actividad extends Model
{
    protected $table = 'actividades';

    public $timestamps = false;

    protected $fillable = [
        'nombre',
        'activo'
    ];

    protected $casts = [
        'activo' => 'boolean'
    ];
}
