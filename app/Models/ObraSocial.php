<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ObraSocial extends Model
{
    protected $table = 'obras_sociales';

    public $timestamps = false;

    protected $fillable = ['nombre'];

    public function historialAfiliados(): HasMany
    {
        return $this->hasMany(ObraSocialPaciente::class, 'id_obra_social');
    }

    public function afiliadosActivos(): HasMany
    {
        return $this->hasMany(ObraSocialPaciente::class, 'id_obra_social')->activo();
    }
}
