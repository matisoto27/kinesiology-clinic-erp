<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PacienteController;
use App\Http\Controllers\TurnoController;

Route::get('/', [TurnoController::class, 'paginaInicio']);
Route::get('/pacientes', [PacienteController::class, 'paginaInicio']);
Route::get('/buscar-pacientes', [PacienteController::class, 'buscarPorApellidoNombre']);
Route::post('/turnos/{id}/confirmar-asistencia', [TurnoController::class, 'confirmarAsistencia'])->name('turnos.confirmarAsistencia');
