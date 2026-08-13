<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection as SupportCollection;

class Turno extends Model
{
    protected $table = 'turnos';

    public $timestamps = true;

    protected $fillable = [
        'id_act_pac',
        'fecha_hora',
        'estado',
        'id_turno_original'
    ];

    protected $casts = [
        'fecha_hora' => 'datetime'
    ];

    protected function estado(): Attribute
    {
        // SALIDAS:
        // Actividades de tipo general: Ausente - Ausente avisó - Presente - Presente recupera
        // Actividades de tipo kinesiología: Ausente - Presente

        return Attribute::make(
            get: function (string $valor) {
                if ($valor === 'Presente' && $this->esReprogramado()) {
                    return 'Presente recupera';
                }

                return $valor;
            }
        );
    }

    protected function apNomPaciente(): Attribute
    {
        return Attribute::make(
            get: fn() => $this->actividadPaciente->ap_nom_paciente
        );
    }

    protected function nroTurno(): Attribute
    {
        return Attribute::make(
            get: function () {
                $fechaReferencia = $this->id_turno_original !== null
                    ? $this->turnoOriginal?->fecha_hora
                    : $this->fecha_hora;

                if ($fechaReferencia === null) {
                    return null;
                }

                return self::query()
                    ->where('id_act_pac', $this->id_act_pac)
                    ->whereNull('id_turno_original')
                    ->where('fecha_hora', '<=', $fechaReferencia)
                    ->count();
            }
        );
    }

    public function actividadPaciente(): BelongsTo
    {
        return $this->belongsTo(ActividadPaciente::class, 'id_act_pac');
    }

    public function notas(): HasMany
    {
        return $this->hasMany(NotaTurno::class, 'id_turno');
    }

    public function turnoOriginal(): BelongsTo
    {
        return $this->belongsTo(Turno::class, 'id_turno_original');
    }

    public function turnoRecuperacion(): HasOne
    {
        return $this->hasOne(Turno::class, 'id_turno_original');
    }

    public function esAusenteAviso(): bool
    {
        return $this->estado === 'Ausente avisó';
    }

    public function esReprogramado(): bool
    {
        return $this->id_turno_original !== null;
    }

    public function puedeSerReprogramado(): bool
    {
        if ($this->estado === 'Presente') {
            return false;
        }

        if ($this->actividadPaciente->actividad->esActividadGeneral()) {
            if ($this->turnoOriginal || $this->turnoRecuperacion) {
                return false;
            }

            return $this->esAusenteAviso() || $this->fecha_hora->isFuture();
        }

        // Los turnos de kinesiología siempre pueden reprogramarse, salvo que este turno en particular ya haya sido reemplazado por otro.
        return !$this->turnoRecuperacion;
    }

    public function scopeConActPac(Builder $consulta): Builder
    {
        return $consulta->join('actividades_pacientes', 'turnos.id_act_pac', '=', 'actividades_pacientes.id');
    }

    public function scopeConActividad(Builder $consulta): Builder
    {
        return $consulta->join('actividades', 'actividades_pacientes.id_actividad', '=', 'actividades.id');
    }

    public function scopeDeTipo(Builder $consulta, int $idTipoActividad): Builder
    {
        return $consulta->where('actividades.id_tipo_actividad', $idTipoActividad);
    }

    public function scopeDeLaActividad(Builder $consulta, int $idActividad): Builder
    {
        return $consulta->where('actividades_pacientes.id_actividad', $idActividad);
    }

    public function scopeDelPaciente(Builder $consulta, int $idPaciente, bool $esRegular): Builder
    {
        return $esRegular
            ? $consulta->where('actividades_pacientes.id_paciente', $idPaciente)
            : $consulta->where('actividades_pacientes.id_paciente_casual', $idPaciente);
    }

    public function scopeBuscarPaciente(Builder $consulta, string $termino): Builder
    {
        return $consulta->whereHas(
            'actividadPaciente',
            fn($sc) => $sc->buscarPaciente($termino)
        );
    }

    public function scopeEntreFechas(Builder $consulta, string $limiteInferior, string $limiteSuperior): Builder
    {
        return $consulta->whereBetween('fecha_hora', [$limiteInferior, $limiteSuperior]);
    }

    public function scopeActivosParaCupo(Builder $consulta): Builder
    {
        return $consulta
            ->whereDoesntHave('turnoRecuperacion')
            ->where('turnos.estado', '!=', 'Ausente avisó');
    }

    public function scopeVisiblesEnAgenda(Builder $consulta): Builder
    {
        return $consulta->where(function (Builder $q) {
            $q->whereDoesntHave('turnoRecuperacion')
                ->orWhereHas(
                    'actividadPaciente.actividad',
                    fn (Builder $sc) => $sc->where('id_tipo_actividad', Actividad::TIPO_GENERAL)
                );
        });
    }

    public function scopeCantidadMayorIgualQue(Builder $consulta, int $cantidad): Builder
    {
        return $consulta->havingRaw('COUNT(*) >= ?', [$cantidad]);
    }

    public static function pacienteEntreFechas(int $idPaciente, bool $esRegular, string $limiteInferior, string $limiteSuperior): SupportCollection
    {
        return self::conActPac()
            ->delPaciente($idPaciente, $esRegular)
            ->entreFechas($limiteInferior, $limiteSuperior)
            ->activosParaCupo()
            ->pluck('fecha_hora');
    }
}
