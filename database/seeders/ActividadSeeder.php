<?php

namespace Database\Seeders;

use App\Models\Actividad;
use Illuminate\Database\Seeder;

class ActividadSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $actividades = [
            ['id' => 1, 'nombre' => 'Gimnasio', 'id_tipo_actividad' => 1],
            ['id' => 2, 'nombre' => 'Pilates', 'id_tipo_actividad' => 1],
            ['id' => 3, 'nombre' => 'Kinesiología convencional', 'id_tipo_actividad' => 2],
            ['id' => 4, 'nombre' => 'Quiropraxia', 'id_tipo_actividad' => 2],
            ['id' => 5, 'nombre' => 'RPG', 'id_tipo_actividad' => 2],
            ['id' => 6, 'nombre' => 'ATM', 'id_tipo_actividad' => 2],
            ['id' => 7, 'nombre' => 'DLM', 'id_tipo_actividad' => 2],
            ['id' => 8, 'nombre' => 'Masajes', 'id_tipo_actividad' => 2]
        ];

        foreach ($actividades as $act) {
            Actividad::firstOrCreate(
                ['id' => $act['id']],
                [
                    'nombre' => $act['nombre'],
                    'id_tipo_actividad' => $act['id_tipo_actividad']
                ]
            );
        }
    }
}
