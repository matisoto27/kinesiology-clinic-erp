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
            ['nombre' => 'ACA SALUD'],
            ['nombre' => 'ALIANZA MÉDICA SA'],
            ['nombre' => 'AMR'],
            ['nombre' => 'AMUR'],
            ['nombre' => 'AMOEIAG'],
            ['nombre' => 'ANDINA ART'],
            ['nombre' => 'BRITÁNICA SALUD'],
            ['nombre' => 'CAJA FORENSE'],
            ['nombre' => 'CAJA DE INGENIEROS'],
            ['nombre' => 'CS ECONÓMICAS'],
            ['nombre' => 'CONFERENCIA'],
            ['nombre' => 'DASUTEN'],
            ['nombre' => 'DOCTHOS'],
            ['nombre' => 'ENSALUD SA'],
            ['nombre' => 'ENERGÍA SALUD'],
            ['nombre' => 'EPISCOPAL'],
            ['nombre' => 'FEDERACIÓN MÉDICA'],
            ['nombre' => 'GALENO ART'],
            ['nombre' => 'GRUPO SAN NICOLÁS'],
            ['nombre' => 'IAPOS'],
            ['nombre' => 'IOSFA'],
            ['nombre' => 'ITER MEDICINA SA'],
            ['nombre' => 'JERÁRQUICOS SALUD'],
            ['nombre' => 'LA SEGUNDA ART'],
            ['nombre' => 'LA SEGUNDA PERSONAS'],
            ['nombre' => 'MEDICAR WORK'],
            ['nombre' => 'MEDICINA ARGENTINA'],
            ['nombre' => 'MUTUAL FEDERADA'],
            ['nombre' => 'MUTUALYF'],
            ['nombre' => 'OPDEA'],
            ['nombre' => 'OSPSA'],
            ['nombre' => 'OSPAC'],
            ['nombre' => 'OSPESGA'],
            ['nombre' => 'OS LUIS PASTEUR'],
            ['nombre' => 'OS DE FUTBOLISTAS'],
            ['nombre' => 'PODER JUDICIAL'],
            ['nombre' => 'PREVENCIÓN SALUD SA'],
            ['nombre' => 'POLICÍA FEDERAL'],
            ['nombre' => 'SANCOR'],
            ['nombre' => 'SADAIC'],
            ['nombre' => 'SERVE'],
            ['nombre' => 'SINDICATO DE PRENSA'],
            ['nombre' => 'SUTIAGA'],
            ['nombre' => 'SWISS MEDICAL'],
            ['nombre' => 'TELEVISION']
        ];

        foreach ($obrasSociales as $os) {
            ObraSocial::firstOrCreate(
                ['nombre' => $os['nombre']],
                []
            );
        }
    }
}
