<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection as SupportCollection;

class Turno extends Model
{
    protected $table = 'turnos';

    public $timestamps = false;

    protected $fillable = [
        'id_act_pac',
        'nro_turno',
        'fecha_hora',
        'asiste'
    ];

    protected $casts = [
        'fecha_hora' => 'datetime',
        'asiste' => 'boolean'
    ];

    public function actividadPaciente()
    {
        return $this->belongsTo(ActividadPaciente::class, 'id_act_pac');
    }

    public function notas()
    {
        return $this->hasMany(NotaTurno::class, 'id_turno');
    }

    public function scopeConActPac(Builder $consulta): Builder
    {
        return $consulta->join('actividades_pacientes', 'turnos.id_act_pac', '=', 'actividades_pacientes.id');
    }

    public function scopeDeLaActividad(Builder $consulta, int $idActividad): Builder
    {
        return $consulta->where('actividades_pacientes.id_actividad', $idActividad);
    }

    public function scopeDelPaciente(Builder $consulta, int $idPaciente): Builder
    {
        return $consulta->where('actividades_pacientes.id_paciente', $idPaciente);
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

    public static function pacienteEntreFechas(int $idPaciente, string $limiteInferior, string $limiteSuperior): SupportCollection
    {
        return self::conActPac()
            ->delPaciente($idPaciente)
            ->entreFechas($limiteInferior, $limiteSuperior)
            ->pluck('fecha_hora');
    }
}
