<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PacienteCasual extends Model
{
    protected $table = 'pacientes_casuales';

    public $timestamps = true;

    protected $fillable = [
        'nombre',
        'apellido',
        'telefono'
    ];

    public function turnosDirectos(): HasMany
    {
        return $this->hasMany(TurnoDirecto::class, 'id_paciente_casual');
    }
}
