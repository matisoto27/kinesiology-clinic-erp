<?php

namespace Database\Seeders;

use App\Models\TipoActividad;
use Illuminate\Database\Seeder;

class TipoActividadSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tipos = [
            ['id' => 1, 'descripcion' => 'General'],
            ['id' => 2, 'descripcion' => 'Kinesiología']
        ];

        foreach ($tipos as $tipo) {
            TipoActividad::firstOrCreate(
                ['id' => $tipo['id']],
                ['descripcion' => $tipo['descripcion']]
            );
        }
    }
}
