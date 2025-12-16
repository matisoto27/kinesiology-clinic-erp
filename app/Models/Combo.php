<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Combo extends Model
{
    protected $table = 'combos';

    public $timestamps = false;

    protected $fillable = [
        'nombre',
        'cantidad_sesiones',
        'es_mensual'
    ];

    protected $casts = [
        'es_mensual' => 'boolean'
    ];

    public function actividades(): BelongsToMany
    {
        return $this->belongsToMany(Actividad::class, 'actividades_combos', 'id_combo', 'id_actividad');
    }
}
