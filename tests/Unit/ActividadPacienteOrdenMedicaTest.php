<?php

namespace Tests\Unit;

use App\Models\Actividad;
use App\Models\ActividadPaciente;
use App\Models\Paciente;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActividadPacienteOrdenMedicaTest extends TestCase
{
    use RefreshDatabase;

    public function test_normalizar_fecha_orden_vacia_usa_el_sentinel_pendiente(): void
    {
        $this->assertSame(
            ActividadPaciente::FECHA_ORDEN_PENDIENTE,
            ActividadPaciente::normalizarFechaOrden(null)
        );
        $this->assertSame(
            ActividadPaciente::FECHA_ORDEN_PENDIENTE,
            ActividadPaciente::normalizarFechaOrden('')
        );
        $this->assertSame(
            ActividadPaciente::FECHA_ORDEN_PENDIENTE,
            ActividadPaciente::normalizarFechaOrden('   ')
        );
    }

    public function test_normalizar_fecha_orden_real_devuelve_ymd(): void
    {
        $this->assertSame('2026-03-15', ActividadPaciente::normalizarFechaOrden('2026-03-15'));
    }

    public function test_estados_de_orden_medica_sobre_el_modelo(): void
    {
        $sinOrden = $this->crearRegistro(fechaEmisionOrd: null);
        $pendiente = $this->crearRegistro(fechaEmisionOrd: ActividadPaciente::FECHA_ORDEN_PENDIENTE);
        $cargada = $this->crearRegistro(fechaEmisionOrd: '2026-04-01');

        $this->assertFalse($sinOrden->tieneOrdenMedica());
        $this->assertFalse($sinOrden->ordenPendiente());
        $this->assertFalse($sinOrden->ordenCargada());

        $this->assertTrue($pendiente->tieneOrdenMedica());
        $this->assertTrue($pendiente->ordenPendiente());
        $this->assertFalse($pendiente->ordenCargada());

        $this->assertTrue($cargada->tieneOrdenMedica());
        $this->assertFalse($cargada->ordenPendiente());
        $this->assertTrue($cargada->ordenCargada());
    }

    public function test_scopes_de_orden_medica(): void
    {
        $sinOrden = $this->crearRegistro(fechaEmisionOrd: null);
        $pendiente = $this->crearRegistro(fechaEmisionOrd: ActividadPaciente::FECHA_ORDEN_PENDIENTE);
        $cargada = $this->crearRegistro(fechaEmisionOrd: '2026-04-01');

        $this->assertEqualsCanonicalizing(
            [$pendiente->id, $cargada->id],
            ActividadPaciente::query()->conOrdenMedica()->pluck('id')->all()
        );

        $this->assertSame(
            [$cargada->id],
            ActividadPaciente::query()->conOrdenCargada()->pluck('id')->all()
        );

        $this->assertSame(
            [$sinOrden->id],
            ActividadPaciente::query()->sinOrdenMedica()->pluck('id')->all()
        );
    }

    private function crearRegistro(?string $fechaEmisionOrd): ActividadPaciente
    {
        $paciente = Paciente::create([
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

        return ActividadPaciente::create([
            'id_actividad' => Actividad::KINESIOLOGIA_CONVENCIONAL,
            'id_paciente' => $paciente->id,
            'cant_sesiones' => 5,
            'total_a_pagar' => 9000,
            'pago_completado' => $fechaEmisionOrd !== null,
            'fecha_emision_ord' => $fechaEmisionOrd,
        ]);
    }
}
