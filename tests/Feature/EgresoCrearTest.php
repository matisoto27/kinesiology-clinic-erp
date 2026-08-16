<?php

namespace Tests\Feature;

use App\Models\Caja;
use App\Models\Egreso;
use App\Models\Profesional;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class EgresoCrearTest extends TestCase
{
    use RefreshDatabase;

    public function test_egreso_en_efectivo_descuenta_solo_el_saldo_de_caja(): void
    {
        $profesional = $this->crearProfesional();
        $this->crearCaja(saldoEfectivo: 10000, saldoTransferencia: 8000);

        Livewire::test('egresos.crear')
            ->set('id_profesional', $profesional->id)
            ->set('motivo', 'Compra de insumo')
            ->set('metodo', 'Efectivo')
            ->set('monto', 3500)
            ->call('almacenar')
            ->assertRedirect(route('movimientos'));

        $this->assertSame(1, Egreso::count());
        $egreso = Egreso::first();
        $this->assertSame('Efectivo', $egreso->metodo);
        $this->assertSame('3500.00', (string) $egreso->monto);

        $caja = Caja::first();
        $this->assertSame('6500.00', (string) $caja->saldo_efectivo);
        $this->assertSame('8000.00', (string) $caja->saldo_transferencia);
    }

    public function test_egreso_en_transferencia_descuenta_solo_el_saldo_de_transferencia(): void
    {
        $profesional = $this->crearProfesional();
        $this->crearCaja(saldoEfectivo: 10000, saldoTransferencia: 8000);

        Livewire::test('egresos.crear')
            ->set('id_profesional', $profesional->id)
            ->set('motivo', 'Pago a proveedor')
            ->set('metodo', 'Transferencia')
            ->set('monto', 2500)
            ->call('almacenar')
            ->assertRedirect(route('movimientos'));

        $this->assertSame(1, Egreso::count());
        $this->assertSame('Transferencia', Egreso::first()->metodo);

        $caja = Caja::first();
        $this->assertSame('10000.00', (string) $caja->saldo_efectivo);
        $this->assertSame('5500.00', (string) $caja->saldo_transferencia);
    }

    public function test_egreso_en_efectivo_no_puede_superar_el_saldo_de_caja(): void
    {
        $profesional = $this->crearProfesional();
        $this->crearCaja(saldoEfectivo: 1000, saldoTransferencia: 50000);

        Livewire::test('egresos.crear')
            ->set('id_profesional', $profesional->id)
            ->set('motivo', 'Compra de insumo')
            ->set('metodo', 'Efectivo')
            ->set('monto', 1000.01)
            ->call('almacenar')
            ->assertHasErrors(['monto']);

        $this->assertSame(0, Egreso::count());
        $this->assertSame('1000.00', (string) Caja::first()->saldo_efectivo);
    }

    public function test_egreso_en_transferencia_puede_superar_el_saldo_registrado(): void
    {
        $profesional = $this->crearProfesional();
        $this->crearCaja(saldoEfectivo: 10000, saldoTransferencia: 1000);

        Livewire::test('egresos.crear')
            ->set('id_profesional', $profesional->id)
            ->set('motivo', 'Transferencia al banco')
            ->set('metodo', 'Transferencia')
            ->set('monto', 2500)
            ->call('almacenar')
            ->assertHasNoErrors()
            ->assertRedirect(route('movimientos'));

        $this->assertSame(1, Egreso::count());
        $this->assertSame('-1500.00', (string) Caja::first()->saldo_transferencia);
        $this->assertSame('10000.00', (string) Caja::first()->saldo_efectivo);
    }

    public function test_egreso_en_efectivo_acepta_el_saldo_exacto_de_caja(): void
    {
        $profesional = $this->crearProfesional();
        $this->crearCaja(saldoEfectivo: 1500, saldoTransferencia: 0);

        Livewire::test('egresos.crear')
            ->set('id_profesional', $profesional->id)
            ->set('motivo', 'Cierre de caja')
            ->set('metodo', 'Efectivo')
            ->set('monto', 1500)
            ->call('almacenar')
            ->assertRedirect(route('movimientos'));

        $this->assertSame('0.00', (string) Caja::first()->saldo_efectivo);
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

    private function crearCaja(float $saldoEfectivo, float $saldoTransferencia): Caja
    {
        return Caja::create([
            'saldo_efectivo' => $saldoEfectivo,
            'saldo_transferencia' => $saldoTransferencia,
        ]);
    }
}
