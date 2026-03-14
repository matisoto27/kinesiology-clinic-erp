<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Egreso extends Model
{
    protected $table = 'egresos';

    protected $fillable = [
        'monto',
        'motivo',
        'id_profesional'
    ];

    protected $casts = [
        'monto' => 'decimal:2'
    ];

    public function profesional(): BelongsTo
    {
        return $this->belongsTo(Profesional::class, 'id_profesional');
    }
}
