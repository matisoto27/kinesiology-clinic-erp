<?php

namespace Database\Seeders;

use App\Models\Horario;
use Illuminate\Database\Seeder;

class HorarioSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $horarios = [
            ['hora_inicio' => '07:00:00', 'franja' => 'M'],
            ['hora_inicio' => '07:30:00', 'franja' => 'M'],
            ['hora_inicio' => '08:00:00', 'franja' => 'M'],
            ['hora_inicio' => '08:30:00', 'franja' => 'M'],
            ['hora_inicio' => '09:00:00', 'franja' => 'M'],
            ['hora_inicio' => '09:30:00', 'franja' => 'M'],
            ['hora_inicio' => '10:00:00', 'franja' => 'M'],
            ['hora_inicio' => '10:30:00', 'franja' => 'M'],
            ['hora_inicio' => '11:00:00', 'franja' => 'M'],
            ['hora_inicio' => '11:30:00', 'franja' => 'M'],
            ['hora_inicio' => '15:00:00', 'franja' => 'T'],
            ['hora_inicio' => '15:30:00', 'franja' => 'T'],
            ['hora_inicio' => '16:00:00', 'franja' => 'T'],
            ['hora_inicio' => '16:30:00', 'franja' => 'T'],
            ['hora_inicio' => '17:00:00', 'franja' => 'T'],
            ['hora_inicio' => '17:30:00', 'franja' => 'T'],
            ['hora_inicio' => '18:00:00', 'franja' => 'T'],
            ['hora_inicio' => '18:30:00', 'franja' => 'T'],
            ['hora_inicio' => '19:00:00', 'franja' => 'T'],
            ['hora_inicio' => '19:30:00', 'franja' => 'T']
        ];

        foreach ($horarios as $indice => $datos) {
            Horario::firstOrCreate(
                ['id' => $indice + 1],
                $datos
            );
        }
    }
}
