<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActividadPaciente extends Model
{
    protected $table = 'actividades_pacientes';

    public $timestamps = false;

    protected $fillable = [
        'id_actividad',
        'id_paciente',
        'fecha_comienzo',
        'cant_sesiones',
        'es_fijo',
        'total_a_pagar',
        'fecha_emision_ord',
        'sesiones_cubiertas',
        'pago_completado'
    ];

    protected $casts = [
        'fecha_comienzo' => 'date',
        'es_fijo' => 'boolean',
        'total_a_pagar' => 'decimal:2',
        'fecha_emision_ord' => 'date',
        'pago_completado' => 'boolean'
    ];

    public function actividad()
    {
        return $this->belongsTo(Actividad::class, 'id_actividad');
    }

    public function paciente()
    {
        return $this->belongsTo(Paciente::class, 'id_paciente');
    }

    public function turnos()
    {
        return $this->hasMany(Turno::class, 'id_act_pac');
    }
}
