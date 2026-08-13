<?php

namespace App\Support\Registros;

use App\Models\ActividadPaciente;
use App\Models\PacienteFijo;
use Illuminate\Support\Collection;

final class ResultadoInscripcionGeneral
{
    public function __construct(
        public readonly ActividadPaciente $inscripcionParaCobro,
        public readonly Collection $inscripciones,
        public readonly PacienteFijo $pacienteFijo,
        public readonly bool $esDual,
    ) {}
}
