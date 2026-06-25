<?php

namespace App\Models;

use App\Models\Actividad;
use App\Models\Combo;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;

class ActividadCombo extends Model
{
    protected $table = 'actividades_combos';

    public $timestamps = false;

    protected $fillable = [
        'id_actividad',
        'id_combo',
        'activo'
    ];

    protected $casts = [
        'activo' => 'boolean'
    ];

    protected function nombreActividad(): Attribute
    {
        return Attribute::make(
            get: fn() => $this->actividad->nombre
        );
    }

    function actividad(): BelongsTo
    {
        return $this->belongsTo(Actividad::class, 'id_actividad');
    }

    function combo(): BelongsTo
    {
        return $this->belongsTo(Combo::class, 'id_combo');
    }

    function precios(): HasMany
    {
        return $this->hasMany(Precio::class, 'id_actividad_combo');
    }

    function precioVigente(): HasOne
    {
        return $this->hasOne(Precio::class, 'id_actividad_combo')
            ->where('fecha_desde', '<=', now())
            ->latest('fecha_desde');
    }

    public function scopeWhereActividad(Builder $consulta, int $idActividad): Builder
    {
        return $consulta->where('actividades_combos.id_actividad', $idActividad);
    }

    public function scopeWhereCombo(Builder $consulta, int $idCombo): Builder
    {
        return $consulta->where('actividades_combos.id_combo', $idCombo);
    }

    public function scopeActivo(Builder $consulta): Builder
    {
        return $consulta->where('actividades_combos.activo', true);
    }

    public static function obtenerPrecioPruebaPilates(): float
    {
        $vinculo = self::query()
            ->whereActividad(Actividad::PILATES)
            ->whereCombo(Combo::CLASE_PRUEBA)
            ->with(['precioVigente'])
            ->firstOrFail();

        if (!$vinculo->precioVigente) {
            throw new Exception('La clase de prueba de Pilates no tiene un precio determinado actualmente.');
        }

        if (!$vinculo->activo) {
            throw new Exception('La clase de prueba de Pilates no se encuentra habilitada en este momento.');
        }

        return (float) $vinculo->precioVigente->valor;
    }

    public static function obtenerPrecioMensual(int $idActividadCombo): float
    {
        $vinculo = self::query()
            ->with(['precioVigente', 'combo'])
            ->findOrFail($idActividadCombo);

        if (!$vinculo->combo->es_mensual) {
            throw new Exception('El combo seleccionado no corresponde a un abono mensual.');
        }

        if (!$vinculo->precioVigente) {
            throw new Exception('El combo seleccionado no tiene un precio determinado actualmente.');
        }

        if (!$vinculo->activo) {
            throw new Exception('El combo seleccionado no se encuentra disponible en este momento.');
        }

        return (float) $vinculo->precioVigente->valor;
    }

    public static function obtenerPrecioMensualPorFrecuencia(int $idActividad, int $frecuenciaSemanal): float
    {
        $cantidadSesiones = $frecuenciaSemanal * 4;

        $vinculo = self::activo()
            ->whereActividad($idActividad)
            ->whereHas('combo', fn ($q) => $q
                ->where('es_mensual', true)
                ->where('cantidad_sesiones', $cantidadSesiones))
            ->with(['precioVigente', 'combo'])
            ->first();

        if (!$vinculo) {
            throw new Exception("No existe un combo mensual x{$frecuenciaSemanal} configurado para la actividad indicada.");
        }

        if (!$vinculo->precioVigente) {
            throw new Exception("El combo mensual x{$frecuenciaSemanal} no tiene un precio determinado actualmente.");
        }

        return (float) $vinculo->precioVigente->valor;
    }

    public static function calcularTotalAPagar(int $idActividad, int $cantidadSesiones, bool $exigirComboExacto = false): float
    {
        $mensajeSesionIndividual = 'La actividad no tiene un precio determinado para su sesión individual.';

        $vinculos = self::activo()
            ->whereActividad($idActividad)
            ->whereHas('combo', fn ($q) => $q->where('es_mensual', false))
            ->whereHas('precioVigente')
            ->with(['precioVigente', 'combo'])
            ->get()
            ->keyBy(fn (self $vinculo) => $vinculo->combo->cantidad_sesiones);

        if ($exigirComboExacto) {
            return self::precioDelCombo($vinculos, $cantidadSesiones);
        }

        if (!$vinculos->has(1)) {
            throw new Exception($mensajeSesionIndividual);
        }

        $vinculoIndividual = $vinculos->get(1);

        if (!$vinculoIndividual->precioVigente) {
            throw new Exception($mensajeSesionIndividual);
        }

        $precioIndividual = (float) $vinculoIndividual->precioVigente->valor;

        if ($vinculos->count() === 1) {
            return $precioIndividual * $cantidadSesiones;
        }

        if ($vinculos->has($cantidadSesiones)) {
            return self::precioDelCombo($vinculos, $cantidadSesiones);
        }

        return $precioIndividual * $cantidadSesiones;
    }

    private static function precioDelCombo(Collection $vinculos, int $cantidadSesiones): float
    {
        $vinculoCombo = $vinculos->get($cantidadSesiones);

        if (!$vinculos->has($cantidadSesiones) || !$vinculoCombo->precioVigente) {
            throw new Exception("La actividad no tiene un precio determinado para el combo de {$cantidadSesiones} sesiones.");
        }

        return (float) $vinculoCombo->precioVigente->valor;
    }
}
