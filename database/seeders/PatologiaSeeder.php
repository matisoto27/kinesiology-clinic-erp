<?php

namespace Database\Seeders;

use App\Models\Patologia;
use Illuminate\Database\Seeder;

class PatologiaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $patologias = [
            ['nombre' => 'DBT'],
            ['nombre' => 'HTA'],
            ['nombre' => 'ACV'],
            ['nombre' => 'ASMA'],
            ['nombre' => 'EPOC']
        ];

        foreach ($patologias as $pat) {
            Patologia::firstOrCreate(
                ['nombre' => $pat['nombre']],
                []
            );
        }
    }
}
