<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;

class ActividadPaciente extends Model
{
    protected $table = 'actividades_pacientes';

    public $timestamps = true;

    protected $fillable = [
        'cant_sesiones',
        'total_a_pagar',
        'pago_completado',
        'fecha_emision_ord',
        'fecha_recargo',
        'porcentaje_recargo',
        'monto_recargo',
        'id_actividad',
        'id_paciente', // Puede ser null
        'id_paciente_casual', // Puede ser null
        'frecuencia_total_dual',
        'id_act_pac_dual',
    ];

    protected $casts = [
        'cant_sesiones' => 'integer',
        'frecuencia_total_dual' => 'integer',
        'total_a_pagar' => 'decimal:2',
        'pago_completado' => 'boolean',
        'fecha_emision_ord' => 'date',
        'fecha_recargo' => 'date',
        'porcentaje_recargo' => 'decimal:2',
        'monto_recargo' => 'decimal:2'
    ];

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
        return $this->belongsTo(Paciente::class, 'id_paciente')->withTrashed();
    }

    public function pacienteCasual(): BelongsTo
    {
        return $this->belongsTo(PacienteCasual::class, 'id_paciente_casual')->withTrashed();
    }

    public function turnos(): HasMany
    {
        return $this->hasMany(Turno::class, 'id_act_pac');
    }

    public function primerTurno(): HasOne
    {
        return $this->hasOne(Turno::class, 'id_act_pac')
            ->whereNull('id_turno_original')
            ->orderBy('fecha_hora');
    }

    public function ultimoTurno(): HasOne
    {
        return $this->hasOne(Turno::class, 'id_act_pac')
            ->whereNull('id_turno_original')
            ->orderByDesc('fecha_hora');
    }

    public function pacienteFijo(): HasOne
    {
        return $this->hasOne(PacienteFijo::class, 'id_paciente', 'id_paciente');
    }

    public function pagos(): HasMany
    {
        return $this->hasMany(Pago::class, 'id_act_pac');
    }

    public function actPacDual(): BelongsTo
    {
        return $this->belongsTo(self::class, 'id_act_pac_dual');
    }

    public function esDualCompleto(): bool
    {
        return $this->id_act_pac_dual !== null
            && $this->frecuencia_total_dual !== null;
    }

    public function esDualOperativo(): bool
    {
        return $this->id_act_pac_dual !== null
            && (int) $this->cant_sesiones > 0
            && $this->actPacDual !== null
            && (int) $this->actPacDual->cant_sesiones > 0;
    }

    public function esPrimeraDual(): bool
    {
        return $this->esDualCompleto() && (int) $this->id < (int) $this->id_act_pac_dual;
    }

    public function esSegundaDual(): bool
    {
        return $this->esDualCompleto() && !$this->esPrimeraDual();
    }

    public function scopeDualCompleto(Builder $consulta): Builder
    {
        return $consulta->whereNotNull('id_act_pac_dual')
            ->whereNotNull('frecuencia_total_dual');
    }

    public static function filtrarProximasPagables(Collection $inscripciones): Collection
    {
        return $inscripciones
            ->filter(fn (self $inscripcion) => $inscripcion->calcularDeuda() > 0)
            ->groupBy(fn (self $inscripcion) => sprintf(
                '%s-%s',
                $inscripcion->id_paciente ?? 'c' . $inscripcion->id_paciente_casual,
                $inscripcion->id_actividad
            ))
            ->map(fn (Collection $grupo) => $grupo->sortBy('id')->first())
            ->values();
    }

    public function esProximaPagable(): bool
    {
        if ($this->calcularDeuda() <= 0) {
            return false;
        }

        $consulta = self::query()
            ->withSum('pagos', 'monto')
            ->sinPagar()
            ->where('id_actividad', $this->id_actividad);

        if ($this->id_paciente !== null) {
            $consulta->where('id_paciente', $this->id_paciente);
        } else {
            $consulta->where('id_paciente_casual', $this->id_paciente_casual);
        }

        $proxima = self::filtrarProximasPagables($consulta->get())->first();

        return $proxima !== null && (int) $proxima->id === (int) $this->id;
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
        return $this->esCasual() && (float) $this->total_a_pagar <= 0;
    }

    public function esPrueba(): bool
    {
        return $this->esCasual() && (float) $this->total_a_pagar > 0;
    }

    public function esPrimeraInscripcion(): bool
    {
        if ($this->id_paciente === null) {
            return false;
        }

        if (!in_array((int) $this->id_actividad, [Actividad::GIMNASIO, Actividad::PILATES], true)) {
            return false;
        }

        return !self::query()
            ->where('id_paciente', $this->id_paciente)
            ->where('id_actividad', $this->id_actividad)
            ->where('id', '<', $this->id)
            ->exists();
    }

    public function perteneceAPacienteFijo(): bool
    {
        if ($this->id_paciente === null) {
            return false;
        }

        if ($this->relationLoaded('pacienteFijo')) {
            return $this->pacienteFijo !== null;
        }

        return $this->pacienteFijo()->exists();
    }

    public function frecuenciaSemanal(): int
    {
        // OJO: La frecuencia operativa sale de HorarioPacienteFijo.
        return (int) ($this->cant_sesiones / 4);
    }

    public function cantSesionesGrupo(): int
    {
        return (int) $this->cant_sesiones + (int) ($this->actPacDual?->cant_sesiones ?? 0);
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

    public function pagoCubreTotal(float $totalAPagar): bool
    {
        if ($totalAPagar <= 0) {
            return true;
        }

        $totalFinal = $totalAPagar + (float) ($this->monto_recargo ?? 0);
        $totalPagado = (float) ($this->pagos_sum_monto ?? $this->pagos()->sum('monto'));

        return $totalPagado >= $totalFinal;
    }
}
