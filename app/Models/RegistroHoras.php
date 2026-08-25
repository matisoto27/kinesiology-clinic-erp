<?php

namespace App\Models;

use App\Enums\RubroHorasProfesional;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RegistroHoras extends Model
{
    protected $table = 'horas_trabajadas';

    public $timestamps = true;

    protected $fillable = [
        'rubro',
        'cantidad_horas',
        'total_a_cobrar',
        'fecha_trabajada',
        'id_profesional',
    ];

    protected $casts = [
        'rubro' => RubroHorasProfesional::class,
        'total_a_cobrar' => 'decimal:2',
        'fecha_trabajada' => 'date',
    ];

    protected $appends = [
        'valor_hora_aplicado',
    ];

    protected function valorHoraAplicado(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->cantidad_horas > 0
                ? round((float) $this->total_a_cobrar / $this->cantidad_horas, 2)
                : 0.0
        );
    }

    public function profesional(): BelongsTo
    {
        return $this->belongsTo(Profesional::class, 'id_profesional');
    }
}
