<?php

namespace Tests\Feature;

use App\Models\Actividad;
use App\Models\ActividadPaciente;
use App\Models\Caja;
use App\Models\Egreso;
use App\Models\Paciente;
use App\Models\Pago;
use App\Models\Profesional;
use App\Models\Turno;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MovimientosTest extends TestCase
{
    use RefreshDatabase;

    public function test_filtro_de_transferencia_incluye_pagos_y_egresos(): void
    {
        $profesional = $this->crearProfesional();
        $this->crearCaja();
        $this->crearPago($profesional, 'Efectivo');
        $this->crearPago($profesional, 'Transferencia');
        $this->crearEgreso($profesional, 'Efectivo');
        $this->crearEgreso($profesional, 'Transferencia');

        Livewire::test('movimientos')
            ->set('filtroMetodo', 'transferencia')
            ->set('filtroTipo', 'todos')
            ->assertSee('Transferencia recibida')
            ->assertSee('Transferencia enviada')
            ->assertDontSee('Ingreso de Caja')
            ->assertDontSee('Egreso de Caja');
    }

    public function test_filtro_de_egreso_incluye_ambos_metodos(): void
    {
        $profesional = $this->crearProfesional();
        $this->crearCaja();
        $this->crearPago($profesional, 'Efectivo');
        $this->crearEgreso($profesional, 'Efectivo');
        $this->crearEgreso($profesional, 'Transferencia');

        Livewire::test('movimientos')
            ->set('filtroTipo', 'egreso')
            ->set('filtroMetodo', 'todos')
            ->assertSee('Egreso de Caja')
            ->assertSee('Transferencia enviada')
            ->assertDontSee('Ingreso de Caja');
    }

    public function test_filtro_de_egreso_en_transferencia_no_fuerza_ingresos(): void
    {
        $profesional = $this->crearProfesional();
        $this->crearCaja();
        $this->crearPago($profesional, 'Transferencia');
        $this->crearEgreso($profesional, 'Transferencia');
        $this->crearEgreso($profesional, 'Efectivo');

        Livewire::test('movimientos')
            ->set('filtroMetodo', 'transferencia')
            ->set('filtroTipo', 'egreso')
            ->assertSet('filtroTipo', 'egreso')
            ->assertSet('filtroMetodo', 'transferencia')
            ->assertSee('Transferencia enviada')
            ->assertDontSee('Transferencia recibida')
            ->assertDontSee('Egreso de Caja');
    }

    public function test_filtro_de_efectivo_mapea_al_valor_persistido(): void
    {
        $profesional = $this->crearProfesional();
        $this->crearCaja();
        $this->crearPago($profesional, 'Efectivo');
        $this->crearPago($profesional, 'Transferencia');

        Livewire::test('movimientos')
            ->set('filtroMetodo', 'efectivo')
            ->set('filtroTipo', 'ingreso')
            ->assertSee('Ingreso de Caja')
            ->assertDontSee('Transferencia recibida');
    }

    private function crearProfesional(): Profesional
    {
        return Profesional::create([
            'dni' => (string) random_int(10000000, 99999999),
            'nombre' => 'Ana',
            'apellido' => 'García',
            'activo' => true,
        ]);
    }

    private function crearCaja(): Caja
    {
        return Caja::create([
            'saldo_efectivo' => 0,
            'saldo_transferencia' => 0,
        ]);
    }

    private function crearPago(Profesional $profesional, string $metodo): Pago
    {
        $paciente = Paciente::create([
            'dni' => (string) random_int(10000000, 99999999),
            'nombre' => 'Luis',
            'apellido' => 'Pérez',
            'fecha_nac' => '1990-01-01',
            'domicilio' => 'Calle 123',
            'telefono' => '1111111111',
            'profesion' => 'Profesión',
            'actividad_fisica' => 'Ninguna',
            'es_adulto_mayor' => false,
        ]);

        $inscripcion = ActividadPaciente::create([
            'id_actividad' => Actividad::PILATES,
            'id_paciente' => $paciente->id,
            'cant_sesiones' => 8,
            'total_a_pagar' => 10000,
            'pago_completado' => false,
        ]);

        Turno::create([
            'id_act_pac' => $inscripcion->id,
            'fecha_hora' => '2026-06-01 10:00:00',
        ]);

        return Pago::create([
            'id_act_pac' => $inscripcion->id,
            'id_profesional' => $profesional->id,
            'metodo' => $metodo,
            'monto' => 1000,
        ]);
    }

    private function crearEgreso(Profesional $profesional, string $metodo): Egreso
    {
        return Egreso::create([
            'metodo' => $metodo,
            'monto' => 500,
            'motivo' => 'Motivo de prueba ' . $metodo,
            'id_profesional' => $profesional->id,
        ]);
    }
}
