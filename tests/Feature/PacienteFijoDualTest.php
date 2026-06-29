<?php

namespace Tests\Feature;

use App\Models\Actividad;
use App\Models\ActividadCombo;
use App\Models\ActividadPaciente;
use App\Models\Combo;
use App\Models\Horario;
use App\Models\Paciente;
use App\Models\PacienteFijo;
use App\Models\Precio;
use App\Models\TipoActividad;
use App\Models\Turno;
use App\Services\ActividadPacienteService;
use App\Services\TurnoService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class PacienteFijoDualTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_registrar_paciente_fijo_dual_crea_dos_filas_enlazadas(): void
    {
        Carbon::setTestNow('2026-06-01 08:00:00');

        $this->crearActividadesGeneralesPlanDual(preciosPorFrecuencia: [
            1 => 10000.00,
            2 => 20000.00,
            3 => 30000.00,
        ]);

        $paciente = $this->crearPaciente();
        $pareja = $this->crearInscripcionesYPacienteFijoDual($paciente);

        $this->assertSame($pareja['pilates']->id, $pareja['gym']->id_pac_fijo_dual);
        $this->assertSame($pareja['gym']->id, $pareja['pilates']->id_pac_fijo_dual);
        $this->assertSame(2, PacienteFijo::count());
    }

    public function test_generador_mensual_renova_par_dual_de_forma_atomica(): void
    {
        Carbon::setTestNow('2026-06-01 08:00:00');

        $this->crearActividadesGeneralesPlanDual(preciosPorFrecuencia: [
            1 => 10000.00,
            2 => 20000.00,
            3 => 30000.00,
        ]);

        $paciente = $this->crearPaciente();
        $pareja = $this->crearInscripcionesYPacienteFijoDual($paciente);

        $inscripcionesIniciales = ActividadPaciente::count();
        $turnosIniciales = Turno::count();

        $this->mockTurnoServiceParaUnaRenovacionDual();

        $this->ejecutarGeneradorTurnosMensuales($pareja['gym']->id);

        $this->assertSame($inscripcionesIniciales + 2, ActividadPaciente::count());
        $this->assertSame($turnosIniciales + 8 + 4, Turno::count());

        $nuevoGym = ActividadPaciente::query()
            ->where('id_paciente', $paciente->id)
            ->where('id_actividad', Actividad::GIMNASIO)
            ->where('es_fijo', true)
            ->latest('id')
            ->first();

        $nuevoPilates = ActividadPaciente::query()
            ->where('id_paciente', $paciente->id)
            ->where('id_actividad', Actividad::PILATES)
            ->where('es_fijo', true)
            ->latest('id')
            ->first();

        $this->assertNotNull($nuevoGym);
        $this->assertNotNull($nuevoPilates);
        $this->assertTrue($nuevoGym->esDualCompleto());
        $this->assertTrue($nuevoPilates->esDualCompleto());
        $this->assertSame($nuevoPilates->id, $nuevoGym->id_act_pac_dual);
        $this->assertSame($nuevoGym->id, $nuevoPilates->id_act_pac_dual);
        $this->assertSame('20000.00', (string) $nuevoGym->total_a_pagar);
        $this->assertSame('10000.00', (string) $nuevoPilates->total_a_pagar);
    }

    public function test_command_post_alta_dual_no_renova_si_hay_cobertura_suficiente(): void
    {
        Carbon::setTestNow('2026-06-01 08:00:00');

        $this->crearActividadesGeneralesPlanDual(preciosPorFrecuencia: [
            1 => 10000.00,
            2 => 20000.00,
            3 => 30000.00,
        ]);

        $paciente = $this->crearPaciente();
        $pareja = $this->crearInscripcionesYPacienteFijoDual($paciente, coberturaExtendida: true);

        $inscripcionesIniciales = ActividadPaciente::count();
        $turnosIniciales = Turno::count();

        $this->mockTurnoServiceParaUnaRenovacionDual();
        $this->ejecutarGeneradorTurnosMensuales($pareja['gym']->id);

        $this->assertSame($inscripcionesIniciales, ActividadPaciente::count());
        $this->assertSame($turnosIniciales, Turno::count());
    }

    /**
     * @return array{gym: PacienteFijo, pilates: PacienteFijo}
     */
    private function crearInscripcionesYPacienteFijoDual(Paciente $paciente, bool $coberturaExtendida = false): array
    {
        $service = app(ActividadPacienteService::class);

        $fechaAnclaGym = $coberturaExtendida ? '2026-07-27' : '2026-06-01';
        $turnosPilates = $coberturaExtendida
            ? $this->turnosViernes(4, '2026-07-31 10:00:00')
            : $this->turnosViernes(4);

        $service->registrar([
            'plan_dual' => true,
            'id_actividad' => Actividad::GIMNASIO,
            'id_paciente' => $paciente->id,
            'autogenerados' => true,
            'fecha_ancla' => $fechaAnclaGym,
            'frecuencia_semanal' => 2,
            'cant_sesiones' => 8,
            'turnos' => [
                ['dia_semana' => 'Lunes', 'hora_inicio' => '10:00:00'],
                ['dia_semana' => 'Miércoles', 'hora_inicio' => '10:00:00'],
            ],
        ]);

        $service->registrar([
            'plan_dual' => true,
            'id_actividad' => Actividad::PILATES,
            'id_paciente' => $paciente->id,
            'autogenerados' => false,
            'frecuencia_semanal' => 1,
            'cant_sesiones' => 4,
            'turnos' => $turnosPilates,
        ]);

        $pacienteFijoGym = PacienteFijo::create([
            'id_actividad' => Actividad::GIMNASIO,
            'id_paciente' => $paciente->id,
        ]);
        $pacienteFijoGym->horarios()->createMany([
            ['dia_semana' => 1, 'hora_inicio' => '10:00:00'],
            ['dia_semana' => 3, 'hora_inicio' => '10:00:00'],
        ]);

        $pacienteFijoPilates = PacienteFijo::create([
            'id_actividad' => Actividad::PILATES,
            'id_paciente' => $paciente->id,
        ]);
        $pacienteFijoPilates->horarios()->createMany([
            ['dia_semana' => 5, 'hora_inicio' => '10:00:00'],
        ]);

        $pacienteFijoGym->update(['id_pac_fijo_dual' => $pacienteFijoPilates->id]);
        $pacienteFijoPilates->update(['id_pac_fijo_dual' => $pacienteFijoGym->id]);

        return [
            'gym' => $pacienteFijoGym->refresh(),
            'pilates' => $pacienteFijoPilates->refresh(),
        ];
    }

    private function crearActividadesGeneralesPlanDual(array $preciosPorFrecuencia): void
    {
        $tipo = TipoActividad::create([
            'id' => Actividad::TIPO_GENERAL,
            'descripcion' => 'General',
        ]);

        $gimnasio = Actividad::create([
            'id' => Actividad::GIMNASIO,
            'nombre' => 'Gimnasio',
            'id_tipo_actividad' => $tipo->id,
        ]);

        $pilates = Actividad::create([
            'id' => Actividad::PILATES,
            'nombre' => 'Pilates',
            'id_tipo_actividad' => $tipo->id,
        ]);

        $horario = Horario::create([
            'hora_inicio' => '10:00:00',
            'franja' => 'M',
        ]);

        $gimnasio->horarios()->attach($horario->id);
        $pilates->horarios()->attach($horario->id);

        foreach ($preciosPorFrecuencia as $frecuencia => $precio) {
            $this->crearComboMensual($gimnasio, $frecuencia, $precio);
            $this->crearComboMensual($pilates, $frecuencia, $precio);
        }
    }

    private function crearComboMensual(Actividad $actividad, int $frecuenciaSemanal, float $precio): void
    {
        $combo = Combo::create([
            'nombre' => "Mx{$frecuenciaSemanal}-" . rand(100, 999),
            'cantidad_sesiones' => $frecuenciaSemanal * 4,
            'es_mensual' => true,
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

    private function ejecutarGeneradorTurnosMensuales(int $idPacienteFijoLider): void
    {
        Artisan::call('app:generar-turnos-mensuales', [
            '--id_paciente_fijo' => $idPacienteFijoLider,
        ]);
    }

    private function mockTurnoServiceParaUnaRenovacionDual(): void
    {
        $this->mock(TurnoService::class, function ($mock) {
            $mock->shouldReceive('prepararFechas')
                ->andReturnUsing(function ($actividad, $idPaciente, array $turnosSolicitados) {
                    $esPilates = (int) $actividad->id === Actividad::PILATES;
                    $fechaBase = Carbon::now()->addDays(61)->addDays($esPilates ? 7 : 0);
                    $cantidad = count($turnosSolicitados);

                    return collect(range(1, $cantidad))->map(fn ($i) => [
                        'nro_turno' => $i,
                        'fecha_hora' => $fechaBase->copy()->addDays($i)->format('Y-m-d H:i:s'),
                    ])->all();
                });
        });
    }

    private function turnosViernes(int $cantidad, string $fechaInicio = '2026-06-05 10:00:00'): array
    {
        $turnos = [];
        $fecha = Carbon::parse($fechaInicio);

        for ($i = 0; $i < $cantidad; $i++) {
            $turnos[] = $fecha->copy()->addWeeks($i)->format('Y-m-d H:i:s');
        }

        return $turnos;
    }
}
