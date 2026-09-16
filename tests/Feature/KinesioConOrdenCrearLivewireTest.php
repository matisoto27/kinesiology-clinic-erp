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
use App\Models\Precio;
use App\Models\Turno;
use App\Services\ActividadPacienteService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Livewire\Livewire;
use Tests\TestCase;

class KinesioConOrdenCrearLivewireTest extends TestCase
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

    public function test_al_seleccionar_paciente_sin_registro_abre_formulario_de_alta(): void
    {
        $paciente = $this->crearPacienteConAfiliacion();

        Livewire::test('actividades-pacientes.kinesiologia.con-orden.crear')
            ->call('seleccionarSugerencia', $paciente->id, $paciente->apellido_nombre)
            ->assertSet('idPacienteSeleccionado', $paciente->id)
            ->assertSee('Nuevo registro de sesiones con orden médica')
            ->assertSee('(Opcional, puede cargarla más tarde)')
            ->assertDontSee('Abrir otro registro');
    }

    public function test_al_seleccionar_paciente_con_registro_en_curso_entra_en_modo_continuar(): void
    {
        $paciente = $this->crearPacienteConAfiliacion();
        app(ActividadPacienteService::class)->abrirKinesioConOrden(
            $paciente->id,
            5,
            '2026-06-01 10:00:00',
            null
        );

        Livewire::test('actividades-pacientes.kinesiologia.con-orden.crear')
            ->call('seleccionarSugerencia', $paciente->id, $paciente->apellido_nombre)
            ->assertSee('Registro de sesiones en curso')
            ->assertSee('1/5')
            ->assertSee('No cargada')
            ->assertSee('Agregar turno')
            ->assertDontSee('Abrir otro registro');
    }

    public function test_abrir_registro_sin_fecha_guarda_orden_pendiente_y_permanece_en_la_pagina(): void
    {
        $paciente = $this->crearPacienteConAfiliacion();

        $componente = Livewire::test('actividades-pacientes.kinesiologia.con-orden.crear')
            ->call('seleccionarSugerencia', $paciente->id, $paciente->apellido_nombre)
            ->set('cantSesiones', '5')
            ->set('fechaTurno', '2026-06-01')
            ->set('horaTurno', '10:00')
            ->call('almacenar')
            ->assertHasNoErrors()
            ->assertSee('Registro de sesiones en curso')
            ->assertSee('No cargada');

        $this->assertStringContainsString('1/5', $componente->get('mensajeExito'));

        $registro = ActividadPaciente::query()->first();
        $this->assertNotNull($registro);
        $this->assertTrue($registro->ordenPendiente());
        $this->assertSame(1, Turno::count());
    }

    public function test_abrir_registro_con_fecha_de_orden_mes_dia_anio(): void
    {
        $paciente = $this->crearPacienteConAfiliacion();

        Livewire::test('actividades-pacientes.kinesiologia.con-orden.crear')
            ->call('seleccionarSugerencia', $paciente->id, $paciente->apellido_nombre)
            ->set('cantSesiones', '10')
            ->set('mesOrden', '3')
            ->set('diaOrden', '15')
            ->set('anioOrden', '2026')
            ->set('fechaTurno', '2026-06-02')
            ->set('horaTurno', '10:00')
            ->call('almacenar')
            ->assertHasNoErrors()
            ->assertSee('15/03/2026');

        $registro = ActividadPaciente::first();
        $this->assertTrue($registro->ordenCargada());
        $this->assertSame(10, $registro->cant_sesiones);
        $this->assertSame('2026-03-15', $registro->fecha_emision_ord->format('Y-m-d'));
    }

    public function test_agregar_turno_limpia_el_slot_y_actualiza_el_progreso(): void
    {
        $paciente = $this->crearPacienteConAfiliacion();
        $registro = app(ActividadPacienteService::class)->abrirKinesioConOrden(
            $paciente->id,
            5,
            '2026-06-01 10:00:00',
            '2026-03-15'
        );

        $componente = Livewire::test('actividades-pacientes.kinesiologia.con-orden.crear')
            ->call('seleccionarSugerencia', $paciente->id, $paciente->apellido_nombre)
            ->assertSet('idRegistroSesionesSeleccionado', $registro->id)
            ->set('fechaTurno', '2026-06-02')
            ->set('horaTurno', '10:00')
            ->call('almacenar')
            ->assertHasNoErrors()
            ->assertSet('fechaTurno', '')
            ->assertSet('horaTurno', '')
            ->assertSee('2/5');

        $this->assertStringContainsString('2/5', $componente->get('mensajeExito'));
        $this->assertSame(2, $registro->turnos()->count());
    }

    public function test_cargar_fecha_de_orden_desde_el_modo_continuar(): void
    {
        $paciente = $this->crearPacienteConAfiliacion();
        app(ActividadPacienteService::class)->abrirKinesioConOrden(
            $paciente->id,
            5,
            '2026-06-01 10:00:00',
            null
        );

        Livewire::test('actividades-pacientes.kinesiologia.con-orden.crear')
            ->call('seleccionarSugerencia', $paciente->id, $paciente->apellido_nombre)
            ->set('mesOrden', '4')
            ->set('diaOrden', '10')
            ->set('anioOrden', '2026')
            ->call('guardarFechaOrden')
            ->assertHasNoErrors()
            ->assertSet('mensajeExito', 'Fecha de orden médica guardada.')
            ->assertSee('10/04/2026')
            ->assertDontSee('No cargada');

        $this->assertTrue(ActividadPaciente::first()->ordenCargada());
    }

    public function test_validacion_exige_cant_sesiones_al_abrir(): void
    {
        $paciente = $this->crearPacienteConAfiliacion();

        Livewire::test('actividades-pacientes.kinesiologia.con-orden.crear')
            ->call('seleccionarSugerencia', $paciente->id, $paciente->apellido_nombre)
            ->set('fechaTurno', '2026-06-01')
            ->set('horaTurno', '10:00')
            ->call('almacenar')
            ->assertHasErrors(['cantSesiones']);
    }

    public function test_si_elige_mes_exige_tambien_el_dia(): void
    {
        $paciente = $this->crearPacienteConAfiliacion();

        Livewire::test('actividades-pacientes.kinesiologia.con-orden.crear')
            ->call('seleccionarSugerencia', $paciente->id, $paciente->apellido_nombre)
            ->set('cantSesiones', '5')
            ->set('mesOrden', '3')
            ->set('diaOrden', '')
            ->set('fechaTurno', '2026-06-01')
            ->set('horaTurno', '10:00')
            ->call('almacenar')
            ->assertHasErrors(['diaOrden']);
    }

    public function test_anio_por_defecto_es_el_actual_y_permite_el_anterior(): void
    {
        $paciente = $this->crearPacienteConAfiliacion();

        $componente = Livewire::test('actividades-pacientes.kinesiologia.con-orden.crear')
            ->call('seleccionarSugerencia', $paciente->id, $paciente->apellido_nombre);

        $this->assertSame('2026', $componente->get('anioOrden'));
        $this->assertSame([2025, 2026], $componente->instance()->aniosDisponibles());
    }

    public function test_sin_afiliacion_muestra_error_de_negocio_al_abrir(): void
    {
        $paciente = $this->crearPaciente();

        Livewire::test('actividades-pacientes.kinesiologia.con-orden.crear')
            ->call('seleccionarSugerencia', $paciente->id, $paciente->apellido_nombre)
            ->set('cantSesiones', '5')
            ->set('fechaTurno', '2026-06-01')
            ->set('horaTurno', '10:00')
            ->call('almacenar')
            ->assertHasErrors(['fechaTurno']);

        $this->assertSame(0, ActividadPaciente::count());
    }

    public function test_no_permite_abrir_otro_registro_si_hay_uno_en_curso(): void
    {
        $paciente = $this->crearPacienteConAfiliacion();
        app(ActividadPacienteService::class)->abrirKinesioConOrden(
            $paciente->id,
            5,
            '2026-06-01 10:00:00'
        );

        Livewire::test('actividades-pacientes.kinesiologia.con-orden.crear')
            ->call('seleccionarSugerencia', $paciente->id, $paciente->apellido_nombre)
            ->assertSee('Registro de sesiones en curso')
            ->assertDontSee('Nuevo registro de sesiones con orden médica')
            ->assertDontSee('Abrir otro registro');
    }

    public function test_al_completar_el_registro_informa_completo(): void
    {
        $paciente = $this->crearPacienteConAfiliacion();
        $registro = ActividadPaciente::create([
            'id_actividad' => Actividad::KINESIOLOGIA_CONVENCIONAL,
            'id_paciente' => $paciente->id,
            'cant_sesiones' => 5,
            'total_a_pagar' => 9000,
            'pago_completado' => true,
            'fecha_emision_ord' => '2026-03-15',
        ]);

        foreach (['2026-06-01', '2026-06-02', '2026-06-03', '2026-06-04'] as $dia) {
            Turno::create([
                'id_act_pac' => $registro->id,
                'fecha_hora' => "{$dia} 10:00:00",
            ]);
        }

        $componente = Livewire::test('actividades-pacientes.kinesiologia.con-orden.crear')
            ->call('seleccionarSugerencia', $paciente->id, $paciente->apellido_nombre)
            ->set('fechaTurno', '2026-06-05')
            ->set('horaTurno', '10:00')
            ->call('almacenar')
            ->assertHasNoErrors();

        $mensaje = $componente->get('mensajeExito');
        $this->assertStringContainsString('5/5', $mensaje);
        $this->assertStringContainsString('Registro completo', $mensaje);

        $this->assertTrue(
            app(ActividadPacienteService::class)->kinesioConOrdenEnCurso($paciente->id)->isEmpty()
        );
    }

    public function test_muestra_turnos_pasados_atenuados_en_la_lista(): void
    {
        $paciente = $this->crearPacienteConAfiliacion();
        $registro = ActividadPaciente::create([
            'id_actividad' => Actividad::KINESIOLOGIA_CONVENCIONAL,
            'id_paciente' => $paciente->id,
            'cant_sesiones' => 5,
            'total_a_pagar' => 9000,
            'pago_completado' => true,
            'fecha_emision_ord' => '2026-03-15',
        ]);

        Turno::create([
            'id_act_pac' => $registro->id,
            'fecha_hora' => '2026-05-28 10:00:00',
        ]);
        Turno::create([
            'id_act_pac' => $registro->id,
            'fecha_hora' => '2026-06-03 10:00:00',
        ]);

        Livewire::test('actividades-pacientes.kinesiologia.con-orden.crear')
            ->call('seleccionarSugerencia', $paciente->id, $paciente->apellido_nombre)
            ->assertSee('Pasado')
            ->assertSee('28/05/2026')
            ->assertSee('03/06/2026');
    }

    public function test_mount_con_id_paciente_preselecciona_y_entra_en_continuar(): void
    {
        $paciente = $this->crearPacienteConAfiliacion();
        app(ActividadPacienteService::class)->abrirKinesioConOrden(
            $paciente->id,
            5,
            '2026-06-01 10:00:00',
            '2026-03-15'
        );

        Livewire::withQueryParams(['id_paciente' => $paciente->id])
            ->test('actividades-pacientes.kinesiologia.con-orden.crear')
            ->assertSet('idPacienteSeleccionado', $paciente->id)
            ->assertSee('Registro de sesiones en curso')
            ->assertSee('1/5');
    }

    public function test_limpiar_seleccion_vuelve_al_buscador(): void
    {
        $paciente = $this->crearPacienteConAfiliacion();

        Livewire::test('actividades-pacientes.kinesiologia.con-orden.crear')
            ->call('seleccionarSugerencia', $paciente->id, $paciente->apellido_nombre)
            ->call('limpiarSeleccion')
            ->assertSet('idPacienteSeleccionado', null)
            ->assertSet('busqueda', '')
            ->assertDontSee('Nuevo registro de sesiones con orden médica');
    }

    public function test_alta_rechaza_slot_sin_cupo(): void
    {
        $paciente = $this->crearPacienteConAfiliacion();

        // Agota el cupo del slot (max_turnos_convencional = 4).
        for ($i = 0; $i < 4; $i++) {
            $reg = ActividadPaciente::create([
                'id_actividad' => Actividad::KINESIOLOGIA_CONVENCIONAL,
                'id_paciente' => $this->crearPaciente()->id,
                'cant_sesiones' => 1,
                'total_a_pagar' => 0,
                'pago_completado' => true,
            ]);
            Turno::create([
                'id_act_pac' => $reg->id,
                'fecha_hora' => '2026-06-01 10:00:00',
            ]);
        }

        Livewire::test('actividades-pacientes.kinesiologia.con-orden.crear')
            ->call('seleccionarSugerencia', $paciente->id, $paciente->apellido_nombre)
            ->set('cantSesiones', '5')
            ->set('fechaTurno', '2026-06-01')
            ->set('horaTurno', '10:00')
            ->call('almacenar')
            ->assertHasErrors(['fechaTurno']);

        $this->assertSame(0, ActividadPaciente::where('id_paciente', $paciente->id)->count());
    }

    public function test_continuar_exige_fecha_y_hora_del_turno(): void
    {
        $paciente = $this->crearPacienteConAfiliacion();
        app(ActividadPacienteService::class)->abrirKinesioConOrden(
            $paciente->id,
            5,
            '2026-06-01 10:00:00',
            '2026-03-15'
        );

        Livewire::test('actividades-pacientes.kinesiologia.con-orden.crear')
            ->call('seleccionarSugerencia', $paciente->id, $paciente->apellido_nombre)
            ->call('almacenar')
            ->assertHasErrors(['fechaTurno', 'horaTurno']);
    }

    public function test_continuar_rechaza_agregar_turno_si_el_registro_ya_esta_completo(): void
    {
        $paciente = $this->crearPacienteConAfiliacion();
        $registro = ActividadPaciente::create([
            'id_actividad' => Actividad::KINESIOLOGIA_CONVENCIONAL,
            'id_paciente' => $paciente->id,
            'cant_sesiones' => 5,
            'total_a_pagar' => 9000,
            'pago_completado' => true,
            'fecha_emision_ord' => '2026-03-15',
        ]);

        foreach (range(0, 4) as $i) {
            Turno::create([
                'id_act_pac' => $registro->id,
                'fecha_hora' => Carbon::parse('2026-06-01 10:00:00')->addDays($i),
            ]);
        }

        // Completo ⇒ ya no está "en curso": entra en alta, no en continuar.
        Livewire::test('actividades-pacientes.kinesiologia.con-orden.crear')
            ->call('seleccionarSugerencia', $paciente->id, $paciente->apellido_nombre)
            ->assertSee('Nuevo registro de sesiones con orden médica')
            ->assertDontSee('Agregar turno');
    }

    public function test_reenviar_mientras_procesa_no_duplica_turno_en_alta(): void
    {
        $paciente = $this->crearPacienteConAfiliacion();

        $componente = Livewire::test('actividades-pacientes.kinesiologia.con-orden.crear')
            ->call('seleccionarSugerencia', $paciente->id, $paciente->apellido_nombre)
            ->set('cantSesiones', '5')
            ->set('fechaTurno', '2026-06-01')
            ->set('horaTurno', '10:00');

        $componente->set('procesando', true);
        $componente->call('almacenar');

        $this->assertSame(0, ActividadPaciente::count());
        $this->assertSame(0, Turno::count());
    }

    public function test_proxima_semana_y_cambio_de_fecha_limpian_slot(): void
    {
        $paciente = $this->crearPacienteConAfiliacion();

        Livewire::test('actividades-pacientes.kinesiologia.con-orden.crear')
            ->call('seleccionarSugerencia', $paciente->id, $paciente->apellido_nombre)
            ->set('fechaTurno', '2026-06-01')
            ->set('horaTurno', '10:00')
            ->set('proximaSemana', true)
            ->assertSet('fechaTurno', '')
            ->assertSet('horaTurno', '')
            ->set('fechaTurno', '2026-06-08')
            ->set('horaTurno', '10:00')
            ->set('fechaTurno', '2026-06-09')
            ->assertSet('horaTurno', '');
    }

    public function test_alta_con_10_sesiones_inicia_progreso_1_de_10(): void
    {
        $paciente = $this->crearPacienteConAfiliacion();

        $componente = Livewire::test('actividades-pacientes.kinesiologia.con-orden.crear')
            ->call('seleccionarSugerencia', $paciente->id, $paciente->apellido_nombre)
            ->set('cantSesiones', '10')
            ->set('fechaTurno', '2026-06-01')
            ->set('horaTurno', '10:00')
            ->call('almacenar')
            ->assertHasNoErrors()
            ->assertSee('1/10');

        $this->assertStringContainsString('1/10', $componente->get('mensajeExito'));
        $this->assertSame(10, ActividadPaciente::first()->cant_sesiones);
    }

    public function test_no_ofrece_dias_donde_el_paciente_ya_tiene_turno_de_kinesio(): void
    {
        $this->prepararConvencionalConCombos(['10:00:00', '11:00:00']);
        $paciente = $this->crearPacienteConAfiliacion();

        app(ActividadPacienteService::class)->abrirKinesioConOrden(
            $paciente->id,
            5,
            '2026-06-01 10:00:00',
            '2026-03-15'
        );

        $dias = Livewire::test('actividades-pacientes.kinesiologia.con-orden.crear')
            ->call('seleccionarSugerencia', $paciente->id, $paciente->apellido_nombre)
            ->instance()
            ->diasSemana;

        $this->assertArrayNotHasKey('2026-06-01', $dias);
        $this->assertArrayHasKey('2026-06-02', $dias);
    }

    private function prepararConvencionalConCombos(array $horas = ['10:00:00']): void
    {
        $actividad = Actividad::findOrFail(Actividad::KINESIOLOGIA_CONVENCIONAL);

        foreach ($horas as $hora) {
            $yaAsociada = $actividad->horarios()->where('hora_inicio', $hora)->exists();
            if ($yaAsociada) {
                continue;
            }

            $horario = Horario::create([
                'hora_inicio' => $hora,
                'franja' => 'M',
            ]);
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

    private function crearPacienteConAfiliacion(): Paciente
    {
        $paciente = $this->crearPaciente();

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
