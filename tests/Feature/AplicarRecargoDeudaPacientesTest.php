<?php

namespace Tests\Feature;

use App\Models\Actividad;
use App\Models\ActividadPaciente;
use App\Models\Paciente;
use App\Models\Turno;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class AplicarRecargoDeudaPacientesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.recargo_mora' => 0.15]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_aplica_recargo_a_pilates_simple_luego_de_la_cortesia(): void
    {
        $inscripcion = $this->crearInscripcion(
            Actividad::PILATES,
            total: 15000,
            primerTurno: '2026-06-01 10:00:00'
        );

        Carbon::setTestNow('2026-06-12 08:00:00');
        Artisan::call('app:aplicar-recargo-deuda-pacientes');

        $inscripcion->refresh();
        $this->assertSame('2026-06-12', $inscripcion->fecha_recargo->format('Y-m-d'));
        $this->assertSame('2250.00', (string) $inscripcion->monto_recargo);
        $this->assertSame('15.00', (string) $inscripcion->porcentaje_recargo);
    }

    public function test_aplica_recargo_a_gimnasio_simple_impago(): void
    {
        $inscripcion = $this->crearInscripcion(
            Actividad::GIMNASIO,
            total: 20000,
            primerTurno: '2026-06-01 10:00:00'
        );

        Carbon::setTestNow('2026-06-12 08:00:00');
        Artisan::call('app:aplicar-recargo-deuda-pacientes');

        $inscripcion->refresh();
        $this->assertNotNull($inscripcion->fecha_recargo);
        $this->assertSame('3000.00', (string) $inscripcion->monto_recargo);
    }

    public function test_no_aplica_recargo_dentro_de_la_cortesia(): void
    {
        $inscripcion = $this->crearInscripcion(
            Actividad::GIMNASIO,
            total: 20000,
            primerTurno: '2026-06-01 10:00:00'
        );

        Carbon::setTestNow('2026-06-11 08:00:00');
        Artisan::call('app:aplicar-recargo-deuda-pacientes');

        $this->assertNull($inscripcion->fresh()->fecha_recargo);
    }

    public function test_dual_aplica_recargo_solo_en_la_inscripcion_de_cobro(): void
    {
        [$gimnasio, $pilates] = $this->crearParDual(
            primerGym: '2026-06-01 10:00:00',
            primerPilates: '2026-06-03 10:00:00',
            totalGym: 30000,
            totalPilates: 0
        );

        Carbon::setTestNow('2026-06-12 08:00:00');
        Artisan::call('app:aplicar-recargo-deuda-pacientes');

        $gimnasio->refresh();
        $pilates->refresh();

        $this->assertNotNull($gimnasio->fecha_recargo);
        $this->assertSame('4500.00', (string) $gimnasio->monto_recargo);
        $this->assertNull($pilates->fecha_recargo);
        $this->assertNull($pilates->monto_recargo);
    }

    public function test_dual_usa_el_primer_turno_del_par_para_la_cortesia(): void
    {
        [$gimnasio] = $this->crearParDual(
            primerGym: '2026-06-08 10:00:00',
            primerPilates: '2026-06-01 10:00:00',
            totalGym: 30000,
            totalPilates: 0
        );

        // Gym solo vencería el 19/6 (8/6 + 10). El ancla del par es el 1/6 → cortesía hasta el 11/6.
        Carbon::setTestNow('2026-06-11 08:00:00');
        Artisan::call('app:aplicar-recargo-deuda-pacientes');
        $this->assertNull($gimnasio->fresh()->fecha_recargo);

        Carbon::setTestNow('2026-06-12 08:00:00');
        Artisan::call('app:aplicar-recargo-deuda-pacientes');
        $this->assertNotNull($gimnasio->fresh()->fecha_recargo);
    }

    public function test_no_reaplica_recargo_si_ya_tiene_fecha_recargo(): void
    {
        $inscripcion = $this->crearInscripcion(
            Actividad::PILATES,
            total: 15000,
            primerTurno: '2026-06-01 10:00:00'
        );
        $inscripcion->update([
            'fecha_recargo' => '2026-06-12',
            'porcentaje_recargo' => 15,
            'monto_recargo' => 2250,
        ]);

        Carbon::setTestNow('2026-07-01 08:00:00');
        Artisan::call('app:aplicar-recargo-deuda-pacientes');

        $inscripcion->refresh();
        $this->assertSame('2026-06-12', $inscripcion->fecha_recargo->format('Y-m-d'));
        $this->assertSame('2250.00', (string) $inscripcion->monto_recargo);
    }

    /**
     * @return array{0: ActividadPaciente, 1: ActividadPaciente}
     */
    private function crearParDual(
        string $primerGym,
        string $primerPilates,
        float $totalGym,
        float $totalPilates
    ): array {
        $paciente = $this->crearPaciente();

        $gimnasio = ActividadPaciente::create([
            'id_actividad' => Actividad::GIMNASIO,
            'id_paciente' => $paciente->id,
            'cant_sesiones' => 8,
            'total_a_pagar' => $totalGym,
            'pago_completado' => $totalGym <= 0,
            'frecuencia_total_dual' => 3,
        ]);
        $pilates = ActividadPaciente::create([
            'id_actividad' => Actividad::PILATES,
            'id_paciente' => $paciente->id,
            'cant_sesiones' => 4,
            'total_a_pagar' => $totalPilates,
            'pago_completado' => $totalPilates <= 0,
            'frecuencia_total_dual' => 3,
        ]);

        $gimnasio->update(['id_act_pac_dual' => $pilates->id]);
        $pilates->update(['id_act_pac_dual' => $gimnasio->id]);

        $this->crearTurnoOriginal($gimnasio->id, $primerGym);
        $this->crearTurnoOriginal($pilates->id, $primerPilates);

        return [$gimnasio->fresh(), $pilates->fresh()];
    }

    private function crearInscripcion(int $idActividad, float $total, string $primerTurno): ActividadPaciente
    {
        $inscripcion = ActividadPaciente::create([
            'id_actividad' => $idActividad,
            'id_paciente' => $this->crearPaciente()->id,
            'cant_sesiones' => 8,
            'total_a_pagar' => $total,
            'pago_completado' => false,
        ]);

        $this->crearTurnoOriginal($inscripcion->id, $primerTurno);

        return $inscripcion;
    }

    private function crearTurnoOriginal(int $idActPac, string $fechaHora): void
    {
        Turno::create([
            'id_act_pac' => $idActPac,
            'fecha_hora' => $fechaHora,
            'estado' => 'Ausente',
        ]);
    }

    private function crearPaciente(): Paciente
    {
        return Paciente::create([
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
    }
}
