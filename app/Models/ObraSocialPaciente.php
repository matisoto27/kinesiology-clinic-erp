<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ObraSocialPaciente extends Model
{
    protected $table = 'obras_sociales_pacientes';

    public $timestamps = false;

    protected $fillable = [
        'id_obra_social',
        'nombre_os',
        'id_paciente',
        'fecha_desde',
        'fecha_hasta'
    ];

    protected $casts = [
        'fecha_desde' => 'date',
        'fecha_hasta' => 'date'
    ];

    protected function nombreMostrable(): Attribute
    {
        return Attribute::get(fn () => $this->obraSocial?->nombre ?? $this->nombre_os);
    }

    public function obraSocial(): BelongsTo
    {
        return $this->belongsTo(ObraSocial::class, 'id_obra_social');
    }

    public function paciente(): BelongsTo
    {
        return $this->belongsTo(Paciente::class, 'id_paciente');
    }
}
