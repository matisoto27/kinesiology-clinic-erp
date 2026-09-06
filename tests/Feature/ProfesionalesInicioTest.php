<?php

namespace Tests\Feature;

use App\Models\Profesional;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProfesionalesInicioTest extends TestCase
{
    use RefreshDatabase;

    public function test_lista_pagina_de_a_diez_y_filtra_por_estado(): void
    {
        for ($i = 0; $i < 11; $i++) {
            $this->crearProfesional("Prof{$i}", "Apellido{$i}");
        }
        $inactivo = $this->crearProfesional('Zzz', 'Inactivo', activo: false);

        $componente = Livewire::test('profesionales.inicio');

        $primeraPagina = $componente->instance()->profesionales();
        $this->assertSame(10, $primeraPagina->count());
        $this->assertSame(12, $primeraPagina->total());

        $componente->set('filtroEstado', 'inactivos')->assertSee($inactivo->dni);

        $soloInactivos = $componente->instance()->profesionales();
        $this->assertSame(1, $soloInactivos->total());
    }

    public function test_eliminar_borra_el_profesional_y_muestra_mensaje_de_exito(): void
    {
        $profesional = $this->crearProfesional('Ana', 'García');

        Livewire::test('profesionales.inicio')
            ->call('eliminar', $profesional->id)
            ->assertSee('El profesional ha sido eliminado correctamente.');

        $this->assertNull(Profesional::find($profesional->id));
    }

    private function crearProfesional(string $nombre, string $apellido, bool $activo = true): Profesional
    {
        return Profesional::create([
            'dni' => (string) random_int(10000000, 99999999),
            'nombre' => $nombre,
            'apellido' => $apellido,
            'activo' => $activo,
        ]);
    }
}
