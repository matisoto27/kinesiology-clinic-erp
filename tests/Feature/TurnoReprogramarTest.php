<?php

namespace Tests\Feature;

use App\Exceptions\ReglaNegocioException;
use App\Models\Actividad;
use App\Models\ActividadPaciente;
use App\Models\Horario;
use App\Models\Paciente;
use App\Models\Turno;
use App\Services\TurnoService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class TurnoReprogramarTest extends TestCase
{
    use RefreshDatabase;

    private const ORIGINAL = '2026-06-05 10:00:00';

    private const DESTINO = '2026-06-04 10:00:00';

    private TurnoService $turnoService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->turnoService = app(TurnoService::class);
        Config::set('app.max_turnos_gimnasio', 8);
        Config::set('app.max_turnos_pilates', 6);
        Carbon::setTestNow('2026-06-01 08:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_rechaza_reprogramar_a_fecha_pasada_de_la_misma_semana(): void
    {
        Carbon::setTestNow('2026-06-06 08:00:00');

        $this->asociarHora(Actividad::PILATES, '10:00:00');

        $inscripcion = $this->crearInscripcion(Actividad::PILATES, cantSesiones: 8);
        $turno = $this->crearTurnoAa($inscripcion, self::ORIGINAL);

        $this->expectException(ReglaNegocioException::class);
        $this->expectExceptionMessage('No se puede reprogramar a una fecha que ya pasó.');

        $this->turnoService->reprogramar(
            $turno,
            Carbon::parse(self::DESTINO)
        );
    }

    public function test_rechaza_reprogramar_a_semana_anterior(): void
    {
        $this->asociarHora(Actividad::PILATES, '10:00:00');

        $inscripcion = $this->crearInscripcion(Actividad::PILATES, cantSesiones: 8);
        $turno = $this->crearTurnoAa($inscripcion, self::ORIGINAL);

        $this->expectException(ReglaNegocioException::class);
        $this->expectExceptionMessage('No se puede reprogramar a una fecha anterior a la semana del turno.');

        $this->turnoService->reprogramar(
            $turno,
            Carbon::parse('2026-05-29 10:00:00')
        );
    }

    public function test_permite_reprogramar_dentro_de_la_misma_semana(): void
    {
        $this->asociarHora(Actividad::PILATES, '10:00:00');

        $inscripcion = $this->crearInscripcion(Actividad::PILATES, cantSesiones: 8);
        $turno = $this->crearTurnoAa($inscripcion, self::ORIGINAL);

        $reprogramado = $this->turnoService->reprogramar(
            $turno,
            Carbon::parse(self::DESTINO)
        );

        $this->assertSame(self::DESTINO, $reprogramado->fecha_hora->format('Y-m-d H:i:s'));
    }

    /**
     * @return array{pilates: ActividadPaciente, gimnasio: ActividadPaciente, turno: Turno}
     */
    private function crearDualConTurnoPilates(): array
    {
        $this->asociarHora(Actividad::GIMNASIO, '10:00:00');
        $this->asociarHora(Actividad::PILATES, '10:00:00');

        $paciente = $this->crearPaciente();
        $gimnasio = $this->crearInscripcion(Actividad::GIMNASIO, cantSesiones: 8, paciente: $paciente, total: 30000);
        $pilates = $this->crearInscripcion(Actividad::PILATES, cantSesiones: 4, paciente: $paciente, total: 0);

        $gimnasio->update([
            'id_act_pac_dual' => $pilates->id,
            'frecuencia_total_dual' => 3,
        ]);
        $pilates->update([
            'id_act_pac_dual' => $gimnasio->id,
            'frecuencia_total_dual' => 3,
            'pago_completado' => true,
        ]);

        $turno = $this->crearTurnoAa($pilates, self::ORIGINAL);

        return [
            'pilates' => $pilates->fresh(),
            'gimnasio' => $gimnasio->fresh(),
            'turno' => $turno->fresh(),
        ];
    }

    private function crearInscripcion(
        int $idActividad,
        int $cantSesiones,
        ?Paciente $paciente = null,
        float $total = 10000
    ): ActividadPaciente {
        return ActividadPaciente::create([
            'id_actividad' => $idActividad,
            'id_paciente' => ($paciente ?? $this->crearPaciente())->id,
            'cant_sesiones' => $cantSesiones,
            'total_a_pagar' => $total,
            'pago_completado' => $total <= 0,
        ]);
    }

    private function crearTurnoAa(
        ActividadPaciente $inscripcion,
        string $fechaHora,
        string $estado = 'Ausente avisó'
    ): Turno {
        return Turno::create([
            'id_act_pac' => $inscripcion->id,
            'fecha_hora' => $fechaHora,
            'estado' => $estado,
        ]);
    }

    private function asociarHora(int $idActividad, string $hora): void
    {
        $actividad = Actividad::findOrFail($idActividad);
        $horario = Horario::create([
            'hora_inicio' => $hora,
            'franja' => 'M',
        ]);
        $actividad->horarios()->attach($horario->id);
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
