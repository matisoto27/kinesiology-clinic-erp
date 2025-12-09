<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Horario extends Model
{
    protected $table = 'horarios';

    public $timestamps = false;

    protected $fillable = [
        'hora_inicio',
        'hora_fin',
        'franja',
        'activo'
    ];

    protected $casts = [
        'activo' => 'boolean'
    ];

    public function actividades()
    {
        return $this->belongsToMany(Actividad::class, 'horarios_actividades', 'id_horario', 'id_actividad');
    }
}
