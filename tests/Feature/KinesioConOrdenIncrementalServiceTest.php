<?php

namespace Tests\Feature;

use App\Exceptions\ReglaNegocioException;
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
use Tests\TestCase;

class KinesioConOrdenIncrementalServiceTest extends TestCase
{
    use RefreshDatabase;

    private ActividadPacienteService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(ActividadPacienteService::class);
        Config::set('app.max_turnos_convencional', 4);
        Carbon::setTestNow('2026-06-01 08:00:00'); // lunes
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_abrir_kinesio_con_orden_y_fecha_real(): void
    {
        $this->prepararConvencionalConCombos();
        $paciente = $this->crearPacienteConAfiliacion();

        $registro = $this->service->abrirKinesioConOrden(
            $paciente->id,
            5,
            '2026-06-01 10:00:00',
            '2026-03-15'
        );

        $this->assertSame(Actividad::KINESIOLOGIA_CONVENCIONAL, (int) $registro->id_actividad);
        $this->assertSame(5, $registro->cant_sesiones);
        $this->assertSame('9000.00', (string) $registro->total_a_pagar);
        $this->assertTrue($registro->pago_completado);
        $this->assertTrue($registro->ordenCargada());
        $this->assertSame('2026-03-15', $registro->fecha_emision_ord->format('Y-m-d'));
        $this->assertCount(1, $registro->turnos);
        $this->assertSame('2026-06-01 10:00:00', $registro->turnos->first()->fecha_hora->format('Y-m-d H:i:s'));
    }

    public function test_abrir_sin_fecha_guarda_sentinel_de_orden_pendiente(): void
    {
        $this->prepararConvencionalConCombos();
        $paciente = $this->crearPacienteConAfiliacion();

        $registro = $this->service->abrirKinesioConOrden(
            $paciente->id,
            10,
            '2026-06-02 10:00:00',
            null
        );

        $this->assertTrue($registro->tieneOrdenMedica());
        $this->assertTrue($registro->ordenPendiente());
        $this->assertFalse($registro->ordenCargada());
        $this->assertSame(
            ActividadPaciente::FECHA_ORDEN_PENDIENTE,
            $registro->fecha_emision_ord->format('Y-m-d')
        );
        $this->assertSame(10, $registro->cant_sesiones);
        $this->assertSame('16000.00', (string) $registro->total_a_pagar);
    }

    public function test_abrir_exige_afiliacion_a_obra_social_de_catalogo(): void
    {
        $this->prepararConvencionalConCombos();
        $paciente = $this->crearPaciente();

        $this->expectException(ReglaNegocioException::class);
        $this->expectExceptionMessage('afiliación vigente');

        $this->service->abrirKinesioConOrden($paciente->id, 5, '2026-06-01 10:00:00');
    }

    public function test_abrir_rechaza_cantidad_de_sesiones_invalida(): void
    {
        $this->prepararConvencionalConCombos();
        $paciente = $this->crearPacienteConAfiliacion();

        $this->expectException(ReglaNegocioException::class);
        $this->expectExceptionMessage('5 o 10 sesiones');

        $this->service->abrirKinesioConOrden($paciente->id, 3, '2026-06-01 10:00:00');
    }

    public function test_abrir_rechaza_si_el_slot_no_tiene_cupo(): void
    {
        Config::set('app.max_turnos_convencional', 1);
        $this->prepararConvencionalConCombos();
        $paciente = $this->crearPacienteConAfiliacion();

        $this->ocuparSlot('2026-06-01 10:00:00');

        $this->expectException(ReglaNegocioException::class);
        $this->expectExceptionMessage('cupo disponible');

        $this->service->abrirKinesioConOrden($paciente->id, 5, '2026-06-01 10:00:00');
    }

    public function test_kinesio_con_orden_en_curso_solo_incluye_incompletos_con_orden(): void
    {
        $this->prepararConvencionalConCombos();
        $paciente = $this->crearPacienteConAfiliacion();

        $enCurso = $this->service->abrirKinesioConOrden($paciente->id, 5, '2026-06-01 10:00:00');
        $completo = $this->crearRegistroCompleto($paciente, cantSesiones: 5);
        $particular = ActividadPaciente::create([
            'id_actividad' => Actividad::KINESIOLOGIA_CONVENCIONAL,
            'id_paciente' => $paciente->id,
            'cant_sesiones' => 5,
            'total_a_pagar' => 9000,
            'pago_completado' => false,
            'fecha_emision_ord' => null,
        ]);
        Turno::create([
            'id_act_pac' => $particular->id,
            'fecha_hora' => '2026-07-01 10:00:00',
        ]);

        $otroPaciente = $this->crearPacienteConAfiliacion();
        $this->service->abrirKinesioConOrden($otroPaciente->id, 5, '2026-06-03 10:00:00');

        $ids = $this->service->kinesioConOrdenEnCurso($paciente->id)->pluck('id')->all();

        $this->assertSame([$enCurso->id], $ids);
        $this->assertNotContains($completo->id, $ids);
        $this->assertNotContains($particular->id, $ids);
    }

    public function test_agregar_turno_incrementa_hasta_completar_el_registro(): void
    {
        $this->prepararConvencionalConCombos();
        $paciente = $this->crearPacienteConAfiliacion();

        $registro = $this->service->abrirKinesioConOrden($paciente->id, 5, '2026-06-01 10:00:00');

        $this->service->agregarTurnoAKinesioConOrden($registro, '2026-06-02 10:00:00');
        $this->service->agregarTurnoAKinesioConOrden($registro, '2026-06-03 10:00:00');
        $this->service->agregarTurnoAKinesioConOrden($registro, '2026-06-04 10:00:00');
        $this->service->agregarTurnoAKinesioConOrden($registro, '2026-06-05 10:00:00');

        $this->assertSame(5, $registro->turnos()->whereNull('id_turno_original')->count());
        $this->assertTrue(
            $this->service->kinesioConOrdenEnCurso($paciente->id)->isEmpty()
        );

        $this->expectException(ReglaNegocioException::class);
        $this->expectExceptionMessage('todas las sesiones');

        $this->service->agregarTurnoAKinesioConOrden($registro->fresh(), '2026-06-08 10:00:00');
    }

    public function test_agregar_turno_no_cuenta_reprogramaciones_para_el_cupo_de_la_orden(): void
    {
        $this->prepararConvencionalConCombos();
        $paciente = $this->crearPacienteConAfiliacion();

        $registro = $this->service->abrirKinesioConOrden($paciente->id, 5, '2026-06-01 10:00:00');
        $original = $registro->turnos()->first();

        Turno::create([
            'id_act_pac' => $registro->id,
            'fecha_hora' => '2026-06-08 10:00:00',
            'id_turno_original' => $original->id,
        ]);

        $this->assertSame(1, $registro->turnos()->whereNull('id_turno_original')->count());
        $this->assertCount(1, $this->service->kinesioConOrdenEnCurso($paciente->id));

        $this->service->agregarTurnoAKinesioConOrden($registro, '2026-06-02 10:00:00');
        $this->assertSame(2, $registro->turnos()->whereNull('id_turno_original')->count());
    }

    public function test_agregar_turno_rechaza_registro_particular_sin_orden(): void
    {
        $this->prepararConvencionalConCombos();
        $paciente = $this->crearPaciente();

        $particular = ActividadPaciente::create([
            'id_actividad' => Actividad::KINESIOLOGIA_CONVENCIONAL,
            'id_paciente' => $paciente->id,
            'cant_sesiones' => 5,
            'total_a_pagar' => 9000,
            'pago_completado' => false,
            'fecha_emision_ord' => null,
        ]);

        $this->expectException(ReglaNegocioException::class);
        $this->expectExceptionMessage('orden médica');

        $this->service->agregarTurnoAKinesioConOrden($particular, '2026-06-01 10:00:00');
    }

    public function test_cargar_fecha_orden_medica_pisa_el_sentinel(): void
    {
        $this->prepararConvencionalConCombos();
        $paciente = $this->crearPacienteConAfiliacion();

        $registro = $this->service->abrirKinesioConOrden($paciente->id, 5, '2026-06-01 10:00:00');
        $this->assertTrue($registro->ordenPendiente());

        $actualizado = $this->service->cargarFechaOrdenMedica($registro, '2026-05-20');

        $this->assertTrue($actualizado->ordenCargada());
        $this->assertSame('2026-05-20', $actualizado->fecha_emision_ord->format('Y-m-d'));
    }

    public function test_cargar_fecha_orden_rechaza_el_sentinel(): void
    {
        $this->prepararConvencionalConCombos();
        $paciente = $this->crearPacienteConAfiliacion();
        $registro = $this->service->abrirKinesioConOrden($paciente->id, 5, '2026-06-01 10:00:00');

        $this->expectException(ReglaNegocioException::class);
        $this->expectExceptionMessage('fecha de orden médica válida');

        $this->service->cargarFechaOrdenMedica($registro, ActividadPaciente::FECHA_ORDEN_PENDIENTE);
    }

    public function test_abrir_segundo_registro_en_curso_es_rechazado(): void
    {
        $this->prepararConvencionalConCombos(['10:00:00', '11:00:00']);
        $paciente = $this->crearPacienteConAfiliacion();

        $this->service->abrirKinesioConOrden($paciente->id, 5, '2026-06-01 10:00:00');

        $this->expectException(ReglaNegocioException::class);
        $this->expectExceptionMessage('ya tiene un registro de sesiones con orden médica en curso');

        $this->service->abrirKinesioConOrden($paciente->id, 5, '2026-06-08 11:00:00');
    }

    public function test_agregar_turno_rechaza_si_ya_tiene_kinesio_el_mismo_dia(): void
    {
        $this->prepararConvencionalConCombos(['10:00:00', '11:00:00']);
        $paciente = $this->crearPacienteConAfiliacion();

        $registro = $this->service->abrirKinesioConOrden($paciente->id, 5, '2026-06-01 10:00:00');

        $this->expectException(ReglaNegocioException::class);
        $this->expectExceptionMessage('ya tiene un turno de kinesiología ese día');

        $this->service->agregarTurnoAKinesioConOrden($registro, '2026-06-01 11:00:00');
    }

    public function test_registrar_sin_orden_es_rechazado_si_hay_registro_con_orden_en_curso(): void
    {
        $actividad = $this->prepararConvencionalConCombos(['10:00:00', '11:00:00']);
        $paciente = $this->crearPacienteConAfiliacion();

        $this->service->abrirKinesioConOrden($paciente->id, 5, '2026-06-01 10:00:00');

        $this->expectException(ReglaNegocioException::class);
        $this->expectExceptionMessage(ActividadPacienteService::MENSAJE_KINESIO_SIN_ORDEN_CON_REGISTRO_EN_CURSO);

        $this->service->registrar([
            'id_actividad' => $actividad->id,
            'id_paciente' => $paciente->id,
            'autogenerados' => false,
            'frecuencia_semanal' => 1,
            'cant_sesiones' => 5,
            'turnos' => $this->turnosManualesDesde('2026-07-01 11:00:00', 5),
        ]);
    }

    public function test_registrar_sin_orden_permitido_si_el_registro_con_orden_esta_completo(): void
    {
        $actividad = $this->prepararConvencionalConCombos(['10:00:00', '11:00:00']);
        $this->crearCombo($actividad, 1, 2000.00);
        $paciente = $this->crearPacienteConAfiliacion();

        $this->crearRegistroCompleto($paciente, 5);

        $resultado = $this->service->registrar([
            'id_actividad' => $actividad->id,
            'id_paciente' => $paciente->id,
            'autogenerados' => false,
            'frecuencia_semanal' => 1,
            'cant_sesiones' => 5,
            'turnos' => $this->turnosManualesDesde('2026-07-01 11:00:00', 5),
        ]);

        $this->assertNull($resultado->inscripcion->fecha_emision_ord);
        $this->assertSame(5, $resultado->inscripcion->cant_sesiones);
    }

    public function test_aplicar_orden_amplia_particular_sin_pagos_al_cupo_de_la_orden(): void
    {
        $this->prepararConvencionalConCombos();
        $paciente = $this->crearPacienteConAfiliacion();
        $particular = $this->crearParticular($paciente, cantSesiones: 2, turnos: 2);

        $registro = $this->service->aplicarOrdenAParticular($particular, 5, '2026-05-20');

        $this->assertTrue($registro->ordenCargada());
        $this->assertSame(5, $registro->cant_sesiones);
        $this->assertSame('9000.00', (string) $registro->total_a_pagar);
        $this->assertTrue($registro->pago_completado);
        $this->assertCount(2, $registro->turnos);
        $this->assertTrue(
            $this->service->kinesioConOrdenEnCurso($paciente->id)->contains('id', $registro->id)
        );
    }

    public function test_aplicar_orden_rechaza_si_hay_mas_turnos_que_el_cupo(): void
    {
        $this->prepararConvencionalConCombos();
        $paciente = $this->crearPacienteConAfiliacion();
        $particular = $this->crearParticular($paciente, cantSesiones: 7, turnos: 7);

        $this->expectException(ReglaNegocioException::class);
        $this->expectExceptionMessage('una orden de 5 sesiones no los cubre');

        $this->service->aplicarOrdenAParticular($particular, 5, '2026-05-20');
    }

    public function test_aplicar_orden_rechaza_si_ya_tiene_pagos(): void
    {
        $this->prepararConvencionalConCombos();
        $paciente = $this->crearPacienteConAfiliacion();
        $particular = $this->crearParticular($paciente, cantSesiones: 2, turnos: 2);

        $profesional = \App\Models\Profesional::create([
            'dni' => (string) random_int(10000000, 99999999),
            'nombre' => 'Ana',
            'apellido' => 'García',
            'activo' => true,
        ]);

        \App\Models\Pago::create([
            'id_act_pac' => $particular->id,
            'id_profesional' => $profesional->id,
            'metodo' => 'Efectivo',
            'monto' => 4000,
            'es_copago' => false,
        ]);

        $this->expectException(ReglaNegocioException::class);
        $this->expectExceptionMessage('ya tiene pagos');

        $this->service->aplicarOrdenAParticular($particular, 5, '2026-05-20');
    }

    public function test_aplicar_orden_rechaza_si_no_es_convencional(): void
    {
        $this->prepararConvencionalConCombos();
        $paciente = $this->crearPacienteConAfiliacion();
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

        $this->expectException(ReglaNegocioException::class);
        $this->expectExceptionMessage('Kinesiología Convencional');

        $this->service->aplicarOrdenAParticular($particular, 5, '2026-05-20');
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

    private function prepararConvencionalConCombos(array $horas = ['10:00:00']): Actividad
    {
        $actividad = Actividad::findOrFail(Actividad::KINESIOLOGIA_CONVENCIONAL);

        foreach ($horas as $hora) {
            $horario = Horario::create([
                'hora_inicio' => $hora,
                'franja' => 'M',
            ]);
            $actividad->horarios()->attach($horario->id);
        }

        $this->crearCombo($actividad, 5, 9000.00);
        $this->crearCombo($actividad, 10, 16000.00);

        return $actividad->fresh(['horarios']);
    }

    private function crearCombo(Actividad $actividad, int $cantidad, float $precio): void
    {
        $combo = Combo::create([
            'nombre' => "Cx{$cantidad} T" . uniqid(),
            'cantidad_sesiones' => $cantidad,
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

    private function turnosManualesDesde(string $fechaHoraInicio, int $cantidad): array
    {
        $inicio = Carbon::parse($fechaHoraInicio);

        return collect(range(0, $cantidad - 1))
            ->map(fn (int $i) => $inicio->copy()->addDays($i)->toDateTimeString())
            ->all();
    }

    private function crearRegistroCompleto(Paciente $paciente, int $cantSesiones): ActividadPaciente
    {
        $registro = ActividadPaciente::create([
            'id_actividad' => Actividad::KINESIOLOGIA_CONVENCIONAL,
            'id_paciente' => $paciente->id,
            'cant_sesiones' => $cantSesiones,
            'total_a_pagar' => 9000,
            'pago_completado' => true,
            'fecha_emision_ord' => '2026-01-10',
        ]);

        for ($i = 0; $i < $cantSesiones; $i++) {
            Turno::create([
                'id_act_pac' => $registro->id,
                'fecha_hora' => Carbon::parse('2026-05-01 10:00:00')->addDays($i),
            ]);
        }

        return $registro;
    }

    private function ocuparSlot(string $fechaHora): void
    {
        $ocupante = $this->crearPaciente();
        $registro = ActividadPaciente::create([
            'id_actividad' => Actividad::KINESIOLOGIA_CONVENCIONAL,
            'id_paciente' => $ocupante->id,
            'cant_sesiones' => 1,
            'total_a_pagar' => 0,
            'pago_completado' => true,
        ]);

        Turno::create([
            'id_act_pac' => $registro->id,
            'fecha_hora' => $fechaHora,
        ]);
    }

    private function crearPaciente(array $extra = []): Paciente
    {
        return Paciente::create(array_merge([
            'dni' => (string) random_int(10000000, 99999999),
            'nombre' => 'Nombre',
            'apellido' => 'Apellido',
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

        $obraSocial = ObraSocial::create([
            'nombre' => 'OS ' . uniqid(),
            'activo' => true,
        ]);

        ObraSocialPaciente::create([
            'id_obra_social' => $obraSocial->id,
            'id_paciente' => $paciente->id,
            'fecha_desde' => '2025-01-01',
            'fecha_hasta' => null,
        ]);

        return $paciente;
    }
}
