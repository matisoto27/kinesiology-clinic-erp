<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class AntecedentePatologico extends Pivot
{
    protected $table = 'antecedentes_patologicos';

    public $timestamps = false;

    protected $fillable = [
        'id_paciente',
        'id_patologia',
        'fecha_desde'
    ];

    protected $casts = [
        'fecha_desde' => 'date'
    ];

    function paciente(): BelongsTo
    {
        return $this->belongsTo(Paciente::class, 'id_paciente');
    }

    function patologia(): BelongsTo
    {
        return $this->belongsTo(Patologia::class, 'id_patologia');
    }
}
