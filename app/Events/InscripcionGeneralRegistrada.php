<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

class InscripcionGeneralRegistrada
{
    use Dispatchable;

    public function __construct(
        public readonly int $idPacienteFijo,
    ) {}
}
