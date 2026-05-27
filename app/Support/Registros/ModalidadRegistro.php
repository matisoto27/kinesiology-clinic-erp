<?php

namespace App\Support\Registros;

final class ModalidadRegistro
{
    public static function esConOrden(array $datos): bool
    {
        return array_key_exists('sesiones_cubiertas', $datos)
            || array_key_exists('mes', $datos)
            || array_key_exists('dia', $datos);
    }

    public static function debeUsarPrecioMensual(array $datos): bool
    {
        return !self::esConOrden($datos)
            && array_key_exists('id_actividad_combo', $datos);
    }
}
