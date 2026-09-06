<?php

namespace Tests\Feature;

use App\Models\ContactoEmergencia;
use App\Models\ObraSocial;
use App\Models\ObraSocialPaciente;
use App\Models\Paciente;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PacientesInicioTest extends TestCase
{
    use RefreshDatabase;

    public function test_lista_pacientes_sin_cargar_detalle_hasta_que_se_pide(): void
    {
        $this->crearPaciente('11111111', 'Juan', 'Pérez');

        Livewire::test('pacientes.inicio')
            ->assertSee('Pérez, Juan')
            ->assertSet('idPacienteSeleccionado', null);
    }

    public function test_ver_detalle_carga_obra_social_contactos_y_patologias_on_demand(): void
    {
        $paciente = $this->crearPaciente('22222222', 'Ana', 'García', esAdultoMayor: true, viveCon: 'Su hija');

        $iapos = ObraSocial::create(['nombre' => 'IAPOS', 'activo' => true]);
        ObraSocialPaciente::create([
            'id_paciente' => $paciente->id,
            'id_obra_social' => $iapos->id,
            'fecha_desde' => now(),
        ]);

        ContactoEmergencia::create([
            'id_paciente' => $paciente->id,
            'nombre' => 'María García',
            'telefono' => '3415555555',
            'vinculo' => 'Hijo/a',
        ]);

        $componente = Livewire::test('pacientes.inicio')
            ->call('verDetalle', $paciente->id)
            ->assertSet('idPacienteSeleccionado', $paciente->id)
            ->assertSee('IAPOS')
            ->assertSee('María García')
            ->assertSee('Sin antecedentes patológicos.')
            ->assertSee('No registra síntomas activos.');

        $detalle = $componente->instance()->detallePaciente();

        $this->assertSame('IAPOS', $detalle['obra_social']);
        $this->assertCount(1, $detalle['contactos_emergencia']);
        $this->assertSame('María García', $detalle['contactos_emergencia'][0]['nombre']);

        $componente->call('cerrarDetalle')->assertSet('idPacienteSeleccionado', null);
    }

    private function crearPaciente(
        string $dni,
        string $nombre,
        string $apellido,
        bool $esAdultoMayor = false,
        ?string $viveCon = null
    ): Paciente {
        return Paciente::create([
            'dni' => $dni,
            'nombre' => $nombre,
            'apellido' => $apellido,
            'fecha_nac' => '1990-01-01',
            'domicilio' => 'Calle 123',
            'telefono' => '1111111111',
            'profesion' => 'Profesion',
            'actividad_fisica' => 'Ninguna',
            'es_adulto_mayor' => $esAdultoMayor,
            'vive_con' => $viveCon,
        ]);
    }
}
