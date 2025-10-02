<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SintomaPaciente extends Model
{
    protected $table = 'sintomas_pacientes';

    public $timestamps = false;

    protected $fillable = [
        'id_sintoma',
        'id_paciente',
        'fecha_desde',
        'fecha_hasta'
    ];

    protected $casts = [
        'fecha_desde' => 'datetime',
        'fecha_hasta' => 'datetime'
    ];

    public function sintoma()
    {
        return $this->belongsTo(Sintoma::class);
    }

    public function paciente()
    {
        return $this->belongsTo(Paciente::class);
    }
}
