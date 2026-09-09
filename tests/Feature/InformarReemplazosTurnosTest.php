<?php

namespace Tests\Feature;

use App\Models\Actividad;
use App\Models\ActividadCombo;
use App\Models\ActividadPaciente;
use App\Models\Combo;
use App\Models\Horario;
use App\Models\Paciente;
use App\Models\Precio;
use App\Models\Turno;
use App\Services\ActividadPacienteService;
use Carbon\Carbon;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class InformarReemplazosTurnosTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_registrar_autogenerados_devuelve_reemplazos_cuando_hay_reasignacion(): void
    {
        Carbon::setTestNow('2026-06-01 08:00:00');
        Config::set('app.max_turnos_convencional', 1);

        $actividad = $this->prepararKinesiologiaConvencionalConHorarios(['10:00:00', '11:00:00']);
        $paciente = $this->crearPaciente();
        $this->ocuparSlot($actividad, '2026-06-08 10:00:00');

        $resultado = app(ActividadPacienteService::class)->registrar([
            'id_actividad' => $actividad->id,
            'id_paciente' => $paciente->id,
            'autogenerados' => true,
            'fecha_ancla' => '2026-06-01',
            'frecuencia_semanal' => 1,
            'cant_sesiones' => 2,
            'turnos' => [
                ['dia_semana' => 'Lunes', 'hora_inicio' => '10:00:00'],
            ],
        ]);

        $this->assertSame([
            [
                'solicitado' => '2026-06-08 10:00:00',
                'asignado' => '2026-06-08 11:00:00',
            ],
        ], $resultado->reemplazos);

        $fechas = $resultado->inscripcion->turnos
            ->pluck('fecha_hora')
            ->map(fn ($f) => Carbon::parse($f)->format('Y-m-d H:i:s'))
            ->sort()
            ->values()
            ->all();

        $this->assertSame([
            '2026-06-01 10:00:00',
            '2026-06-08 11:00:00',
        ], $fechas);
    }

    public function test_registrar_sin_reasignacion_devuelve_reemplazos_vacios(): void
    {
        Carbon::setTestNow('2026-06-01 08:00:00');
        Config::set('app.max_turnos_convencional', 1);

        $actividad = $this->prepararKinesiologiaConvencionalConHorarios(['10:00:00']);
        $paciente = $this->crearPaciente();

        $resultado = app(ActividadPacienteService::class)->registrar([
            'id_actividad' => $actividad->id,
            'id_paciente' => $paciente->id,
            'autogenerados' => true,
            'fecha_ancla' => '2026-06-01',
            'frecuencia_semanal' => 1,
            'cant_sesiones' => 2,
            'turnos' => [
                ['dia_semana' => 'Lunes', 'hora_inicio' => '10:00:00'],
            ],
        ]);

        $this->assertSame([], $resultado->reemplazos);
        $this->assertCount(2, $resultado->inscripcion->turnos);
    }

    public function test_store_incluye_reemplazos_en_la_respuesta_json(): void
    {
        Carbon::setTestNow('2026-06-01 08:00:00');
        Config::set('app.max_turnos_convencional', 1);

        $actividad = $this->prepararKinesiologiaConvencionalConHorarios(['10:00:00', '11:00:00']);
        $paciente = $this->crearPaciente();
        $this->ocuparSlot($actividad, '2026-06-08 10:00:00');

        $this->withoutMiddleware(ValidateCsrfToken::class)
            ->withSession(['autorizado' => true])
            ->postJson(route('actividades-pacientes.store'), [
                'id_actividad' => $actividad->id,
                'id_paciente' => $paciente->id,
                'autogenerados' => true,
                'fecha_ancla' => '2026-06-01',
                'frecuencia_semanal' => 1,
                'cant_sesiones' => 2,
                'turnos' => [
                    ['dia_semana' => 'Lunes', 'hora_inicio' => '10:00:00'],
                ],
            ])
            ->assertOk()
            ->assertJson([
                'reemplazos' => [
                    [
                        'solicitado' => '2026-06-08 10:00:00',
                        'asignado' => '2026-06-08 11:00:00',
                    ],
                ],
            ])
            ->assertJsonStructure(['id', 'reemplazos']);
    }

    public function test_store_sin_reasignacion_responde_reemplazos_vacios(): void
    {
        Carbon::setTestNow('2026-06-01 08:00:00');
        Config::set('app.max_turnos_convencional', 1);

        $actividad = $this->prepararKinesiologiaConvencionalConHorarios(['10:00:00']);
        $paciente = $this->crearPaciente();

        $payload = [
            'id_actividad' => $actividad->id,
            'id_paciente' => $paciente->id,
            'autogenerados' => true,
            'fecha_ancla' => '2026-06-01',
            'frecuencia_semanal' => 1,
            'cant_sesiones' => 2,
            'turnos' => [
                ['dia_semana' => 'Lunes', 'hora_inicio' => '10:00:00'],
            ],
        ];

        $this->withoutMiddleware(ValidateCsrfToken::class)
            ->withSession(['autorizado' => true])
            ->postJson(route('actividades-pacientes.store'), $payload)
            ->assertOk()
            ->assertJsonPath('reemplazos', [])
            ->assertJsonStructure(['id', 'reemplazos']);
    }

    /**
     * @param  list<string>  $horasInicio
     */
    private function prepararKinesiologiaConvencionalConHorarios(array $horasInicio): Actividad
    {
        $actividad = Actividad::findOrFail(Actividad::KINESIOLOGIA_CONVENCIONAL);

        foreach ($horasInicio as $hora) {
            $horario = Horario::create([
                'hora_inicio' => $hora,
                'franja' => 'M',
            ]);
            $actividad->horarios()->attach($horario->id);
        }

        $this->crearPrecioSesion($actividad, 2000.00);

        return $actividad->fresh(['horarios']);
    }

    private function crearPrecioSesion(Actividad $actividad, float $precio): void
    {
        $combo = Combo::create([
            'nombre' => 'Cx1 T' . uniqid(),
            'cantidad_sesiones' => 1,
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

    private function ocuparSlot(Actividad $actividad, string $fechaHora): void
    {
        $otro = $this->crearPaciente();

        $inscripcion = ActividadPaciente::create([
            'id_actividad' => $actividad->id,
            'id_paciente' => $otro->id,
            'cant_sesiones' => 1,
            'total_a_pagar' => 0,
            'pago_completado' => true,
        ]);

        Turno::create([
            'id_act_pac' => $inscripcion->id,
            'fecha_hora' => $fechaHora,
        ]);
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
}
