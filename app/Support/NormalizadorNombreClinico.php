<?php

namespace App\Support;

class NormalizadorNombreClinico
{
    public static function normalizar(string $nombre): string
    {
        $nombre = trim(preg_replace('/\s+/u', ' ', $nombre));

        if ($nombre === '') {
            return '';
        }

        $sinEspacios = preg_replace('/\s+/u', '', $nombre);

        if (mb_strlen($sinEspacios) <= 4) {
            return mb_strtoupper($nombre, 'UTF-8');
        }

        $palabras = preg_split('/\s+/u', $nombre, -1, PREG_SPLIT_NO_EMPTY);

        $normalizadas = array_map(function (string $palabra): string {
            if (mb_strtolower($palabra, 'UTF-8') === 'de') {
                return 'de';
            }

            return mb_convert_case(mb_strtolower($palabra, 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
        }, $palabras);

        return implode(' ', $normalizadas);
    }
}
