<?php

namespace Tests\Feature;

use App\Models\Caja;
use App\Models\Ingreso;
use App\Models\Paciente;
use App\Models\Profesional;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class IngresoCrearTest extends TestCase
{
    use RefreshDatabase;

    public function test_ingreso_en_efectivo_incrementa_solo_el_saldo_de_caja(): void
    {
        $profesional = $this->crearProfesional();
        $paciente = $this->crearPaciente();
        $this->crearCaja(saldoEfectivo: 1000, saldoTransferencia: 2000);

        Livewire::test('ingresos.crear')
            ->set('idPacienteSeleccionado', $paciente->id)
            ->set('idProfesional', $profesional->id)
            ->set('motivo', 'Venta de almohadita')
            ->set('metodo', 'Efectivo')
            ->set('monto', 3500)
            ->call('almacenar')
            ->assertRedirect(route('movimientos'));

        $this->assertSame(1, Ingreso::count());
        $ingreso = Ingreso::first();
        $this->assertSame('Efectivo', $ingreso->metodo);
        $this->assertSame('3500.00', (string) $ingreso->monto);
        $this->assertSame($paciente->id, $ingreso->id_paciente);
        $this->assertSame($profesional->id, $ingreso->id_profesional);

        $caja = Caja::first();
        $this->assertSame('4500.00', (string) $caja->saldo_efectivo);
        $this->assertSame('2000.00', (string) $caja->saldo_transferencia);
    }

    public function test_ingreso_en_transferencia_incrementa_solo_el_saldo_de_transferencia(): void
    {
        $profesional = $this->crearProfesional();
        $paciente = $this->crearPaciente();
        $this->crearCaja(saldoEfectivo: 1000, saldoTransferencia: 2000);

        Livewire::test('ingresos.crear')
            ->set('idPacienteSeleccionado', $paciente->id)
            ->set('idProfesional', $profesional->id)
            ->set('motivo', 'Venta de almohadita')
            ->set('metodo', 'Transferencia')
            ->set('monto', 23000)
            ->call('almacenar')
            ->assertRedirect(route('movimientos'));

        $this->assertSame(1, Ingreso::count());
        $this->assertSame('Transferencia', Ingreso::first()->metodo);

        $caja = Caja::first();
        $this->assertSame('1000.00', (string) $caja->saldo_efectivo);
        $this->assertSame('25000.00', (string) $caja->saldo_transferencia);
    }

    public function test_seleccionar_paciente_desde_la_busqueda_permite_registrar_el_ingreso(): void
    {
        $profesional = $this->crearProfesional();
        $paciente = $this->crearPaciente(nombre: 'Luis', apellido: 'Pérez');
        $this->crearCaja(saldoEfectivo: 0, saldoTransferencia: 0);

        $componente = Livewire::test('ingresos.crear')
            ->set('busqueda', 'Pérez');

        $this->assertTrue($componente->instance()->resultadosBusqueda->contains('id', $paciente->id));

        $componente
            ->call('seleccionarSugerencia', $paciente->id, 'Pérez, Luis')
            ->assertSet('idPacienteSeleccionado', $paciente->id)
            ->assertSet('busqueda', 'Pérez, Luis')
            ->set('idProfesional', $profesional->id)
            ->set('motivo', 'Venta de almohadita')
            ->set('metodo', 'Efectivo')
            ->set('monto', 5000)
            ->call('almacenar')
            ->assertRedirect(route('movimientos'));

        $this->assertSame($paciente->id, Ingreso::first()->id_paciente);
    }

    public function test_limpiar_seleccion_resetea_busqueda_y_paciente_seleccionado(): void
    {
        $paciente = $this->crearPaciente();
        $this->crearCaja(saldoEfectivo: 0, saldoTransferencia: 0);

        Livewire::test('ingresos.crear')
            ->call('seleccionarSugerencia', $paciente->id, 'Apellido, Nombre')
            ->assertSet('idPacienteSeleccionado', $paciente->id)
            ->call('limpiarSeleccion')
            ->assertSet('idPacienteSeleccionado', null)
            ->assertSet('busqueda', '');
    }

    public function test_ingreso_no_tiene_limite_de_monto_y_puede_superar_el_saldo_actual_de_caja(): void
    {
        $profesional = $this->crearProfesional();
        $paciente = $this->crearPaciente();
        $this->crearCaja(saldoEfectivo: 100, saldoTransferencia: 0);

        Livewire::test('ingresos.crear')
            ->set('idPacienteSeleccionado', $paciente->id)
            ->set('idProfesional', $profesional->id)
            ->set('motivo', 'Venta de almohadita')
            ->set('metodo', 'Efectivo')
            ->set('monto', 999999)
            ->call('almacenar')
            ->assertHasNoErrors()
            ->assertRedirect(route('movimientos'));

        $this->assertSame(1, Ingreso::count());
        $this->assertSame('1000099.00', (string) Caja::first()->saldo_efectivo);
    }

    public function test_ingreso_requiere_paciente_profesional_metodo_monto_y_motivo(): void
    {
        $this->crearCaja(saldoEfectivo: 0, saldoTransferencia: 0);

        Livewire::test('ingresos.crear')
            ->call('almacenar')
            ->assertHasErrors(['idPacienteSeleccionado', 'idProfesional', 'metodo', 'monto', 'motivo']);

        $this->assertSame(0, Ingreso::count());
    }

    public function test_ingreso_no_acepta_monto_cero_o_negativo(): void
    {
        $profesional = $this->crearProfesional();
        $paciente = $this->crearPaciente();
        $this->crearCaja(saldoEfectivo: 0, saldoTransferencia: 0);

        Livewire::test('ingresos.crear')
            ->set('idPacienteSeleccionado', $paciente->id)
            ->set('idProfesional', $profesional->id)
            ->set('motivo', 'Venta de almohadita')
            ->set('metodo', 'Efectivo')
            ->set('monto', 0)
            ->call('almacenar')
            ->assertHasErrors(['monto']);

        $this->assertSame(0, Ingreso::count());
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

    private function crearPaciente(string $nombre = 'Luis', string $apellido = 'Pérez'): Paciente
    {
        return Paciente::create([
            'dni' => (string) random_int(10000000, 99999999),
            'nombre' => $nombre,
            'apellido' => $apellido,
            'fecha_nac' => '1990-01-01',
            'domicilio' => 'Calle 123',
            'telefono' => '1111111111',
            'profesion' => 'Profesión',
            'actividad_fisica' => 'Ninguna',
            'es_adulto_mayor' => false,
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
