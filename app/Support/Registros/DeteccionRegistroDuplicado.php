<?php

namespace App\Support\Registros;

use Illuminate\Database\QueryException;

final class DeteccionRegistroDuplicado
{
    public const MENSAJE = 'El paciente ya ha realizado un registro para esta actividad en la fecha de hoy.';

    public static function esDuplicado(QueryException $excepcion): bool
    {
        return self::analizar(
            codigoSql: $excepcion->errorInfo[1] ?? null,
            sqlState: $excepcion->errorInfo[0] ?? null,
            mensaje: $excepcion->getMessage()
        );
    }

    /**
     * Permite testear la regla sin construir una QueryException real.
     */
    public static function analizar(int|string|null $codigoSql, ?string $sqlState, string $mensaje): bool
    {
        if ($codigoSql === 1062) {
            return true;
        }

        return str_contains($mensaje, 'act_pac_fecha_unique')
            || (
                $sqlState === '23000'
                && str_contains($mensaje, 'actividades_pacientes')
                && str_contains($mensaje, 'fecha_comienzo')
            );
    }
}
