<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HorarioPacienteFijo extends Model
{
    protected $table = 'horarios_pacientes_fijos';

    public $timestamps = true;

    protected $fillable = [
        'id_paciente_fijo',
        'dia_semana',
        'hora_inicio'
    ];

    public function pacienteFijo(): BelongsTo
    {
        return $this->belongsTo(PacienteFijo::class, 'id_paciente_fijo');
    }

    public function getNombreDiaAttribute(): string
    {
        return ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'][$this->dia_semana];
    }
}
