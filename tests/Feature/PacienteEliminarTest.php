<?php

namespace Tests\Feature;

use App\Models\Actividad;
use App\Models\ActividadPaciente;
use App\Models\Pago;
use App\Models\Paciente;
use App\Models\Profesional;
use App\Models\Turno;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PacienteEliminarTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-06-15 08:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_eliminar_libera_turnos_futuros_sin_historial_y_da_de_baja_al_paciente(): void
    {
        $paciente = $this->crearPaciente();
        $actPac = ActividadPaciente::create([
            'id_actividad' => Actividad::KINESIOLOGIA_CONVENCIONAL,
            'id_paciente' => $paciente->id,
            'cant_sesiones' => 10,
            'total_a_pagar' => 0,
        ]);

        Turno::create(['id_act_pac' => $actPac->id, 'fecha_hora' => '2026-06-10 10:00:00', 'estado' => 'Ausente']);
        Turno::create(['id_act_pac' => $actPac->id, 'fecha_hora' => '2026-06-20 10:00:00', 'estado' => 'Ausente']);

        $this->withoutMiddleware(ValidateCsrfToken::class)
            ->withSession(['autorizado' => true])
            ->delete(route('pacientes.eliminar', ['paciente' => $paciente->id]))
            ->assertRedirect()
            ->assertSessionHas('exito');

        $this->assertTrue($paciente->fresh()->trashed());
        $this->assertSame(0, ActividadPaciente::where('id_paciente', $paciente->id)->count());
        $this->assertSame(0, Turno::where('id_act_pac', $actPac->id)->count());
    }

    public static function motivosDeConservacion(): array
    {
        return [
            'con pagos' => ['conPagos'],
            'con turno presente sin pagos' => ['conTurnoPresente'],
        ];
    }

    #[DataProvider('motivosDeConservacion')]
    public function test_eliminar_conserva_historial_y_deja_de_ocupar_cupo_en_agenda(string $escenario): void
    {
        $paciente = $this->crearPaciente();
        $actPac = ActividadPaciente::create([
            'id_actividad' => Actividad::KINESIOLOGIA_CONVENCIONAL,
            'id_paciente' => $paciente->id,
            'cant_sesiones' => 10,
            'total_a_pagar' => 5000,
        ]);

        $turnoFuturo = match ($escenario) {
            'conPagos' => $this->prepararEscenarioConPagos($actPac),
            'conTurnoPresente' => $this->prepararEscenarioConTurnoPresente($actPac),
        };

        $turnosIniciales = Turno::where('id_act_pac', $actPac->id)->count();

        $this->withoutMiddleware(ValidateCsrfToken::class)
            ->withSession(['autorizado' => true])
            ->delete(route('pacientes.eliminar', ['paciente' => $paciente->id]))
            ->assertRedirect()
            ->assertSessionHas('exito');

        $this->assertTrue($paciente->fresh()->trashed());
        // Se conserva el historial (pagos o asistencia registrada)...
        $this->assertSame(1, ActividadPaciente::where('id_paciente', $paciente->id)->count());
        $this->assertSame($turnosIniciales, Turno::where('id_act_pac', $actPac->id)->count());
        // ...pero deja de ocupar cupo/agenda porque el paciente ya no está activo.
        $this->assertFalse(Turno::activosParaCupo()->whereKey($turnoFuturo->id)->exists());
        $this->assertFalse(Turno::visiblesEnAgenda()->whereKey($turnoFuturo->id)->exists());
    }

    private function prepararEscenarioConPagos(ActividadPaciente $actPac): Turno
    {
        $profesional = Profesional::create([
            'dni' => fake()->unique()->numerify('########'),
            'nombre' => 'Nombre',
            'apellido' => 'Apellido',
            'activo' => true,
        ]);

        Pago::create([
            'metodo' => 'Efectivo',
            'monto' => 5000,
            'es_copago' => false,
            'id_act_pac' => $actPac->id,
            'id_profesional' => $profesional->id,
        ]);

        return Turno::create(['id_act_pac' => $actPac->id, 'fecha_hora' => '2026-06-20 10:00:00', 'estado' => 'Ausente']);
    }

    private function prepararEscenarioConTurnoPresente(ActividadPaciente $actPac): Turno
    {
        Turno::create(['id_act_pac' => $actPac->id, 'fecha_hora' => '2026-06-10 10:00:00', 'estado' => 'Presente']);

        return Turno::create(['id_act_pac' => $actPac->id, 'fecha_hora' => '2026-06-20 10:00:00', 'estado' => 'Ausente']);
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
