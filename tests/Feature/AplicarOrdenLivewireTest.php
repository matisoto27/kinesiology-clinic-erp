<?php

namespace Tests\Feature;

use App\Models\Actividad;
use App\Models\ActividadCombo;
use App\Models\ActividadPaciente;
use App\Models\Combo;
use App\Models\Horario;
use App\Models\ObraSocial;
use App\Models\ObraSocialPaciente;
use App\Models\Paciente;
use App\Models\Pago;
use App\Models\Precio;
use App\Models\Profesional;
use App\Models\Turno;
use App\Services\ActividadPacienteService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Livewire\Livewire;
use Tests\TestCase;

class AplicarOrdenLivewireTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('app.max_turnos_convencional', 4);
        Carbon::setTestNow('2026-06-01 08:00:00');
        $this->prepararConvencionalConCombos();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_sin_cantidad_la_lista_esta_vacia(): void
    {
        $paciente = $this->crearPacienteConAfiliacion();
        $this->crearParticular($paciente, cantSesiones: 2, turnos: 2);

        Livewire::test('actividades-pacientes.aplicar-orden')
            ->assertSet('cantidadSesiones', '')
            ->assertSee('Primero seleccione una cantidad');
    }

    public function test_lista_particular_convertible_para_orden_de_5(): void
    {
        $paciente = $this->crearPacienteConAfiliacion(['apellido' => 'Convertible', 'nombre' => 'Ana']);
        $particular = $this->crearParticular($paciente, cantSesiones: 2, turnos: 2);

        $componente = Livewire::test('actividades-pacientes.aplicar-orden')
            ->set('cantidadSesiones', '5')
            ->assertSee('Convertible')
            ->assertSee('2/2 turnos');

        $ids = $componente->instance()->inscripcionesFiltradas->pluck('id');
        $this->assertTrue($ids->contains($particular->id));
    }

    public function test_no_lista_si_turnos_efectivos_superan_el_cupo_de_la_orden(): void
    {
        $paciente = $this->crearPacienteConAfiliacion(['apellido' => 'Excedido']);
        $particular = $this->crearParticular($paciente, cantSesiones: 7, turnos: 7);

        $componente = Livewire::test('actividades-pacientes.aplicar-orden')
            ->set('cantidadSesiones', '5')
            ->assertSee('No existe ningún registro que tenga una cantidad de sesiones compatible para una orden médica de 5 sesiones')
            ->assertDontSee('Excedido');

        $this->assertFalse(
            $componente->instance()->inscripcionesFiltradas->contains('id', $particular->id)
        );
    }

    public function test_particular_con_7_turnos_aparece_para_orden_de_10(): void
    {
        $paciente = $this->crearPacienteConAfiliacion(['apellido' => 'CubreDiez']);
        $particular = $this->crearParticular($paciente, cantSesiones: 7, turnos: 7);

        $componente = Livewire::test('actividades-pacientes.aplicar-orden')
            ->set('cantidadSesiones', '10')
            ->assertSee('CubreDiez');

        $this->assertTrue(
            $componente->instance()->inscripcionesFiltradas->contains('id', $particular->id)
        );
    }

    public function test_no_lista_si_ya_tiene_pagos(): void
    {
        $paciente = $this->crearPacienteConAfiliacion(['apellido' => 'Pagado']);
        $particular = $this->crearParticular($paciente, cantSesiones: 2, turnos: 2);

        Pago::create([
            'id_act_pac' => $particular->id,
            'id_profesional' => $this->crearProfesional()->id,
            'metodo' => 'Efectivo',
            'monto' => 4000,
            'es_copago' => false,
        ]);

        $componente = Livewire::test('actividades-pacientes.aplicar-orden')
            ->set('cantidadSesiones', '5')
            ->assertDontSee('Pagado');

        $this->assertFalse(
            $componente->instance()->inscripcionesFiltradas->contains('id', $particular->id)
        );
    }

    public function test_no_lista_si_no_es_convencional(): void
    {
        $paciente = $this->crearPacienteConAfiliacion(['apellido' => 'OtraAct']);
        $otra = Actividad::create([
            'nombre' => 'Otra kin ' . uniqid(),
            'id_tipo_actividad' => Actividad::TIPO_KINESIOLOGIA,
        ]);

        $particular = ActividadPaciente::create([
            'id_actividad' => $otra->id,
            'id_paciente' => $paciente->id,
            'cant_sesiones' => 2,
            'total_a_pagar' => 4000,
            'pago_completado' => false,
            'fecha_emision_ord' => null,
        ]);
        Turno::create([
            'id_act_pac' => $particular->id,
            'fecha_hora' => '2026-06-01 10:00:00',
        ]);

        $componente = Livewire::test('actividades-pacientes.aplicar-orden')
            ->set('cantidadSesiones', '5')
            ->assertDontSee('OtraAct');

        $this->assertFalse(
            $componente->instance()->inscripcionesFiltradas->contains('id', $particular->id)
        );
    }

    public function test_no_lista_si_paciente_sin_obra_social(): void
    {
        $paciente = $this->crearPaciente(['apellido' => 'SinOS']);
        $particular = $this->crearParticular($paciente, cantSesiones: 2, turnos: 2);

        $componente = Livewire::test('actividades-pacientes.aplicar-orden')
            ->set('cantidadSesiones', '5')
            ->assertDontSee('SinOS');

        $this->assertFalse(
            $componente->instance()->inscripcionesFiltradas->contains('id', $particular->id)
        );
    }

    public function test_no_lista_si_ya_tiene_orden_medica(): void
    {
        $paciente = $this->crearPacienteConAfiliacion(['apellido' => 'ConOrden']);
        $registro = ActividadPaciente::create([
            'id_actividad' => Actividad::KINESIOLOGIA_CONVENCIONAL,
            'id_paciente' => $paciente->id,
            'cant_sesiones' => 5,
            'total_a_pagar' => 9000,
            'pago_completado' => true,
            'fecha_emision_ord' => ActividadPaciente::FECHA_ORDEN_PENDIENTE,
        ]);
        Turno::create([
            'id_act_pac' => $registro->id,
            'fecha_hora' => '2026-06-01 10:00:00',
        ]);

        $componente = Livewire::test('actividades-pacientes.aplicar-orden')
            ->set('cantidadSesiones', '5')
            ->assertDontSee('ConOrden');

        $this->assertFalse(
            $componente->instance()->inscripcionesFiltradas->contains('id', $registro->id)
        );
    }

    public function test_cambiar_cantidad_limpia_la_seleccion_del_registro(): void
    {
        $paciente = $this->crearPacienteConAfiliacion();
        $particular = $this->crearParticular($paciente, cantSesiones: 2, turnos: 2);

        Livewire::test('actividades-pacientes.aplicar-orden')
            ->set('cantidadSesiones', '5')
            ->set('idActPac', (string) $particular->id)
            ->set('cantidadSesiones', '10')
            ->assertSet('idActPac', '');
    }

    public function test_validacion_exige_registro_y_cantidad(): void
    {
        Livewire::test('actividades-pacientes.aplicar-orden')
            ->call('aplicarOrden')
            ->assertHasErrors(['idActPac', 'cantidadSesiones']);
    }

    public function test_aplica_orden_amplia_cupo_y_redirige_a_continuar(): void
    {
        $paciente = $this->crearPacienteConAfiliacion(['apellido' => 'Redirect']);
        $particular = $this->crearParticular($paciente, cantSesiones: 2, turnos: 2);

        Livewire::test('actividades-pacientes.aplicar-orden')
            ->set('cantidadSesiones', '5')
            ->set('idActPac', (string) $particular->id)
            ->set('dia', 20)
            ->set('mes', 5)
            ->set('anio', 2026)
            ->call('aplicarOrden')
            ->assertHasNoErrors()
            ->assertRedirect(route(
                'actividades-pacientes.kinesiologia.con-orden.crear',
                ['id_paciente' => $paciente->id]
            ));

        $particular->refresh();
        $this->assertTrue($particular->ordenCargada());
        $this->assertSame(5, $particular->cant_sesiones);
        $this->assertSame('9000.00', (string) $particular->total_a_pagar);
        $this->assertTrue($particular->pago_completado);
        $this->assertSame('2026-05-20', $particular->fecha_emision_ord->format('Y-m-d'));
        $this->assertCount(2, $particular->turnos()->whereNull('id_turno_original')->get());
        $this->assertTrue(
            app(ActividadPacienteService::class)
                ->kinesioConOrdenEnCurso($paciente->id)
                ->contains('id', $particular->id)
        );
    }

    public function test_aplicar_orden_de_10_a_particular_con_pocos_turnos(): void
    {
        $paciente = $this->crearPacienteConAfiliacion();
        $particular = $this->crearParticular($paciente, cantSesiones: 3, turnos: 3);

        Livewire::test('actividades-pacientes.aplicar-orden')
            ->set('cantidadSesiones', '10')
            ->set('idActPac', (string) $particular->id)
            ->set('dia', 1)
            ->set('mes', 6)
            ->set('anio', 2026)
            ->call('aplicarOrden')
            ->assertRedirect();

        $particular->refresh();
        $this->assertSame(10, $particular->cant_sesiones);
        $this->assertSame('16000.00', (string) $particular->total_a_pagar);
    }

    public function test_aplicar_orden_no_convierte_si_el_registro_tiene_pagos(): void
    {
        $paciente = $this->crearPacienteConAfiliacion();
        $particular = $this->crearParticular($paciente, cantSesiones: 2, turnos: 2);

        Pago::create([
            'id_act_pac' => $particular->id,
            'id_profesional' => $this->crearProfesional()->id,
            'metodo' => 'Efectivo',
            'monto' => 1000,
            'es_copago' => false,
        ]);

        Livewire::test('actividades-pacientes.aplicar-orden')
            ->set('cantidadSesiones', '5')
            ->set('idActPac', (string) $particular->id)
            ->set('dia', 20)
            ->set('mes', 5)
            ->set('anio', 2026)
            ->call('aplicarOrden')
            ->assertNoRedirect();

        $particular->refresh();
        $this->assertNull($particular->fecha_emision_ord);
        $this->assertSame(2, $particular->cant_sesiones);
        $this->assertFalse($particular->pago_completado);
    }

    private function crearParticular(Paciente $paciente, int $cantSesiones, int $turnos): ActividadPaciente
    {
        $particular = ActividadPaciente::create([
            'id_actividad' => Actividad::KINESIOLOGIA_CONVENCIONAL,
            'id_paciente' => $paciente->id,
            'cant_sesiones' => $cantSesiones,
            'total_a_pagar' => 4000,
            'pago_completado' => false,
            'fecha_emision_ord' => null,
        ]);

        for ($i = 0; $i < $turnos; $i++) {
            Turno::create([
                'id_act_pac' => $particular->id,
                'fecha_hora' => Carbon::parse('2026-06-01 10:00:00')->addDays($i),
            ]);
        }

        return $particular;
    }

    private function prepararConvencionalConCombos(): void
    {
        $actividad = Actividad::findOrFail(Actividad::KINESIOLOGIA_CONVENCIONAL);

        if (!$actividad->horarios()->where('hora_inicio', '10:00:00')->exists()) {
            $horario = Horario::create(['hora_inicio' => '10:00:00', 'franja' => 'M']);
            $actividad->horarios()->attach($horario->id);
        }

        foreach ([5 => 9000.00, 10 => 16000.00] as $cant => $precio) {
            $existe = ActividadCombo::query()
                ->where('id_actividad', $actividad->id)
                ->whereHas('combo', fn ($q) => $q->where('cantidad_sesiones', $cant))
                ->exists();

            if ($existe) {
                continue;
            }

            $combo = Combo::create([
                'nombre' => "Cx{$cant} T" . uniqid(),
                'cantidad_sesiones' => $cant,
            ]);
            $vinculo = ActividadCombo::create([
                'id_actividad' => $actividad->id,
                'id_combo' => $combo->id,
                'activo' => true,
            ]);
            Precio::create([
                'id_actividad_combo' => $vinculo->id,
                'fecha_desde' => '2025-01-01',
                'valor' => $precio,
            ]);
        }
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

    private function crearPaciente(array $extra = []): Paciente
    {
        return Paciente::create(array_merge([
            'dni' => (string) random_int(10000000, 99999999),
            'nombre' => 'Luis',
            'apellido' => 'Pérez',
            'fecha_nac' => '1990-01-01',
            'domicilio' => 'Calle 123',
            'telefono' => '1111111111',
            'profesion' => 'Profesion',
            'actividad_fisica' => 'Ninguna',
            'es_adulto_mayor' => false,
        ], $extra));
    }

    private function crearPacienteConAfiliacion(array $extra = []): Paciente
    {
        $paciente = $this->crearPaciente($extra);

        ObraSocialPaciente::create([
            'id_obra_social' => ObraSocial::create([
                'nombre' => 'OS ' . uniqid(),
                'activo' => true,
            ])->id,
            'id_paciente' => $paciente->id,
            'fecha_desde' => '2025-01-01',
            'fecha_hasta' => null,
        ]);

        return $paciente;
    }
}
