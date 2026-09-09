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

    public function test_un_paciente_regular_particular_no_es_gympass_aunque_no_tenga_nada_a_pagar(): void
    {
        $paciente = $this->crearPacienteRegular();

        // Caso real: la pata de Pilates de una inscripción dual, que se cobra en $0.
        $actPac = $this->crearInscripcionRegular($paciente, Actividad::PILATES, totalAPagar: 0);

        $this->assertFalse($actPac->esGympass());
        $this->assertFalse($actPac->esPrueba());
    }

    #[DataProvider('actividades')]
    public function test_un_paciente_regular_gympass_es_gympass_en_gimnasio_y_pilates(int $idActividad): void
    {
        $paciente = $this->crearPacienteRegular(['es_gympass' => true]);
        $actPac = $this->crearInscripcionRegular($paciente, $idActividad, totalAPagar: 0);

        $this->assertTrue($actPac->esGympass());
        $this->assertFalse($actPac->esPrueba());
    }

    public function test_un_paciente_regular_gympass_no_es_gympass_en_kinesiologia(): void
    {
        $paciente = $this->crearPacienteRegular(['es_gympass' => true]);
        $kine = Actividad::create([
            'nombre' => 'Kine Test',
            'id_tipo_actividad' => Actividad::TIPO_KINESIOLOGIA,
        ]);

        $actPac = $this->crearInscripcionRegular($paciente, $kine->id, totalAPagar: 9000);

        $this->assertFalse($actPac->esGympass());
        $this->assertFalse($actPac->esPrueba());
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function crearPacienteRegular(array $extra = []): Paciente
    {
        return Paciente::create(array_merge([
            'dni' => (string) random_int(10000000, 99999999),
            'nombre' => 'Nombre',
            'apellido' => 'Apellido',
            'fecha_nac' => '1990-01-01',
            'domicilio' => 'Calle 123',
            'telefono' => '9999999999',
            'profesion' => 'Profesión',
            'actividad_fisica' => 'Ninguna',
            'es_adulto_mayor' => false,
        ], $extra));
    }

    private function crearInscripcionRegular(Paciente $paciente, int $idActividad, float $totalAPagar): ActividadPaciente
    {
        $actPac = ActividadPaciente::create([
            'id_actividad' => $idActividad,
            'id_paciente' => $paciente->id,
            'cant_sesiones' => 4,
            'total_a_pagar' => $totalAPagar,
            'pago_completado' => $totalAPagar <= 0,
        ]);

        return $actPac->load('pacienteRegular');
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
