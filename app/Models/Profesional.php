<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Profesional extends Model
{
    const UPDATED_AT = null;

    protected $table = 'profesionales';

    public $timestamps = true;

    protected $fillable = [
        'dni',
        'nombre',
        'apellido',
        'activo'
    ];

    protected $casts = [
        'activo' => 'boolean',
        'created_at' => 'datetime'
    ];

    public function pagos(): HasMany
    {
        return $this->hasMany(Pago::class, 'id_profesional');
    }

    public function scopeActivo(Builder $consulta): Builder
    {
        return $consulta->where('activo', true);
    }
}
