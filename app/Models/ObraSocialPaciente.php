<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class ObraSocialPaciente extends Pivot
{
    protected $table = 'obras_sociales_pacientes';

    public $timestamps = false;

    protected $fillable = [
        'id_obra_social',
        'id_paciente',
        'fecha_desde',
        'fecha_hasta'
    ];

    protected $casts = [
        'fecha_desde' => 'date',
        'fecha_hasta' => 'date'
    ];

    public function obraSocial(): BelongsTo
    {
        return $this->belongsTo(ObraSocial::class, 'id_obra_social');
    }

    public function paciente(): BelongsTo
    {
        return $this->belongsTo(Paciente::class, 'id_paciente');
    }
}
