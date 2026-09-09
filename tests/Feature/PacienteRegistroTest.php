<?php

namespace Tests\Feature;

use App\Models\Paciente;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PacienteRegistroTest extends TestCase
{
    use RefreshDatabase;

    public function test_rechaza_dni_duplicado_entre_activos(): void
    {
        Paciente::create($this->datosPaciente(['dni' => '12345678']));

        Livewire::test('pacientes.crear')
            ->set($this->payloadLivewire(['dni' => '12345678']))
            ->call('almacenar')
            ->assertHasErrors(['dni']);

        $this->assertSame(1, Paciente::count());
    }

    public function test_restaura_paciente_soft_deleted_con_el_mismo_dni(): void
    {
        $eliminado = Paciente::create($this->datosPaciente([
            'dni' => '87654321',
            'nombre' => 'Ana',
            'apellido' => 'García',
            'telefono' => '1111111111',
        ]));
        $eliminado->delete();

        $this->assertSame(0, Paciente::count());
        $this->assertSame(1, Paciente::withTrashed()->count());

        Livewire::test('pacientes.crear')
            ->set($this->payloadLivewire([
                'dni' => '87654321',
                'nombre' => 'Ana María',
                'apellido' => 'García',
                'telefono' => '2222222222',
            ]))
            ->call('almacenar')
            ->assertRedirect(route('pacientes.inicio'));

        $this->assertSame(1, Paciente::count());
        $restaurado = Paciente::first();
        $this->assertSame($eliminado->id, $restaurado->id);
        $this->assertSame('Ana María', $restaurado->nombre);
        $this->assertSame('2222222222', $restaurado->telefono);
        $this->assertNull($restaurado->deleted_at);
    }

    public function test_crea_paciente_cuando_no_existe(): void
    {
        Livewire::test('pacientes.crear')
            ->set($this->payloadLivewire(['dni' => '11223344']))
            ->call('almacenar')
            ->assertRedirect(route('pacientes.inicio'));

        $this->assertSame(1, Paciente::count());
        $this->assertSame('11223344', Paciente::first()->dni);
        $this->assertFalse(Paciente::first()->es_gympass);
    }

    public function test_crea_paciente_gympass(): void
    {
        Livewire::test('pacientes.crear')
            ->set($this->payloadLivewire([
                'dni' => '22334455',
                'esGympass' => true,
            ]))
            ->call('almacenar')
            ->assertRedirect(route('pacientes.inicio'));

        $this->assertTrue(Paciente::first()->es_gympass);
    }

    public function test_editar_no_permite_desmarcar_gympass(): void
    {
        $paciente = Paciente::create($this->datosPaciente([
            'dni' => '33445566',
            'actividad_fisica' => 'Moderada',
            'es_gympass' => true,
        ]));

        Livewire::test('pacientes.editar', ['paciente' => $paciente])
            ->set('esGympass', false)
            ->call('actualizar')
            ->assertRedirect(route('pacientes.inicio'));

        $this->assertTrue($paciente->fresh()->es_gympass);
    }

    public function test_editar_permite_marcar_gympass_si_aun_no_lo_era(): void
    {
        $paciente = Paciente::create($this->datosPaciente([
            'dni' => '44556677',
            'actividad_fisica' => 'Moderada',
        ]));

        Livewire::test('pacientes.editar', ['paciente' => $paciente])
            ->set('esGympass', true)
            ->call('actualizar')
            ->assertRedirect(route('pacientes.inicio'));

        $this->assertTrue($paciente->fresh()->es_gympass);
    }

    public function test_restaurar_conserva_gympass_aunque_el_alta_lo_mande_desmarcado(): void
    {
        $eliminado = Paciente::create($this->datosPaciente([
            'dni' => '55667788',
            'es_gympass' => true,
        ]));
        $eliminado->delete();

        Livewire::test('pacientes.crear')
            ->set($this->payloadLivewire([
                'dni' => '55667788',
                'esGympass' => false,
            ]))
            ->call('almacenar')
            ->assertRedirect(route('pacientes.inicio'));

        $this->assertTrue(Paciente::first()->es_gympass);
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function datosPaciente(array $extra = []): array
    {
        return array_merge([
            'dni' => (string) random_int(10000000, 99999999),
            'nombre' => 'Nombre',
            'apellido' => 'Apellido',
            'fecha_nac' => '1990-01-01',
            'domicilio' => 'Calle 123',
            'telefono' => '1111111111',
            'profesion' => 'Profesion',
            'actividad_fisica' => 'Ninguna',
            'es_adulto_mayor' => false,
        ], $extra);
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function payloadLivewire(array $extra = []): array
    {
        $base = [
            'dni' => '99887766',
            'nombre' => 'Luis',
            'apellido' => 'Pérez',
            'fechaNac' => '1990-01-01',
            'domicilio' => 'Calle 123',
            'telefono' => '3333333333',
            'profesion' => 'Docente',
            'actividadFisica' => 'Moderada',
            'esAdultoMayor' => false,
        ];

        return array_merge($base, $extra);
    }
}
