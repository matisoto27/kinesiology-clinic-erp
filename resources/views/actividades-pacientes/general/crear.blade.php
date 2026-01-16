@extends('layouts.app')

@section('content')
    <div class="contenedor max-w-4xl">
        <form data-url="{{ route('actividades-pacientes.almacenar') }}" method="POST" class="formulario" id="formulario">
            @csrf

            <input type="hidden" name="paciente" id="id-paciente-input">

            <h2 class="titulo-formulario">Registrar comienzo de actividad</h2>

            <div class="mb-4 flex flex-col w-[calc((100%-2.5rem)/3)]">

                <div class="flex items-center gap-1">
                    <label for="nombre-input" class="etiqueta-formulario">Paciente</label>
                    <button type="button" class="cursor-pointer hidden" id="eliminar-button">
                        <i class="fa-solid fa-xmark icono-eliminar"></i>
                    </button>
                </div>

                <div id="nombre-div">
                    <div class="flex items-center">
                        <i class="fa-solid fa-magnifying-glass icono-lupa"></i>
                        <input type="text" placeholder="Ingrese el nombre" id="nombre-input" required>
                    </div>
                    <ul class="hidden" id="sugerencias">
                        <!-- Pacientes sugeridos -->
                    </ul>
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
                <input type="checkbox" class="checkbox-formulario" id="turnos-checkbox" checked>
                <label for="turnos-checkbox" class="etiqueta-formulario">Generar turnos automaticamente</label>
            </div>

            <div id="contenedor-turnos"></div>

            <button type="submit" class="boton-registrar">Registrar</button>

        </form>

    </div>
@endsection

@push('scripts')
    @vite('resources/js/shared.js')
    @vite('resources/js/pages/actividades-pacientes/general/crear.js')
    <script src="https://kit.fontawesome.com/a186e728b7.js" crossorigin="anonymous"></script> <!-- Icono Lupa -->
@endpush
