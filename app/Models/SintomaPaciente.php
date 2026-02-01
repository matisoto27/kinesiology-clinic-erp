<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class SintomaPaciente extends Pivot
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
        'fecha_desde' => 'date',
        'fecha_hasta' => 'date'
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
