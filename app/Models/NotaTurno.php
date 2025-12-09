<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class NotaTurno extends Model
{
    protected $table = 'notas';

    public $timestamps = false;

    protected $fillable = [
        'id_turno',
        'fecha_realizada',
        'contenido'
    ];

    protected $casts = [
        'fecha_realizada' => 'datetime'
    ];

    public function getFechaRealizadaAttribute($value)
    {
        return Carbon::parse($value)->format('d/m/Y H:i');
    }
}
