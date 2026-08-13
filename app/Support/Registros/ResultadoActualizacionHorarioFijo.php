<?php

namespace App\Support\Registros;

use App\Models\PacienteFijo;
use Illuminate\Support\Collection;

final class ResultadoActualizacionHorarioFijo
{
    /**
     * @param  array<int, array{fecha_hora: string, id_actividad: int, nombre_actividad: string}>  $turnosACrear
     * @param  array<int, array{fecha_hora: string, id_actividad: int, nombre_actividad: string}>  $turnosAEliminar
     */
    public function __construct(
        public readonly PacienteFijo $pacienteFijo,
        public readonly int $frecuenciaAnterior,
        public readonly int $frecuenciaNueva,
        public readonly float $totalAnterior,
        public readonly float $totalNuevo,
        public readonly float $cargoExtra,
        public readonly bool $huboPrimerTurno,
        public readonly array $turnosACrear,
        public readonly array $turnosAEliminar,
        public readonly Collection $inscripciones,
        public readonly ?int $idInscripcionParaCobro,
        public readonly bool $sinCambiosEfectivos = false,
    ) {}
}
