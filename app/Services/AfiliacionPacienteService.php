<?php

namespace App\Services;

use App\Models\ObraSocialPaciente;
use App\Models\Paciente;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

class AfiliacionPacienteService
{
    public function sincronizar(Paciente $paciente, ?int $idObraSocial, ?string $nombreLibre): void
    {
        $idObraSocial = $idObraSocial ?: null;
        $nombreLibre = $this->normalizarNombreLibre($nombreLibre);

        if ($idObraSocial !== null && $nombreLibre !== null) {
            throw new InvalidArgumentException('La afiliación no puede tener obra social de catálogo y nombre libre a la vez.');
        }

        $actual = ObraSocialPaciente::query()
            ->where('id_paciente', $paciente->id)
            ->whereNull('fecha_hasta')
            ->first();

        if ($idObraSocial === null && $nombreLibre === null) {
            $actual?->update(['fecha_hasta' => Carbon::today()]);
            return;
        }

        if ($this->esMismaAfiliacion($actual, $idObraSocial, $nombreLibre)) {
            return;
        }

        $actual?->update(['fecha_hasta' => Carbon::today()]);

        ObraSocialPaciente::create([
            'id_paciente' => $paciente->id,
            'id_obra_social' => $idObraSocial,
            'nombre_os' => $nombreLibre,
            'fecha_desde' => Carbon::today(),
            'fecha_hasta' => null,
        ]);
    }

    private function esMismaAfiliacion(?ObraSocialPaciente $actual, ?int $idObraSocial, ?string $nombreLibre): bool
    {
        if ($actual === null) {
            return false;
        }

        if ($idObraSocial !== null) {
            return (int) $actual->id_obra_social === $idObraSocial;
        }

        return $actual->id_obra_social === null
            && mb_strtolower((string) $actual->nombre_os) === mb_strtolower((string) $nombreLibre);
    }

    private function normalizarNombreLibre(?string $nombreLibre): ?string
    {
        $nombreLibre = trim((string) $nombreLibre);

        return $nombreLibre === '' ? null : $nombreLibre;
    }
}
