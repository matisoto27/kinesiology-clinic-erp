<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PacienteFijo extends Model
{
    protected $table = 'pacientes_fijos';

    public $timestamps = true;

    protected $fillable = [
        'id_actividad',
        'id_paciente',
        'id_pac_fijo_dual',
        'activo'
    ];

    protected $casts = [
        'activo' => 'boolean'
    ];

    public function actividad(): BelongsTo
    {
        return $this->belongsTo(Actividad::class, 'id_actividad');
    }

    public function paciente(): BelongsTo
    {
        return $this->belongsTo(Paciente::class, 'id_paciente');
    }

    public function pacFijoDual(): BelongsTo
    {
        return $this->belongsTo(self::class, 'id_pac_fijo_dual');
    }

    public function horarios(): HasMany
    {
        return $this->hasMany(HorarioPacienteFijo::class, 'id_paciente_fijo')
            ->orderBy('dia_semana')
            ->orderBy('hora_inicio');
    }

    public function esDual(): bool
    {
        return $this->id_pac_fijo_dual !== null;
    }

    public function scopePrincipales(Builder $consulta): Builder
    {
        return $consulta->where(function (Builder $subconsulta) {
            $subconsulta->whereNull('id_pac_fijo_dual')
                ->orWhereColumn('pacientes_fijos.id', '<', 'pacientes_fijos.id_pac_fijo_dual');
        });
    }
}
