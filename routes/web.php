<?php

use App\Http\Controllers\ActividadComboController;
use App\Http\Controllers\ActividadController;
use App\Http\Controllers\ActividadPacienteController;
use App\Http\Controllers\NotaTurnoController;
use App\Http\Controllers\PacienteController;
use App\Http\Controllers\PagoController;
use App\Http\Controllers\TurnoController;
use Illuminate\Support\Facades\Route;

Route::get('/', [TurnoController::class, 'paginaInicio']);

Route::get('/actividades', [ActividadController::class, 'index']);
Route::get('/actividades/{id}/combos', [ActividadController::class, 'obtenerCombos']);
Route::get('/actividades/{id}/turnos-disponibles', [ActividadController::class, 'obtenerTurnosDisponibles']);

Route::get('/actividades-combos/{id}/precio-actual', [ActividadComboController::class, 'obtenerPrecioActual']);

Route::get('/actividades-pacientes/general/crear', [ActividadPacienteController::class, 'crearGeneral'])
    ->name('actividades-pacientes.general.crear');
Route::get('/actividades-pacientes/kinesiologia/orden/crear', [ActividadPacienteController::class, 'crearKinesiologiaConOrden'])
    ->name('actividades-pacientes.kinesiologia-orden.crear');
Route::get('/actividades-pacientes/kinesiologia/sin-orden/crear', [ActividadPacienteController::class, 'crearKinesiologiaSinOrden'])
    ->name('actividades-pacientes.kinesiologia-sin-orden.crear');
Route::post('/actividades-pacientes', [ActividadPacienteController::class, 'almacenar'])
    ->name('actividades-pacientes.almacenar');

Route::get('/pacientes/registrar', [PacienteController::class, 'paginaCrear'])->name('pacientes.paginaCrear');
Route::post('/pacientes', [PacienteController::class, 'crearPaciente']);
Route::get('/pacientes', [PacienteController::class, 'paginaInicio']);
Route::get('/pacientes/{id}/actividades-generales-sin-suscripcion', [PacienteController::class, 'obtenerActividadesGeneralesSinSuscripcion']);
Route::get('/buscar-pacientes', [PacienteController::class, 'buscarPorApellidoNombre']);
Route::delete('/notas/{id}', [NotaTurnoController::class, 'eliminarNota']);

Route::controller(PagoController::class)->group(function () {
    Route::get('/pagos/crear', 'crear')->name('pagos.crear');
    Route::post('/pagos', 'almacenar')->name('pagos.almacenar');
});

Route::post('/turnos/{id}/confirmar-asistencia', [TurnoController::class, 'confirmarAsistencia'])->name('turnos.confirmarAsistencia');
Route::post('/turnos/{idTurno}/notas', [NotaTurnoController::class, 'crearNota']);
Route::get('/turnos/{idTurno}/notas', [NotaTurnoController::class, 'obtenerNotasDesdeTurno']);
Route::get('/turnos/calendario', [TurnoController::class, 'paginaCalendario'])->name('turnos.calendario');
