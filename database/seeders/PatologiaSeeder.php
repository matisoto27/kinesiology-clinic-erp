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
            ['nombre' => 'EPOC'],
            ['nombre' => 'Hernia de Disco'],
            ['nombre' => 'Escoliosis'],
            ['nombre' => 'Artritis Reumatoidea'],
            ['nombre' => 'Artrosis'],
            ['nombre' => 'Esguince'],
            ['nombre' => 'Desgarro Muscular'],
            ['nombre' => 'Tendinitis'],
            ['nombre' => 'Síndrome del Túnel Carpiano'],
            ['nombre' => 'Fascitis Plantar'],
            ['nombre' => 'Fibromialgia'],
            ['nombre' => 'Parálisis Facial'],
            ['nombre' => 'Enfermedad de Parkinson'],
            ['nombre' => 'Esclerosis Múltiple'],
            ['nombre' => 'Lumbociatalgia'],
            ['nombre' => 'Fractura'],
            ['nombre' => 'Luxación'],
            ['nombre' => 'Bursitis'],
            ['nombre' => 'Pubalgia'],
            ['nombre' => 'Pie Plano / Cavo'],
            ['nombre' => 'Linfedema'],
            ['nombre' => 'Post-operatorio de LCA'],
            ['nombre' => 'Reemplazo de Cadera'],
            ['nombre' => 'Reemplazo de Rodilla'],
            ['nombre' => 'Cefalea Tensional'],
            ['nombre' => 'Bruxismo']
        ];

        foreach ($patologias as $pat) {
            Patologia::firstOrCreate(
                ['nombre' => $pat['nombre']],
                []
            );
        }
    }
}
