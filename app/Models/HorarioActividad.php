<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HorarioActividad extends Model
{
    protected $table = 'horarios_actividades';

    public $timestamps = false;

    protected $fillable = [
        'id_actividad',
        'id_horario'
    ];

    public function actividad()
    {
        return $this->belongsTo(Actividad::class, 'id_actividad');
    }

    public function horario()
    {
        return $this->belongsTo(Horario::class, 'id_horario');
    }
}
