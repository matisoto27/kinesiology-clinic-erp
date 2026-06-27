<?php

namespace Tests\Unit;

use App\Models\Actividad;
use App\Models\ActividadCombo;
use App\Models\ActividadPaciente;
use App\Models\Combo;
use App\Models\Precio;
use App\Models\TipoActividad;
use App\Services\PlanDualService;
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

    public function test_calcular_totales_proporcionales_distribuye_precio_del_plan(): void
    {
        $totales = $this->service->calcularTotalesProporcionales(30000.00, 2, 1);

        $this->assertSame(3, $totales['frecuencia_total']);
        $this->assertSame(20000.00, $totales['total_primera']);
        $this->assertSame(10000.00, $totales['total_segunda']);
        $this->assertSame(30000.00, $totales['total_primera'] + $totales['total_segunda']);
    }

    public function test_calcular_totales_proporcionales_rechaza_frecuencia_total_invalida(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('La frecuencia total del plan no tiene un valor válido.');

        $this->service->calcularTotalesProporcionales(30000.00, 3, 3);
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

    public function test_preview_precio_segunda_visita_usa_combo_de_frecuencia_total(): void
    {
        $this->crearPreciosMensualesDual(frecuenciaTotal: 3, precioGym: 30000.00, precioPilates: 30000.00);

        $preview = $this->service->previewPrecioSegundaVisita(2, 1);

        $this->assertSame(3, $preview['frecuencia_total']);
        $this->assertSame(30000.00, $preview['precio_plan']);
        $this->assertSame(20000.00, $preview['total_primera']);
        $this->assertSame(10000.00, $preview['total_segunda']);
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

    private function crearPreciosMensualesDual(int $frecuenciaTotal, float $precioGym, float $precioPilates): void
    {
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

        $this->crearComboMensual($gimnasio, $frecuenciaTotal, $precioGym);
        $this->crearComboMensual($pilates, $frecuenciaTotal, $precioPilates);
    }

    private function crearComboMensual(Actividad $actividad, int $frecuenciaSemanal, float $precio): void
    {
        $combo = Combo::create([
            'nombre' => "Combo mensual x{$frecuenciaSemanal} unit " . uniqid(),
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
