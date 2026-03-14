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
        'nro_turno',
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
        // Actividades de tipo kinesiología: Ausente - Presente (SIEMPRE TIENEN TURNO ORIGINAL NULO)

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

    public function esReprogramado(): bool
    {
        return $this->id_turno_original !== null;
    }

    public function puedeSerReprogramado(): bool
    {
        if ($this->turnoOriginal || $this->turnoRecuperacion) return false; // Esto aplica solamente para actividades de tipo general
        if ($this->estado === 'Presente') return false;
        return $this->estado === 'Ausente avisó' || $this->fecha_hora->isFuture();
    }

    public function scopeConActPac(Builder $consulta): Builder
    {
        return $consulta->join('actividades_pacientes', 'turnos.id_act_pac', '=', 'actividades_pacientes.id');
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

    public function scopeConActividad(Builder $consulta): Builder
    {
        return $consulta->join('actividades', 'actividades_pacientes.id_actividad', '=', 'actividades.id');
    }

    public function scopeDeTipo(Builder $consulta, int $idTipoActividad): Builder
    {
        return $consulta->where('actividades.id_tipo_actividad', $idTipoActividad);
    }

    public function scopeEntreFechas(Builder $consulta, string $limiteInferior, string $limiteSuperior): Builder
    {
        return $consulta->whereBetween('fecha_hora', [$limiteInferior, $limiteSuperior]);
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
            ->pluck('fecha_hora');
    }
}
