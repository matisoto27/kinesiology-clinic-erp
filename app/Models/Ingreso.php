<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Ingreso extends Model
{
    protected $table = 'ingresos';

    protected $fillable = [
        'metodo',
        'monto',
        'motivo',
        'id_paciente',
        'id_profesional',
    ];

    protected $casts = [
        'monto' => 'decimal:2'
    ];

    public function paciente(): BelongsTo
    {
        return $this->belongsTo(Paciente::class, 'id_paciente');
    }

    public function profesional(): BelongsTo
    {
        return $this->belongsTo(Profesional::class, 'id_profesional');
    }
}
