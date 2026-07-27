<?php

namespace Tests\Unit;

use App\Support\NormalizadorNombreClinico;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class NormalizadorNombreClinicoTest extends TestCase
{
    #[DataProvider('nombresNormalizados')]
    public function test_normaliza_nombres_clinicos(string $entrada, string $esperado): void
    {
        $this->assertSame($esperado, NormalizadorNombreClinico::normalizar($entrada));
    }

    public static function nombresNormalizados(): array
    {
        return [
            'sigla corta' => ['dbt', 'DBT'],
            'sigla con espacio ignorado en conteo' => ['  epoc  ', 'EPOC'],
            'titulo con de minuscula' => ['dolor de cuello', 'Dolor de Cuello'],
            'corrige De a de' => ['Hernia De Disco', 'Hernia de Disco'],
            'nombre largo sin de' => ['artritis reumatoidea', 'Artritis Reumatoidea'],
        ];
    }
}
