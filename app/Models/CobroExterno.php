<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CobroExterno extends Model
{
    protected $table = 'cobros_externos';

    public const UPDATED_AT = null;

    protected $fillable = [
        'monto',
        'id_act_pac',
    ];

    protected $casts = [
        'monto' => 'decimal:2',
        'created_at' => 'datetime',
    ];

    public function actividadPaciente(): BelongsTo
    {
        return $this->belongsTo(ActividadPaciente::class, 'id_act_pac');
    }
}
