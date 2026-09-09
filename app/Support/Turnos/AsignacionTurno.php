<?php

namespace App\Support\Turnos;

final class AsignacionTurno
{
    public function __construct(
        public readonly string $solicitado,
        public readonly string $asignado,
    ) {}

    public function fueReemplazado(): bool
    {
        return $this->solicitado !== $this->asignado;
    }
}
