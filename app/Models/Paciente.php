<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class Paciente extends Model
{
    protected $table = 'pacientes';

    public $timestamps = false;

    protected $fillable = [
        'dni',
        'nombre',
        'apellido',
        'fecha_nac',
        'telefono',
        'fecha_ingreso',
        'sesiones_a_favor',
        'activo'
    ];

    protected $casts = [
        'fecha_nac' => 'date',
        'fecha_ingreso' => 'date',
        'activo' => 'boolean'
    ];

    public function historialAfiliaciones(): HasMany
    {
        return $this->hasMany(ObraSocialPaciente::class, 'id_paciente');
    }

    public function afiliacionVigente(): HasOne
    {
        return $this->hasOne(ObraSocialPaciente::class, 'id_paciente')->activo();
    }

    public function scopeTieneObraSocial(Builder $consulta): Builder
    {
        return $consulta->whereHas('afiliacionVigente');
    }

    public function obtenerActividadesGeneralesSinSuscripcion(): Collection
    {
        $diferenciaEnDias = 3;

        $ultimosTurnos = ActividadPaciente::query()
            ->select('id_actividad', DB::raw('MAX(tur.fecha_hora) as max_fecha_hora_turno'))
            ->join('turnos AS tur', 'actividades_pacientes.id', '=', 'tur.id_act_pac') 
            ->where('actividades_pacientes.id_paciente', $this->id)
            ->groupBy('id_actividad');

        $actividades = Actividad::query()
            ->where('actividades.id_tipo_actividad', 1)
            ->leftJoinSub($ultimosTurnos, 'ut', function ($join) {
                $join->on('actividades.id', '=', 'ut.id_actividad');
            })
            ->where(function ($query) use ($diferenciaEnDias) {
                $query->whereNull('ut.id_actividad')
                      ->orWhere(function ($q) use ($diferenciaEnDias) {
                        $ahora = Carbon::now();
                        $q->whereRaw('ut.max_fecha_hora_turno > ?', [$ahora])
                          ->whereRaw("TIMESTAMPDIFF(DAY, ?, ut.max_fecha_hora_turno) <= ?", [$ahora, $diferenciaEnDias]);
                      });
            })
            ->select('actividades.*')
            ->get();

        return $actividades;
    }
}
