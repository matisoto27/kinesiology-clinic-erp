<?php

namespace Database\Seeders;

use App\Models\Combo;
use Illuminate\Database\Seeder;

class ComboSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $combos = [
            ['id' => 1, 'nombre' => 'Combo mensual x1', 'cantidad_sesiones' => 4, 'es_mensual' => true],
            ['id' => 2, 'nombre' => 'Combo mensual x2', 'cantidad_sesiones' => 8, 'es_mensual' => true],
            ['id' => 3, 'nombre' => 'Combo mensual x3', 'cantidad_sesiones' => 12, 'es_mensual' => true],
            ['id' => 4, 'nombre' => 'Combo mensual x4', 'cantidad_sesiones' => 16, 'es_mensual' => true],
            ['id' => 5, 'nombre' => 'Combo mensual x5', 'cantidad_sesiones' => 20, 'es_mensual' => true],
            ['id' => 6, 'nombre' => 'Sesion individual', 'cantidad_sesiones' => 1, 'es_mensual' => false],
            ['id' => 7, 'nombre' => 'Combo x3 sesiones', 'cantidad_sesiones' => 3, 'es_mensual' => false],
            ['id' => 8, 'nombre' => 'Combo x5 sesiones', 'cantidad_sesiones' => 5, 'es_mensual' => false],
            ['id' => 9, 'nombre' => 'Combo x10 sesiones', 'cantidad_sesiones' => 10, 'es_mensual' => false],
            ['id' => 10, 'nombre' => 'Clase de prueba', 'cantidad_sesiones' => 1, 'es_mensual' => false]
        ];

        foreach ($combos as $combo) {
            Combo::firstOrCreate(
                ['id' => $combo['id']],
                [
                    'nombre' => $combo['nombre'],
                    'cantidad_sesiones' => $combo['cantidad_sesiones'],
                    'es_mensual' => $combo['es_mensual']
                ]
            );
        }
    }
}
