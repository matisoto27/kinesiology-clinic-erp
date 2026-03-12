<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class PacienteCasual extends Model
{
    use SoftDeletes;

    protected $table = 'pacientes_casuales';

    public $timestamps = true;

    protected $fillable = [
        'nombre',
        'apellido',
        'telefono'
    ];

    protected $appends = [
        'apellido_nombre'
    ];

    protected function apellidoNombre(): Attribute
    {
        return Attribute::make(
            get: fn () => "{$this->apellido}, {$this->nombre}"
        );
    }

    public function turnosDirectos(): HasMany
    {
        return $this->hasMany(TurnoDirecto::class, 'id_paciente_casual');
    }

    public function scopeBuscarPorApNom(Builder $consulta, $termino): Builder
    {
        $termino = trim($termino);

        return $consulta->where(function ($subconsulta) use ($termino) {
            $subconsulta->where('apellido', 'LIKE', "%{$termino}%")
                ->orWhere('nombre', 'LIKE', "%{$termino}%")
                ->orWhere(DB::raw("CONCAT(apellido, ' ', nombre)"), 'LIKE', "%{$termino}%")
                ->orWhere(DB::raw("CONCAT(nombre, ' ', apellido)"), 'LIKE', "%{$termino}%");
        });
    }
}
