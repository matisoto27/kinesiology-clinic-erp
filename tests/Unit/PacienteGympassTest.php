<?php

namespace Tests\Unit;

use App\Models\Paciente;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PacienteGympassTest extends TestCase
{
    use RefreshDatabase;

    public function test_es_gympass_es_falso_por_defecto(): void
    {
        $paciente = $this->crearPaciente();

        $this->assertFalse($paciente->es_gympass);
        $this->assertFalse($paciente->fresh()->es_gympass);
        $this->assertSame(0, (int) Paciente::query()->whereKey($paciente->id)->value('es_gympass'));
    }

    public function test_la_columna_default_es_false_sin_pasar_el_campo(): void
    {
        $id = DB::table('pacientes')->insertGetId([
            'dni' => (string) random_int(10000000, 99999999),
            'nombre' => 'Nombre',
            'apellido' => 'Apellido',
            'fecha_nac' => '1990-01-01',
            'domicilio' => 'Calle 123',
            'telefono' => '1111111111',
            'profesion' => 'Profesion',
            'actividad_fisica' => 'Ninguna',
            'es_adulto_mayor' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertSame(0, (int) DB::table('pacientes')->where('id', $id)->value('es_gympass'));
        $this->assertFalse(Paciente::query()->findOrFail($id)->es_gympass);
    }

    public function test_permite_marcar_como_gympass(): void
    {
        $paciente = $this->crearPaciente();

        $paciente->update(['es_gympass' => true]);

        $this->assertTrue($paciente->fresh()->es_gympass);
    }

    public function test_no_permite_desmarcar_gympass_una_vez_persistido(): void
    {
        $paciente = $this->crearPaciente(['es_gympass' => true]);

        $paciente->update(['es_gympass' => false]);

        $this->assertTrue($paciente->fresh()->es_gympass);
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function crearPaciente(array $extra = []): Paciente
    {
        return Paciente::create(array_merge([
            'dni' => (string) random_int(10000000, 99999999),
            'nombre' => 'Nombre',
            'apellido' => 'Apellido',
            'fecha_nac' => '1990-01-01',
            'domicilio' => 'Calle 123',
            'telefono' => '1111111111',
            'profesion' => 'Profesion',
            'actividad_fisica' => 'Ninguna',
            'es_adulto_mayor' => false,
        ], $extra));
    }
}
