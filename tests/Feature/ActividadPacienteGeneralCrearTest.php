<?php

namespace Tests\Feature;

use App\Models\Actividad;
use App\Models\ActividadPaciente;
use App\Models\Horario;
use App\Models\Paciente;
use App\Models\PrecioMensual;
use App\Models\Turno;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ActividadPacienteGeneralCrearTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_semana_subsiguiente_ofrece_las_fechas_de_la_semana_mas_dos(): void
    {
        Carbon::setTestNow('2026-06-03 10:00:00'); // miércoles semana actual

        $componente = $this->completarGrillaPilates(['Lunes', 'Miércoles'])
            ->set('semanaInicio', 'subsiguiente');

        $this->assertSame(
            ['2026-06-15', '2026-06-17'],
            $componente->instance()->fechasCandidatasPrimeraClase
        );
        $this->assertSame('', $componente->get('fechaAncla'));
    }

    public function test_semana_siguiente_sigue_ofreciendo_la_semana_mas_uno(): void
    {
        Carbon::setTestNow('2026-06-03 10:00:00');

        $componente = $this->completarGrillaPilates(['Lunes', 'Miércoles'])
            ->set('semanaInicio', 'siguiente');

        $this->assertSame(
            ['2026-06-08', '2026-06-10'],
            $componente->instance()->fechasCandidatasPrimeraClase
        );
    }

    public function test_semana_actual_omite_dias_ya_pasados_y_autocompleta_si_queda_una_candidata(): void
    {
        Carbon::setTestNow('2026-06-03 10:00:00');

        $componente = $this->completarGrillaPilates(['Lunes', 'Miércoles'])
            ->set('semanaInicio', 'actual');

        $this->assertSame(
            ['2026-06-03'],
            $componente->instance()->fechasCandidatasPrimeraClase
        );
        $this->assertSame('2026-06-03', $componente->get('fechaAncla'));
    }

    public function test_viernes_a_las_19_fuerza_semana_siguiente_y_no_la_subsiguiente(): void
    {
        Carbon::setTestNow('2026-06-05 19:00:00');

        $componente = $this->completarGrillaPilates(['Lunes', 'Miércoles']);

        $this->assertTrue($componente->instance()->debeForzarSemanaSiguiente);
        $this->assertSame('siguiente', $componente->get('semanaInicio'));
        $this->assertSame(
            ['2026-06-08', '2026-06-10'],
            $componente->instance()->fechasCandidatasPrimeraClase
        );

        $componente->set('semanaInicio', 'subsiguiente');

        $this->assertSame(
            ['2026-06-15', '2026-06-17'],
            $componente->instance()->fechasCandidatasPrimeraClase
        );
    }

    public function test_registrar_con_semana_subsiguiente_ancla_el_primer_turno_en_esa_semana(): void
    {
        Carbon::setTestNow('2026-06-03 10:00:00');

        $this->crearPreciosMensuales([1 => 15000.00]);
        $this->asociarHorarioAPilates();
        $paciente = $this->crearPaciente();

        $componente = $this->completarGrillaPilates(['Lunes'], frecuencia: 1)
            ->set('idPacienteSeleccionado', $paciente->id)
            ->set('semanaInicio', 'subsiguiente')
            ->call('almacenar');

        $inscripcion = ActividadPaciente::query()->first();
        $this->assertNotNull($inscripcion);
        $componente->assertRedirect(route('actividades-pacientes.pagos.crear', ['id' => $inscripcion->id]));

        $primerTurno = Turno::query()->orderBy('fecha_hora')->first();
        $this->assertSame('2026-06-15 10:00:00', $primerTurno->fecha_hora->format('Y-m-d H:i:s'));
        $this->assertSame(4, Turno::count());
    }

    /**
     * @param  list<string>  $dias
     */
    private function completarGrillaPilates(array $dias, int $frecuencia = 2)
    {
        $horarios = [];

        foreach (Actividad::diasSemanaDisponibles() as $dia) {
            $horarios[$dia] = [
                Actividad::GIMNASIO => '',
                Actividad::PILATES => '',
            ];
        }

        foreach ($dias as $dia) {
            $horarios[$dia][Actividad::PILATES] = '10:00:00';
        }

        return Livewire::test('actividades-pacientes.general.crear')
            ->set('frecuenciaSemanal', $frecuencia)
            ->set('horarios', $horarios);
    }

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
