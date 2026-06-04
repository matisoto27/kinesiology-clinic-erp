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
        'fecha_comienzo',
        'cant_sesiones',
        'es_fijo',
        'total_a_pagar',
        'pago_completado',
        'fecha_emision_ord',
        'fecha_recargo',
        'porcentaje_recargo',
        'monto_recargo',
        'id_actividad',
        'id_paciente', // Puede ser null
        'id_paciente_casual' // Puede ser null
    ];

    protected $casts = [
        'fecha_comienzo' => 'date',
        'cant_sesiones' => 'integer',
        'es_fijo' => 'boolean',
        'total_a_pagar' => 'decimal:2',
        'pago_completado' => 'boolean',
        'fecha_emision_ord' => 'date',
        'fecha_recargo' => 'date',
        'porcentaje_recargo' => 'decimal:2',
        'monto_recargo' => 'decimal:2'
    ];

    protected function fechaMostrar(): Attribute
    {
        return Attribute::make(
            get: fn() => $this->es_fijo ? $this->created_at : $this->fecha_comienzo
        );
    }

    protected function totalConRecargo(): Attribute
    {
        return Attribute::make(
            get: fn() => (float) $this->total_a_pagar + (float) ($this->monto_recargo ?? 0)
        );
    }

    protected function nombreActividad(): Attribute
    {
        return Attribute::make(
            get: fn() => $this->actividad->nombre
        );
    }

    protected function apNomPaciente(): Attribute
    {
        return Attribute::make(
            get: fn() => $this->paciente?->apellido_nombre
        );
    }

    protected function deuda(): Attribute
    {
        return Attribute::make(
            get: fn() => $this->calcularDeuda()
        );
    }

    protected function paciente(): Attribute
    {
        return Attribute::make(
            get: fn() => $this->pacienteRegular ?? $this->pacienteCasual
        );
    }

    public function actividad(): BelongsTo
    {
        return $this->belongsTo(Actividad::class, 'id_actividad');
    }

    public function pacienteRegular(): BelongsTo
    {
        return $this->belongsTo(Paciente::class, 'id_paciente');
    }

    public function pacienteCasual(): BelongsTo
    {
        return $this->belongsTo(PacienteCasual::class, 'id_paciente_casual');
    }

    public function turnos(): HasMany
    {
        return $this->hasMany(Turno::class, 'id_act_pac');
    }

    public function primerTurno(): HasOne
    {
        return $this->hasOne(Turno::class, 'id_act_pac')
            ->whereNull('id_turno_original')
            ->orderBy('nro_turno');
    }

    public function ultimoTurno(): HasOne
    {
        return $this->hasOne(Turno::class, 'id_act_pac')
            ->whereNull('id_turno_original')
            ->orderByDesc('nro_turno');
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

    public function esRegular(): bool
    {
        return $this->id_paciente !== null;
    }

    public function esCasual(): bool
    {
        return $this->id_paciente_casual !== null;
    }

    public function esGympass(): bool
    {
        return $this->esCasual() && $this->id_actividad === Actividad::GIMNASIO;
    }

    public function esPruebaPilates(): bool
    {
        return $this->esCasual() && $this->id_actividad === Actividad::PILATES;
    }

    public function scopeTienePacienteRegular(Builder $consulta): Builder
    {
        return $consulta->whereNull('actividades_pacientes.id_paciente_casual');
    }

    public function scopeNoFijos(Builder $consulta): Builder
    {
        return $consulta->whereDoesntHave('pacienteFijo');
    }

    public function scopeConUltimoTurnoVigente(Builder $consulta): Builder
    {
        return $consulta->whereHas('ultimoTurno', fn (Builder $q) => $q->where('fecha_hora', '>', now()));
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

    public function scopeBuscarPaciente(Builder $consulta, string $termino): Builder
    {
        return $consulta->where(function ($subconsulta) use ($termino) {
            $subconsulta->whereHas('pacienteRegular', fn($sc) => $sc->buscarPorApNom($termino))
                ->orWhereHas('pacienteCasual', fn($sc) => $sc->buscarPorApNom($termino));
        });
    }

    public function calcularDeuda(): float
    {
        if ($this->pago_completado) {
            return 0.0;
        }

        $totalFinal = (float) $this->total_con_recargo;
        $totalPagado = $this->pagos_sum_monto ?? $this->pagos->sum('monto');

        return max(0, (float) ($totalFinal - $totalPagado));
    }
}
