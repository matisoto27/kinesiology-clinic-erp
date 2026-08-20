<?php

namespace Tests\Unit;

use App\Models\Actividad;
use App\Models\ActividadPaciente;
use App\Models\Paciente;
use App\Models\Pago;
use App\Models\Profesional;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActividadPacientePagoCubreTotalTest extends TestCase
{
    use RefreshDatabase;

    public function test_total_cero_queda_cubierto_sin_pagos(): void
    {
        $inscripcion = $this->crearInscripcion(total: 0, recargo: 0);

        $this->assertTrue($inscripcion->pagoCubreTotal(0));
    }

    public function test_pagos_iguales_al_total_sin_recargo_cubren(): void
    {
        $inscripcion = $this->crearInscripcion(total: 30000, recargo: 0);
        $this->registrarPago($inscripcion, 30000);

        $this->assertTrue($inscripcion->pagoCubreTotal(30000));
    }

    public function test_pagos_iguales_al_total_no_cubren_si_hay_recargo(): void
    {
        $inscripcion = $this->crearInscripcion(total: 30000, recargo: 3000);
        $this->registrarPago($inscripcion, 30000);

        $this->assertFalse($inscripcion->pagoCubreTotal(30000));
    }

    public function test_pagos_iguales_al_total_con_recargo_cubren(): void
    {
        $inscripcion = $this->crearInscripcion(total: 30000, recargo: 3000);
        $this->registrarPago($inscripcion, 33000);

        $this->assertTrue($inscripcion->pagoCubreTotal(30000));
    }

    public function test_pagos_del_total_anterior_no_cubren_un_total_nuevo_mayor(): void
    {
        $inscripcion = $this->crearInscripcion(total: 20000, recargo: 0);
        $this->registrarPago($inscripcion, 20000);

        $this->assertFalse($inscripcion->pagoCubreTotal(27500));
    }

    private function crearInscripcion(float $total, float $recargo): ActividadPaciente
    {
        $paciente = Paciente::create([
            'dni' => (string) random_int(10000000, 99999999),
            'nombre' => 'Nombre',
            'apellido' => 'Apellido',
            'fecha_nac' => '1990-01-01',
            'domicilio' => 'Calle 123',
            'telefono' => '1111111111',
            'profesion' => 'Profesion',
            'actividad_fisica' => 'Ninguna',
            'es_adulto_mayor' => false,
        ]);

        return ActividadPaciente::create([
            'id_actividad' => Actividad::GIMNASIO,
            'id_paciente' => $paciente->id,
            'cant_sesiones' => 8,
            'total_a_pagar' => $total,
            'monto_recargo' => $recargo,
            'pago_completado' => false,
        ]);
    }

    private function registrarPago(ActividadPaciente $inscripcion, float $monto): void
    {
        $profesional = Profesional::create([
            'dni' => (string) random_int(10000000, 99999999),
            'nombre' => 'Ana',
            'apellido' => 'García',
            'activo' => true,
        ]);

        Pago::create([
            'id_act_pac' => $inscripcion->id,
            'id_profesional' => $profesional->id,
            'metodo' => 'Efectivo',
            'monto' => $monto,
        ]);
    }
}
