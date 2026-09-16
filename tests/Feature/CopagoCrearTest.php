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
use Illuminate\Support\Facades\Config;
use Livewire\Livewire;
use Tests\TestCase;

class CopagoCrearTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('precios.copago', 7000);
    }

    public function test_lista_registros_con_primer_turno_dentro_de_dos_semanas(): void
    {
        \Carbon\Carbon::setTestNow('2026-06-01 10:00:00');

        $dentro = $this->crearInscripcionConTurno(cantSesiones: 5);
        $dentro->turnos()->first()->update(['fecha_hora' => '2026-06-10 10:00:00']);

        $fuera = $this->crearInscripcionConTurno(cantSesiones: 5);
        $fuera->turnos()->first()->update(['fecha_hora' => '2026-06-20 10:00:00']);

        $ids = Livewire::test('pagos.copagos.crear')
            ->instance()
            ->actividadesPacientes
            ->pluck('id')
            ->all();

        $this->assertContains($dentro->id, $ids);
        $this->assertNotContains($fuera->id, $ids);

        \Carbon\Carbon::setTestNow();
    }

    public function test_monto_por_defecto_viene_de_config(): void
    {
        Livewire::test('pagos.copagos.crear')
            ->assertSet('montoStr', '7.000,00')
            ->assertSet('monto', 7000.0);
    }

    public function test_registra_un_copago_e_incrementa_el_saldo_correspondiente(): void
    {
        $profesional = $this->crearProfesional();
        $inscripcion = $this->crearInscripcionConTurno(cantSesiones: 5);
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
        $inscripcion = $this->crearInscripcionConTurno(cantSesiones: 5);
        $this->crearCaja(saldoEfectivo: 1000, saldoTransferencia: 2000);

        $componente = Livewire::test('pagos.copagos.crear')
            ->set('idActPac', (string) $inscripcion->id)
            ->set('idProfesional', (string) $profesional->id)
            ->set('metodo', 'Transferencia')
            ->set('montoStr', '7.000,00');

        $componente->set('procesando', true);
        $componente->call('almacenar');

        $this->assertSame(0, Pago::count());

        $caja = Caja::first();
        $this->assertSame('2000.00', (string) $caja->saldo_transferencia);
    }

    public function test_luego_de_registrar_el_flag_procesando_vuelve_a_quedar_libre(): void
    {
        $profesional = $this->crearProfesional();
        $inscripcion = $this->crearInscripcionConTurno(cantSesiones: 5);
        $this->crearCaja(saldoEfectivo: 1000, saldoTransferencia: 2000);

        $componente = Livewire::test('pagos.copagos.crear')
            ->set('idActPac', (string) $inscripcion->id)
            ->set('idProfesional', (string) $profesional->id)
            ->set('metodo', 'Transferencia')
            ->set('montoStr', '7.000,00')
            ->call('almacenar');

        $this->assertFalse($componente->get('procesando'));
    }

    public function test_lista_registros_con_orden_pendiente_sentinel(): void
    {
        $pendiente = $this->crearInscripcionConTurno(
            fechaEmisionOrd: ActividadPaciente::FECHA_ORDEN_PENDIENTE,
            cantSesiones: 5
        );
        $cargada = $this->crearInscripcionConTurno(
            fechaEmisionOrd: now()->toDateString(),
            cantSesiones: 5
        );

        $componente = Livewire::test('pagos.copagos.crear');

        $ids = $componente->instance()->actividadesPacientes->pluck('id')->all();

        $this->assertEqualsCanonicalizing([$pendiente->id, $cargada->id], $ids);
    }

    public function test_no_lista_registros_que_ya_tienen_todos_los_copagos(): void
    {
        $conCupo = $this->crearInscripcionConTurno(cantSesiones: 2);
        $sinCupo = $this->crearInscripcionConTurno(cantSesiones: 2);
        $profesional = $this->crearProfesional();

        Pago::create([
            'id_act_pac' => $sinCupo->id,
            'id_profesional' => $profesional->id,
            'metodo' => 'Efectivo',
            'monto' => 7000,
            'es_copago' => true,
        ]);
        Pago::create([
            'id_act_pac' => $sinCupo->id,
            'id_profesional' => $profesional->id,
            'metodo' => 'Efectivo',
            'monto' => 7000,
            'es_copago' => true,
        ]);

        $ids = Livewire::test('pagos.copagos.crear')
            ->instance()
            ->actividadesPacientes
            ->pluck('id')
            ->all();

        $this->assertSame([$conCupo->id], $ids);
    }

    public function test_rechaza_registrar_mas_copagos_que_cant_sesiones(): void
    {
        $profesional = $this->crearProfesional();
        $inscripcion = $this->crearInscripcionConTurno(cantSesiones: 2);
        $this->crearCaja(saldoEfectivo: 0, saldoTransferencia: 0);

        Pago::create([
            'id_act_pac' => $inscripcion->id,
            'id_profesional' => $profesional->id,
            'metodo' => 'Efectivo',
            'monto' => 7000,
            'es_copago' => true,
        ]);
        Pago::create([
            'id_act_pac' => $inscripcion->id,
            'id_profesional' => $profesional->id,
            'metodo' => 'Efectivo',
            'monto' => 7000,
            'es_copago' => true,
        ]);

        Livewire::test('pagos.copagos.crear')
            ->set('idActPac', (string) $inscripcion->id)
            ->set('idProfesional', (string) $profesional->id)
            ->set('metodo', 'Efectivo')
            ->set('montoStr', '7.000,00')
            ->call('almacenar')
            ->assertHasErrors(['idActPac']);

        $this->assertSame(2, Pago::where('es_copago', true)->count());
    }

    public function test_pagos_que_no_son_copago_no_consumen_el_cupo(): void
    {
        $profesional = $this->crearProfesional();
        $inscripcion = $this->crearInscripcionConTurno(cantSesiones: 1);
        $this->crearCaja(saldoEfectivo: 0, saldoTransferencia: 0);

        Pago::create([
            'id_act_pac' => $inscripcion->id,
            'id_profesional' => $profesional->id,
            'metodo' => 'Efectivo',
            'monto' => 1000,
            'es_copago' => false,
        ]);

        Livewire::test('pagos.copagos.crear')
            ->set('idActPac', (string) $inscripcion->id)
            ->set('idProfesional', (string) $profesional->id)
            ->set('metodo', 'Efectivo')
            ->set('montoStr', '7.000,00')
            ->call('almacenar')
            ->assertRedirect(route('movimientos'));

        $this->assertSame(1, Pago::where('es_copago', true)->count());
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

    private function crearInscripcionConTurno(
        ?string $fechaEmisionOrd = null,
        int $cantSesiones = 8
    ): ActividadPaciente {
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
            'cant_sesiones' => $cantSesiones,
            'total_a_pagar' => 10000,
            'pago_completado' => false,
            'fecha_emision_ord' => $fechaEmisionOrd ?? now(),
        ]);

        Turno::create([
            'id_act_pac' => $inscripcion->id,
            'fecha_hora' => now(),
        ]);

        return $inscripcion;
    }
}
