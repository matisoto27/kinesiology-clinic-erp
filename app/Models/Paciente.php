<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Paciente extends Model
{
    protected $table = 'pacientes';

    public $timestamps = false;

    protected $fillable = [
        'dni',
        'nombre',
        'apellido',
        'fecha_nac',
        'telefono',
        'fecha_ingreso',
        'sesiones_a_favor',
        'activo'
    ];

    protected $casts = [
        'fecha_nac' => 'date',
        'fecha_ingreso' => 'date',
        'activo' => 'boolean'
    ];
}
