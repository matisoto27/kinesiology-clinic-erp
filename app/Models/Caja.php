<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Caja extends Model
{
    protected $table = 'caja';

    protected $fillable = [
        'saldo_efectivo',
        'saldo_transferencia',
    ];

    protected $casts = [
        'saldo_efectivo' => 'decimal:2',
        'saldo_transferencia' => 'decimal:2',
    ];
}
