<?php

namespace App\Support\Turnos;

final class ResultadoPreparacionTurnos
{
    /**
     * @param  list<AsignacionTurno>  $asignaciones
     */
    public function __construct(
        public readonly array $asignaciones,
    ) {}

    /**
     * @param  list<string>  $fechasHora
     */
    public static function desdeFechasExactas(array $fechasHora): self
    {
        return new self(array_map(
            fn (string $fecha) => new AsignacionTurno($fecha, $fecha),
            array_values($fechasHora)
        ));
    }

    /**
     * @return list<array{fecha_hora: string}>
     */
    public function paraPersistir(): array
    {
        $fechas = array_map(
            fn (AsignacionTurno $asignacion) => $asignacion->asignado,
            $this->asignaciones
        );

        sort($fechas);

        return array_map(fn (string $fecha) => ['fecha_hora' => $fecha], $fechas);
    }

    public function huboReemplazos(): bool
    {
        foreach ($this->asignaciones as $asignacion) {
            if ($asignacion->fueReemplazado()) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<array{solicitado: string, asignado: string}>
     */
    public function reemplazos(): array
    {
        $reemplazos = [];

        foreach ($this->asignaciones as $asignacion) {
            if (!$asignacion->fueReemplazado()) {
                continue;
            }

            $reemplazos[] = [
                'solicitado' => $asignacion->solicitado,
                'asignado' => $asignacion->asignado,
            ];
        }

        return $reemplazos;
    }
}
