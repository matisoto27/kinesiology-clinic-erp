@extends('layouts.app')

@section('content')
    <div class="contenedor max-w-3xl">
        <form data-url="{{ route('actividades-pacientes.almacenar') }}" method="POST" class="formulario" id="formulario">
            @csrf

            <input type="hidden" name="paciente" id="id-paciente-input" required>

            <h2 class="titulo-formulario">Turnos kinesiología sin orden médica</h2>

            <div class="fila-formulario">

                <div class="columna-campo flex-1">

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

                <div class="columna-campo flex-1">
                    <label for="actividad-select" class="etiqueta-formulario">Tratamiento a realizar</label>
                    <select class="entrada w-full" id="actividad-select" required>
                        <option value="" disabled selected>Seleccione el tratamiento</option>
                    </select>
                </div>

            </div>

            <div class="fila-formulario">

                <div class="columna-campo w-[35%]">
                    <label class="etiqueta-formulario">Cantidad de sesiones</label>
                    <input type="number" class="entrada-deshabilitada" min="1" max="20" placeholder="Ingrese una cantidad" id="cantidad-input" disabled required />
                </div>

                <div class="columna-campo w-[45%]">
                    <label class="etiqueta-formulario">Frecuencia semanal de turnos</label>
                    <select class="entrada-deshabilitada" id="frecuencia-select" disabled required>
                        <option value="" disabled selected>Seleccione una frecuencia</option>
                    </select>
                </div>

                <div class="columna-campo w-[20%]">
                    <label class="etiqueta-formulario">Precio</label>
                    <input class="entrada-deshabilitada appearance-none" value="$0,00" id="precio-input" disabled>
                </div>

            </div>

            <div class="ultima-fila-formulario">
                <input type="checkbox" id="turnos-checkbox" checked>
                <label for="turnos-checkbox" class="etiqueta-formulario">Generar turnos automáticamente</label>
            </div>

            <div id="contenedor-turnos"></div>

            <button type="submit" class="boton-registrar">Registrar</button>

        </form>
    </div>
@endsection

@push('scripts')
    @vite('resources/js/pages/actividades-pacientes/kinesiologia/sin-orden/crear.js')
    <script src="https://kit.fontawesome.com/a186e728b7.js" crossorigin="anonymous"></script> <!-- Icono Lupa -->
@endpush
