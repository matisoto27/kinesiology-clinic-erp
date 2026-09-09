<?php

namespace Tests\Unit;

use App\Models\Paciente;
use App\Models\PrecioMensual;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrecioMensualGympassTest extends TestCase
{
    use RefreshDatabase;

    public function test_obtener_vigente_para_paciente_devuelve_cero_si_es_gympass(): void
    {
        PrecioMensual::create([
            'frecuencia_semanal' => 2,
            'fecha_desde' => '2025-01-01',
            'valor' => 20000,
        ]);

        $paciente = $this->crearPaciente(['es_gympass' => true]);

        $this->assertSame(0.0, PrecioMensual::obtenerVigenteParaPaciente($paciente, 2));
    }

    public function test_obtener_vigente_para_paciente_usa_la_lista_si_no_es_gympass(): void
    {
        PrecioMensual::create([
            'frecuencia_semanal' => 2,
            'fecha_desde' => '2025-01-01',
            'valor' => 20000,
        ]);

        $paciente = $this->crearPaciente();

        $this->assertSame(20000.0, PrecioMensual::obtenerVigenteParaPaciente($paciente, 2));
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function crearPaciente(array $extra = []): Paciente
    {
        return Paciente::create(array_merge([
            'dni' => (string) random_int(10000000, 99999999),
            'nombre' => 'Nombre',
            'apellido' => 'Apellido',
            'fecha_nac' => '1990-01-01',
            'domicilio' => 'Calle 123',
            'telefono' => '1111111111',
            'profesion' => 'Profesion',
            'actividad_fisica' => 'Ninguna',
            'es_adulto_mayor' => false,
        ], $extra));
    }
}
