<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ObraSocialPaciente extends Model
{
    protected $table = 'obras_sociales_pacientes';

    public $timestamps = false;

    protected $fillable = [
        'id_obra_social',
        'id_paciente',
        'fecha_desde',
        'activo'
    ];

    protected $casts = [
        'fecha_desde' => 'date',
        'activo' => 'boolean'
    ];

    public function obraSocial(): BelongsTo
    {
        return $this->belongsTo(ObraSocial::class, 'id_obra_social');
    }

    public function paciente(): BelongsTo
    {
        return $this->belongsTo(Paciente::class, 'id_paciente');
    }

    public function scopeActivo(Builder $consulta): Builder
    {
        return $consulta->where('activo', true);
    }
}
