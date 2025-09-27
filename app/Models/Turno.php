<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Turno extends Model
{
    protected $table = 'turnos';

    public $timestamps = false;

    protected $fillable = [
        'id_act_pac',
        'nro_turno',
        'fecha_hora',
        'asiste'
    ];

    protected $casts = [
        'fecha_hora' => 'datetime',
        'asiste' => 'boolean'
    ];

    public function actividadPaciente()
    {
        return $this->belongsTo(ActividadPaciente::class, 'id_act_pac');
    }
}
