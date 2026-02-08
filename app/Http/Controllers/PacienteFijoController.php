<?php

namespace App\Http\Controllers;

use App\Models\ActividadPaciente;
use App\Models\PacienteFijo;

class PacienteFijoController extends Controller
{
    public function crear()
    {
        $inscripciones = ActividadPaciente::select('id', 'id_actividad', 'id_paciente', 'fecha_comienzo', 'cant_sesiones')
            ->with([
                'actividad' => function ($consulta) {
                    $consulta->select('id', 'nombre')->with('horarios:id,hora_inicio');
                },
                'paciente:id,nombre,apellido',
                'ultimoTurno:turnos.id,turnos.id_act_pac,turnos.fecha_hora'
            ])
            ->noFijos()
            ->get();

        return view('pacientes-fijos.crear', compact('inscripciones'));
    }

    public function inicio()
    {
        $pacientesFijos = PacienteFijo::select('id', 'id_actividad', 'id_paciente', 'activo')
            ->with([
                'actividad:id,nombre',
                'paciente:id,nombre,apellido',
                'horarios'
            ])
            ->get();

        return view('pacientes-fijos.inicio', compact('pacientesFijos'));
    }
}
