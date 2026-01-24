@extends('layouts.app')

@section('content')
    <div class="contenedor max-w-4xl">
        <form data-url="{{ route('actividades-pacientes.almacenar') }}" data-url-pago="{{ route('actividades-pacientes.pagos.crear', ['id' => '__ID__']) }}" method="POST" class="formulario" id="formulario">
            @csrf

            <h2 class="titulo-formulario">Registrar comienzo de actividad</h2>

            <div class="mb-4 flex flex-col w-[calc((100%-2.5rem)/3)]">
                <x-buscador entidad="paciente" />
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
    @vite('resources/js/pages/actividades-pacientes/general/crear.js')
@endpush
