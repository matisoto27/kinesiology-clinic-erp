@extends('layouts.app')

@section('content')
    <div class="contenedor max-w-3xl">
        <form data-url="{{ route('actividades-pacientes.almacenar') }}" method="POST" id="formulario">
            @csrf

            <input type="hidden" name="paciente" id="id-paciente-input">

            <h2 class="titulo-formulario">Turnos kinesiología con orden médica</h2>

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

                <div class="columna-campo flex-1">

                    <h3 class="etiqueta-formulario">Fecha emisión órden médica</h3>

                    <div class="flex gap-2">

                        <select class="entrada flex-1" id="mes-select" required>
                            <option value="" disabled selected>Seleccione mes</option>
                            <option value="1">Enero</option>
                            <option value="2">Febrero</option>
                            <option value="3">Marzo</option>
                            <option value="4">Abril</option>
                            <option value="5">Mayo</option>
                            <option value="6">Junio</option>
                            <option value="7">Julio</option>
                            <option value="8">Agosto</option>
                            <option value="9">Septiembre</option>
                            <option value="10">Octubre</option>
                            <option value="11">Noviembre</option>
                            <option value="12">Diciembre</option>
                        </select>

                        <select class="entrada-deshabilitada flex-1" id="dia-select" disabled required>
                            <option value="" disabled selected>Seleccione día</option>
                        </select>

                    </div>

                </div>

                <div class="columna-campo flex-1">
                    <label for="cantidad-select" class="etiqueta-formulario">Sesiones que cubre</label>
                    <select class="entrada w-full" id="cantidad-select" required>
                        <option value="" disabled selected>Seleccione una cantidad</option>
                        <option value="5">5</option>
                        <option value="10">10</option>
                    </select>
                </div>

            </div>

            <div class="mb-4 flex flex-col w-[calc((100%-2.5rem)/2)]">

                <label for="frecuencia-select" class="etiqueta-formulario">Frecuencia semanal de turnos</label>

                <select class="entrada-deshabilitada" id="frecuencia-select" disabled required>
                    <option value="" disabled selected>Seleccione una frecuencia</option>
                    <option value="1">1 vez por semana</option>
                    <option value="2">2 veces por semana</option>
                    <option value="3">3 veces por semana</option>
                    <option value="4">4 veces por semana</option>
                    <option value="5">5 veces por semana</option>
                </select>

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
    @vite('resources/js/pages/actividades-pacientes/kinesiologia/crear.js')
    <script src="https://kit.fontawesome.com/a186e728b7.js" crossorigin="anonymous"></script> <!-- Icono Lupa -->
@endpush
