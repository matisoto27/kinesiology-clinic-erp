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
            ['id' => Actividad::GIMNASIO, 'nombre' => 'Gimnasio', 'id_tipo_actividad' => 1],
            ['id' => Actividad::PILATES, 'nombre' => 'Pilates', 'id_tipo_actividad' => 1],
            ['id' => Actividad::KINESIOLOGIA_CONVENCIONAL, 'nombre' => 'Kinesiología convencional', 'id_tipo_actividad' => 2],
            ['id' => Actividad::QUIROPRAXIA, 'nombre' => 'Quiropraxia', 'id_tipo_actividad' => 2],
            ['id' => Actividad::RPG, 'nombre' => 'RPG', 'id_tipo_actividad' => 2],
            ['id' => Actividad::ATM, 'nombre' => 'ATM', 'id_tipo_actividad' => 2],
            ['id' => Actividad::DLM, 'nombre' => 'DLM', 'id_tipo_actividad' => 2],
            ['id' => Actividad::MASAJES, 'nombre' => 'Masajes', 'id_tipo_actividad' => 2]
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
