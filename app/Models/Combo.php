<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Combo extends Model
{
    protected $table = 'combos';

    public $timestamps = false;

    protected $fillable = [
        'nombre',
        'cantidad_sesiones',
    ];
}
