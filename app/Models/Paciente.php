<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class Paciente extends Model
{
    use SoftDeletes;

    protected $table = 'pacientes';

    public $timestamps = true;

    protected $fillable = [
        'dni',
        'nombre',
        'apellido',
        'fecha_nac',
        'domicilio',
        'telefono',
        'profesion',
        'actividad_fisica',
        'es_adulto_mayor',
        'vive_con'
    ];

    protected $casts = [
        'fecha_nac' => 'date',
        'es_adulto_mayor' => 'boolean'
    ];

    protected $appends = [
        'nombre_completo',
        'fecha_nacimiento',
        'edad',
        'fecha_ingreso'
    ];

    protected function nombreCompleto(): Attribute
    {
        return Attribute::make(
            get: fn () => "{$this->apellido}, {$this->nombre}"
        );
    }

    protected function fechaNacimiento(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->fecha_nac?->format('d-m-Y')
        );
    }

    protected function edad(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->fecha_nac?->age
        );
    }

    protected function fechaIngreso(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->created_at?->format('d-m-Y')
        );
    }

    public function contactosEmergencia(): HasMany
    {
        return $this->hasMany(ContactoEmergencia::class, 'id_paciente');
    }

    public function patologias(): BelongsToMany
    {
        return $this->belongsToMany(Patologia::class, 'antecedentes_patologicos', 'id_paciente', 'id_patologia')
            ->withPivot('fecha_desde')
            ->using(AntecedentePatologico::class);
    }

    public function sintomas(): BelongsToMany
    {
        return $this->belongsToMany(Sintoma::class, 'sintomas_pacientes', 'id_paciente', 'id_sintoma')
            ->withPivot('fecha_desde', 'fecha_hasta')
            ->using(SintomaPaciente::class);
    }

    public function sintomasActivos(): BelongsToMany
    {
        return $this->sintomas()->wherePivotNull('fecha_hasta');
    }

    public function historialAfiliaciones(): BelongsToMany
    {
        return $this->belongsToMany(ObraSocial::class, 'obras_sociales_pacientes', 'id_paciente', 'id_obra_social')
            ->withPivot('fecha_desde', 'fecha_hasta')
            ->using(ObraSocialPaciente::class);
    }

    public function afiliacionVigente(): HasOneThrough
    {
        return $this->hasOneThrough(ObraSocial::class, ObraSocialPaciente::class, 'id_paciente', 'id', 'id', 'id_obra_social')
            ->whereNull('obras_sociales_pacientes.fecha_hasta');
    }

    public function scopeTieneObraSocial(Builder $consulta): Builder
    {
        return $consulta->whereHas('afiliacionVigente');
    }

    public function scopeBuscarPorApNom(Builder $consulta, $termino): Builder
    {
        $termino = trim($termino);

        return $consulta->where(function ($subconsulta) use ($termino) {
            $subconsulta->where('apellido', 'LIKE', "%{$termino}%")
                ->orWhere('nombre', 'LIKE', "%{$termino}%")
                ->orWhere(DB::raw("CONCAT(apellido, ' ', nombre)"), 'LIKE', "%{$termino}%")
                ->orWhere(DB::raw("CONCAT(nombre, ' ', apellido)"), 'LIKE', "%{$termino}%");
        });
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
