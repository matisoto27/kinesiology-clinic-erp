<?php

namespace Database\Seeders;

use App\Models\ObraSocial;
use Illuminate\Database\Seeder;

class ObraSocialSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $obrasSociales = [
            ['nombre' => 'IAPOS'],
            ['nombre' => 'OSDE'],
            ['nombre' => 'PAMI'],
            ['nombre' => 'Swiss Medical'],
            ['nombre' => 'Sancor Salud'],
            ['nombre' => 'Esencial'],
            ['nombre' => 'IOMA'],
            ['nombre' => 'OSECAC'],
            ['nombre' => 'Galeno'],
            ['nombre' => 'Medifé']
        ];

        foreach ($obrasSociales as $os) {
            ObraSocial::firstOrCreate(
                ['nombre' => $os['nombre']],
                []
            );
        }
    }
}
