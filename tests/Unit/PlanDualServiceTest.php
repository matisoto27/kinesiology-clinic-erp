<?php

namespace Tests\Unit;

use App\Models\Actividad;
use App\Models\ActividadCombo;
use App\Models\ActividadPaciente;
use App\Models\Combo;
use App\Models\Paciente;
use App\Models\Precio;
use App\Models\Turno;
use App\Services\PlanDualService;
use Carbon\Carbon;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanDualServiceTest extends TestCase
{
    use RefreshDatabase;

    private PlanDualService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(PlanDualService::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_preview_precio_segunda_visita_usa_combo_de_frecuencia_total(): void
    {
        $this->crearPreciosMensualesDual(frecuenciaTotal: 3, precioGym: 30000.00, precioPilates: 30000.00);

        $preview = $this->service->previewPrecioSegundaVisita(2, 1);

        $this->assertSame(3, $preview['frecuencia_total']);
        $this->assertSame(30000.00, $preview['precio_plan']);
    }

    public function test_validar_precios_coincidentes_no_lanza_excepcion_cuando_coinciden(): void
    {
        $this->crearPreciosMensualesDual(frecuenciaTotal: 3, precioGym: 30000.00, precioPilates: 30000.00);

        $this->service->validarPreciosCoincidentes(3);

        $this->assertSame(30000.00, $this->service->obtenerPrecioPlan(3));
    }

    public function test_validar_precios_coincidentes_exige_mismo_valor_en_gym_y_pilates(): void
    {
        $this->crearPreciosMensualesDual(frecuenciaTotal: 3, precioGym: 30000.00, precioPilates: 31000.00);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage(sprintf(PlanDualService::MENSAJE_PRECIOS_NO_COINCIDEN, 3));

        $this->service->validarPreciosCoincidentes(3);
    }

    public function test_frecuencias_permitidas_segunda_inscripcion_respetan_tope_de_cinco(): void
    {
        $inscripcion = new ActividadPaciente([
            'cant_sesiones' => 8,
        ]);

        $this->assertSame([1, 2, 3], $this->service->frecuenciasPermitidasSegundaInscripcion($inscripcion));
    }

    public function test_id_actividad_faltante_alterna_gimnasio_y_pilates(): void
    {
        $this->assertSame(Actividad::PILATES, $this->service->idActividadFaltante(Actividad::GIMNASIO));
        $this->assertSame(Actividad::GIMNASIO, $this->service->idActividadFaltante(Actividad::PILATES));
    }

    public function test_actividades_generales_disponibles_con_dual_pendiente_filtra_solo_la_faltante(): void
    {
        Carbon::setTestNow('2026-06-01 10:00:00');

        $paciente = $this->crearPaciente();

        $pendiente = ActividadPaciente::create([
            'id_actividad' => Actividad::GIMNASIO,
            'id_paciente' => $paciente->id,
            'cant_sesiones' => 4,
            'es_fijo' => false,
            'total_a_pagar' => 0,
            'plan_dual_pendiente' => true,
        ]);

        Turno::create([
            'id_act_pac' => $pendiente->id,
            'nro_turno' => 1,
            'fecha_hora' => '2026-06-05 10:00:00',
        ]);

        $disponibles = $this->service->actividadesGeneralesDisponibles($paciente);

        $this->assertCount(1, $disponibles);
        $this->assertSame(Actividad::PILATES, $disponibles->first()->id);
    }

    public function test_obtener_dual_pendiente_elimina_inscripcion_anticuada(): void
    {
        Carbon::setTestNow('2026-06-01 18:00:00');

        $paciente = $this->crearPaciente();

        $pendiente = ActividadPaciente::create([
            'id_actividad' => Actividad::GIMNASIO,
            'id_paciente' => $paciente->id,
            'cant_sesiones' => 4,
            'es_fijo' => false,
            'total_a_pagar' => 0,
            'plan_dual_pendiente' => true,
        ]);

        $pendiente->forceFill(['created_at' => '2026-06-01 08:00:00'])->save();

        $this->assertNull($this->service->obtenerDualPendiente($paciente->id));
        $this->assertSame(0, ActividadPaciente::count());
    }

    public function test_puede_iniciar_plan_dual_cuando_gimnasio_y_pilates_estan_disponibles(): void
    {
        Carbon::setTestNow('2026-06-01 10:00:00');

        $paciente = $this->crearPaciente();

        $this->assertTrue($this->service->puedeIniciarPlanDual($paciente));
    }

    public function test_no_puede_iniciar_plan_dual_si_falta_gimnasio_disponible(): void
    {
        Carbon::setTestNow('2026-06-01 10:00:00');

        $paciente = $this->crearPaciente();
        $this->crearInscripcionConUltimoTurno(
            $paciente,
            Actividad::GIMNASIO,
            '2026-06-20 10:00:00'
        );

        $disponibles = $paciente->obtenerActividadesGeneralesSinSuscripcion()->pluck('id');

        $this->assertFalse($disponibles->contains(Actividad::GIMNASIO));
        $this->assertTrue($disponibles->contains(Actividad::PILATES));
        $this->assertFalse($this->service->puedeIniciarPlanDual($paciente));
    }

    public function test_no_puede_iniciar_plan_dual_si_falta_pilates_disponible(): void
    {
        Carbon::setTestNow('2026-06-01 10:00:00');

        $paciente = $this->crearPaciente();
        $this->crearInscripcionConUltimoTurno(
            $paciente,
            Actividad::PILATES,
            '2026-06-20 10:00:00'
        );

        $this->assertFalse($this->service->puedeIniciarPlanDual($paciente));
    }

    public function test_no_puede_iniciar_plan_dual_con_pendiente_existente(): void
    {
        Carbon::setTestNow('2026-06-01 10:00:00');

        $paciente = $this->crearPaciente();

        ActividadPaciente::create([
            'id_actividad' => Actividad::GIMNASIO,
            'id_paciente' => $paciente->id,
            'cant_sesiones' => 4,
            'es_fijo' => false,
            'total_a_pagar' => 0,
            'plan_dual_pendiente' => true,
        ]);

        $this->assertFalse($this->service->puedeIniciarPlanDual($paciente));
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
            'profesion' => 'Profesión',
            'actividad_fisica' => 'Ninguna',
            'es_adulto_mayor' => false,
        ]);
    }

    private function crearInscripcionConUltimoTurno(
        Paciente $paciente,
        int $idActividad,
        string $fechaUltimoTurno
    ): ActividadPaciente {
        $actPac = ActividadPaciente::create([
            'id_actividad' => $idActividad,
            'id_paciente' => $paciente->id,
            'cant_sesiones' => 8,
            'es_fijo' => false,
            'total_a_pagar' => 1000,
            'plan_dual_pendiente' => false,
        ]);

        Turno::create([
            'id_act_pac' => $actPac->id,
            'nro_turno' => 1,
            'fecha_hora' => $fechaUltimoTurno,
        ]);

        return $actPac;
    }

    private function crearPreciosMensualesDual(int $frecuenciaTotal, float $precioGym, float $precioPilates): void
    {
        $gimnasio = Actividad::findOrFail(Actividad::GIMNASIO);
        $pilates = Actividad::findOrFail(Actividad::PILATES);

        $this->crearComboMensual($gimnasio, $frecuenciaTotal, $precioGym);
        $this->crearComboMensual($pilates, $frecuenciaTotal, $precioPilates);
    }

    private function crearComboMensual(Actividad $actividad, int $frecuenciaSemanal, float $precio): void
    {
        $combo = Combo::create([
            'nombre' => "CMx{$frecuenciaSemanal} T" . uniqid(),
            'cantidad_sesiones' => $frecuenciaSemanal * 4,
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
}
