<?php

namespace Tests\Unit;

use App\Models\Actividad;
use App\Models\ActividadPaciente;
use App\Models\Paciente;
use App\Models\PacienteCasual;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ActividadPacienteModalidadCasualTest extends TestCase
{
    use RefreshDatabase;

    public static function actividades(): array
    {
        return [
            'gimnasio' => [Actividad::GIMNASIO],
            'pilates' => [Actividad::PILATES],
        ];
    }

    #[DataProvider('actividades')]
    public function test_es_gympass_cuando_el_casual_no_tiene_nada_a_pagar_sin_importar_la_actividad(int $idActividad): void
    {
        $actPac = $this->crearInscripcionCasual($idActividad, totalAPagar: 0);

        $this->assertTrue($actPac->esGympass());
        $this->assertFalse($actPac->esPrueba());
    }

    #[DataProvider('actividades')]
    public function test_es_prueba_cuando_el_casual_tiene_un_monto_a_pagar_sin_importar_la_actividad(int $idActividad): void
    {
        $actPac = $this->crearInscripcionCasual($idActividad, totalAPagar: 15000);

        $this->assertTrue($actPac->esPrueba());
        $this->assertFalse($actPac->esGympass());
    }

    public function test_un_paciente_regular_nunca_es_gympass_ni_prueba_aunque_no_tenga_nada_a_pagar(): void
    {
        $paciente = Paciente::create([
            'dni' => (string) random_int(10000000, 99999999),
            'nombre' => 'Nombre',
            'apellido' => 'Apellido',
            'fecha_nac' => '1990-01-01',
            'domicilio' => 'Calle 123',
            'telefono' => '9999999999',
            'profesion' => 'Profesión',
            'actividad_fisica' => 'Ninguna',
            'es_adulto_mayor' => false,
        ]);

        // Caso real: la pata de Pilates de una inscripción dual, que se cobra en $0.
        $actPac = ActividadPaciente::create([
            'id_actividad' => Actividad::PILATES,
            'id_paciente' => $paciente->id,
            'cant_sesiones' => 4,
            'total_a_pagar' => 0,
            'pago_completado' => true,
        ]);

        $this->assertFalse($actPac->esGympass());
        $this->assertFalse($actPac->esPrueba());
    }

    private function crearInscripcionCasual(int $idActividad, float $totalAPagar): ActividadPaciente
    {
        $paciente = PacienteCasual::create([
            'nombre' => 'Nombre',
            'apellido' => 'Apellido',
            'telefono' => (string) random_int(1000000000, 9999999999),
        ]);

        return ActividadPaciente::create([
            'id_actividad' => $idActividad,
            'id_paciente_casual' => $paciente->id,
            'cant_sesiones' => 1,
            'total_a_pagar' => $totalAPagar,
            'pago_completado' => $totalAPagar <= 0,
        ]);
    }
}
