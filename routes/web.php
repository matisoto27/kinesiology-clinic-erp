<?php

use App\Http\Controllers\NotaTurnoController;
use App\Http\Controllers\PacienteController;
use App\Http\Controllers\TurnoController;
use Illuminate\Support\Facades\Route;

Route::get('/', [TurnoController::class, 'paginaInicio']);
Route::get('/pacientes/registrar', [PacienteController::class, 'paginaCrear'])->name('pacientes.paginaCrear');
Route::post('/pacientes', [PacienteController::class, 'crearPaciente']);
Route::get('/pacientes', [PacienteController::class, 'paginaInicio']);
Route::get('/buscar-pacientes', [PacienteController::class, 'buscarPorApellidoNombre']);
Route::delete('/notas/{id}', [NotaTurnoController::class, 'eliminarNota']);
Route::post('/turnos/{id}/confirmar-asistencia', [TurnoController::class, 'confirmarAsistencia'])->name('turnos.confirmarAsistencia');
Route::post('/turnos/{idTurno}/notas', [NotaTurnoController::class, 'crearNota']);
Route::get('/turnos/{idTurno}/notas', [NotaTurnoController::class, 'obtenerNotasDesdeTurno']);
Route::get('/turnos/calendario', [TurnoController::class, 'paginaCalendario'])->name('turnos.calendario');
