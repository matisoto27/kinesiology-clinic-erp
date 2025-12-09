<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PrecioCombo extends Model
{
    protected $table = 'precios';

    public $timestamps = false;

    protected $fillable = [
        'id_combo',
        'fecha_desde',
        'valor'
    ];

    protected $casts = [
        'fecha_desde' => 'datetime'
    ];
}
