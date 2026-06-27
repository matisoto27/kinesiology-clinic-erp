<?php

namespace Tests\Feature;

use App\Models\Actividad;
use App\Models\ActividadCombo;
use App\Models\ActividadPaciente;
use App\Models\Combo;
use App\Models\Horario;
use App\Models\Paciente;
use App\Models\Precio;
use App\Models\TipoActividad;
use App\Models\Turno;
use App\Services\ActividadPacienteService;
use App\Services\PlanDualService;
use Carbon\Carbon;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanDualInscripcionTest extends TestCase
{
    use RefreshDatabase;

    private ActividadPacienteService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(ActividadPacienteService::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_primera_inscripcion_dual_queda_pendiente_sin_cobro(): void
    {
        Carbon::setTestNow('2026-06-01 08:00:00');

        $this->crearActividadesGeneralesPlanDual(preciosPorFrecuencia: [2 => 20000.00]);
        $paciente = $this->crearPaciente();

        $inscripcion = $this->service->registrar($this->payloadPrimeraInscripcionDual(
            paciente: $paciente,
            frecuenciaSemanal: 2
        ));

        $this->assertTrue($inscripcion->plan_dual_pendiente);
        $this->assertSame('0.00', (string) $inscripcion->total_a_pagar);
        $this->assertFalse($inscripcion->pago_completado);
        $this->assertSame(8, $inscripcion->cant_sesiones);
        $this->assertNull($inscripcion->id_act_pac_dual);
        $this->assertNull($inscripcion->frecuencia_total_dual);
        $this->assertSame(Actividad::GIMNASIO, (int) $inscripcion->id_actividad);
        $this->assertCount(8, $inscripcion->turnos);
        $this->assertSame(1, ActividadPaciente::where('plan_dual_pendiente', true)->count());
    }

    public function test_segunda_inscripcion_dual_completa_plan_y_distribuye_totales(): void
    {
        Carbon::setTestNow('2026-06-01 08:00:00');

        $this->crearActividadesGeneralesPlanDual(preciosPorFrecuencia: [
            2 => 20000.00,
            3 => 30000.00,
        ]);
        $paciente = $this->crearPaciente();

        $primera = $this->service->registrar($this->payloadPrimeraInscripcionDual(
            paciente: $paciente,
            frecuenciaSemanal: 2
        ));

        $segunda = $this->service->registrar($this->payloadSegundaInscripcionDualManual(
            paciente: $paciente,
            frecuenciaSemanal: 1,
            turnos: $this->turnosViernes(4)
        ));

        $primera->refresh();
        $segunda->refresh();

        $this->assertFalse($primera->plan_dual_pendiente);
        $this->assertFalse($segunda->plan_dual_pendiente);
        $this->assertSame(3, $primera->frecuencia_total_dual);
        $this->assertSame(3, $segunda->frecuencia_total_dual);
        $this->assertSame($segunda->id, $primera->id_act_pac_dual);
        $this->assertSame($primera->id, $segunda->id_act_pac_dual);
        $this->assertSame('20000.00', (string) $primera->total_a_pagar);
        $this->assertSame('10000.00', (string) $segunda->total_a_pagar);
        $this->assertTrue($segunda->esDualCompleto());
        $this->assertSame(2, ActividadPaciente::count());
        $this->assertSame(12, Turno::count());
    }

    public function test_segunda_inscripcion_dual_falla_si_precios_gym_y_pilates_no_coinciden(): void
    {
        Carbon::setTestNow('2026-06-01 08:00:00');

        $this->crearActividadesGeneralesPlanDual(preciosPorFrecuencia: [
            2 => 20000.00,
            3 => 30000.00,
        ], precioPilatesPorFrecuencia: [
            3 => 31000.00,
        ]);
        $paciente = $this->crearPaciente();

        $this->service->registrar($this->payloadPrimeraInscripcionDual(
            paciente: $paciente,
            frecuenciaSemanal: 2
        ));

        try {
            $this->service->registrar($this->payloadSegundaInscripcionDualManual(
                paciente: $paciente,
                frecuenciaSemanal: 1,
                turnos: $this->turnosViernes(4)
            ));
            $this->fail('Se esperaba una excepción por precios desalineados del plan dual.');
        } catch (Exception $e) {
            $this->assertSame(
                sprintf(PlanDualService::MENSAJE_PRECIOS_NO_COINCIDEN, 3),
                $e->getMessage()
            );
        }

        $this->assertSame(1, ActividadPaciente::count());
        $this->assertTrue(ActividadPaciente::first()->plan_dual_pendiente);
    }

    public function test_segunda_inscripcion_dual_autogenerada_rechaza_dias_repetidos(): void
    {
        Carbon::setTestNow('2026-06-01 08:00:00');

        $this->crearActividadesGeneralesPlanDual(preciosPorFrecuencia: [
            2 => 20000.00,
            3 => 30000.00,
        ]);
        $paciente = $this->crearPaciente();

        $this->service->registrar($this->payloadPrimeraInscripcionDual(
            paciente: $paciente,
            frecuenciaSemanal: 2
        ));

        try {
            $this->service->registrar([
                'plan_dual' => true,
                'id_actividad' => Actividad::PILATES,
                'id_paciente' => $paciente->id,
                'autogenerados' => true,
                'fecha_ancla' => '2026-06-05',
                'frecuencia_semanal' => 1,
                'cant_sesiones' => 4,
                'turnos' => [
                    ['dia_semana' => 'Lunes', 'hora_inicio' => '10:00:00'],
                ],
            ]);
            $this->fail('Se esperaba una excepción por días repetidos entre inscripciones duales.');
        } catch (Exception $e) {
            $this->assertSame(
                'Los días de la segunda inscripción no pueden repetir días de la primera inscripción dual.',
                $e->getMessage()
            );
        }

        $this->assertSame(1, ActividadPaciente::count());
    }

    private function payloadPrimeraInscripcionDual(Paciente $paciente, int $frecuenciaSemanal): array
    {
        return [
            'plan_dual' => true,
            'id_actividad' => Actividad::GIMNASIO,
            'id_paciente' => $paciente->id,
            'autogenerados' => true,
            'fecha_ancla' => '2026-06-01',
            'frecuencia_semanal' => $frecuenciaSemanal,
            'cant_sesiones' => $frecuenciaSemanal * 4,
            'turnos' => [
                ['dia_semana' => 'Lunes', 'hora_inicio' => '10:00:00'],
                ['dia_semana' => 'Miércoles', 'hora_inicio' => '10:00:00'],
            ],
        ];
    }

    private function payloadSegundaInscripcionDualManual(
        Paciente $paciente,
        int $frecuenciaSemanal,
        array $turnos
    ): array {
        return [
            'plan_dual' => true,
            'id_actividad' => Actividad::PILATES,
            'id_paciente' => $paciente->id,
            'autogenerados' => false,
            'frecuencia_semanal' => $frecuenciaSemanal,
            'cant_sesiones' => $frecuenciaSemanal * 4,
            'turnos' => $turnos,
        ];
    }

    /**
     * @param  array<int, float>  $preciosPorFrecuencia
     * @param  array<int, float>  $precioPilatesPorFrecuencia
     */
    private function crearActividadesGeneralesPlanDual(
        array $preciosPorFrecuencia,
        array $precioPilatesPorFrecuencia = []
    ): void {
        $tipo = TipoActividad::create(['descripcion' => 'General']);

        $gimnasio = Actividad::create([
            'id' => Actividad::GIMNASIO,
            'nombre' => 'Gimnasio',
            'id_tipo_actividad' => $tipo->id,
        ]);

        $pilates = Actividad::create([
            'id' => Actividad::PILATES,
            'nombre' => 'Pilates',
            'id_tipo_actividad' => $tipo->id,
        ]);

        $horario = Horario::create([
            'hora_inicio' => '10:00:00',
            'franja' => 'M',
        ]);

        $gimnasio->horarios()->attach($horario->id);
        $pilates->horarios()->attach($horario->id);

        foreach ($preciosPorFrecuencia as $frecuencia => $precioGym) {
            $precioPilates = $precioPilatesPorFrecuencia[$frecuencia] ?? $precioGym;

            $this->crearComboMensual($gimnasio, $frecuencia, $precioGym);
            $this->crearComboMensual($pilates, $frecuencia, $precioPilates);
        }
    }

    private function crearComboMensual(Actividad $actividad, int $frecuenciaSemanal, float $precio): void
    {
        $cantidadSesiones = $frecuenciaSemanal * 4;

        $combo = Combo::create([
            'nombre' => "Combo mensual x{$frecuenciaSemanal} test " . uniqid(),
            'cantidad_sesiones' => $cantidadSesiones,
            'es_mensual' => true,
        ]);

        $actividadCombo = ActividadCombo::create([
            'id_actividad' => $actividad->id,
            'id_combo' => $combo->id,
            'activo' => true,
        ]);

        Precio::create([
            'id_actividad_combo' => $actividadCombo->id,
            'fecha_desde' => '2025-01-01',
            'valor' => $precio,
        ]);
    }

    private function crearPaciente(): Paciente
    {
        return Paciente::create([
            'dni' => fake()->unique()->numerify('########'),
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

    private function turnosViernes(int $cantidad): array
    {
        $turnos = [];
        $fecha = Carbon::parse('2026-06-05 10:00:00');

        for ($i = 0; $i < $cantidad; $i++) {
            $turnos[] = $fecha->copy()->addWeeks($i)->format('Y-m-d H:i:s');
        }

        return $turnos;
    }
}
