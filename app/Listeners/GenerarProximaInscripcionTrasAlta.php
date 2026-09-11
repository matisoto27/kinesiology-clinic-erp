<?php

namespace App\Listeners;

use App\Events\InscripcionGeneralRegistrada;
use App\Models\PacienteFijo;
use App\Services\RenovacionInscripcionService;

class GenerarProximaInscripcionTrasAlta
{
    public function __construct(
        private RenovacionInscripcionService $renovacionInscripcionService,
    ) {}

    public function handle(InscripcionGeneralRegistrada $event): void
    {
        $pacienteFijo = PacienteFijo::query()
            ->with(['horarios', 'paciente'])
            ->find($event->idPacienteFijo);

        if (!$pacienteFijo?->paciente) {
            return;
        }

        $this->renovacionInscripcionService->renovarSiCorresponde($pacienteFijo);
    }
}
