<?php

namespace Tests\Feature;

use App\Models\Actividad;
use App\Models\ActividadPaciente;
use App\Models\Caja;
use App\Models\CobroExterno;
use App\Models\Paciente;
use App\Models\Pago;
use App\Models\Profesional;
use App\Models\Turno;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ActividadesPacientesCobroExternoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.codigo_cobro_externo' => 'clave-secreta']);
    }

    public function test_admin_con_clave_correcta_marca_pago_sin_crear_pago_ni_mover_caja(): void
    {
        $inscripcion = $this->crearInscripcionKinesiologia(totalAPagar: 15000);
        $this->crearCaja(saldoEfectivo: 1000, saldoTransferencia: 2000);
        $this->iniciarSesionAdmin();

        Livewire::test('actividades-pacientes.inicio')
            ->call('verDetalles', $inscripcion->id)
            ->call('abrirFormularioCobroExterno')
            ->set('claveCobroExterno', 'clave-secreta')
            ->call('confirmarCobroExterno')
            ->assertSet('mostrarModal', false);

        $this->assertTrue($inscripcion->fresh()->pago_completado);
        $this->assertSame(1, CobroExterno::count());
        $this->assertSame('15000.00', (string) CobroExterno::first()->monto);
        $this->assertSame($inscripcion->id, CobroExterno::first()->id_act_pac);
        $this->assertSame(0, Pago::count());

        $caja = Caja::first();
        $this->assertSame('1000.00', (string) $caja->saldo_efectivo);
        $this->assertSame('2000.00', (string) $caja->saldo_transferencia);
    }

    public function test_monto_del_cobro_externo_refleja_la_deuda_restante(): void
    {
        $inscripcion = $this->crearInscripcionKinesiologia(totalAPagar: 20000);
        $profesional = $this->crearProfesional();
        $this->iniciarSesionAdmin();

        Pago::create([
            'id_act_pac' => $inscripcion->id,
            'id_profesional' => $profesional->id,
            'metodo' => 'Efectivo',
            'monto' => 5000,
        ]);

        Livewire::test('actividades-pacientes.inicio')
            ->call('verDetalles', $inscripcion->id)
            ->call('abrirFormularioCobroExterno')
            ->set('claveCobroExterno', 'clave-secreta')
            ->call('confirmarCobroExterno');

        $this->assertSame('15000.00', (string) CobroExterno::first()->monto);
        $this->assertTrue($inscripcion->fresh()->pago_completado);
        $this->assertSame(1, Pago::count());
    }

    public function test_clave_incorrecta_no_modifica_nada(): void
    {
        $inscripcion = $this->crearInscripcionKinesiologia(totalAPagar: 10000);
        $this->iniciarSesionAdmin();

        Livewire::test('actividades-pacientes.inicio')
            ->call('verDetalles', $inscripcion->id)
            ->call('abrirFormularioCobroExterno')
            ->set('claveCobroExterno', 'clave-errada')
            ->call('confirmarCobroExterno')
            ->assertHasErrors(['claveCobroExterno'])
            ->assertSet('mostrarModal', true);

        $this->assertFalse($inscripcion->fresh()->pago_completado);
        $this->assertSame(0, CobroExterno::count());
        $this->assertSame(0, Pago::count());
    }

    public function test_sin_sesion_admin_no_permite_abrir_ni_confirmar(): void
    {
        $inscripcion = $this->crearInscripcionKinesiologia(totalAPagar: 10000);

        Livewire::test('actividades-pacientes.inicio')
            ->call('verDetalles', $inscripcion->id)
            ->assertDontSee('Marcar pago completado')
            ->call('abrirFormularioCobroExterno')
            ->assertSet('mostrarFormularioCobroExterno', false)
            ->set('claveCobroExterno', 'clave-secreta')
            ->call('confirmarCobroExterno')
            ->assertSet('mostrarModal', false);

        $this->assertFalse($inscripcion->fresh()->pago_completado);
        $this->assertSame(0, CobroExterno::count());
    }

    public function test_actividad_general_no_permite_cobro_externo(): void
    {
        $inscripcion = $this->crearInscripcion(Actividad::PILATES, totalAPagar: 10000);
        $this->iniciarSesionAdmin();

        Livewire::test('actividades-pacientes.inicio')
            ->call('verDetalles', $inscripcion->id)
            ->assertDontSee('Marcar pago completado')
            ->call('abrirFormularioCobroExterno')
            ->assertSet('mostrarFormularioCobroExterno', false)
            ->set('claveCobroExterno', 'clave-secreta')
            ->call('confirmarCobroExterno');

        $this->assertFalse($inscripcion->fresh()->pago_completado);
        $this->assertSame(0, CobroExterno::count());
    }

    public function test_inscripcion_ya_completada_no_permite_cobro_externo(): void
    {
        $inscripcion = $this->crearInscripcionKinesiologia(totalAPagar: 10000, pagoCompletado: true);
        $this->iniciarSesionAdmin();

        Livewire::test('actividades-pacientes.inicio')
            ->call('verDetalles', $inscripcion->id)
            ->assertDontSee('Marcar pago completado')
            ->call('abrirFormularioCobroExterno')
            ->assertSet('mostrarFormularioCobroExterno', false);

        $this->assertSame(0, CobroExterno::count());
    }

    public function test_con_sesion_admin_muestra_el_boton_en_kinesiologia_pendiente(): void
    {
        $inscripcion = $this->crearInscripcionKinesiologia(totalAPagar: 10000);
        $this->iniciarSesionAdmin();

        Livewire::test('actividades-pacientes.inicio')
            ->call('verDetalles', $inscripcion->id)
            ->assertSee('Marcar pago completado');
    }

    public function test_codigo_env_vacio_rechaza_la_confirmacion(): void
    {
        config(['app.codigo_cobro_externo' => '']);

        $inscripcion = $this->crearInscripcionKinesiologia(totalAPagar: 10000);
        $this->iniciarSesionAdmin();

        Livewire::test('actividades-pacientes.inicio')
            ->call('verDetalles', $inscripcion->id)
            ->call('abrirFormularioCobroExterno')
            ->set('claveCobroExterno', 'cualquier-cosa')
            ->call('confirmarCobroExterno')
            ->assertHasErrors(['claveCobroExterno']);

        $this->assertFalse($inscripcion->fresh()->pago_completado);
        $this->assertSame(0, CobroExterno::count());
    }

    private function iniciarSesionAdmin(): void
    {
        session([
            'acceso_admin' => true,
            'timestamp_ingreso' => now()->timestamp,
        ]);
    }

    private function crearInscripcionKinesiologia(
        float $totalAPagar,
        bool $pagoCompletado = false,
    ): ActividadPaciente {
        return $this->crearInscripcion(
            Actividad::KINESIOLOGIA_CONVENCIONAL,
            $totalAPagar,
            $pagoCompletado,
        );
    }

    private function crearInscripcion(
        int $idActividad,
        float $totalAPagar,
        bool $pagoCompletado = false,
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
            'id_actividad' => $idActividad,
            'id_paciente' => $paciente->id,
            'cant_sesiones' => 10,
            'total_a_pagar' => $totalAPagar,
            'pago_completado' => $pagoCompletado,
        ]);

        Turno::create([
            'id_act_pac' => $inscripcion->id,
            'fecha_hora' => now()->subDay(),
        ]);

        return $inscripcion->fresh(['actividad']);
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
