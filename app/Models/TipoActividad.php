<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TipoActividad extends Model
{
    protected $table = 'tipos_actividad';

    public $timestamps = false;

    protected $fillable = [
        'descripcion'
    ];

    public function actividades(): HasMany
    {
        return $this->hasMany(Actividad::class, 'id_tipo_actividad');
    }
}
