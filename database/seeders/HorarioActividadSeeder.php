<?php

namespace Database\Seeders;

use App\Models\Horario;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class HorarioActividadSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $horas = Horario::pluck('id', 'hora_inicio');

        $bloqueGym = ['07:00:00', '07:30:00', '08:00:00', '08:30:00', '09:00:00', '09:30:00', '10:00:00', '10:30:00', '11:00:00', '16:00:00', '16:30:00', '17:00:00', '17:30:00', '18:00:00', '18:30:00', '19:00:00'];
        $bloquePilates = ['07:00:00', '08:00:00', '09:00:00', '10:00:00', '11:00:00', '15:00:00', '16:00:00', '17:00:00', '18:00:00', '19:00:00'];
        $bloqueKinesio = ['08:00:00', '08:30:00', '09:00:00', '09:30:00', '10:00:00', '10:30:00', '11:00:00', '11:30:00', '16:00:00', '16:30:00', '17:00:00', '17:30:00', '18:00:00', '18:30:00', '19:00:00', '19:30:00'];

        $horariosActividades = [];

        foreach ($bloqueGym as $h) {
            $horariosActividades[] = ['id_horario' => $horas[$h], 'id_actividad' => 1];
        }

        foreach ($bloquePilates as $h) {
            $horariosActividades[] = ['id_horario' => $horas[$h], 'id_actividad' => 2];
        }

        for ($idAct = 3; $idAct <= 8; $idAct++) {
            foreach ($bloqueKinesio as $h) {
                $horariosActividades[] = ['id_horario' => $horas[$h], 'id_actividad' => $idAct];
            }
        }

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
