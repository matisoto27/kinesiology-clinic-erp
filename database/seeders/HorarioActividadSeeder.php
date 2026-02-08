<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class HorarioActividadSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $horas = DB::table('horarios')->pluck('id', 'hora_inicio');

        $horariosActividades = [
            // Gimnasio
            ['id_horario' => $horas['08:00:00'], 'id_actividad' => 1],
            ['id_horario' => $horas['08:30:00'], 'id_actividad' => 1],
            ['id_horario' => $horas['09:00:00'], 'id_actividad' => 1],
            ['id_horario' => $horas['09:30:00'], 'id_actividad' => 1],
            ['id_horario' => $horas['10:00:00'], 'id_actividad' => 1],
            ['id_horario' => $horas['10:30:00'], 'id_actividad' => 1],
            ['id_horario' => $horas['11:00:00'], 'id_actividad' => 1],
            ['id_horario' => $horas['11:30:00'], 'id_actividad' => 1],
            ['id_horario' => $horas['16:00:00'], 'id_actividad' => 1],
            ['id_horario' => $horas['16:30:00'], 'id_actividad' => 1],
            ['id_horario' => $horas['17:00:00'], 'id_actividad' => 1],
            ['id_horario' => $horas['17:30:00'], 'id_actividad' => 1],
            ['id_horario' => $horas['18:00:00'], 'id_actividad' => 1],
            ['id_horario' => $horas['18:30:00'], 'id_actividad' => 1],
            ['id_horario' => $horas['19:00:00'], 'id_actividad' => 1],
            // Pilates
            ['id_horario' => $horas['08:00:00'], 'id_actividad' => 2],
            ['id_horario' => $horas['09:00:00'], 'id_actividad' => 2],
            ['id_horario' => $horas['10:00:00'], 'id_actividad' => 2],
            ['id_horario' => $horas['11:00:00'], 'id_actividad' => 2],
            ['id_horario' => $horas['16:00:00'], 'id_actividad' => 2],
            ['id_horario' => $horas['17:00:00'], 'id_actividad' => 2],
            ['id_horario' => $horas['18:00:00'], 'id_actividad' => 2],
            ['id_horario' => $horas['19:00:00'], 'id_actividad' => 2],
            // Kinesiología convencional
            ['id_horario' => $horas['08:00:00'], 'id_actividad' => 3],
            ['id_horario' => $horas['08:30:00'], 'id_actividad' => 3],
            ['id_horario' => $horas['09:00:00'], 'id_actividad' => 3],
            ['id_horario' => $horas['09:30:00'], 'id_actividad' => 3],
            ['id_horario' => $horas['10:00:00'], 'id_actividad' => 3],
            ['id_horario' => $horas['10:30:00'], 'id_actividad' => 3],
            ['id_horario' => $horas['11:00:00'], 'id_actividad' => 3],
            ['id_horario' => $horas['11:30:00'], 'id_actividad' => 3],
            ['id_horario' => $horas['16:00:00'], 'id_actividad' => 3],
            ['id_horario' => $horas['16:30:00'], 'id_actividad' => 3],
            ['id_horario' => $horas['17:00:00'], 'id_actividad' => 3],
            ['id_horario' => $horas['17:30:00'], 'id_actividad' => 3],
            ['id_horario' => $horas['18:00:00'], 'id_actividad' => 3],
            ['id_horario' => $horas['18:30:00'], 'id_actividad' => 3],
            ['id_horario' => $horas['19:00:00'], 'id_actividad' => 3],
            ['id_horario' => $horas['19:30:00'], 'id_actividad' => 3]
        ];

        foreach ($horariosActividades as $horAct) {
            DB::table('horarios_actividades')->updateOrInsert(
                [
                    'id_horario' => $horAct['id_horario'],
                    'id_actividad' => $horAct['id_actividad']
                ],
                []
            );
        }
    }
}
