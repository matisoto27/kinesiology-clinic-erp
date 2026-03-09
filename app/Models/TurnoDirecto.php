<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class TurnoDirecto extends Model
{
    protected $table = 'turnos_directos';

    public $timestamps = true;

    protected $fillable = [
        'fecha_hora_registro',
        'fecha_hora',
        'nro_turno',
        'estado',
        'id_actividad',
        'id_paciente_casual',
        'id_turno_original'
    ];

    protected $casts = [
        'fecha_hora_registro' => 'datetime',
        'fecha_hora' => 'datetime',
        'nro_turno' => 'integer'
    ];

    public function actividad(): BelongsTo
    {
        return $this->belongsTo(Actividad::class, 'id_actividad');
    }

    public function pacienteCasual(): BelongsTo
    {
        return $this->belongsTo(PacienteCasual::class, 'id_paciente_casual');
    }

    public function turnoOriginal(): BelongsTo
    {
        return $this->belongsTo(TurnoDirecto::class, 'id_turno_original');
    }

    public function turnoRecuperacion(): HasOne
    {
        return $this->hasOne(TurnoDirecto::class, 'id_turno_original');
    }
}
