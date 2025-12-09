<?php

use App\Http\Controllers\ActividadController;
use App\Http\Controllers\ActividadPacienteController;
use App\Http\Controllers\ComboActividadController;
use App\Http\Controllers\NotaTurnoController;
use App\Http\Controllers\PacienteController;
use App\Http\Controllers\TurnoController;
use Illuminate\Support\Facades\Route;

Route::get('/', [TurnoController::class, 'paginaInicio']);
Route::get('/actividades/{id}/combos', [ActividadController::class, 'obtenerCombos']);
Route::get('/actividades/{id}/turnos-disponibles', [ActividadController::class, 'obtenerTurnosDisponibles']);
Route::get('/actividades-pacientes/registrar', [ActividadPacienteController::class, 'paginaCrear'])->name('actividades-pacientes.paginaCrear');
Route::post('/actividades-pacientes', [ActividadPacienteController::class, 'crear']);
Route::get('/combos-actividad/{id}/precio-actual', [ComboActividadController::class, 'obtenerPrecioActual']);
Route::get('/pacientes/registrar', [PacienteController::class, 'paginaCrear'])->name('pacientes.paginaCrear');
Route::post('/pacientes', [PacienteController::class, 'crearPaciente']);
Route::get('/pacientes', [PacienteController::class, 'paginaInicio']);
Route::get('/pacientes/{id}/actividades-generales-sin-suscripcion', [PacienteController::class, 'obtenerActividadesGeneralesSinSuscripcion']);
Route::get('/buscar-pacientes', [PacienteController::class, 'buscarPorApellidoNombre']);
Route::delete('/notas/{id}', [NotaTurnoController::class, 'eliminarNota']);
Route::post('/turnos/{id}/confirmar-asistencia', [TurnoController::class, 'confirmarAsistencia'])->name('turnos.confirmarAsistencia');
Route::post('/turnos/{idTurno}/notas', [NotaTurnoController::class, 'crearNota']);
Route::get('/turnos/{idTurno}/notas', [NotaTurnoController::class, 'obtenerNotasDesdeTurno']);
Route::get('/turnos/calendario', [TurnoController::class, 'paginaCalendario'])->name('turnos.calendario');
