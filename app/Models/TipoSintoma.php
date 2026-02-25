<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TipoSintoma extends Model
{
    protected $table = 'tipos_sintoma';

    public $timestamps = false;

    protected $fillable = [
        'nombre',
        'activo'
    ];

    protected $casts = [
        'activo' => 'boolean'
    ];

    public function sintomas(): HasMany
    {
        return $this->hasMany(Sintoma::class, 'id_tipo');
    }

    public function sintomasActivos(): HasMany
    {
        return $this->sintomas()
            ->where('activo', true)
            ->orderBy('nombre');
    }
}
