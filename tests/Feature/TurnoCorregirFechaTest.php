<?php

namespace Tests\Feature;

use App\Exceptions\ReglaNegocioException;
use App\Models\Actividad;
use App\Models\ActividadPaciente;
use App\Models\Paciente;
use App\Models\Turno;
use App\Services\TurnoService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TurnoCorregirFechaTest extends TestCase
{
    use RefreshDatabase;

    private TurnoService $turnoService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->turnoService = app(TurnoService::class);
        Carbon::setTestNow('2026-06-01 08:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_corrige_la_fecha_del_turno_vigente_de_kinesiologia(): void
    {
        $turno = $this->crearTurnoKine('2026-06-03 10:00:00');

        $actualizado = $this->turnoService->corregirFecha(
            $turno,
            Carbon::parse('2026-06-04 11:00:00')
        );

        $this->assertSame($turno->id, $actualizado->id);
        $this->assertSame('2026-06-04 11:00:00', $actualizado->fecha_hora->format('Y-m-d H:i:s'));
        $this->assertSame(1, Turno::count());
    }

    public function test_corrige_un_reprogramado_de_kinesiologia_sin_crear_otro_turno(): void
    {
        $original = $this->crearTurnoKine('2026-06-03 10:00:00', 'Ausente avisó');
        $reprogramado = Turno::create([
            'id_act_pac' => $original->id_act_pac,
            'fecha_hora' => '2026-06-04 10:00:00',
            'estado' => 'Ausente',
            'id_turno_original' => $original->id,
        ]);

        $actualizado = $this->turnoService->corregirFecha(
            $reprogramado,
            Carbon::parse('2026-06-05 10:00:00')
        );

        $this->assertSame($reprogramado->id, $actualizado->id);
        $this->assertSame($original->id, $actualizado->id_turno_original);
        $this->assertSame('2026-06-05 10:00:00', $actualizado->fecha_hora->format('Y-m-d H:i:s'));
        $this->assertSame(2, Turno::count());
    }

    public function test_rechaza_corregir_un_turno_con_asistencia(): void
    {
        $turno = $this->crearTurnoKine('2026-06-03 10:00:00', 'Presente');

        $this->expectException(ReglaNegocioException::class);
        $this->expectExceptionMessage('No se puede corregir un turno donde el paciente ya ha asistido.');

        $this->turnoService->corregirFecha($turno, Carbon::parse('2026-06-04 10:00:00'));
    }

    public function test_rechaza_corregir_un_turno_marcado_aa(): void
    {
        $turno = $this->crearTurnoKine('2026-06-03 10:00:00', 'Ausente avisó');

        $this->expectException(ReglaNegocioException::class);
        $this->expectExceptionMessage('Este turno ya fue marcado como Ausente Avisó.');

        $this->turnoService->corregirFecha($turno, Carbon::parse('2026-06-04 10:00:00'));
    }

    public function test_rechaza_corregir_un_turno_general(): void
    {
        $inscripcion = $this->crearInscripcion(Actividad::PILATES);
        $turno = Turno::create([
            'id_act_pac' => $inscripcion->id,
            'fecha_hora' => '2026-06-03 10:00:00',
            'estado' => 'Ausente',
        ]);

        $this->expectException(ReglaNegocioException::class);
        $this->expectExceptionMessage('La corrección simple de fecha solo aplica a turnos de kinesiología.');

        $this->turnoService->corregirFecha($turno, Carbon::parse('2026-06-04 10:00:00'));
    }

    private function crearTurnoKine(string $fechaHora, string $estado = 'Ausente'): Turno
    {
        $inscripcion = $this->crearInscripcion(Actividad::QUIROPRAXIA);

        return Turno::create([
            'id_act_pac' => $inscripcion->id,
            'fecha_hora' => $fechaHora,
            'estado' => $estado,
        ]);
    }

    private function crearInscripcion(int $idActividad): ActividadPaciente
    {
        return ActividadPaciente::create([
            'id_actividad' => $idActividad,
            'id_paciente' => $this->crearPaciente()->id,
            'cant_sesiones' => 4,
            'total_a_pagar' => 0,
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
