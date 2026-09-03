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
        // Ausente - Ausente avisó - Presente - Presente recupera

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
                $fechaReferencia = $this->fecha_hora;

                if ($this->id_turno_original !== null) {
                    $fechaOriginal = $this->turnoOriginal?->fecha_hora
                        ?? self::query()->where('id', $this->id_turno_original)->value('fecha_hora');

                    if ($fechaOriginal === null) {
                        return null;
                    }

                    $fechaReferencia = $fechaOriginal;
                }

                $idsActPac = [$this->id_act_pac];
                $parId = $this->actividadPaciente?->id_act_pac_dual;
                if ($parId) {
                    $idsActPac[] = $parId;
                }

                return self::query()
                    ->whereIn('id_act_pac', $idsActPac)
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

    public function puedeCancelarReprogramacion(): bool
    {
        if (!$this->esAusenteAviso() || $this->esReprogramado()) {
            return false;
        }

        $recuperacion = $this->turnoRecuperacion;

        if ($recuperacion === null) {
            return $this->fecha_hora->isFuture();
        }

        return $recuperacion->fecha_hora->isFuture()
            && !str_contains((string) $recuperacion->estado, 'Presente');
    }

    public function puedeSerModificado(): bool
    {
        if (str_contains((string) $this->estado, 'Presente')) {
            return false;
        }

        if ($this->actividadPaciente->actividad->esActividadGeneral()) {
            if ($this->turnoRecuperacion) {
                return false;
            }

            if ($this->esReprogramado()) {
                return true;
            }

            return $this->esAusenteAviso() || $this->fecha_hora->isFuture();
        }

        // Kinesio: se puede corregir la fecha del turno vigente (original o reprogramado, pero no AA).
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
            ->where('turnos.estado', '!=', 'Ausente avisó')
            ->dePacienteActivo();
    }

    public function scopeVisiblesEnAgenda(Builder $consulta): Builder
    {
        return $consulta
            ->where(function (Builder $q) {
                $q->whereDoesntHave('turnoRecuperacion')
                    ->orWhereHas(
                        'actividadPaciente.actividad',
                        fn (Builder $sc) => $sc->where('id_tipo_actividad', Actividad::TIPO_GENERAL)
                    );
            })
            ->dePacienteActivo();
    }

    /**
     * Excluye turnos cuyo paciente (regular o casual) fue dado de baja (soft delete).
     * Evita que pacientes eliminados sigan ocupando cupo o apareciendo en la agenda.
     */
    public function scopeDePacienteActivo(Builder $consulta): Builder
    {
        return $consulta->whereHas(
            'actividadPaciente',
            fn (Builder $sc) => $sc->where(function (Builder $q) {
                $q->whereHas('pacienteRegular', fn (Builder $p) => $p->withoutTrashed())
                    ->orWhereHas('pacienteCasual', fn (Builder $p) => $p->withoutTrashed());
            })
        );
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
