<?php

namespace Database\Seeders;

use App\Models\TipoActividad;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

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
            DB::table('tipos_actividad')->updateOrInsert(
                ['id' => $tipo['id']],
                ['descripcion' => $tipo['descripcion']]
            );
        }
    }
}
