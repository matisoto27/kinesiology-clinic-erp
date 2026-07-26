<?php

namespace Tests\Unit;

use App\Support\Registros\ModalidadRegistro;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RegistrosReglasTest extends TestCase
{
    #[DataProvider('payloadsConOrden')]
    public function test_detecta_registro_con_orden(array $datos): void
    {
        $this->assertTrue(ModalidadRegistro::esConOrden($datos));
    }

    #[DataProvider('payloadsSinOrden')]
    public function test_detecta_registro_sin_orden(array $datos): void
    {
        $this->assertFalse(ModalidadRegistro::esConOrden($datos));
    }

    #[DataProvider('estrategiasDePrecio')]
    public function test_define_si_debe_usar_precio_mensual(array $datos, bool $esperado): void
    {
        $this->assertSame($esperado, ModalidadRegistro::debeUsarPrecioMensual($datos));
    }

    public static function payloadsConOrden(): array
    {
        return [
            'sesiones cubiertas' => [['sesiones_cubiertas' => 5]],
            'mes y dia' => [['mes' => 3, 'dia' => 15]],
            'payload completo' => [['sesiones_cubiertas' => 10, 'mes' => 6, 'dia' => 2]],
        ];
    }

    public static function payloadsSinOrden(): array
    {
        return [
            'kine sin orden' => [['cant_sesiones' => 5]],
            'general con combo' => [['cant_sesiones' => 4, 'id_actividad_combo' => 12]],
            'vacio' => [[]],
        ];
    }

    public static function estrategiasDePrecio(): array
    {
        return [
            'general sin orden' => [
                ['cant_sesiones' => 4, 'id_actividad_combo' => 3],
                true,
            ],
            'kine sin combo' => [
                ['cant_sesiones' => 5],
                false,
            ],
            'con orden con combo espurio' => [
                ['sesiones_cubiertas' => 5, 'id_actividad_combo' => 3],
                false,
            ],
        ];
    }
}
