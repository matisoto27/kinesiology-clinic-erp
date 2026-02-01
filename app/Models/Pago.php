<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Pago extends Model
{
    protected $table = 'pagos';

    public $timestamps = true;

    protected $fillable = [
        'id_act_pac',
        'nro_pago',
        'metodo',
        'monto',
        'id_profesional'
    ];

    protected $casts = [
        'nro_pago' => 'integer',
        'monto' => 'decimal:2'
    ];

    protected static function booted()
    {
        static::creating(function ($pago) {
            $ultimoNro = static::where('id_act_pac', $pago->id_act_pac)->max('nro_pago') ?? 0;
            $pago->nro_pago = $ultimoNro + 1;
        });
    }

    public function actividadPaciente(): BelongsTo
    {
        return $this->belongsTo(ActividadPaciente::class, 'id_act_pac');
    }

    public function profesional(): BelongsTo
    {
        return $this->belongsTo(Profesional::class, 'id_profesional');
    }
}
