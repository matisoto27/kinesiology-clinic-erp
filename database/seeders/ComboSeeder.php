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
            ['id' => 1, 'nombre' => 'Sesion individual', 'cantidad_sesiones' => 1],
            ['id' => 2, 'nombre' => 'Combo x3 sesiones', 'cantidad_sesiones' => 3],
            ['id' => 3, 'nombre' => 'Combo x5 sesiones', 'cantidad_sesiones' => 5],
            ['id' => 4, 'nombre' => 'Combo x10 sesiones', 'cantidad_sesiones' => 10],
        ];

        foreach ($combos as $combo) {
            Combo::firstOrCreate(
                ['id' => $combo['id']],
                [
                    'nombre' => $combo['nombre'],
                    'cantidad_sesiones' => $combo['cantidad_sesiones'],
                ]
            );
        }
    }
}
