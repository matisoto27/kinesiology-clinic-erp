<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TipoSintoma extends Model
{
    protected $table = 'tipos_sintomas';

    public $timestamps = false;

    protected $fillable = [
        'nombre',
        'activo'
    ];

    protected $casts = [
        'activo' => 'boolean'
    ];

    public function sintomas()
    {
        return $this->hasMany(Sintoma::class, 'id_tipo');
    }
}
