<?php

namespace Tests\Feature;

use App\Models\Actividad;
use App\Models\ActividadPaciente;
use App\Models\Caja;
use App\Models\Paciente;
use App\Models\Pago;
use App\Models\Profesional;
use App\Models\Turno;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PagoCrearTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

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

    public function test_al_seleccionar_inscripcion_muestra_mes_ciclo_rango_y_saldo_de_esa_inscripcion(): void
    {
        Carbon::setTestNow('2026-06-10 12:00:00');

        $inscripcion = $this->crearInscripcionPendiente(
            totalAPagar: 15000,
            primerTurno: '2026-06-01 10:00:00',
            ultimoTurno: '2026-06-22 10:00:00',
        );

        Livewire::test('pagos.crear')
            ->call('seleccionarInscripcion', $inscripcion->id)
            ->assertSet('idActPac', (string) $inscripcion->id)
            ->assertSee('JUNIO 2026')
            ->assertSee('01/06/2026 → 22/06/2026')
            ->assertSee('Saldo pendiente de esta inscripción')
            ->assertSee('15.000,00')
            ->assertDontSee('Deuda total del paciente');
    }

    public function test_advierte_si_el_paciente_ya_tiene_transferencia_registrada_hoy(): void
    {
        Carbon::setTestNow('2026-06-10 15:00:00');

        $profesional = $this->crearProfesional();
        $paciente = $this->crearPaciente();

        $inscripcionPagada = $this->crearInscripcionParaPaciente(
            $paciente,
            totalAPagar: 10000,
            pagoCompletado: true,
            primerTurno: '2026-05-04 10:00:00',
        );

        Pago::create([
            'id_act_pac' => $inscripcionPagada->id,
            'id_profesional' => $profesional->id,
            'metodo' => 'Transferencia',
            'monto' => 10000,
        ]);

        $inscripcionPendiente = $this->crearInscripcionParaPaciente(
            $paciente,
            totalAPagar: 12000,
            pagoCompletado: false,
            primerTurno: '2026-06-01 10:00:00',
            ultimoTurno: '2026-06-22 10:00:00',
        );

        Livewire::test('pagos.crear')
            ->call('seleccionarInscripcion', $inscripcionPendiente->id)
            ->assertSee('Ya hay una transferencia de $10.000,00 registrada hoy')
            ->assertSee('MAYO 2026')
            ->assertSee('Pagos de este paciente (últimos 7 días)');
    }

    public function test_mensaje_de_confirmacion_incluye_monto_y_mes_del_ciclo(): void
    {
        Carbon::setTestNow('2026-06-10 12:00:00');

        $inscripcion = $this->crearInscripcionPendiente(
            totalAPagar: 10000,
            primerTurno: '2026-06-01 10:00:00',
        );

        $component = Livewire::test('pagos.crear')
            ->call('seleccionarInscripcion', $inscripcion->id)
            ->set('montoStr', '5.000,00');

        $this->assertSame(
            'Vas a imputar $5.000,00 a la inscripción de JUNIO 2026 (inicia 01/06/2026). ¿Continuar?',
            $component->instance()->mensajeConfirmacionPago
        );
    }

    public function test_sugerencias_priorizan_el_mes_del_ciclo_en_la_etiqueta(): void
    {
        Carbon::setTestNow('2026-06-10 12:00:00');

        $this->crearInscripcionPendiente(
            totalAPagar: 10000,
            primerTurno: '2026-06-01 10:00:00',
            apellido: 'Rubarth',
            nombre: 'Fernando',
        );

        Livewire::test('pagos.crear')
            ->set('busquedaInscripcion', 'Rubarth')
            ->assertSee('JUNIO 2026 ·')
            ->assertSee('Rubarth, Fernando')
            ->assertSee('(inicia 01/06/2026)');
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

    private function crearInscripcionPendiente(
        float $totalAPagar,
        string $primerTurno = '2026-06-01 10:00:00',
        ?string $ultimoTurno = null,
        string $nombre = 'Luis',
        string $apellido = 'Pérez',
    ): ActividadPaciente {
        return $this->crearInscripcionParaPaciente(
            $this->crearPaciente($nombre, $apellido),
            $totalAPagar,
            pagoCompletado: false,
            primerTurno: $primerTurno,
            ultimoTurno: $ultimoTurno,
        );
    }

    private function crearInscripcionParaPaciente(
        Paciente $paciente,
        float $totalAPagar,
        bool $pagoCompletado,
        string $primerTurno,
        ?string $ultimoTurno = null,
    ): ActividadPaciente {
        $inscripcion = ActividadPaciente::create([
            'id_actividad' => Actividad::PILATES,
            'id_paciente' => $paciente->id,
            'cant_sesiones' => 8,
            'total_a_pagar' => $totalAPagar,
            'pago_completado' => $pagoCompletado,
        ]);

        Turno::create([
            'id_act_pac' => $inscripcion->id,
            'fecha_hora' => $primerTurno,
        ]);

        if ($ultimoTurno !== null && $ultimoTurno !== $primerTurno) {
            Turno::create([
                'id_act_pac' => $inscripcion->id,
                'fecha_hora' => $ultimoTurno,
            ]);
        }

        return $inscripcion->fresh(['primerTurno', 'ultimoTurno', 'actividad', 'pacienteRegular']);
    }
}
