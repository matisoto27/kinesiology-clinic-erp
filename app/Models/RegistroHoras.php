<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RegistroHoras extends Model
{
    protected $table = 'horas_trabajadas';

    public $timestamps = true;

    protected $fillable = [
        'valor_hora_profesional',
        'cantidad_horas',
        'total_a_cobrar',
        'fecha_trabajada',
        'id_profesional'
    ];

    protected $casts = [
        'valor_hora_profesional' => 'decimal:2',
        'total_a_cobrar' => 'decimal:2',
        'fecha_trabajada' => 'date'
    ];

    public function profesional(): BelongsTo
    {
        return $this->belongsTo(Profesional::class, 'id_profesional');
    }
}
