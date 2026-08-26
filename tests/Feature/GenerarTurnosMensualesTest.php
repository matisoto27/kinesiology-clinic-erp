<?php

namespace Tests\Feature;

use App\Models\Actividad;
use App\Models\ActividadPaciente;
use App\Models\Horario;
use App\Models\Paciente;
use App\Models\PrecioMensual;
use App\Models\Turno;
use App\Services\ActividadPacienteService;
use App\Services\TurnoService;
use App\Support\Registros\ResultadoInscripcionGeneral;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * Regla de negocio 9: la renovación mensual se dispara cuando faltan ≤ 7 días
 * para el fin del ciclo (primer turno original + 4 semanas), no en base al
 * último turno generado. En dual, la ancla del ciclo es el menor primer turno
 * entre las dos inscripciones del par.
 */
class GenerarTurnosMensualesTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_renueva_inscripcion_simple_cuando_faltan_7_dias_o_menos_para_el_fin_del_ciclo(): void
    {
        Carbon::setTestNow('2026-06-01 08:00:00'); // lunes, ancla del ciclo

        $this->crearPreciosMensuales([2 => 20000.00]);
        $this->asociarHorarioAPilates();

        $paciente = $this->crearPaciente();
        $pacienteFijo = $this->registrarInscripcionSimple($paciente)->pacienteFijo;

        // Ciclo: [1/6, 29/6). El 23/6 faltan 6 días para el fin (dentro de la ventana de 7).
        Carbon::setTestNow('2026-06-23 08:00:00');

        $inscripcionesIniciales = ActividadPaciente::count();
        $turnosIniciales = Turno::count();

        $this->mockTurnoServiceParaUnaRenovacion();
        $this->ejecutarGeneradorTurnosMensuales($pacienteFijo->id);

        $this->assertSame($inscripcionesIniciales + 1, ActividadPaciente::count());
        $this->assertSame($turnosIniciales + 8, Turno::count()); // frecuencia 2 × 4 semanas

        $nueva = ActividadPaciente::query()
            ->where('id_paciente', $paciente->id)
            ->where('id_actividad', Actividad::PILATES)
            ->latest('id')
            ->first();

        $this->assertNotNull($nueva);
        $this->assertSame(8, $nueva->cant_sesiones);
        $this->assertNull($nueva->frecuencia_total_dual);
        $this->assertSame('2026-06-29', $nueva->primerTurno->fecha_hora->format('Y-m-d'));
    }

    public function test_no_renueva_inscripcion_simple_si_faltan_mas_de_7_dias_para_el_fin_del_ciclo(): void
    {
        Carbon::setTestNow('2026-06-01 08:00:00');

        $this->crearPreciosMensuales([2 => 20000.00]);
        $this->asociarHorarioAPilates();

        $paciente = $this->crearPaciente();
        $pacienteFijo = $this->registrarInscripcionSimple($paciente)->pacienteFijo;

        // Fin del ciclo: 29/6. El 15/6 faltan 14 días, fuera de la ventana de anticipación.
        Carbon::setTestNow('2026-06-15 08:00:00');

        $inscripcionesIniciales = ActividadPaciente::count();
        $turnosIniciales = Turno::count();

        $this->mockTurnoServiceParaUnaRenovacion();
        $this->ejecutarGeneradorTurnosMensuales($pacienteFijo->id);

        $this->assertSame($inscripcionesIniciales, ActividadPaciente::count());
        $this->assertSame($turnosIniciales, Turno::count());
    }

    public function test_renueva_par_dual_de_forma_atomica_usando_el_menor_primer_turno_como_ancla(): void
    {
        Carbon::setTestNow('2026-06-01 08:00:00');

        $this->crearPreciosMensuales([
            1 => 10000.00,
            2 => 20000.00,
            3 => 30000.00,
        ]);
        $this->asociarHorarioAGimnasioYPilates();

        $paciente = $this->crearPaciente();
        $pacienteFijo = $this->registrarInscripcionDual($paciente)->pacienteFijo;

        $primerTurnoDelPar = Turno::query()
            ->whereHas('actividadPaciente', fn ($q) => $q->where('id_paciente', $paciente->id))
            ->whereNull('id_turno_original')
            ->orderBy('fecha_hora')
            ->value('fecha_hora');

        // Entrar en la ventana de anticipación (7 días) respecto al fin del ciclo.
        Carbon::setTestNow(Carbon::parse($primerTurnoDelPar)->addWeeks(4)->subDays(3)->setTime(8, 0));

        $inscripcionesIniciales = ActividadPaciente::count();
        $turnosIniciales = Turno::count();

        $this->mockTurnoServiceParaUnaRenovacion();
        $this->ejecutarGeneradorTurnosMensuales($pacienteFijo->id);

        $this->assertSame($inscripcionesIniciales + 2, ActividadPaciente::count());
        $this->assertSame($turnosIniciales + 8 + 4, Turno::count());

        $nuevoGym = ActividadPaciente::query()
            ->where('id_paciente', $paciente->id)
            ->where('id_actividad', Actividad::GIMNASIO)
            ->latest('id')
            ->first();

        $nuevoPilates = ActividadPaciente::query()
            ->where('id_paciente', $paciente->id)
            ->where('id_actividad', Actividad::PILATES)
            ->latest('id')
            ->first();

        $this->assertTrue($nuevoGym->esDualCompleto());
        $this->assertTrue($nuevoPilates->esDualCompleto());
        $this->assertSame($nuevoPilates->id, $nuevoGym->id_act_pac_dual);
        $this->assertSame($nuevoGym->id, $nuevoPilates->id_act_pac_dual);
        $this->assertSame(3, $nuevoGym->frecuencia_total_dual);
        $this->assertSame(3, $nuevoPilates->frecuencia_total_dual);
        $this->assertSame(8, $nuevoGym->cant_sesiones); // 2 horarios × 4 semanas
        $this->assertSame(4, $nuevoPilates->cant_sesiones); // 1 horario × 4 semanas
        $this->assertSame('30000.00', (string) $nuevoGym->total_a_pagar);
        $this->assertSame('0.00', (string) $nuevoPilates->total_a_pagar);
        $this->assertFalse($nuevoGym->pago_completado);
        $this->assertTrue($nuevoPilates->pago_completado);
        $this->assertLessThan($nuevoPilates->id, $nuevoGym->id);
    }

    public function test_no_renueva_si_el_paciente_fijo_fue_eliminado(): void
    {
        Carbon::setTestNow('2026-06-01 08:00:00');

        $this->crearPreciosMensuales([2 => 20000.00]);
        $this->asociarHorarioAPilates();

        $paciente = $this->crearPaciente();
        $pacienteFijo = $this->registrarInscripcionSimple($paciente)->pacienteFijo;

        $paciente->delete();

        Carbon::setTestNow('2026-06-23 08:00:00');

        $inscripcionesIniciales = ActividadPaciente::count();
        $turnosIniciales = Turno::count();

        $this->mockTurnoServiceParaUnaRenovacion();
        $this->ejecutarGeneradorTurnosMensuales($pacienteFijo->id);

        $this->assertSame($inscripcionesIniciales, ActividadPaciente::count());
        $this->assertSame($turnosIniciales, Turno::count());
    }

    public function test_no_renueva_par_dual_si_el_menor_primer_turno_esta_fuera_de_la_ventana_de_anticipacion(): void
    {
        Carbon::setTestNow('2026-06-01 08:00:00');

        $this->crearPreciosMensuales([
            1 => 10000.00,
            2 => 20000.00,
            3 => 30000.00,
        ]);
        $this->asociarHorarioAGimnasioYPilates();

        $paciente = $this->crearPaciente();
        $pacienteFijo = $this->registrarInscripcionDual($paciente, fechaAncla: '2026-07-27')->pacienteFijo;

        $inscripcionesIniciales = ActividadPaciente::count();
        $turnosIniciales = Turno::count();

        $this->mockTurnoServiceParaUnaRenovacion();
        $this->ejecutarGeneradorTurnosMensuales($pacienteFijo->id);

        $this->assertSame($inscripcionesIniciales, ActividadPaciente::count());
        $this->assertSame($turnosIniciales, Turno::count());
    }

    private function registrarInscripcionSimple(Paciente $paciente, string $fechaAncla = '2026-06-01'): ResultadoInscripcionGeneral
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

    private function registrarInscripcionDual(Paciente $paciente, string $fechaAncla = '2026-06-01'): ResultadoInscripcionGeneral
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

    private function ejecutarGeneradorTurnosMensuales(int $idPacienteFijo): void
    {
        Artisan::call('app:generar-turnos-mensuales', [
            '--id_paciente_fijo' => $idPacienteFijo,
        ]);
    }

    private function mockTurnoServiceParaUnaRenovacion(): void
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
