<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Profesional extends Model
{
    use SoftDeletes; // No trabaja más en Punto-Kinésico

    protected $table = 'profesionales';

    public $timestamps = true;

    protected $fillable = [
        'dni',
        'nombre',
        'apellido',
        'activo' // Temporalmente deja de estar disponible
    ];

    protected $casts = [
        'activo' => 'boolean'
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
