<?php

namespace Tests\Unit;

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
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ActividadTurnosDisponiblesTest extends TestCase
{
    use RefreshDatabase;

    private const SLOT_ORIGINAL = '2026-06-03 10:00:00';

    private const SLOT_NUEVO = '2026-06-04 10:00:00';

    private TurnoService $turnoService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->turnoService = app(TurnoService::class);
        Config::set('app.max_turnos_gimnasio', 1);
        Config::set('app.max_turnos_pilates', 1);
        Config::set('app.max_turnos_convencional', 1);
        Carbon::setTestNow('2026-06-01 08:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public static function actividades(): array
    {
        return [
            'gimnasio' => [Actividad::GIMNASIO],
            'quiropraxia' => [Actividad::QUIROPRAXIA],
        ];
    }

    #[DataProvider('actividades')]
    public function test_ausente_ocupa_cupo_y_bloquea_reprogramar(int $idActividad): void
    {
        ['actividad' => $actividad, 'turno' => $turno, 'otroPaciente' => $otro] = $this->escenario($idActividad, 'Ausente');

        $this->assertNotContains(self::SLOT_ORIGINAL, $this->disponibles($actividad, $otro));

        $this->expectException(ReglaNegocioException::class);
        $this->turnoService->reprogramar($turno, Carbon::parse(self::SLOT_NUEVO));
    }

    #[DataProvider('actividades')]
    public function test_aa_libera_cupo_y_reprogramar_ocupa_nueva(int $idActividad): void
    {
        ['actividad' => $actividad, 'turno' => $turno, 'otroPaciente' => $otro] = $this->escenario($idActividad, 'Ausente avisó');

        $this->assertContains(self::SLOT_ORIGINAL, $this->disponibles($actividad, $otro));

        $this->turnoService->reprogramar($turno, Carbon::parse(self::SLOT_NUEVO));

        $despues = $this->disponibles($actividad, $otro);
        $this->assertContains(self::SLOT_ORIGINAL, $despues);
        $this->assertNotContains(self::SLOT_NUEVO, $despues);
    }

    public function test_paciente_recupera_fecha_original_tras_reprogramar(): void
    {
        Config::set('app.max_turnos_gimnasio', 8);

        $gimnasio = $this->prepararActividad(Actividad::GIMNASIO);
        $paciente = $this->crearPaciente();
        $turno = $this->crearTurno($paciente, Actividad::GIMNASIO, 'Ausente avisó');

        $this->turnoService->reprogramar($turno, Carbon::parse(self::SLOT_NUEVO));

        $disponibles = $this->disponibles($gimnasio, $paciente);
        $this->assertContains(self::SLOT_ORIGINAL, $disponibles);
        $this->assertNotContains(self::SLOT_NUEVO, $disponibles);
    }

    private function escenario(int $idActividad, string $estado): array
    {
        $actividad = $this->prepararActividad($idActividad);
        $paciente = $this->crearPaciente();
        $otroPaciente = $this->crearPaciente();
        $turno = $this->crearTurno($paciente, $idActividad, $estado);

        return [
            'actividad' => $actividad,
            'turno' => $turno,
            'otroPaciente' => $otroPaciente,
        ];
    }

    private function disponibles(Actividad $actividad, Paciente $paciente): array
    {
        return $actividad->turnosDisponibles(
            $paciente->id,
            Carbon::parse('2026-06-01')->startOfDay(),
            Carbon::parse('2026-06-05')->endOfDay()
        );
    }

    private function prepararActividad(int $idActividad): Actividad
    {
        $actividad = Actividad::findOrFail($idActividad);
        $horario = Horario::create([
            'hora_inicio' => '10:00:00',
            'franja' => 'M',
        ]);
        $actividad->horarios()->attach($horario->id);

        return $actividad->fresh(['horarios']);
    }

    private function crearTurno(Paciente $paciente, int $idActividad, string $estado): Turno
    {
        $actPac = ActividadPaciente::create([
            'id_actividad' => $idActividad,
            'id_paciente' => $paciente->id,
            'cant_sesiones' => 4,
            'total_a_pagar' => 0,
        ]);

        return Turno::create([
            'id_act_pac' => $actPac->id,
            'fecha_hora' => self::SLOT_ORIGINAL,
            'estado' => $estado,
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
}
