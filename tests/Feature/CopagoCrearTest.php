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

class CopagoCrearTest extends TestCase
{
    use RefreshDatabase;

    public function test_registra_un_copago_e_incrementa_el_saldo_correspondiente(): void
    {
        $profesional = $this->crearProfesional();
        $inscripcion = $this->crearInscripcionConTurno();
        $this->crearCaja(saldoEfectivo: 1000, saldoTransferencia: 2000);

        Livewire::test('pagos.copagos.crear')
            ->set('idActPac', (string) $inscripcion->id)
            ->set('idProfesional', (string) $profesional->id)
            ->set('metodo', 'Transferencia')
            ->set('montoStr', '7.000,00')
            ->call('almacenar')
            ->assertRedirect(route('movimientos'));

        $this->assertSame(1, Pago::count());
        $this->assertTrue((bool) Pago::first()->es_copago);

        $caja = Caja::first();
        $this->assertSame('1000.00', (string) $caja->saldo_efectivo);
        $this->assertSame('9000.00', (string) $caja->saldo_transferencia);
    }

    public function test_reenviar_el_formulario_mientras_se_procesa_no_duplica_el_copago(): void
    {
        $profesional = $this->crearProfesional();
        $inscripcion = $this->crearInscripcionConTurno();
        $this->crearCaja(saldoEfectivo: 1000, saldoTransferencia: 2000);

        $componente = Livewire::test('pagos.copagos.crear')
            ->set('idActPac', (string) $inscripcion->id)
            ->set('idProfesional', (string) $profesional->id)
            ->set('metodo', 'Transferencia')
            ->set('montoStr', '7.000,00');

        // Simula el reintento: el usuario vuelve a disparar "almacenar" mientras
        // el primer submit todavía está marcado como en curso (procesando = true).
        $componente->set('procesando', true);
        $componente->call('almacenar');

        $this->assertSame(0, Pago::count());

        $caja = Caja::first();
        $this->assertSame('2000.00', (string) $caja->saldo_transferencia);
    }

    public function test_luego_de_registrar_el_flag_procesando_vuelve_a_quedar_libre(): void
    {
        $profesional = $this->crearProfesional();
        $inscripcion = $this->crearInscripcionConTurno();
        $this->crearCaja(saldoEfectivo: 1000, saldoTransferencia: 2000);

        $componente = Livewire::test('pagos.copagos.crear')
            ->set('idActPac', (string) $inscripcion->id)
            ->set('idProfesional', (string) $profesional->id)
            ->set('metodo', 'Transferencia')
            ->set('montoStr', '7.000,00')
            ->call('almacenar');

        $this->assertFalse($componente->get('procesando'));
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

    private function crearInscripcionConTurno(): ActividadPaciente
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
            'fecha_emision_ord' => now(),
        ]);

        Turno::create([
            'id_act_pac' => $inscripcion->id,
            'fecha_hora' => now(),
        ]);

        return $inscripcion;
    }
}
