<?php

namespace Tests\Feature;

use App\Enums\RubroHorasProfesional;
use App\Exceptions\ReglaNegocioException;
use App\Models\Profesional;
use App\Models\RegistroHoras;
use App\Services\RegistroHorasService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RegistroHorasTest extends TestCase
{
    use RefreshDatabase;

    private RegistroHorasService $service;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'tarifas_profesionales.gimnasio' => 5000,
            'tarifas_profesionales.pilates' => 6000,
            'tarifas_profesionales.kinesiologia' => 7000,
        ]);

        $this->service = app(RegistroHorasService::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_registrar_persiste_total_segun_tarifa_del_rubro(): void
    {
        Carbon::setTestNow('2026-08-20');

        $profesional = $this->crearProfesional();

        $registro = $this->service->registrar(
            $profesional,
            RubroHorasProfesional::Pilates,
            3,
            Carbon::parse('2026-08-20')
        );

        $this->assertSame(RubroHorasProfesional::Pilates, $registro->rubro);
        $this->assertSame(3, $registro->cantidad_horas);
        $this->assertSame('18000.00', (string) $registro->total_a_cobrar);
        $this->assertSame(6000.0, $registro->valor_hora_aplicado);
    }

    public function test_registrar_rechaza_duplicado_mismo_profesional_fecha_y_rubro(): void
    {
        $profesional = $this->crearProfesional();
        $fecha = Carbon::parse('2026-08-20');

        $this->service->registrar($profesional, RubroHorasProfesional::Gimnasio, 2, $fecha);

        $this->expectException(ReglaNegocioException::class);
        $this->expectExceptionMessage('Gimnasio');

        $this->service->registrar($profesional, RubroHorasProfesional::Gimnasio, 4, $fecha);
    }

    public function test_registrar_permite_mismo_profesional_y_fecha_en_rubros_distintos(): void
    {
        $profesional = $this->crearProfesional();
        $fecha = Carbon::parse('2026-08-20');

        $this->service->registrar($profesional, RubroHorasProfesional::Gimnasio, 2, $fecha);
        $this->service->registrar($profesional, RubroHorasProfesional::Pilates, 1, $fecha);

        $this->assertSame(2, RegistroHoras::count());
    }

    public function test_actualizar_cantidad_horas_usa_tarifa_congelada(): void
    {
        $profesional = $this->crearProfesional();
        $registro = $this->service->registrar(
            $profesional,
            RubroHorasProfesional::Kinesiologia,
            2,
            Carbon::parse('2026-08-20')
        );

        config(['tarifas_profesionales.kinesiologia' => 9999]);

        $this->service->actualizarCantidadHoras($registro, 4);

        $registro->refresh();
        $this->assertSame(4, $registro->cantidad_horas);
        $this->assertSame('28000.00', (string) $registro->total_a_cobrar);
        $this->assertSame(7000.0, $registro->valor_hora_aplicado);
    }

    public function test_livewire_crear_registra_horas_trabajadas(): void
    {
        Carbon::setTestNow('2026-08-20');

        $profesional = $this->crearProfesional();

        Livewire::test('profesionales.horas-trabajadas.crear')
            ->set('idProfesional', $profesional->id)
            ->set('rubro', RubroHorasProfesional::Gimnasio->value)
            ->set('cantidadHoras', 2)
            ->set('fechaTrabajada', '2026-08-20')
            ->call('almacenar')
            ->assertHasNoErrors();

        $registro = RegistroHoras::first();
        $this->assertNotNull($registro);
        $this->assertSame(RubroHorasProfesional::Gimnasio, $registro->rubro);
        $this->assertSame('10000.00', (string) $registro->total_a_cobrar);
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
}
