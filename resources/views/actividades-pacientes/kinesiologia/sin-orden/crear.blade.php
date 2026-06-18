@extends('layouts.app')

@section('content')
    <div class="contenedor max-w-3xl">
        <form data-url="{{ route('actividades-pacientes.store') }}" data-url-pago="{{ route('actividades-pacientes.pagos.crear', ['id' => '__ID__']) }}" method="POST" class="formulario" id="formulario">
            @csrf

            <h2 class="titulo-formulario">Turnos kinesiología sin orden médica</h2>

            <div class="fila-formulario">

                <div class="columna-campo flex-1">
                    <x-buscador entidad="paciente" />
                </div>

                <div class="columna-campo flex-1">
                    <label for="actividad-select" class="etiqueta-formulario">Tratamiento a realizar</label>
                    <select class="entrada w-full" id="actividad-select" required>
                        <option value="" disabled selected>Seleccione el tratamiento</option>
                    </select>
                </div>

            </div>

            <div class="fila-formulario">

                <div class="columna-campo w-[40%]">
                    <label for="cantidad-select" class="etiqueta-formulario">Cantidad de sesiones</label>
                    <select id="cantidad-select" class="entrada" disabled required>
                        <option value="" disabled selected>Seleccione una cantidad</option>
                    </select>
                </div>

                <div class="columna-campo w-[40%]">
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

            <div class="mb-4 flex justify-around items-center border hidden" id="dias-container">
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

            <div class="mb-4 flex items-center border hidden" id="inicio-container">
                <div class="columna-campo flex-1">
                    <p class="text-lg font-medium">¿Arranca esta semana o la que viene?</p>

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
                    <select class="entrada" id="primer-turno-select" disabled required>
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
    @vite('resources/js/pages/actividades-pacientes/kinesiologia/sin-orden/crear.js')
@endpush
