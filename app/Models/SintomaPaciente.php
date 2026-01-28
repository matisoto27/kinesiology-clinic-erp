<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class SintomaPaciente extends Pivot
{
    protected $table = 'sintomas_pacientes';

    public $timestamps = true;

    protected $fillable = [
        'id_sintoma',
        'id_paciente',
        'fecha_hasta'
    ];

    protected $casts = [
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
