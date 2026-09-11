<?php

namespace Tests\Feature;

use App\Events\InscripcionGeneralRegistrada;
use App\Models\Actividad;
use App\Models\ActividadPaciente;
use App\Models\Horario;
use App\Models\Paciente;
use App\Models\PrecioMensual;
use App\Models\Turno;
use App\Services\ActividadPacienteService;
use App\Services\HorarioPacienteFijoService;
use App\Services\RenovacionInscripcionService;
use App\Services\TurnoService;
use App\Support\Registros\ResultadoInscripcionGeneral;
use App\Support\Turnos\ExpansorTurnosPatron;
use App\Support\Turnos\ResultadoPreparacionTurnos;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Alta de fijo (evento) genera la próxima inscripción.
 * El cron solo debe ser idempotente / red de seguridad.
 */
class RenovacionInscripcionServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['turnos.dias_anticipacion_renovacion' => 28]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_alta_simple_en_ventana_crea_proxima_inscripcion(): void
    {
        Carbon::setTestNow('2026-06-01 08:00:00');
        $this->crearPreciosMensuales([2 => 20000.00]);
        $this->asociarHorarioAPilates();
        $this->mockTurnoServiceCompleto();

        $paciente = $this->crearPaciente();
        $this->registrarSimple($paciente);

        $inscripciones = ActividadPaciente::query()
            ->where('id_paciente', $paciente->id)
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $inscripciones);
        $this->assertSame('2026-06-01', $inscripciones[0]->fresh('primerTurno')->primerTurno->fecha_hora->format('Y-m-d'));
        $this->assertSame('2026-06-29', $inscripciones[1]->fresh('primerTurno')->primerTurno->fecha_hora->format('Y-m-d'));
    }

    public function test_alta_dual_en_ventana_crea_proximo_par(): void
    {
        Carbon::setTestNow('2026-06-01 08:00:00');
        $this->crearPreciosMensuales([1 => 10000.00, 2 => 20000.00, 3 => 30000.00]);
        $this->asociarHorarioAGimnasioYPilates();
        $this->mockTurnoServiceCompleto();

        $paciente = $this->crearPaciente();
        $this->registrarDual($paciente);

        $this->assertSame(4, ActividadPaciente::where('id_paciente', $paciente->id)->count());
        $this->assertTrue(
            ActividadPaciente::query()
                ->where('id_paciente', $paciente->id)
                ->get()
                ->every(fn (ActividadPaciente $i) => $i->esDualCompleto())
        );
    }

    public function test_cron_es_idempotente_si_la_proxima_ya_existe(): void
    {
        Carbon::setTestNow('2026-06-01 08:00:00');
        $this->crearPreciosMensuales([2 => 20000.00]);
        $this->asociarHorarioAPilates();
        $this->mockTurnoServiceCompleto();

        $paciente = $this->crearPaciente();
        $pacienteFijo = $this->registrarSimple($paciente)->pacienteFijo;

        $this->assertSame(2, ActividadPaciente::where('id_paciente', $paciente->id)->count());

        $this->ejecutarCron($pacienteFijo->id);
        $this->ejecutarCron($pacienteFijo->id);

        $this->assertSame(2, ActividadPaciente::where('id_paciente', $paciente->id)->count());
    }

    public function test_cron_es_idempotente_en_par_dual(): void
    {
        Carbon::setTestNow('2026-06-01 08:00:00');
        $this->crearPreciosMensuales([1 => 10000.00, 2 => 20000.00, 3 => 30000.00]);
        $this->asociarHorarioAGimnasioYPilates();
        $this->mockTurnoServiceCompleto();

        $paciente = $this->crearPaciente();
        $pacienteFijo = $this->registrarDual($paciente)->pacienteFijo;

        $this->assertSame(4, ActividadPaciente::where('id_paciente', $paciente->id)->count());

        $this->ejecutarCron($pacienteFijo->id);
        $this->ejecutarCron($pacienteFijo->id);

        $this->assertSame(4, ActividadPaciente::where('id_paciente', $paciente->id)->count());
    }

    public function test_editar_simple_a_dual_con_futura_ya_generada_no_deja_huerfana_ni_duplica(): void
    {
        // Caso producción: x3 Pilates con mes siguiente (vía alta) → editar a x4 G1 P3.
        Carbon::setTestNow('2026-06-01 08:00:00');
        $this->crearPreciosMensuales([
            3 => 30000.00,
            4 => 40000.00,
        ]);
        $this->asociarHorarioAGimnasioYPilates();
        $this->mockTurnoServiceCompleto();

        $paciente = $this->crearPaciente();
        $resultado = $this->registrarSimpleX3($paciente);
        $pacienteFijo = $resultado->pacienteFijo;
        $pilatesActualId = $resultado->inscripciones->first()->id;

        $this->assertSame(2, ActividadPaciente::where('id_paciente', $paciente->id)->count());

        $futuraSimple = ActividadPaciente::query()
            ->where('id_paciente', $paciente->id)
            ->where('id_actividad', Actividad::PILATES)
            ->where('id', '!=', $pilatesActualId)
            ->first();
        $this->assertNotNull($futuraSimple);
        $this->assertNull($futuraSimple->id_act_pac_dual);

        Carbon::setTestNow('2026-06-10 08:00:00');

        app(HorarioPacienteFijoService::class)->actualizar(
            $pacienteFijo->id,
            [
                ['id_actividad' => Actividad::GIMNASIO, 'dia_semana' => 'Martes', 'hora_inicio' => '10:00:00'],
                ['id_actividad' => Actividad::PILATES, 'dia_semana' => 'Lunes', 'hora_inicio' => '10:00:00'],
                ['id_actividad' => Actividad::PILATES, 'dia_semana' => 'Miércoles', 'hora_inicio' => '10:00:00'],
                ['id_actividad' => Actividad::PILATES, 'dia_semana' => 'Viernes', 'hora_inicio' => '10:00:00'],
            ],
            Carbon::parse('2026-06-10 08:00:00')
        );

        $todas = ActividadPaciente::query()
            ->where('id_paciente', $paciente->id)
            ->orderBy('id')
            ->get();

        $this->assertCount(4, $todas, 'Deben quedar 2 del dual vigente + 2 del dual futuro (sin huérfana simple).');
        $this->assertDatabaseMissing('actividades_pacientes', ['id' => $futuraSimple->id]);
        $this->assertTrue(
            $todas->every(fn (ActividadPaciente $i) => $i->id_act_pac_dual !== null),
            'No debe quedar ninguna inscripción simple huérfana.'
        );
        $this->assertTrue($todas->every(fn (ActividadPaciente $i) => (int) $i->frecuencia_total_dual === 4));

        $this->ejecutarCron($pacienteFijo->id);
        $this->assertSame(4, ActividadPaciente::where('id_paciente', $paciente->id)->count());
    }

    public function test_editar_dual_a_simple_con_futura_dual_regenera_solo_simple(): void
    {
        Carbon::setTestNow('2026-06-01 08:00:00');
        $this->crearPreciosMensuales([1 => 10000.00, 2 => 20000.00, 3 => 30000.00]);
        $this->asociarHorarioAGimnasioYPilates();
        $this->mockTurnoServiceCompleto();

        $paciente = $this->crearPaciente();
        $pacienteFijo = $this->registrarDual($paciente)->pacienteFijo;
        $this->assertSame(4, ActividadPaciente::where('id_paciente', $paciente->id)->count());

        Carbon::setTestNow('2026-06-10 08:00:00');

        app(HorarioPacienteFijoService::class)->actualizar(
            $pacienteFijo->id,
            [
                ['id_actividad' => Actividad::PILATES, 'dia_semana' => 'Lunes', 'hora_inicio' => '10:00:00'],
                ['id_actividad' => Actividad::PILATES, 'dia_semana' => 'Miércoles', 'hora_inicio' => '10:00:00'],
            ],
            Carbon::parse('2026-06-10 08:00:00')
        );

        $futuras = ActividadPaciente::query()
            ->where('id_paciente', $paciente->id)
            ->get()
            ->filter(function (ActividadPaciente $i) {
                $primer = $i->fresh(['primerTurno'])->primerTurno?->fecha_hora;

                return $primer && $primer->gte(Carbon::parse('2026-06-29'));
            });

        $this->assertCount(1, $futuras);
        $futura = $futuras->first();
        $this->assertSame(Actividad::PILATES, (int) $futura->id_actividad);
        $this->assertNull($futura->id_act_pac_dual);
        $this->assertNull($futura->frecuencia_total_dual);
        $this->assertSame(8, $futura->cant_sesiones);
    }

    public function test_edicion_sin_inscripcion_futura_no_crea_proxima_por_si_sola(): void
    {
        Event::fake([InscripcionGeneralRegistrada::class]);

        Carbon::setTestNow('2026-06-01 08:00:00');
        $this->crearPreciosMensuales([2 => 20000.00]);
        $this->asociarHorarioAPilates();
        $this->mockTurnoServiceCompleto();

        $paciente = $this->crearPaciente();
        $pacienteFijo = $this->registrarSimple($paciente)->pacienteFijo;
        $this->assertSame(1, ActividadPaciente::where('id_paciente', $paciente->id)->count());

        Carbon::setTestNow('2026-06-10 08:00:00');

        app(HorarioPacienteFijoService::class)->actualizar(
            $pacienteFijo->id,
            [
                ['id_actividad' => Actividad::PILATES, 'dia_semana' => 'Martes', 'hora_inicio' => '10:00:00'],
                ['id_actividad' => Actividad::PILATES, 'dia_semana' => 'Jueves', 'hora_inicio' => '10:00:00'],
            ],
            Carbon::parse('2026-06-10 08:00:00')
        );

        $this->assertSame(1, ActividadPaciente::where('id_paciente', $paciente->id)->count());
    }

    public function test_regenerar_preserva_ausente_aviso_si_el_slot_sigue_existiendo(): void
    {
        Carbon::setTestNow('2026-06-01 08:00:00');
        $this->crearPreciosMensuales([2 => 20000.00]);
        $this->asociarHorarioAPilates();
        $this->mockTurnoServiceCompleto();

        $paciente = $this->crearPaciente();
        $resultado = $this->registrarSimple($paciente);
        $pacienteFijo = $resultado->pacienteFijo;
        $actualId = $resultado->inscripciones->first()->id;

        $futura = ActividadPaciente::query()
            ->where('id_paciente', $paciente->id)
            ->where('id', '!=', $actualId)
            ->firstOrFail();

        $slotAa = '2026-07-06 10:00:00';
        Turno::query()
            ->where('id_act_pac', $futura->id)
            ->where('fecha_hora', $slotAa)
            ->whereNull('id_turno_original')
            ->firstOrFail()
            ->update(['estado' => 'Ausente avisó']);

        Carbon::setTestNow('2026-06-10 08:00:00');
        app(HorarioPacienteFijoService::class)->actualizar(
            $pacienteFijo->id,
            [
                ['id_actividad' => Actividad::PILATES, 'dia_semana' => 'Lunes', 'hora_inicio' => '10:00:00'],
                ['id_actividad' => Actividad::PILATES, 'dia_semana' => 'Viernes', 'hora_inicio' => '10:00:00'],
            ],
            Carbon::parse('2026-06-10 08:00:00')
        );

        $this->assertDatabaseMissing('actividades_pacientes', ['id' => $futura->id]);

        $nuevaFutura = $this->inscripcionFutura($paciente->id, Actividad::PILATES);
        $this->assertNotNull($nuevaFutura);

        $turnoRestaurado = Turno::query()
            ->where('id_act_pac', $nuevaFutura->id)
            ->where('fecha_hora', $slotAa)
            ->whereNull('id_turno_original')
            ->first();

        $this->assertNotNull($turnoRestaurado);
        $this->assertTrue($turnoRestaurado->esAusenteAviso());
    }

    public function test_regenerar_preserva_ausente_aviso_y_su_recuperacion(): void
    {
        Carbon::setTestNow('2026-06-01 08:00:00');
        $this->crearPreciosMensuales([2 => 20000.00]);
        $this->asociarHorarioAPilates();
        $this->mockTurnoServiceCompleto();

        $paciente = $this->crearPaciente();
        $resultado = $this->registrarSimple($paciente);
        $pacienteFijo = $resultado->pacienteFijo;
        $actualId = $resultado->inscripciones->first()->id;

        $futura = ActividadPaciente::query()
            ->where('id_paciente', $paciente->id)
            ->where('id', '!=', $actualId)
            ->firstOrFail();

        $slotAa = '2026-07-06 10:00:00';
        $slotRecupero = '2026-07-07 11:00:00';

        $original = Turno::query()
            ->where('id_act_pac', $futura->id)
            ->where('fecha_hora', $slotAa)
            ->whereNull('id_turno_original')
            ->firstOrFail();
        $original->update(['estado' => 'Ausente avisó']);

        Turno::create([
            'id_act_pac' => $futura->id,
            'fecha_hora' => $slotRecupero,
            'estado' => 'Ausente',
            'id_turno_original' => $original->id,
        ]);

        Carbon::setTestNow('2026-06-10 08:00:00');
        app(HorarioPacienteFijoService::class)->actualizar(
            $pacienteFijo->id,
            [
                ['id_actividad' => Actividad::PILATES, 'dia_semana' => 'Lunes', 'hora_inicio' => '10:00:00'],
                ['id_actividad' => Actividad::PILATES, 'dia_semana' => 'Viernes', 'hora_inicio' => '10:00:00'],
            ],
            Carbon::parse('2026-06-10 08:00:00')
        );

        $nuevaFutura = $this->inscripcionFutura($paciente->id, Actividad::PILATES);
        $this->assertNotNull($nuevaFutura);

        $aa = Turno::query()
            ->where('id_act_pac', $nuevaFutura->id)
            ->where('fecha_hora', $slotAa)
            ->whereNull('id_turno_original')
            ->firstOrFail();

        $this->assertTrue($aa->esAusenteAviso());

        $recupero = Turno::query()
            ->where('id_turno_original', $aa->id)
            ->first();

        $this->assertNotNull($recupero);
        $this->assertSame($slotRecupero, $recupero->fecha_hora->format('Y-m-d H:i:s'));
        $this->assertSame('Ausente', $recupero->getRawOriginal('estado'));
    }

    public function test_regenerar_ignora_ausente_aviso_si_el_slot_sale_del_patron(): void
    {
        Carbon::setTestNow('2026-06-01 08:00:00');
        $this->crearPreciosMensuales([2 => 20000.00]);
        $this->asociarHorarioAPilates();
        $this->mockTurnoServiceCompleto();

        $paciente = $this->crearPaciente();
        $resultado = $this->registrarSimple($paciente);
        $pacienteFijo = $resultado->pacienteFijo;
        $actualId = $resultado->inscripciones->first()->id;

        $futura = ActividadPaciente::query()
            ->where('id_paciente', $paciente->id)
            ->where('id', '!=', $actualId)
            ->firstOrFail();

        $slotAa = '2026-07-06 10:00:00';
        Turno::query()
            ->where('id_act_pac', $futura->id)
            ->where('fecha_hora', $slotAa)
            ->whereNull('id_turno_original')
            ->firstOrFail()
            ->update(['estado' => 'Ausente avisó']);

        Carbon::setTestNow('2026-06-10 08:00:00');
        app(HorarioPacienteFijoService::class)->actualizar(
            $pacienteFijo->id,
            [
                ['id_actividad' => Actividad::PILATES, 'dia_semana' => 'Martes', 'hora_inicio' => '10:00:00'],
                ['id_actividad' => Actividad::PILATES, 'dia_semana' => 'Jueves', 'hora_inicio' => '10:00:00'],
            ],
            Carbon::parse('2026-06-10 08:00:00')
        );

        $aaRestantes = Turno::query()
            ->whereHas('actividadPaciente', fn ($q) => $q->where('id_paciente', $paciente->id))
            ->where('fecha_hora', $slotAa)
            ->where('estado', 'Ausente avisó')
            ->count();

        $this->assertSame(0, $aaRestantes);
    }

    public function test_regenerar_inscripciones_futuras_directo_no_op_si_no_hay_futuras(): void
    {
        Event::fake([InscripcionGeneralRegistrada::class]);

        Carbon::setTestNow('2026-06-01 08:00:00');
        $this->crearPreciosMensuales([2 => 20000.00]);
        $this->asociarHorarioAPilates();
        $this->mockTurnoServiceCompleto();

        $paciente = $this->crearPaciente();
        $pacienteFijo = $this->registrarSimple($paciente)->pacienteFijo;
        $antes = ActividadPaciente::count();

        app(RenovacionInscripcionService::class)->regenerarInscripcionesFuturas(
            $pacienteFijo->fresh(['horarios', 'paciente']),
            Carbon::parse('2026-06-29')->startOfDay()
        );

        $this->assertSame($antes, ActividadPaciente::count());
    }

    public function test_tras_simple_a_dual_el_cron_no_vuelve_a_generar_otro_par(): void
    {
        Carbon::setTestNow('2026-06-01 08:00:00');
        $this->crearPreciosMensuales([2 => 20000.00, 3 => 30000.00]);
        $this->asociarHorarioAGimnasioYPilates();
        $this->mockTurnoServiceCompleto();

        $paciente = $this->crearPaciente();
        $pacienteFijo = $this->registrarSimple($paciente)->pacienteFijo;

        Carbon::setTestNow('2026-06-10 08:00:00');
        app(HorarioPacienteFijoService::class)->actualizar(
            $pacienteFijo->id,
            [
                ['id_actividad' => Actividad::GIMNASIO, 'dia_semana' => 'Martes', 'hora_inicio' => '10:00:00'],
                ['id_actividad' => Actividad::PILATES, 'dia_semana' => 'Lunes', 'hora_inicio' => '10:00:00'],
                ['id_actividad' => Actividad::PILATES, 'dia_semana' => 'Miércoles', 'hora_inicio' => '10:00:00'],
            ],
            Carbon::parse('2026-06-10 08:00:00')
        );

        $despuesEdicion = ActividadPaciente::where('id_paciente', $paciente->id)->count();
        $this->assertSame(4, $despuesEdicion);

        $this->ejecutarCron($pacienteFijo->id);
        $this->assertSame($despuesEdicion, ActividadPaciente::where('id_paciente', $paciente->id)->count());
    }

    public function test_renovar_si_corresponde_no_duplica_si_ya_existe_en_ancla(): void
    {
        Carbon::setTestNow('2026-06-01 08:00:00');
        $this->crearPreciosMensuales([2 => 20000.00]);
        $this->asociarHorarioAPilates();
        $this->mockTurnoServiceCompleto();

        $paciente = $this->crearPaciente();
        $pacienteFijo = $this->registrarSimple($paciente)->pacienteFijo;
        $this->assertSame(2, ActividadPaciente::where('id_paciente', $paciente->id)->count());

        app(RenovacionInscripcionService::class)->renovarSiCorresponde(
            $pacienteFijo->fresh(['horarios', 'paciente'])
        );

        $this->assertSame(2, ActividadPaciente::where('id_paciente', $paciente->id)->count());
    }

    private function inscripcionFutura(int $idPaciente, int $idActividad): ?ActividadPaciente
    {
        return ActividadPaciente::query()
            ->where('id_paciente', $idPaciente)
            ->where('id_actividad', $idActividad)
            ->get()
            ->first(fn (ActividadPaciente $i) => $i->fresh('primerTurno')->primerTurno?->fecha_hora?->gte(Carbon::parse('2026-06-29')));
    }

    private function registrarSimple(Paciente $paciente, string $fechaAncla = '2026-06-01'): ResultadoInscripcionGeneral
    {
        return app(ActividadPacienteService::class)->registrarInscripcionesGenerales([
            'id_paciente' => $paciente->id,
            'fecha_ancla' => $fechaAncla,
            'horarios' => [
                ['id_actividad' => Actividad::PILATES, 'dia_semana' => 'Lunes', 'hora_inicio' => '10:00:00'],
                ['id_actividad' => Actividad::PILATES, 'dia_semana' => 'Miércoles', 'hora_inicio' => '10:00:00'],
            ],
        ]);
    }

    private function registrarSimpleX3(Paciente $paciente, string $fechaAncla = '2026-06-01'): ResultadoInscripcionGeneral
    {
        return app(ActividadPacienteService::class)->registrarInscripcionesGenerales([
            'id_paciente' => $paciente->id,
            'fecha_ancla' => $fechaAncla,
            'horarios' => [
                ['id_actividad' => Actividad::PILATES, 'dia_semana' => 'Lunes', 'hora_inicio' => '10:00:00'],
                ['id_actividad' => Actividad::PILATES, 'dia_semana' => 'Miércoles', 'hora_inicio' => '10:00:00'],
                ['id_actividad' => Actividad::PILATES, 'dia_semana' => 'Viernes', 'hora_inicio' => '10:00:00'],
            ],
        ]);
    }

    private function registrarDual(Paciente $paciente, string $fechaAncla = '2026-06-01'): ResultadoInscripcionGeneral
    {
        return app(ActividadPacienteService::class)->registrarInscripcionesGenerales([
            'id_paciente' => $paciente->id,
            'fecha_ancla' => $fechaAncla,
            'horarios' => [
                ['id_actividad' => Actividad::GIMNASIO, 'dia_semana' => 'Lunes', 'hora_inicio' => '10:00:00'],
                ['id_actividad' => Actividad::GIMNASIO, 'dia_semana' => 'Miércoles', 'hora_inicio' => '10:00:00'],
                ['id_actividad' => Actividad::PILATES, 'dia_semana' => 'Viernes', 'hora_inicio' => '10:00:00'],
            ],
        ]);
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

    private function crearPaciente(array $extra = []): Paciente
    {
        return Paciente::create(array_merge([
            'dni' => fake()->unique()->numerify('########'),
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

    private function ejecutarCron(int $idPacienteFijo): void
    {
        Artisan::call('app:generar-turnos-mensuales', [
            '--id_paciente_fijo' => $idPacienteFijo,
        ]);
    }

    private function mockTurnoServiceCompleto(): void
    {
        $expandir = function ($fechaAncla, array $patron, int $cantidadSesiones, int $frecuenciaSemanal) {
            $turnos = (new ExpansorTurnosPatron())->expandir(
                $fechaAncla,
                $patron,
                $cantidadSesiones,
                $frecuenciaSemanal
            )['turnos'];

            return ResultadoPreparacionTurnos::desdeFechasExactas(
                collect($turnos)->values()->map(
                    fn ($fecha) => Carbon::parse($fecha)->format('Y-m-d H:i:s')
                )->all()
            );
        };

        $this->mock(TurnoService::class, function ($mock) use ($expandir) {
            $mock->shouldReceive('prepararDesdePatronSinReemplazo')
                ->andReturnUsing(
                    fn ($fechaAncla, array $patron, int $cant, int $freq) => $expandir($fechaAncla, $patron, $cant, $freq)
                );

            $mock->shouldReceive('prepararDesdePatron')
                ->andReturnUsing(
                    fn ($actividad, $idPaciente, $fechaAncla, array $patron, int $cant, int $freq) => $expandir($fechaAncla, $patron, $cant, $freq)
                );
        });
    }
}
