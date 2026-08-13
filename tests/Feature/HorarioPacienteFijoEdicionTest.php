<?php

namespace Tests\Feature;

use App\Models\Actividad;
use App\Models\ActividadPaciente;
use App\Models\Horario;
use App\Models\Paciente;
use App\Models\PrecioMensual;
use App\Models\Turno;
use App\Services\ActividadPacienteService;
use App\Services\HorarioPacienteFijoService;
use App\Services\TurnoService;
use App\Support\Registros\ResultadoInscripcionGeneral;
use Carbon\Carbon;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HorarioPacienteFijoEdicionTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_cambio_de_dias_en_martes_de_semana_2_aplica_desde_semana_3(): void
    {
        // Semana 1: Lun 1/6, Mié 3/6. Semana 2: Lun 8/6, Mié 10/6. Martes 9/6 = corte solicitado.
        Carbon::setTestNow('2026-06-01 08:00:00');

        $this->crearPreciosMensuales([2 => 20000.00]);
        $this->asociarHorarioAPilates();
        $this->mockTurnoServiceSinValidarCupo();

        $paciente = $this->crearPaciente();
        $resultado = app(ActividadPacienteService::class)->registrarInscripcionesGenerales([
            'id_paciente' => $paciente->id,
            'fecha_ancla' => '2026-06-01',
            'horarios' => [
                ['id_actividad' => Actividad::PILATES, 'dia_semana' => 'Lunes', 'hora_inicio' => '10:00:00'],
                ['id_actividad' => Actividad::PILATES, 'dia_semana' => 'Miércoles', 'hora_inicio' => '10:00:00'],
            ],
        ]);

        $pacienteFijo = $resultado->pacienteFijo;
        $inscripcion = $resultado->inscripciones->first();

        Carbon::setTestNow('2026-06-09 08:00:00'); // martes semana 2

        $horariosNuevos = [
            ['id_actividad' => Actividad::PILATES, 'dia_semana' => 'Jueves', 'hora_inicio' => '10:00:00'],
            ['id_actividad' => Actividad::PILATES, 'dia_semana' => 'Viernes', 'hora_inicio' => '10:00:00'],
        ];

        // Pasados (Lun) + slots nuevos (Jue+Vie) = 3 > freq 2 → forzar semana siguiente.
        $this->assertTrue(
            app(HorarioPacienteFijoService::class)->debeForzarSemanaSiguiente($pacienteFijo->id, $horariosNuevos)
        );

        app(HorarioPacienteFijoService::class)->actualizar(
            $pacienteFijo->id,
            $horariosNuevos,
            Carbon::parse('2026-06-09 08:00:00')
        );

        $fechas = Turno::query()
            ->where('id_act_pac', $inscripcion->id)
            ->whereNull('id_turno_original')
            ->orderBy('fecha_hora')
            ->pluck('fecha_hora')
            ->map(fn ($f) => Carbon::parse($f)->format('Y-m-d'))
            ->all();

        $this->assertCount(8, $fechas); // frecuencia operativa 2 × 4 semanas

        $porSemana = collect($fechas)
            ->groupBy(fn (string $fecha) => Carbon::parse($fecha)->startOfWeek(Carbon::MONDAY)->format('Y-m-d'))
            ->map->count();

        $this->assertTrue(
            $porSemana->every(fn (int $cantidad) => $cantidad === 2),
            'Cada semana calendario debe tener exactamente 2 turnos (frecuencia operativa).'
        );

        // Semana 2 intacta (Lun+Mié): el corte efectivo es lunes 15/6.
        $this->assertContains('2026-06-08', $fechas);
        $this->assertContains('2026-06-10', $fechas);
        $this->assertNotContains('2026-06-11', $fechas);
        $this->assertNotContains('2026-06-12', $fechas);

        // Semana 3 en adelante: Jue+Vie, sin Lun/Mié.
        $this->assertNotContains('2026-06-15', $fechas);
        $this->assertNotContains('2026-06-17', $fechas);
        $this->assertContains('2026-06-18', $fechas);
        $this->assertContains('2026-06-19', $fechas);

        $pacienteFijo->refresh()->load('horarios');
        $this->assertSame(
            [4, 5],
            $pacienteFijo->horarios->pluck('dia_semana')->map(fn ($d) => (int) $d)->sort()->values()->all()
        );
    }

    public function test_cambio_a_slot_ya_pasado_esta_semana_aplica_desde_la_semana_siguiente(): void
    {
        // Ancla: Mar 2/6, Jue 4/6 (semana 1). Semana 2: Mar 9/6, Jue 11/6.
        Carbon::setTestNow('2026-06-02 08:00:00');

        $this->crearPreciosMensuales([2 => 20000.00]);
        $this->asociarHorarioAPilates();
        $this->mockTurnoServiceSinValidarCupo();

        $paciente = $this->crearPaciente();
        $resultado = app(ActividadPacienteService::class)->registrarInscripcionesGenerales([
            'id_paciente' => $paciente->id,
            'fecha_ancla' => '2026-06-02',
            'horarios' => [
                ['id_actividad' => Actividad::PILATES, 'dia_semana' => 'Martes', 'hora_inicio' => '10:00:00'],
                ['id_actividad' => Actividad::PILATES, 'dia_semana' => 'Jueves', 'hora_inicio' => '10:00:00'],
            ],
        ]);

        $pacienteFijo = $resultado->pacienteFijo;
        $inscripcion = $resultado->inscripciones->first();

        // Lunes de la semana 2, 14hs: el martes y el jueves de esa semana todavía no ocurrieron.
        Carbon::setTestNow('2026-06-08 14:00:00');

        $horariosNuevos = [
            ['id_actividad' => Actividad::PILATES, 'dia_semana' => 'Lunes', 'hora_inicio' => '10:00:00'],
            ['id_actividad' => Actividad::PILATES, 'dia_semana' => 'Jueves', 'hora_inicio' => '10:00:00'],
        ];

        // El lunes 10:00 de esta semana ya pasó y no existe como turno → forzar semana siguiente.
        $this->assertTrue(
            app(HorarioPacienteFijoService::class)->debeForzarSemanaSiguiente($pacienteFijo->id, $horariosNuevos)
        );

        app(HorarioPacienteFijoService::class)->actualizar(
            $pacienteFijo->id,
            $horariosNuevos,
            Carbon::parse('2026-06-08 14:00:00')
        );

        $fechas = Turno::query()
            ->where('id_act_pac', $inscripcion->id)
            ->whereNull('id_turno_original')
            ->orderBy('fecha_hora')
            ->pluck('fecha_hora')
            ->map(fn ($f) => Carbon::parse($f)->format('Y-m-d'))
            ->all();

        $this->assertCount(8, $fechas); // frecuencia operativa 2 × 4 semanas

        $porSemana = collect($fechas)
            ->groupBy(fn (string $fecha) => Carbon::parse($fecha)->startOfWeek(Carbon::MONDAY)->format('Y-m-d'))
            ->map->count();

        $this->assertTrue(
            $porSemana->every(fn (int $cantidad) => $cantidad === 2),
            'Cada semana calendario debe tener exactamente 2 turnos (frecuencia operativa).'
        );

        // Semana 2 intacta (Mar+Jue del patrón viejo): el lunes 07:00 nuevo no puede
        // crearse esta semana (ya pasó), por lo que el corte efectivo es lunes 15/6.
        $this->assertContains('2026-06-09', $fechas);
        $this->assertContains('2026-06-11', $fechas);
        $this->assertNotContains('2026-06-08', $fechas);

        // Semana 3 en adelante: Lun 07:00 + Jue 11:00.
        $this->assertContains('2026-06-15', $fechas);
        $this->assertContains('2026-06-18', $fechas);
        $this->assertContains('2026-06-22', $fechas);
        $this->assertContains('2026-06-25', $fechas);

        $pacienteFijo->refresh()->load('horarios');
        $this->assertSame(
            [1, 4],
            $pacienteFijo->horarios->pluck('dia_semana')->map(fn ($d) => (int) $d)->sort()->values()->all()
        );
    }

    public function test_editar_cerca_del_fin_del_ciclo_no_extiende_la_ventana_aunque_quede_cupo_contractual(): void
    {
        // Ancla: Lun 1/6. Ventana del ciclo: [1/6, 29/6).
        Carbon::setTestNow('2026-06-01 08:00:00');

        $this->crearPreciosMensuales([2 => 20000.00, 3 => 30000.00]);
        $this->asociarHorarioAPilates();
        $this->mockTurnoServiceSinValidarCupo();

        $paciente = $this->crearPaciente();
        $resultado = app(ActividadPacienteService::class)->registrarInscripcionesGenerales([
            'id_paciente' => $paciente->id,
            'fecha_ancla' => '2026-06-01',
            'horarios' => [
                ['id_actividad' => Actividad::PILATES, 'dia_semana' => 'Lunes', 'hora_inicio' => '10:00:00'],
                ['id_actividad' => Actividad::PILATES, 'dia_semana' => 'Miércoles', 'hora_inicio' => '10:00:00'],
            ],
        ]);

        $pacienteFijo = $resultado->pacienteFijo;
        $inscripcion = $resultado->inscripciones->first();

        // Lunes de la última semana del ciclo (22-28/6), antes del turno de las 10hs:
        // no hay turnos pasados todavía esta semana, así que el corte no se pospone.
        Carbon::setTestNow('2026-06-22 09:00:00');

        app(HorarioPacienteFijoService::class)->actualizar(
            $pacienteFijo->id,
            [
                ['id_actividad' => Actividad::PILATES, 'dia_semana' => 'Lunes', 'hora_inicio' => '10:00:00'],
                ['id_actividad' => Actividad::PILATES, 'dia_semana' => 'Miércoles', 'hora_inicio' => '10:00:00'],
                ['id_actividad' => Actividad::PILATES, 'dia_semana' => 'Viernes', 'hora_inicio' => '10:00:00'],
            ],
            Carbon::parse('2026-06-22 09:00:00')
        );

        $fechas = Turno::query()
            ->where('id_act_pac', $inscripcion->id)
            ->whereNull('id_turno_original')
            ->orderBy('fecha_hora')
            ->pluck('fecha_hora')
            ->map(fn ($f) => Carbon::parse($f)->format('Y-m-d'))
            ->all();

        // El contrato subió a 3×4=12 sesiones, pero la ventana del ciclo termina el 29/6:
        // solo entra el viernes 26/6 de la última semana; el resto del cupo se pierde.
        $this->assertContains('2026-06-26', $fechas);
        $this->assertCount(9, $fechas); // 6 pasados (1,3,8,10,15,17) + 22, 24 (conservados) + 26 (nuevo)

        $this->assertTrue(
            collect($fechas)->every(fn (string $f) => Carbon::parse($f)->lt('2026-06-29')),
            'La ventana del ciclo no debe extenderse más allá de ancla + 4 semanas.'
        );

        // cant_sesiones queda como marca de agua del contrato (3×4), aunque los
        // turnos reales (9) no lo agoten: son la agenda real, no el cupo nominal.
        $this->assertSame(12, $inscripcion->fresh()->cant_sesiones);
    }

    public function test_no_permite_editar_horarios_si_el_paciente_no_esta_cursando_inscripcion(): void
    {
        Carbon::setTestNow('2026-06-01 08:00:00');

        $this->crearPreciosMensuales([1 => 15000.00]);
        $this->asociarHorarioAPilates();
        $this->mockTurnoServiceSinValidarCupo();

        $paciente = $this->crearPaciente();
        $resultado = app(ActividadPacienteService::class)->registrarInscripcionesGenerales([
            'id_paciente' => $paciente->id,
            'fecha_ancla' => '2026-07-01', // ancla futura: todavía no cursa (sin turnos pasados)
            'horarios' => [
                ['id_actividad' => Actividad::PILATES, 'dia_semana' => 'Lunes', 'hora_inicio' => '10:00:00'],
            ],
        ]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage(
            'No se pueden editar los horarios fijos porque el paciente no está cursando una inscripción. Eliminá el registro de fijo y creá la inscripción nuevamente.'
        );

        app(HorarioPacienteFijoService::class)->actualizar(
            $resultado->pacienteFijo->id,
            [
                ['id_actividad' => Actividad::PILATES, 'dia_semana' => 'Martes', 'hora_inicio' => '10:00:00'],
            ],
            Carbon::now()
        );
    }

    public function test_aumento_de_frecuencia_con_slot_futuro_aplica_desde_esta_semana(): void
    {
        // Lun pilates ya pasó. El martes se suma Mié gym: no hay overflow (1 pasado + 1
        // slot nuevo = freq 2) ni slot perdido → el corte NO se pospone.
        Carbon::setTestNow('2026-06-01 08:00:00');

        $this->crearPreciosMensuales([1 => 10000.00, 2 => 20000.00]);
        $this->asociarHorarioAGimnasioYPilates();
        $this->mockTurnoServiceSinValidarCupo();

        $paciente = $this->crearPaciente();
        $resultado = app(ActividadPacienteService::class)->registrarInscripcionesGenerales([
            'id_paciente' => $paciente->id,
            'fecha_ancla' => '2026-06-01',
            'horarios' => [
                ['id_actividad' => Actividad::PILATES, 'dia_semana' => 'Lunes', 'hora_inicio' => '10:00:00'],
            ],
        ]);

        $pacienteFijo = $resultado->pacienteFijo;
        $pilates = $resultado->inscripciones->first();

        Carbon::setTestNow('2026-06-02 08:00:00'); // martes
        $corte = Carbon::parse('2026-06-02 08:00:00');
        $horariosNuevos = [
            ['id_actividad' => Actividad::PILATES, 'dia_semana' => 'Lunes', 'hora_inicio' => '10:00:00'],
            ['id_actividad' => Actividad::GIMNASIO, 'dia_semana' => 'Miércoles', 'hora_inicio' => '10:00:00'],
        ];

        $this->assertFalse(
            app(HorarioPacienteFijoService::class)->debeForzarSemanaSiguiente($pacienteFijo->id, $horariosNuevos)
        );

        app(HorarioPacienteFijoService::class)->actualizar($pacienteFijo->id, $horariosNuevos, $corte);

        $gimnasio = ActividadPaciente::where('id_paciente', $paciente->id)
            ->where('id_actividad', Actividad::GIMNASIO)
            ->first();

        $this->assertNotNull($gimnasio);
        $this->assertTrue(
            Turno::where('id_act_pac', $gimnasio->id)->whereDate('fecha_hora', '2026-06-03')->exists(),
            'El miércoles de esta misma semana debe crearse (no posponer a la siguiente).'
        );
        $this->assertSame(1, Turno::where('id_act_pac', $pilates->id)->whereDate('fecha_hora', '2026-06-01')->count());
    }

    public function test_actividad_sin_turnos_pasados_se_elimina_al_salir_del_patron(): void
    {
        // Pilates (Lunes) arranca sola. El martes se agrega Gimnasio miércoles: aplica
        // esta semana (aumento de freq sin overflow). Gimnasio nace sin turnos pasados.
        // Antes del miércoles se retira del patrón → se elimina la inscripción.
        Carbon::setTestNow('2026-06-01 08:00:00');

        $this->crearPreciosMensuales([1 => 10000.00, 2 => 20000.00]);
        $this->asociarHorarioAGimnasioYPilates();
        $this->mockTurnoServiceSinValidarCupo();

        $paciente = $this->crearPaciente();
        $resultado = app(ActividadPacienteService::class)->registrarInscripcionesGenerales([
            'id_paciente' => $paciente->id,
            'fecha_ancla' => '2026-06-01',
            'horarios' => [
                ['id_actividad' => Actividad::PILATES, 'dia_semana' => 'Lunes', 'hora_inicio' => '10:00:00'],
            ],
        ]);

        $pilates = $resultado->inscripciones->first();

        Carbon::setTestNow('2026-06-02 08:00:00'); // martes: el lunes de pilates ya pasó
        $corte = Carbon::parse('2026-06-02 08:00:00');

        app(HorarioPacienteFijoService::class)->actualizar(
            $resultado->pacienteFijo->id,
            [
                ['id_actividad' => Actividad::PILATES, 'dia_semana' => 'Lunes', 'hora_inicio' => '10:00:00'],
                ['id_actividad' => Actividad::GIMNASIO, 'dia_semana' => 'Miércoles', 'hora_inicio' => '10:00:00'],
            ],
            $corte
        );

        $gimnasio = ActividadPaciente::where('id_paciente', $paciente->id)
            ->where('id_actividad', Actividad::GIMNASIO)
            ->first();

        $this->assertNotNull($gimnasio);
        $this->assertSame(0, Turno::where('id_act_pac', $gimnasio->id)->where('fecha_hora', '<', $corte)->count());
        $this->assertTrue(Turno::where('id_act_pac', $gimnasio->id)->whereDate('fecha_hora', '2026-06-03')->exists());

        // Se retira Gimnasio del patrón antes de que llegue a tener ningún turno pasado.
        app(HorarioPacienteFijoService::class)->actualizar(
            $resultado->pacienteFijo->id,
            [
                ['id_actividad' => Actividad::PILATES, 'dia_semana' => 'Lunes', 'hora_inicio' => '10:00:00'],
            ],
            $corte
        );

        $this->assertNull(ActividadPaciente::find($gimnasio->id));
        $this->assertSame(0, Turno::where('id_act_pac', $gimnasio->id)->count());

        $pilates->refresh();
        $this->assertNull($pilates->id_act_pac_dual);
        $this->assertNull($pilates->frecuencia_total_dual);
    }

    public function test_actividad_con_turnos_pasados_se_cierra_y_mantiene_el_vinculo_dual_al_salir_del_patron(): void
    {
        // Pilates (Lunes) y Gimnasio (Miércoles) ya tuvieron turno; Gimnasio (Viernes) no.
        Carbon::setTestNow('2026-06-01 08:00:00');

        $this->crearPreciosMensuales([1 => 10000.00, 2 => 20000.00]);
        $this->asociarHorarioAGimnasioYPilates();
        $this->mockTurnoServiceSinValidarCupo();

        $paciente = $this->crearPaciente();
        $resultado = $this->registrarInscripcionDual($paciente, [
            ['id_actividad' => Actividad::PILATES, 'dia_semana' => 'Lunes', 'hora_inicio' => '10:00:00'],
            ['id_actividad' => Actividad::GIMNASIO, 'dia_semana' => 'Miércoles', 'hora_inicio' => '10:00:00'],
        ]);

        $pilates = $resultado->inscripciones->firstWhere('id_actividad', Actividad::PILATES);
        $gimnasio = $resultado->inscripciones->firstWhere('id_actividad', Actividad::GIMNASIO);

        // Jueves 4/6, 08hs: pilates (lun) y gimnasio (mié) ya tuvieron su turno de esta semana.
        Carbon::setTestNow('2026-06-04 08:00:00');

        app(HorarioPacienteFijoService::class)->actualizar(
            $resultado->pacienteFijo->id,
            [
                ['id_actividad' => Actividad::PILATES, 'dia_semana' => 'Lunes', 'hora_inicio' => '10:00:00'],
            ],
            Carbon::parse('2026-06-04 08:00:00')
        );

        $gimnasio->refresh();
        $pilates->refresh();

        // Se cierra (no se elimina): conserva el historial, sin cupo activo.
        $this->assertNotNull(ActividadPaciente::find($gimnasio->id));
        $this->assertSame(0, $gimnasio->cant_sesiones);
        $this->assertSame(1, Turno::where('id_act_pac', $gimnasio->id)->where('fecha_hora', '<', '2026-06-04')->count());
        $this->assertSame(0, Turno::where('id_act_pac', $gimnasio->id)->where('fecha_hora', '>=', '2026-06-04')->count());

        // El vínculo dual se mantiene entre ambas inscripciones aunque gimnasio esté cerrado.
        $this->assertSame($pilates->id, $gimnasio->id_act_pac_dual);
        $this->assertSame($gimnasio->id, $pilates->id_act_pac_dual);
    }

    public function test_agregar_gimnasio_a_inscripcion_simple_de_pilates_crea_vinculo_dual_y_mantiene_el_cobro_en_pilates(): void
    {
        Carbon::setTestNow('2026-06-01 08:00:00');

        $this->crearPreciosMensuales([1 => 10000.00, 3 => 30000.00]);
        $this->asociarHorarioAGimnasioYPilates();
        $this->mockTurnoServiceSinValidarCupo();

        $paciente = $this->crearPaciente();
        $resultado = app(ActividadPacienteService::class)->registrarInscripcionesGenerales([
            'id_paciente' => $paciente->id,
            'fecha_ancla' => '2026-06-01',
            'horarios' => [
                ['id_actividad' => Actividad::PILATES, 'dia_semana' => 'Lunes', 'hora_inicio' => '10:00:00'],
            ],
        ]);

        $pilates = $resultado->inscripciones->first();
        $this->assertSame('10000.00', (string) $pilates->total_a_pagar);

        // Lunes de la semana 2, antes del turno de pilates de esa semana: ya cursa
        // (tuvo el turno de la semana 1) y el corte no se pospone.
        Carbon::setTestNow('2026-06-08 09:00:00');

        app(HorarioPacienteFijoService::class)->actualizar(
            $resultado->pacienteFijo->id,
            [
                ['id_actividad' => Actividad::PILATES, 'dia_semana' => 'Lunes', 'hora_inicio' => '10:00:00'],
                ['id_actividad' => Actividad::GIMNASIO, 'dia_semana' => 'Miércoles', 'hora_inicio' => '10:00:00'],
                ['id_actividad' => Actividad::GIMNASIO, 'dia_semana' => 'Viernes', 'hora_inicio' => '10:00:00'],
            ],
            Carbon::parse('2026-06-08 09:00:00')
        );

        $pilates->refresh();
        $gimnasio = ActividadPaciente::where('id_paciente', $paciente->id)
            ->where('id_actividad', Actividad::GIMNASIO)
            ->first();

        $this->assertNotNull($gimnasio);
        $this->assertGreaterThan($pilates->id, $gimnasio->id);

        // El vínculo dual queda establecido y la frecuencia total es la marca de agua del plan.
        $this->assertSame($gimnasio->id, $pilates->id_act_pac_dual);
        $this->assertSame($pilates->id, $gimnasio->id_act_pac_dual);
        $this->assertSame(3, $pilates->frecuencia_total_dual);
        $this->assertSame(3, $gimnasio->frecuencia_total_dual);

        // Invariante: sum(cant_sesiones) / 4 == frecuencia_total_dual.
        $this->assertSame(4, $pilates->cant_sesiones);
        $this->assertSame(8, $gimnasio->cant_sesiones);

        // El cobro (incluido el cargo proporcional por subir de frecuencia) sigue en
        // pilates, la inscripción líder (menor id); gimnasio queda saldado. El cargo
        // extra solo cubre las sesiones nuevas que entran en la ventana restante del
        // ciclo (semanas 2 a 4: 6 turnos de gimnasio), no las 4 semanas completas.
        $this->assertSame('25000.00', (string) $pilates->total_a_pagar);
        $this->assertSame('0.00', (string) $gimnasio->total_a_pagar);
        $this->assertFalse($pilates->pago_completado);
        $this->assertTrue($gimnasio->pago_completado);
    }

    public function test_congela_cant_sesiones_dual_al_operar_por_debajo_del_tope_contratado(): void
    {
        Carbon::setTestNow('2026-06-01 08:00:00');

        $this->crearPreciosMensuales([2 => 20000.00, 4 => 40000.00]);
        $this->asociarHorarioAGimnasioYPilates();
        $this->mockTurnoServiceSinValidarCupo();

        $paciente = $this->crearPaciente();
        $resultado = $this->registrarInscripcionDual($paciente, [
            ['id_actividad' => Actividad::GIMNASIO, 'dia_semana' => 'Lunes', 'hora_inicio' => '10:00:00'],
            ['id_actividad' => Actividad::GIMNASIO, 'dia_semana' => 'Miércoles', 'hora_inicio' => '10:00:00'],
            ['id_actividad' => Actividad::GIMNASIO, 'dia_semana' => 'Viernes', 'hora_inicio' => '10:00:00'],
            ['id_actividad' => Actividad::PILATES, 'dia_semana' => 'Martes', 'hora_inicio' => '10:00:00'],
        ]);

        $gimnasio = $resultado->inscripciones->firstWhere('id_actividad', Actividad::GIMNASIO);
        $pilates = $resultado->inscripciones->firstWhere('id_actividad', Actividad::PILATES);

        $this->assertSame(12, $gimnasio->cant_sesiones);
        $this->assertSame(4, $pilates->cant_sesiones);

        Carbon::setTestNow('2026-06-09 08:00:00'); // martes semana 2, antes del turno de pilates de hoy

        app(HorarioPacienteFijoService::class)->actualizar(
            $resultado->pacienteFijo->id,
            [
                ['id_actividad' => Actividad::GIMNASIO, 'dia_semana' => 'Lunes', 'hora_inicio' => '10:00:00'],
                ['id_actividad' => Actividad::PILATES, 'dia_semana' => 'Martes', 'hora_inicio' => '10:00:00'],
            ],
            Carbon::parse('2026-06-09 08:00:00')
        );

        $gimnasio->refresh();
        $pilates->refresh();

        // Por debajo del tope contratado (4), los cant_sesiones activos se congelan.
        $this->assertSame(12, $gimnasio->cant_sesiones);
        $this->assertSame(4, $pilates->cant_sesiones);
        $this->assertSame(4, $gimnasio->frecuencia_total_dual);
        $this->assertSame(4, $pilates->frecuencia_total_dual);
    }

    public function test_redistribuye_cant_sesiones_dual_al_volver_a_operar_al_tope_contratado(): void
    {
        Carbon::setTestNow('2026-06-01 08:00:00');

        $this->crearPreciosMensuales([2 => 20000.00, 4 => 40000.00]);
        $this->asociarHorarioAGimnasioYPilates();
        $this->mockTurnoServiceSinValidarCupo();

        $paciente = $this->crearPaciente();

        // Arranca operando por debajo del tope (freq 2), pero con la marca de agua
        // de un ciclo anterior contratado a freq 4, congelada en 12+4.
        $resultado = $this->registrarInscripcionDual($paciente, [
            ['id_actividad' => Actividad::GIMNASIO, 'dia_semana' => 'Lunes', 'hora_inicio' => '10:00:00'],
            ['id_actividad' => Actividad::PILATES, 'dia_semana' => 'Martes', 'hora_inicio' => '10:00:00'],
        ]);

        $gimnasio = $resultado->inscripciones->firstWhere('id_actividad', Actividad::GIMNASIO);
        $pilates = $resultado->inscripciones->firstWhere('id_actividad', Actividad::PILATES);

        $gimnasio->update(['cant_sesiones' => 12, 'frecuencia_total_dual' => 4]);
        $pilates->update(['frecuencia_total_dual' => 4]);

        Carbon::setTestNow('2026-06-09 08:00:00'); // martes semana 2

        app(HorarioPacienteFijoService::class)->actualizar(
            $resultado->pacienteFijo->id,
            [
                ['id_actividad' => Actividad::GIMNASIO, 'dia_semana' => 'Lunes', 'hora_inicio' => '10:00:00'],
                ['id_actividad' => Actividad::GIMNASIO, 'dia_semana' => 'Miércoles', 'hora_inicio' => '10:00:00'],
                ['id_actividad' => Actividad::PILATES, 'dia_semana' => 'Martes', 'hora_inicio' => '10:00:00'],
                ['id_actividad' => Actividad::PILATES, 'dia_semana' => 'Jueves', 'hora_inicio' => '10:00:00'],
            ],
            Carbon::parse('2026-06-09 08:00:00')
        );

        $gimnasio->refresh();
        $pilates->refresh();

        // Al volver al tope contratado (4), el pool se redistribuye según freq_act × 4.
        $this->assertSame(8, $gimnasio->cant_sesiones);
        $this->assertSame(8, $pilates->cant_sesiones);
        $this->assertSame(4, $gimnasio->frecuencia_total_dual);
        $this->assertSame(4, $pilates->frecuencia_total_dual);
        $this->assertSame(4, (int) (($gimnasio->cant_sesiones + $pilates->cant_sesiones) / 4));
    }

    /**
     * @param  array<int, array{id_actividad: int, dia_semana: string, hora_inicio: string}>  $horarios
     */
    private function registrarInscripcionDual(Paciente $paciente, array $horarios): ResultadoInscripcionGeneral
    {
        return app(ActividadPacienteService::class)->registrarInscripcionesGenerales([
            'id_paciente' => $paciente->id,
            'fecha_ancla' => '2026-06-01',
            'horarios' => $horarios,
        ]);
    }

    private function asociarHorarioAGimnasioYPilates(): void
    {
        $gimnasio = Actividad::findOrFail(Actividad::GIMNASIO);
        $pilates = Actividad::findOrFail(Actividad::PILATES);

        $horario = Horario::create([
            'hora_inicio' => '10:00:00',
            'franja' => 'M',
        ]);

        $gimnasio->horarios()->attach($horario->id);
        $pilates->horarios()->attach($horario->id);
    }

    /**
     * @param  array<int, float>  $preciosPorFrecuencia
     */
    private function crearPreciosMensuales(array $preciosPorFrecuencia): void
    {
        foreach ($preciosPorFrecuencia as $frecuencia => $precio) {
            PrecioMensual::create([
                'frecuencia_semanal' => $frecuencia,
                'fecha_desde' => '2025-01-01',
                'valor' => $precio,
            ]);
        }
    }

    private function asociarHorarioAPilates(): void
    {
        $pilates = Actividad::findOrFail(Actividad::PILATES);
        $horario = Horario::create([
            'hora_inicio' => '10:00:00',
            'franja' => 'M',
        ]);
        $pilates->horarios()->attach($horario->id);
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

    private function mockTurnoServiceSinValidarCupo(): void
    {
        $this->mock(TurnoService::class, function ($mock) {
            $mock->shouldReceive('prepararFechas')
                ->andReturnUsing(function ($actividad, $idPaciente, array $turnosSolicitados) {
                    return collect($turnosSolicitados)->values()->map(fn ($fecha) => [
                        'fecha_hora' => Carbon::parse($fecha)->format('Y-m-d H:i:s'),
                    ])->all();
                });
        });
    }
}
