<?php

namespace Tests\Feature;

use App\Http\Resources\PacienteResource;
use App\Models\ObraSocial;
use App\Models\ObraSocialPaciente;
use App\Models\Paciente;
use App\Services\AfiliacionPacienteService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Livewire\Livewire;
use Tests\TestCase;

class AfiliacionObraSocialLibreTest extends TestCase
{
    use RefreshDatabase;

    private AfiliacionPacienteService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(AfiliacionPacienteService::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_persiste_obra_social_ajena_sin_crear_entidad_de_catalogo(): void
    {
        Carbon::setTestNow('2026-08-16 10:00:00');

        $paciente = $this->crearPaciente();
        $obrasAntes = ObraSocial::count();

        $this->service->sincronizar($paciente, null, 'OS Pepe');

        $this->assertSame($obrasAntes, ObraSocial::count());
        $this->assertSame(1, ObraSocialPaciente::count());

        $afiliacion = $paciente->fresh()->afiliacionVigente;
        $this->assertNull($afiliacion->id_obra_social);
        $this->assertSame('OS Pepe', $afiliacion->nombre_os);
        $this->assertSame('2026-08-16', $afiliacion->fecha_desde->format('Y-m-d'));
        $this->assertNull($afiliacion->fecha_hasta);
        $this->assertSame('OS Pepe', $afiliacion->nombre_mostrable);
    }

    public function test_quitar_obra_social_ajena_cierra_el_vinculo_vigente(): void
    {
        Carbon::setTestNow('2026-08-16 10:00:00');

        $paciente = $this->crearPaciente();
        $this->service->sincronizar($paciente, null, 'OS Pepe');

        $this->service->sincronizar($paciente, null, null);

        $cerrada = ObraSocialPaciente::first();
        $this->assertSame('2026-08-16', $cerrada->fecha_hasta->format('Y-m-d'));
        $this->assertNull($paciente->fresh()->afiliacionVigente);
    }

    public function test_volver_a_registrar_obra_social_ajena_crea_un_periodo_nuevo(): void
    {
        Carbon::setTestNow('2026-08-16 10:00:00');

        $paciente = $this->crearPaciente();
        $this->service->sincronizar($paciente, null, 'OS Pepe');
        $this->service->sincronizar($paciente, null, null);
        $this->service->sincronizar($paciente, null, 'OS Pepe');

        $this->assertSame(2, ObraSocialPaciente::count());

        $periodos = ObraSocialPaciente::orderBy('id')->get();
        $this->assertSame('2026-08-16', $periodos[0]->fecha_hasta->format('Y-m-d'));
        $this->assertNull($periodos[1]->fecha_hasta);
        $this->assertSame($periodos[1]->id, $paciente->fresh()->afiliacionVigente->id);
    }

    public function test_misma_obra_social_ajena_vigente_no_duplica_el_vinculo(): void
    {
        Carbon::setTestNow('2026-08-16 10:00:00');

        $paciente = $this->crearPaciente();
        $this->service->sincronizar($paciente, null, 'OS Pepe');
        $this->service->sincronizar($paciente, null, 'os pepe');

        $this->assertSame(1, ObraSocialPaciente::count());
        $this->assertNull(ObraSocialPaciente::first()->fecha_hasta);
    }

    public function test_pasar_de_catalogo_a_obra_social_ajena_cierra_y_abre_otro_periodo(): void
    {
        Carbon::setTestNow('2026-08-16 10:00:00');

        $paciente = $this->crearPaciente();
        $iapos = ObraSocial::create(['nombre' => 'IAPOS', 'activo' => true]);

        $this->service->sincronizar($paciente, $iapos->id, null);
        $this->service->sincronizar($paciente, null, 'OS Pepe');

        $periodos = ObraSocialPaciente::orderBy('id')->get();
        $this->assertCount(2, $periodos);
        $this->assertSame($iapos->id, $periodos[0]->id_obra_social);
        $this->assertNotNull($periodos[0]->fecha_hasta);
        $this->assertNull($periodos[1]->id_obra_social);
        $this->assertSame('OS Pepe', $periodos[1]->nombre_os);
        $this->assertNull($periodos[1]->fecha_hasta);
    }

    public function test_paciente_con_obra_social_ajena_no_tiene_cobertura_operativa(): void
    {
        $conLibre = $this->crearPaciente();
        $this->service->sincronizar($conLibre, null, 'OS Pepe');

        $conCatalogo = $this->crearPaciente();
        $iapos = ObraSocial::create(['nombre' => 'IAPOS', 'activo' => true]);
        $this->service->sincronizar($conCatalogo, $iapos->id, null);

        $this->assertFalse(Paciente::query()->tieneObraSocial()->whereKey($conLibre->id)->exists());
        $this->assertTrue(Paciente::query()->tieneObraSocial()->whereKey($conCatalogo->id)->exists());
    }

    public function test_resource_expone_el_nombre_libre_de_la_obra_social_ajena(): void
    {
        $paciente = $this->crearPaciente();
        $this->service->sincronizar($paciente, null, 'OS Pepe');

        $datos = (new PacienteResource(
            $paciente->fresh()->load([
                'afiliacionVigente.obraSocial',
                'contactosEmergencia',
                'patologias',
                'sintomasActivos',
            ])
        ))->resolve();

        $this->assertSame('OS Pepe', $datos['obra_social']);
    }

    public function test_no_permite_catalogo_y_nombre_libre_a_la_vez(): void
    {
        $paciente = $this->crearPaciente();
        $iapos = ObraSocial::create(['nombre' => 'IAPOS', 'activo' => true]);

        $this->expectException(InvalidArgumentException::class);

        $this->service->sincronizar($paciente, $iapos->id, 'OS Pepe');
    }

    public function test_crear_paciente_con_obra_social_ajena_no_ensucia_el_catalogo(): void
    {
        $obrasAntes = ObraSocial::count();

        Livewire::test('pacientes.crear')
            ->set('dni', '30111222')
            ->set('nombre', 'Juan')
            ->set('apellido', 'Perez')
            ->set('fecha_nac', '1990-01-01')
            ->set('domicilio', 'Calle 123')
            ->set('telefono', '3415555555')
            ->set('profesion', 'Docente')
            ->set('actividad_fisica', 'Sedentario')
            ->set('busquedaObraSocial', 'OS Pepe')
            ->call('usarObraSocialLibre')
            ->call('almacenar')
            ->assertRedirect(route('pacientes.inicio'));

        $this->assertSame($obrasAntes, ObraSocial::count());

        $paciente = Paciente::first();
        $afiliacion = $paciente->afiliacionVigente;
        $this->assertNull($afiliacion->id_obra_social);
        $this->assertSame('Os Pepe', $afiliacion->nombre_os);
    }

    public function test_usar_obra_social_libre_resuelve_a_catalogo_si_el_nombre_coincide(): void
    {
        $iapos = ObraSocial::create(['nombre' => 'IAPOS', 'activo' => true]);

        Livewire::test('pacientes.crear')
            ->set('busquedaObraSocial', 'iapos')
            ->call('usarObraSocialLibre')
            ->assertSet('obraSocialSeleccionada.id', $iapos->id)
            ->assertSet('obraSocialSeleccionada.nombre', 'IAPOS');
    }

    public function test_editar_paciente_cierra_la_obra_social_ajena_al_quitarla(): void
    {
        Carbon::setTestNow('2026-08-16 10:00:00');

        $paciente = $this->crearPaciente();
        $this->service->sincronizar($paciente, null, 'OS Pepe');

        Livewire::test('pacientes.editar', ['paciente' => $paciente])
            ->call('limpiarObraSocial')
            ->call('actualizar')
            ->assertRedirect(route('pacientes.inicio'));

        $this->assertNotNull(ObraSocialPaciente::first()->fecha_hasta);
        $this->assertNull($paciente->fresh()->afiliacionVigente);
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
            'actividad_fisica' => 'Sedentario',
            'es_adulto_mayor' => false,
        ]);
    }
}
