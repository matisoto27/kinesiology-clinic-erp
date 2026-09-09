<?php

namespace App\Support\Registros;

use App\Models\ActividadPaciente;

final class ResultadoRegistroActividadPaciente
{
    /**
     * @param  list<array{solicitado: string, asignado: string}>  $reemplazos
     */
    public function __construct(
        public readonly ActividadPaciente $inscripcion,
        public readonly array $reemplazos = [],
    ) {}
}
