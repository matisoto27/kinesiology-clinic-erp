<?php

namespace App\Services;

use App\Enums\RubroHorasProfesional;
use App\Exceptions\ReglaNegocioException;
use App\Models\Profesional;
use App\Models\RegistroHoras;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;

class RegistroHorasService
{
    public function valorHora(RubroHorasProfesional $rubro): int
    {
        return (int) config("tarifas_profesionales.{$rubro->value}");
    }

    public function registrar(
        Profesional $profesional,
        RubroHorasProfesional $rubro,
        int $cantidadHoras,
        CarbonInterface $fecha
    ): RegistroHoras {
        $valorHora = $this->valorHora($rubro);
        $total = $valorHora * $cantidadHoras;

        try {
            return RegistroHoras::create([
                'rubro' => $rubro,
                'cantidad_horas' => $cantidadHoras,
                'total_a_cobrar' => $total,
                'fecha_trabajada' => $fecha,
                'id_profesional' => $profesional->id,
            ]);
        } catch (QueryException $ex) {
            if ($this->esViolacionUnique($ex)) {
                throw new ReglaNegocioException(
                    "Este profesional ya tiene horas de {$rubro->etiqueta()} registradas para la fecha seleccionada."
                );
            }

            throw $ex;
        }
    }

    private function esViolacionUnique(QueryException $ex): bool
    {
        if (($ex->errorInfo[1] ?? null) === 1062) {
            return true;
        }

        $mensaje = strtolower($ex->getMessage());

        return str_contains($mensaje, 'unique constraint')
            || str_contains($mensaje, 'duplicate entry');
    }

    public function actualizarCantidadHoras(RegistroHoras $registro, int $cantidadHoras): void
    {
        $valorHoraAplicado = $registro->valor_hora_aplicado;

        $registro->update([
            'cantidad_horas' => $cantidadHoras,
            'total_a_cobrar' => round($valorHoraAplicado * $cantidadHoras, 2),
        ]);
    }
}
