<?php

namespace Database\Seeders;

use App\Models\ActividadCombo;
use Illuminate\Database\Seeder;

class ActividadComboSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $actividadesCombos = [
            // Kinesiología convencional
            ['id_actividad' => 3, 'id_combo' => 1],
            ['id_actividad' => 3, 'id_combo' => 2],
            ['id_actividad' => 3, 'id_combo' => 3],
            ['id_actividad' => 3, 'id_combo' => 4],
            // El resto de actividades de Tipo II
            ['id_actividad' => 4, 'id_combo' => 1],
            ['id_actividad' => 5, 'id_combo' => 1],
            ['id_actividad' => 6, 'id_combo' => 1],
            ['id_actividad' => 7, 'id_combo' => 1],
            ['id_actividad' => 8, 'id_combo' => 1],
        ];

        foreach ($actividadesCombos as $actComb) {
            ActividadCombo::firstOrCreate(
                [
                    'id_actividad' => $actComb['id_actividad'],
                    'id_combo' => $actComb['id_combo'],
                ],
                []
            );
        }
    }
}
