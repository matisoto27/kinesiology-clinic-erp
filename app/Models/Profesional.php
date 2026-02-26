<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Profesional extends Model
{
    use SoftDeletes; // No trabaja más en Punto-Kinésico

    protected $table = 'profesionales';

    public $timestamps = true;

    protected $fillable = [
        'dni',
        'nombre',
        'apellido',
        'valor_por_hora',
        'codigo_personal',
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

    public function scopeBuscarPorApNom(Builder $consulta, $termino): Builder
    {
        $termino = trim($termino);

        return $consulta->where(function ($subconsulta) use ($termino) {
            $subconsulta->where('apellido', 'LIKE', "%{$termino}%")
                ->orWhere('nombre', 'LIKE', "%{$termino}%")
                ->orWhere(DB::raw("CONCAT(apellido, ' ', nombre)"), 'LIKE', "%{$termino}%");
        });
    }
}
