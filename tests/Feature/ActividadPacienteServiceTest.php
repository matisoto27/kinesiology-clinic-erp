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
use App\Services\TurnoService;
use Carbon\Carbon;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActividadPacienteServiceTest extends TestCase
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

    public function test_registrar_con_orden_usa_precio_combo_x5_y_marca_pago_completado(): void
    {
        Carbon::setTestNow('2026-06-02 09:00:00');

        ['actividad' => $actividad] = $this->crearActividadKinesiologiaConPrecios(precioCombo5: 9000.00);
        $paciente = $this->crearPacienteConAfiliacion();

        $actividadPaciente = $this->service->registrar($this->payloadConOrden(
            actividad: $actividad,
            paciente: $paciente,
            sesionesCubiertas: 5
        ));

        $this->assertSame('9000.00', (string) $actividadPaciente->total_a_pagar);
        $this->assertTrue($actividadPaciente->pago_completado);
        $this->assertSame(5, $actividadPaciente->cant_sesiones);
        $this->assertSame('2026-03-15', $actividadPaciente->fecha_emision_ord->format('Y-m-d'));
        $this->assertCount(5, $actividadPaciente->turnos);
    }

    public function test_registrar_con_orden_usa_precio_combo_x10(): void
    {
        Carbon::setTestNow('2026-06-02 09:00:00');

        ['actividad' => $actividad] = $this->crearActividadKinesiologiaConPrecios(
            precioCombo5: 9000.00,
            precioCombo10: 16000.00
        );
        $paciente = $this->crearPacienteConAfiliacion();

        $actividadPaciente = $this->service->registrar($this->payloadConOrden(
            actividad: $actividad,
            paciente: $paciente,
            sesionesCubiertas: 10
        ));

        $this->assertSame('16000.00', (string) $actividadPaciente->total_a_pagar);
        $this->assertTrue($actividadPaciente->pago_completado);
        $this->assertSame(10, $actividadPaciente->cant_sesiones);
        $this->assertCount(10, $actividadPaciente->turnos);
    }

    public function test_registrar_con_orden_exige_afiliacion_vigente(): void
    {
        Carbon::setTestNow('2026-06-02 09:00:00');

        ['actividad' => $actividad] = $this->crearActividadKinesiologiaConPrecios(precioCombo5: 9000.00);
        $paciente = $this->crearPaciente();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('El paciente seleccionado no posee una afiliación vigente a una obra social.');

        $this->service->registrar($this->payloadConOrden(
            actividad: $actividad,
            paciente: $paciente,
            sesionesCubiertas: 5
        ));
    }

    public function test_registrar_con_orden_falla_si_no_existe_precio_del_combo_solicitado(): void
    {
        Carbon::setTestNow('2026-06-02 09:00:00');

        ['actividad' => $actividad] = $this->crearActividadKinesiologiaConPrecios(
            precioSesion: 2000.00,
            precioCombo10: 18000.00
        );
        $paciente = $this->crearPacienteConAfiliacion();

        try {
            $this->service->registrar($this->payloadConOrden(
                actividad: $actividad,
                paciente: $paciente,
                sesionesCubiertas: 5
            ));
            $this->fail('Se esperaba una excepción al registrar con orden sin precio del combo x5.');
        } catch (Exception $e) {
            $this->assertSame(
                'La actividad no tiene un precio determinado para el combo de 5 sesiones.',
                $e->getMessage()
            );
        }

        $this->assertSame(0, ActividadPaciente::count());
    }

    public function test_registrar_con_orden_no_usa_precio_mensual_aunque_venga_id_actividad_combo(): void
    {
        Carbon::setTestNow('2026-06-02 09:00:00');

        ['actividad' => $actividad, 'actividadComboMensual' => $actividadComboMensual] = $this->crearActividadKinesiologiaConPrecios(
            precioCombo5: 9000.00,
            precioMensual: 50000.00
        );
        $paciente = $this->crearPacienteConAfiliacion();

        $actividadPaciente = $this->service->registrar($this->payloadConOrden(
            actividad: $actividad,
            paciente: $paciente,
            sesionesCubiertas: 5,
            extra: ['id_actividad_combo' => $actividadComboMensual->id]
        ));

        $this->assertSame('9000.00', (string) $actividadPaciente->total_a_pagar);
    }

    public function test_registrar_sin_orden_kine_usa_precio_combo_cuando_existe(): void
    {
        Carbon::setTestNow('2026-06-02 09:00:00');

        ['actividad' => $actividad] = $this->crearActividadKinesiologiaConPrecios(precioCombo5: 9000.00);
        $paciente = $this->crearPaciente();

        $actividadPaciente = $this->service->registrar($this->payloadSinOrdenKine(
            actividad: $actividad,
            paciente: $paciente,
            cantSesiones: 5
        ));

        $this->assertSame('9000.00', (string) $actividadPaciente->total_a_pagar);
        $this->assertFalse($actividadPaciente->pago_completado);
    }

    public function test_registrar_sin_orden_kine_aplica_precio_individual_por_cantidad_si_no_hay_combo_exacto(): void
    {
        Carbon::setTestNow('2026-06-02 09:00:00');

        ['actividad' => $actividad] = $this->crearActividadKinesiologiaConPrecios(
            precioSesion: 2000.00,
            precioCombo10: 18000.00
        );
        $paciente = $this->crearPaciente();

        $actividadPaciente = $this->service->registrar($this->payloadSinOrdenKine(
            actividad: $actividad,
            paciente: $paciente,
            cantSesiones: 5
        ));

        $this->assertSame('10000.00', (string) $actividadPaciente->total_a_pagar);
        $this->assertFalse($actividadPaciente->pago_completado);
    }

    public function test_registrar_sin_orden_general_usa_obtener_precio_mensual(): void
    {
        Carbon::setTestNow('2026-06-02 09:00:00');

        ['actividad' => $actividad, 'actividadCombo' => $actividadCombo] = $this->crearActividadGeneralConAbonoMensual(15000.00);
        $paciente = $this->crearPaciente();

        $actividadPaciente = $this->service->registrar($this->payloadSinOrdenGeneral(
            actividad: $actividad,
            paciente: $paciente,
            actividadCombo: $actividadCombo,
            cantSesiones: 4
        ));

        $this->assertSame('15000.00', (string) $actividadPaciente->total_a_pagar);
        $this->assertFalse($actividadPaciente->pago_completado);
    }

    public function test_registrar_autogenerados_crea_turnos_y_precio_individual_por_cantidad(): void
    {
        Carbon::setTestNow('2026-06-01 08:00:00');

        ['actividad' => $actividad] = $this->crearActividadKinesiologiaConPrecios(precioSesion: 2000.00);
        $this->asociarHorario($actividad, '10:00:00');
        $paciente = $this->crearPaciente();

        $actividadPaciente = $this->service->registrar([
            'id_actividad' => $actividad->id,
            'id_paciente' => $paciente->id,
            'autogenerados' => true,
            'fecha_ancla' => '2026-06-01',
            'frecuencia_semanal' => 2,
            'cant_sesiones' => 4,
            'turnos' => [
                ['dia_semana' => 'Lunes', 'hora_inicio' => '10:00:00'],
                ['dia_semana' => 'Miércoles', 'hora_inicio' => '10:00:00'],
            ],
        ]);

        $this->assertSame(4, $actividadPaciente->cant_sesiones);
        $this->assertSame('8000.00', (string) $actividadPaciente->total_a_pagar);
        $this->assertFalse($actividadPaciente->pago_completado);
        $this->assertCount(4, $actividadPaciente->turnos);
    }

    public function test_no_permite_inscripcion_general_con_plan_activo_fuera_de_ventana(): void
    {
        Carbon::setTestNow('2026-06-01 10:00:00');

        ['actividad' => $actividad, 'actividadCombo' => $actividadCombo] = $this->crearActividadGeneralConAbonoMensual(15000.00);
        $paciente = $this->crearPaciente();

        $this->crearInscripcionConUltimoTurno(
            paciente: $paciente,
            actividad: $actividad,
            fechaUltimoTurno: '2026-06-25 10:00:00'
        );

        $this->expectException(Exception::class);
        $this->expectExceptionMessage(ActividadPacienteService::MENSAJE_INSCRIPCION_NO_DISPONIBLE);

        $this->service->registrar($this->payloadSinOrdenGeneral(
            actividad: $actividad,
            paciente: $paciente,
            actividadCombo: $actividadCombo,
            cantSesiones: 4
        ));
    }

    public function test_permite_inscripcion_general_dentro_de_ventana_de_siete_dias(): void
    {
        Carbon::setTestNow('2026-06-01 10:00:00');

        ['actividad' => $actividad, 'actividadCombo' => $actividadCombo] = $this->crearActividadGeneralConAbonoMensual(15000.00);
        $paciente = $this->crearPaciente();

        $this->crearInscripcionConUltimoTurno(
            paciente: $paciente,
            actividad: $actividad,
            fechaUltimoTurno: '2026-06-06 10:00:00'
        );

        $nueva = $this->service->registrar($this->payloadSinOrdenGeneral(
            actividad: $actividad,
            paciente: $paciente,
            actividadCombo: $actividadCombo,
            cantSesiones: 4
        ));

        $this->assertSame(2, ActividadPaciente::count());
        $this->assertSame(4, $nueva->cant_sesiones);
    }

    public function test_permite_doble_inscripcion_kine_el_mismo_dia_porque_no_aplica_ventana_general(): void
    {
        Carbon::setTestNow('2026-06-02 09:00:00');

        ['actividad' => $actividad] = $this->crearActividadKinesiologiaConPrecios(precioCombo5: 9000.00);
        $paciente = $this->crearPaciente();
        $payload = $this->payloadSinOrdenKine($actividad, $paciente, 5);

        $this->service->registrar($payload);
        $this->service->registrar($payload);

        $this->assertSame(2, ActividadPaciente::count());
        $this->assertSame(10, Turno::count());
    }

    public function test_revierte_transaccion_si_falla_creacion_de_turnos(): void
    {
        Carbon::setTestNow('2026-06-02 09:00:00');

        ['actividad' => $actividad] = $this->crearActividadKinesiologiaConPrecios(precioCombo5: 9000.00);
        $paciente = $this->crearPaciente();

        $this->mock(TurnoService::class, function ($mock) {
            $mock->shouldReceive('prepararTurnosManuales')
                ->once()
                ->andThrow(new Exception('Fallo simulado al persistir turnos.'));
        });

        $service = app(ActividadPacienteService::class);

        try {
            $service->registrar($this->payloadSinOrdenKine($actividad, $paciente, 5));
            $this->fail('Se esperaba una excepción durante la creación de turnos.');
        } catch (Exception $e) {
            $this->assertSame('Fallo simulado al persistir turnos.', $e->getMessage());
        }

        $this->assertSame(0, ActividadPaciente::count());
        $this->assertSame(0, Turno::count());
    }

    private function payloadConOrden(
        Actividad $actividad,
        Paciente $paciente,
        int $sesionesCubiertas,
        array $extra = []
    ): array {
        return array_merge([
            'id_actividad' => $actividad->id,
            'id_paciente' => $paciente->id,
            'autogenerados' => false,
            'frecuencia_semanal' => 1,
            'sesiones_cubiertas' => $sesionesCubiertas,
            'mes' => 3,
            'dia' => 15,
            'turnos' => $this->generarTurnosManuales($sesionesCubiertas),
        ], $extra);
    }

    private function payloadSinOrdenKine(Actividad $actividad, Paciente $paciente, int $cantSesiones): array
    {
        return [
            'id_actividad' => $actividad->id,
            'id_paciente' => $paciente->id,
            'autogenerados' => false,
            'frecuencia_semanal' => 1,
            'cant_sesiones' => $cantSesiones,
            'turnos' => $this->generarTurnosManuales($cantSesiones),
        ];
    }

    private function payloadSinOrdenGeneral(
        Actividad $actividad,
        Paciente $paciente,
        ActividadCombo $actividadCombo,
        int $cantSesiones
    ): array {
        return [
            'id_actividad' => $actividad->id,
            'id_paciente' => $paciente->id,
            'autogenerados' => false,
            'frecuencia_semanal' => 1,
            'cant_sesiones' => $cantSesiones,
            'id_actividad_combo' => $actividadCombo->id,
            'turnos' => $this->generarTurnosManuales($cantSesiones),
        ];
    }

    private function crearActividadKinesiologiaConPrecios(
        float $precioSesion = 2000.00,
        ?float $precioCombo5 = null,
        ?float $precioCombo10 = null,
        ?float $precioMensual = null
    ): array {

        $actividad = Actividad::create([
            'nombre' => 'ActKin T' . uniqid(),
            'id_tipo_actividad' => Actividad::TIPO_KINESIOLOGIA,
        ]);

        $this->crearVinculoConPrecio($actividad, cantidadSesiones: 1, esMensual: false, precio: $precioSesion);

        if ($precioCombo5 !== null) {
            $this->crearVinculoConPrecio($actividad, cantidadSesiones: 5, esMensual: false, precio: $precioCombo5);
        }

        if ($precioCombo10 !== null) {
            $this->crearVinculoConPrecio($actividad, cantidadSesiones: 10, esMensual: false, precio: $precioCombo10);
        }

        $actividadComboMensual = null;

        if ($precioMensual !== null) {
            $actividadComboMensual = $this->crearVinculoConPrecio(
                $actividad,
                cantidadSesiones: 8,
                esMensual: true,
                precio: $precioMensual
            );
        }

        return [
            'actividad' => $actividad,
            'actividadComboMensual' => $actividadComboMensual,
        ];
    }

    private function crearActividadGeneralConAbonoMensual(float $precioMensual): array
    {
        $actividad = Actividad::create([
            'nombre' => 'ActGen T' . uniqid(),
            'id_tipo_actividad' => Actividad::TIPO_GENERAL,
        ]);

        $actividadCombo = $this->crearVinculoConPrecio(
            $actividad,
            cantidadSesiones: 8,
            esMensual: true,
            precio: $precioMensual
        );

        return [
            'actividad' => $actividad,
            'actividadCombo' => $actividadCombo,
        ];
    }

    private function crearVinculoConPrecio(
        Actividad $actividad,
        int $cantidadSesiones,
        bool $esMensual,
        float $precio
    ): ActividadCombo {
        $combo = Combo::create([
            'nombre' => "Cx{$cantidadSesiones} T" . uniqid(),
            'cantidad_sesiones' => $cantidadSesiones,
            'es_mensual' => $esMensual,
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

        return $actividadCombo;
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

    private function crearPacienteConAfiliacion(): Paciente
    {
        $paciente = $this->crearPaciente();

        $obraSocial = ObraSocial::create([
            'nombre' => 'Obra social ' . uniqid(),
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

    private function asociarHorario(Actividad $actividad, string $horaInicio): Horario
    {
        $horario = Horario::create([
            'hora_inicio' => $horaInicio,
            'franja' => 'M',
        ]);

        $actividad->horarios()->attach($horario->id);

        return $horario;
    }

    private function generarTurnosManuales(int $cantidad): array
    {
        $turnos = [];

        for ($i = 1; $i <= $cantidad; $i++) {
            $turnos[] = Carbon::now()->addDays($i)->setTime(10, 0, 0)->format('Y-m-d H:i:s');
        }

        return $turnos;
    }

    private function crearInscripcionConUltimoTurno(
        Paciente $paciente,
        Actividad $actividad,
        string $fechaUltimoTurno
    ): ActividadPaciente {
        $actPac = ActividadPaciente::create([
            'id_actividad' => $actividad->id,
            'id_paciente' => $paciente->id,
            'cant_sesiones' => 2,
            'es_fijo' => false,
            'total_a_pagar' => 1000,
        ]);

        Turno::create([
            'id_act_pac' => $actPac->id,
            'nro_turno' => 1,
            'fecha_hora' => Carbon::parse($fechaUltimoTurno)->subDay()->format('Y-m-d H:i:s'),
        ]);

        Turno::create([
            'id_act_pac' => $actPac->id,
            'nro_turno' => 2,
            'fecha_hora' => $fechaUltimoTurno,
        ]);

        return $actPac;
    }
}
