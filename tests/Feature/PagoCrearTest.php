<?php

namespace Tests\Feature;

use App\Models\Actividad;
use App\Models\ActividadPaciente;
use App\Models\Caja;
use App\Models\Paciente;
use App\Models\Pago;
use App\Models\Profesional;
use App\Models\Turno;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PagoCrearTest extends TestCase
{
    use RefreshDatabase;

    public function test_pago_en_efectivo_incrementa_solo_el_saldo_de_caja(): void
    {
        $profesional = $this->crearProfesional();
        $inscripcion = $this->crearInscripcionPendiente(totalAPagar: 10000);
        $this->crearCaja(saldoEfectivo: 1000, saldoTransferencia: 2000);

        Livewire::test('pagos.crear')
            ->call('seleccionarInscripcion', $inscripcion->id)
            ->set('idProfesional', (string) $profesional->id)
            ->set('metodo', 'Efectivo')
            ->set('montoStr', '4.000,00')
            ->call('almacenar')
            ->assertRedirect(route('movimientos'));

        $this->assertSame(1, Pago::count());
        $this->assertSame('Efectivo', Pago::first()->metodo);

        $caja = Caja::first();
        $this->assertSame('5000.00', (string) $caja->saldo_efectivo);
        $this->assertSame('2000.00', (string) $caja->saldo_transferencia);
    }

    public function test_pago_en_transferencia_incrementa_solo_el_saldo_de_transferencia(): void
    {
        $profesional = $this->crearProfesional();
        $inscripcion = $this->crearInscripcionPendiente(totalAPagar: 10000);
        $this->crearCaja(saldoEfectivo: 1000, saldoTransferencia: 2000);

        Livewire::test('pagos.crear')
            ->call('seleccionarInscripcion', $inscripcion->id)
            ->set('idProfesional', (string) $profesional->id)
            ->set('metodo', 'Transferencia')
            ->set('montoStr', '4.000,00')
            ->call('almacenar')
            ->assertRedirect(route('movimientos'));

        $this->assertSame(1, Pago::count());
        $this->assertSame('Transferencia', Pago::first()->metodo);

        $caja = Caja::first();
        $this->assertSame('1000.00', (string) $caja->saldo_efectivo);
        $this->assertSame('6000.00', (string) $caja->saldo_transferencia);
    }

    private function crearProfesional(): Profesional
    {
        return Profesional::create([
            'dni' => (string) random_int(10000000, 99999999),
            'nombre' => 'Ana',
            'apellido' => 'García',
            'codigo_personal' => 'AG001',
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

    private function crearInscripcionPendiente(float $totalAPagar): ActividadPaciente
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
            'total_a_pagar' => $totalAPagar,
            'pago_completado' => false,
        ]);

        Turno::create([
            'id_act_pac' => $inscripcion->id,
            'fecha_hora' => '2026-06-01 10:00:00',
        ]);

        return $inscripcion;
    }
}
