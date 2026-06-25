@extends('layouts.app')

@section('content')
    <div class="contenedor max-w-4xl">
        <form data-url="{{ route('actividades-pacientes.store') }}" data-url-pago="{{ route('actividades-pacientes.pagos.crear', ['id' => '__ID__']) }}" method="POST" class="formulario" id="formulario">
            @csrf

            <h2 class="titulo-formulario">Registrar comienzo de actividad</h2>

            <div class="fila-formulario">
                <div class="columna-campo w-md">
                    <x-buscador entidad="paciente" />
                </div>
            </div>

            <div class="fila-formulario">

                <div class="columna-campo flex-1">
                    <label class="etiqueta-formulario">Actividad</label>
                    <select class="entrada" id="actividad-select" disabled required>
                        <option value="" disabled selected>Seleccione una actividad</option>
                    </select>
                </div>

                <div class="columna-campo flex-1">
                    <label class="etiqueta-formulario">Frecuencia semanal</label>
                    <select class="entrada" id="cantidad-select" disabled required>
                        <option value="" disabled selected>Seleccione una frecuencia</option>
                    </select>
                </div>

                <div class="columna-campo flex-1">
                    <label class="etiqueta-formulario">Precio</label>
                    <input class="entrada-info" value="$0,00" id="precio-input" disabled>
                </div>

            </div>

            <div class="ultima-fila-formulario">
                <input type="checkbox" class="checkbox-formulario" id="plan-dual-checkbox">
                <label for="plan-dual-checkbox" class="etiqueta-formulario">Inscripción Dual [Gym/Pilates]</label>
            </div>

            <div class="hidden mb-4 p-3 rounded bg-amber-500/20 text-white" id="plan-dual-banner"></div>

            <div class="ultima-fila-formulario">
                <input type="checkbox" class="checkbox-formulario" id="turnos-checkbox" checked>
                <label for="turnos-checkbox" class="etiqueta-formulario">Generar turnos automaticamente</label>
            </div>

            <div class="dias-container hidden" id="dias-container">
                <label>
                    <input type="checkbox" value="Lunes" disabled>
                    Lunes
                </label>

                <label>
                    <input type="checkbox" value="Martes" disabled>
                    Martes
                </label>

                <label>
                    <input type="checkbox" value="Miércoles" disabled>
                    Miércoles
                </label>

                <label>
                    <input type="checkbox" value="Jueves" disabled>
                    Jueves
                </label>

                <label>
                    <input type="checkbox" value="Viernes" disabled>
                    Viernes
                </label>
            </div>

            <div class="semanas-container hidden" id="inicio-container">
                <div class="columna-campo flex-1">
                    <p class="text-white text-lg font-medium">¿Arranca esta semana o la que viene?</p>

                    <label class="flex items-center gap-2">
                        <input type="radio" name="inicio" value="actual">
                        Semana actual
                    </label>

                    <label class="flex items-center gap-2">
                        <input type="radio" name="inicio" value="siguiente">
                        Semana que viene
                    </label>
                </div>

                <div class="columna-campo flex-1">
                    <label class="etiqueta-formulario">Primera clase</label>
                    <select class="primer-fecha-select" id="primer-turno-select" disabled required>
                        <option value="" disabled selected>Seleccione una fecha</option>
                    </select>
                </div>
            </div>

            <div id="turnos-container"></div>

            <button type="submit" class="boton-registrar">Registrar</button>

        </form>

    </div>
@endsection

@push('scripts')
    @vite('resources/js/pages/actividades-pacientes/general/crear.js')
@endpush
