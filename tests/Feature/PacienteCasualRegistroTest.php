<?php

namespace Tests\Feature;

use App\Models\PacienteCasual;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PacienteCasualRegistroTest extends TestCase
{
    use RefreshDatabase;

    public function test_rechaza_telefono_duplicado_entre_activos(): void
    {
        PacienteCasual::create([
            'nombre' => 'Ana',
            'apellido' => 'García',
            'telefono' => '1111111111',
        ]);

        Livewire::test('pacientes-casuales.crear')
            ->set('form.nombre', 'Ana')
            ->set('form.apellido', 'García')
            ->set('form.telefono', '1111111111')
            ->call('almacenar')
            ->assertHasErrors(['form.telefono']);

        $this->assertSame(1, PacienteCasual::count());
    }

    public function test_restaura_paciente_soft_deleted_con_el_mismo_telefono(): void
    {
        $eliminado = PacienteCasual::create([
            'nombre' => 'Ana',
            'apellido' => 'García',
            'telefono' => '2222222222',
        ]);
        $eliminado->delete();

        $this->assertSame(0, PacienteCasual::count());
        $this->assertSame(1, PacienteCasual::withTrashed()->count());

        Livewire::test('pacientes-casuales.crear')
            ->set('form.nombre', 'Ana María')
            ->set('form.apellido', 'García')
            ->set('form.telefono', '2222222222')
            ->call('almacenar')
            ->assertRedirect(route('pacientes-casuales.inicio'));

        $this->assertSame(1, PacienteCasual::count());
        $restaurado = PacienteCasual::first();
        $this->assertSame($eliminado->id, $restaurado->id);
        $this->assertSame('Ana María', $restaurado->nombre);
        $this->assertSame('García', $restaurado->apellido);
        $this->assertNull($restaurado->deleted_at);
    }

    public function test_crea_paciente_cuando_no_existe(): void
    {
        Livewire::test('pacientes-casuales.crear')
            ->set('form.nombre', 'Luis')
            ->set('form.apellido', 'Pérez')
            ->set('form.telefono', '3333333333')
            ->call('almacenar')
            ->assertRedirect(route('pacientes-casuales.inicio'));

        $this->assertSame(1, PacienteCasual::count());
        $this->assertSame('Luis', PacienteCasual::first()->nombre);
    }

    public function test_unique_de_telefono_impide_insertar_duplicado_activo(): void
    {
        PacienteCasual::create([
            'nombre' => 'Uno',
            'apellido' => 'Mismo',
            'telefono' => '5555555555',
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        PacienteCasual::create([
            'nombre' => 'Dos',
            'apellido' => 'Mismo',
            'telefono' => '5555555555',
        ]);
    }

    public function test_editar_permite_conservar_el_mismo_telefono(): void
    {
        $paciente = PacienteCasual::create([
            'nombre' => 'Nora',
            'apellido' => 'López',
            'telefono' => '6666666666',
        ]);

        Livewire::test('pacientes-casuales.editar', ['paciente' => $paciente])
            ->set('form.nombre', 'Nora')
            ->set('form.apellido', 'López')
            ->set('form.telefono', '6666666666')
            ->call('actualizar')
            ->assertHasNoErrors()
            ->assertRedirect(route('pacientes-casuales.inicio'));
    }

    public function test_editar_rechaza_telefono_de_otro_activo(): void
    {
        PacienteCasual::create([
            'nombre' => 'A',
            'apellido' => 'Uno',
            'telefono' => '7777777777',
        ]);
        $paciente = PacienteCasual::create([
            'nombre' => 'B',
            'apellido' => 'Dos',
            'telefono' => '8888888888',
        ]);

        Livewire::test('pacientes-casuales.editar', ['paciente' => $paciente])
            ->set('form.nombre', 'B')
            ->set('form.apellido', 'Dos')
            ->set('form.telefono', '7777777777')
            ->call('actualizar')
            ->assertHasErrors(['form.telefono']);
    }
}
