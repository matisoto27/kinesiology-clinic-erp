@extends('layouts.app')

@section('content')
    <div class="contenedor max-w-3xl">
        <form data-url="{{ route('actividades-pacientes.almacenar') }}" data-url-pago="{{ route('actividades-pacientes.pagos.crear', ['id' => '__ID__']) }}" method="POST" class="formulario" id="formulario">
            @csrf

            <h2 class="titulo-formulario">Turnos kinesiología sin orden médica</h2>

            <div class="fila-formulario">

                <div class="columna-campo flex-1">
                    <x-buscador nombre="paciente" />
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
                    <input type="number" class="entrada" min="1" max="20" placeholder="Ingrese una cantidad" id="cantidad-input" disabled required />
                </div>

                <div class="columna-campo w-[45%]">
                    <label class="etiqueta-formulario">Frecuencia semanal de turnos</label>
                    <select class="entrada" id="frecuencia-select" disabled required>
                        <option value="" disabled selected>Seleccione una frecuencia</option>
                    </select>
                </div>

                <div class="columna-campo w-[20%]">
                    <label class="etiqueta-formulario">Precio</label>
                    <input class="entrada-info" value="$0,00" id="precio-input" disabled>
                </div>

            </div>

            <div class="ultima-fila-formulario">
                <input type="checkbox" class="checkbox-formulario" id="turnos-checkbox" checked>
                <label for="turnos-checkbox" class="etiqueta-formulario">Generar turnos automáticamente</label>
            </div>

            <div id="contenedor-turnos"></div>

            <button type="submit" class="boton-registrar">Registrar</button>

        </form>
    </div>
@endsection

@push('scripts')
    @vite('resources/js/pages/actividades-pacientes/kinesiologia/sin-orden/crear.js')
@endpush
