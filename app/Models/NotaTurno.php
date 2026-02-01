<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotaTurno extends Model
{
    protected $table = 'notas';

    public $timestamps = true;

    protected $fillable = [
        'contenido',
        'id_turno'
    ];
}
