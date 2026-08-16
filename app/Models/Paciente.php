<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
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
        'apellido_nombre',
        'fecha_nacimiento',
        'edad',
        'fecha_ingreso'
    ];

    protected function apellidoNombre(): Attribute
    {
        return Attribute::make(
            get: fn() => "{$this->apellido}, {$this->nombre}"
        );
    }

    protected function fechaNacimiento(): Attribute
    {
        return Attribute::make(
            get: fn() => $this->fecha_nac?->format('d-m-Y')
        );
    }

    protected function edad(): Attribute
    {
        return Attribute::make(
            get: fn() => $this->fecha_nac?->age
        );
    }

    protected function fechaIngreso(): Attribute
    {
        return Attribute::make(
            get: fn() => $this->created_at?->format('d-m-Y')
        );
    }

    public function contactosEmergencia(): HasMany
    {
        return $this->hasMany(ContactoEmergencia::class, 'id_paciente');
    }

    public function pacienteFijo(): HasOne
    {
        return $this->hasOne(PacienteFijo::class, 'id_paciente');
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

    public function afiliacionVigente(): HasOne
    {
        return $this->hasOne(ObraSocialPaciente::class, 'id_paciente')
            ->whereNull('fecha_hasta');
    }

    public function scopeTieneObraSocial(Builder $consulta): Builder
    {
        return $consulta->whereHas('afiliacionVigente', function (Builder $afiliacion) {
            $afiliacion->whereNotNull('id_obra_social');
        });
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

}
