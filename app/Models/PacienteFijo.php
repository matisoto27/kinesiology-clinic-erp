<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PacienteFijo extends Model
{
    protected $table = 'pacientes_fijos';

    public $timestamps = true;

    protected $fillable = [
        'id_paciente',
    ];

    public function paciente(): BelongsTo
    {
        return $this->belongsTo(Paciente::class, 'id_paciente');
    }

    public function horarios(): HasMany
    {
        return $this->hasMany(HorarioPacienteFijo::class, 'id_paciente_fijo')
            ->orderBy('dia_semana')
            ->orderBy('hora_inicio');
    }

    public function esDual(): bool
    {
        return $this->horarios->pluck('id_actividad')->unique()->count() > 1;
    }
}
