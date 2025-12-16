<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Precio extends Model
{
    protected $table = 'precios';

    public $timestamps = false;

    protected $fillable = [
        'id_actividad_combo',
        'fecha_desde',
        'valor'
    ];

    protected $casts = [
        'fecha_desde' => 'datetime',
        'valor' => 'float'
    ];

    public function actividadCombo(): BelongsTo
    {
        return $this->belongsTo(ActividadCombo::class, 'id_actividad_combo');
    }
}
