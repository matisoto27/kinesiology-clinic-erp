<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ActividadPaciente extends Model
{
    protected $table = 'actividades_pacientes';

    public $timestamps = true;

    protected $fillable = [
        'id_actividad',
        'id_paciente',
        'fecha_comienzo',
        'cant_sesiones',
        'es_fijo',
        'total_a_pagar',
        'fecha_emision_ord',
        'sesiones_cubiertas',
        'pago_completado'
    ];

    protected $casts = [
        'fecha_comienzo' => 'date',
        'es_fijo' => 'boolean',
        'total_a_pagar' => 'decimal:2',
        'fecha_emision_ord' => 'date',
        'pago_completado' => 'boolean'
    ];

    protected function nuevoTotalAPagar(): Attribute
    {
        return Attribute::make(
            get: fn() => $this->calcularNuevoTotal()
        );
    }

    protected function deuda(): Attribute
    {
        return Attribute::make(
            get: fn() => $this->calcularDeuda()
        );
    }

    public function actividad(): BelongsTo
    {
        return $this->belongsTo(Actividad::class, 'id_actividad');
    }

    public function paciente(): BelongsTo
    {
        return $this->belongsTo(Paciente::class, 'id_paciente');
    }

    public function turnos(): HasMany
    {
        return $this->hasMany(Turno::class, 'id_act_pac');
    }

    public function ultimoTurno(): HasOne
    {
        return $this->hasOne(Turno::class, 'id_act_pac')->latestOfMany('nro_turno');
    }

    public function pacienteFijo(): HasOne
    {
        return $this->hasOne(PacienteFijo::class, 'id_paciente', 'id_paciente')
            ->whereColumn('id_actividad', 'actividades_pacientes.id_actividad');
    }

    public function pagos(): HasMany
    {
        return $this->hasMany(Pago::class, 'id_act_pac');
    }

    public function scopeNoFijos(Builder $consulta): Builder
    {
        return $consulta->whereDoesntHave('pacienteFijo');
    }

    public function scopeSinPagar(Builder $consulta): Builder
    {
        return $consulta->where('pago_completado', false);
    }

    public function scopeConActividad(Builder $consulta): Builder
    {
        return $consulta->join('actividades', 'actividades_pacientes.id_actividad', '=', 'actividades.id');
    }

    public function scopeDeTipo(Builder $consulta, int $idTipoActividad): Builder
    {
        return $consulta->where('actividades.id_tipo_actividad', $idTipoActividad);
    }

    public function calcularNuevoTotal(): float
    {
        $nuevoTotal = (float) $this->total_a_pagar;

        if ($this->actividad->id_tipo_actividad === 2 && $this->fecha_emision_ord !== null) {
            $cantidadSesiones = (int) $this->cant_sesiones;
            $sesionesCubiertas = (int) ($this->sesiones_cubiertas ?? 0);

            $sesionesRestantes = max(0, $cantidadSesiones - $sesionesCubiertas);

            if ($sesionesRestantes > 0) {
                $nuevoTotal = ActividadCombo::calcularTotalAPagar($this->id_actividad, $sesionesRestantes, $this->actividad->nombre);
            } else {
                $nuevoTotal = 0;
            }
        }

        return $nuevoTotal;
    }

    public function calcularDeuda(): float
    {
        $totalAPagar = (float) $this->nuevo_total_a_pagar;
        $totalPagado = $this->pagos_sum_monto ?? $this->pagos->sum('monto');

        return max(0, (float) ($totalAPagar - $totalPagado));
    }
}
