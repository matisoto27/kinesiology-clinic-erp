<?php

namespace Database\Seeders;

use App\Models\TipoSintoma;
use Illuminate\Database\Seeder;

class TipoSintomaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tipos = [
            ['nombre' => 'Columna Vertebral'],
            ['nombre' => 'Extremidades'],
            ['nombre' => 'Neurológicos'],
            ['nombre' => 'Respiratorios'],
            ['nombre' => 'Sistémicos'],
            ['nombre' => 'Posturales']
        ];

        foreach ($tipos as $tipo) {
            TipoSintoma::firstOrCreate(
                ['nombre' => $tipo['nombre']],
                []
            );
        }
    }
}
