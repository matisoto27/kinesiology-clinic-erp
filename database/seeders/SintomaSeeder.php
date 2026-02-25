<?php

namespace Database\Seeders;

use App\Models\Sintoma;
use App\Models\TipoSintoma;
use Illuminate\Database\Seeder;

class SintomaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tipos = TipoSintoma::pluck('id', 'nombre');

        $sintomas = [
            ['nombre' => 'Cervicalgia', 'id_tipo' => $tipos['Columna Vertebral']],
            ['nombre' => 'Dorsalgia', 'id_tipo' => $tipos['Columna Vertebral']],
            ['nombre' => 'Lumbalgia', 'id_tipo' => $tipos['Columna Vertebral']],
            ['nombre' => 'Sacralgia', 'id_tipo' => $tipos['Columna Vertebral']],
            ['nombre' => 'Coccigodinia', 'id_tipo' => $tipos['Columna Vertebral']],
            ['nombre' => 'Rectificación cervical', 'id_tipo' => $tipos['Columna Vertebral']],
            ['nombre' => 'Radiculopatía', 'id_tipo' => $tipos['Columna Vertebral']],

            ['nombre' => 'Omalgia', 'id_tipo' => $tipos['Extremidades']],
            ['nombre' => 'Gonalgia', 'id_tipo' => $tipos['Extremidades']],
            ['nombre' => 'Coxalgia', 'id_tipo' => $tipos['Extremidades']],
            ['nombre' => 'Epicondilalgia', 'id_tipo' => $tipos['Extremidades']],
            ['nombre' => 'Epitroclealgia', 'id_tipo' => $tipos['Extremidades']],
            ['nombre' => 'Talalgia', 'id_tipo' => $tipos['Extremidades']],
            ['nombre' => 'Metatarsalgia', 'id_tipo' => $tipos['Extremidades']],
            ['nombre' => 'Inestabilidad Articular', 'id_tipo' => $tipos['Extremidades']],

            ['nombre' => 'Parestesia', 'id_tipo' => $tipos['Neurológicos']],
            ['nombre' => 'Hipoestesia', 'id_tipo' => $tipos['Neurológicos']],
            ['nombre' => 'Paresia', 'id_tipo' => $tipos['Neurológicos']],
            ['nombre' => 'Espasticidad', 'id_tipo' => $tipos['Neurológicos']],
            ['nombre' => 'Vértigo / Mareo', 'id_tipo' => $tipos['Neurológicos']],
            ['nombre' => 'Ataxia', 'id_tipo' => $tipos['Neurológicos']],
            ['nombre' => 'Neuralgia', 'id_tipo' => $tipos['Neurológicos']],

            ['nombre' => 'Disnea', 'id_tipo' => $tipos['Respiratorios']],
            ['nombre' => 'Tos', 'id_tipo' => $tipos['Respiratorios']],
            ['nombre' => 'Sibilancias', 'id_tipo' => $tipos['Respiratorios']],
            ['nombre' => 'Tiraje', 'id_tipo' => $tipos['Respiratorios']],
            ['nombre' => 'Expectoración', 'id_tipo' => $tipos['Respiratorios']],
            ['nombre' => 'Taquipnea', 'id_tipo' => $tipos['Respiratorios']],

            ['nombre' => 'Edema', 'id_tipo' => $tipos['Sistémicos']],
            ['nombre' => 'Fatiga', 'id_tipo' => $tipos['Sistémicos']],
            ['nombre' => 'Inflamación', 'id_tipo' => $tipos['Sistémicos']],
            ['nombre' => 'Rigidez Matutina', 'id_tipo' => $tipos['Sistémicos']],
            ['nombre' => 'Mialgia', 'id_tipo' => $tipos['Sistémicos']],
            ['nombre' => 'Febrícula', 'id_tipo' => $tipos['Sistémicos']],

            ['nombre' => 'Hipercifosis', 'id_tipo' => $tipos['Posturales']],
            ['nombre' => 'Hiperlordosis', 'id_tipo' => $tipos['Posturales']],
            ['nombre' => 'Escoliosis Funcional', 'id_tipo' => $tipos['Posturales']],
            ['nombre' => 'Genu Valgo / Varo', 'id_tipo' => $tipos['Posturales']],
            ['nombre' => 'Protracción Escapular', 'id_tipo' => $tipos['Posturales']],
            ['nombre' => 'Descenso de Arco Plantar', 'id_tipo' => $tipos['Posturales']]
        ];

        foreach ($sintomas as $datos) {
            Sintoma::firstOrCreate(
                ['nombre' => $datos['nombre']],
                $datos
            );
        }
    }
}
