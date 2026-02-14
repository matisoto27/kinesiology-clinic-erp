<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Caja extends Model
{
    protected $table = 'caja';

    protected $fillable = [
        'saldo_actual'
    ];

    protected $casts = [
        'saldo_actual' => 'decimal:2'
    ];
}
