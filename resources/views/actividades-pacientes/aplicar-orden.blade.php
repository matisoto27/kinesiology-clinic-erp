@extends('layouts.app')

@section('content')
    <div class="contenedor max-w-3xl">
        <form action="{{ route('actividades-pacientes.actualizar-orden-medica') }}" method="POST" class="formulario">
            @csrf

            <h2 class="titulo-formulario">Aplicar orden médica</h2>

            <div class="fila-formulario">
                <div class="columna-campo flex-1">
                    <label for="act-pac-select" class="etiqueta-formulario">Actividad del paciente</label>
                    <select class="entrada" name="id_act_pac" id="act-pac-select" required>
                        <option value="" disabled selected>Seleccione una opción</option>
                        @foreach($pendientesDePago as $inscripcion)
                            <option data-cant-sesiones="{{ $inscripcion->cant_sesiones }}" data-sesiones-a-favor="{{ $inscripcion->paciente->sesiones_a_favor }}" value="{{ $inscripcion->id }}">
                                [{{ $inscripcion->actividad->nombre }} {{ $inscripcion->cant_sesiones }} sesiones] ({{ $inscripcion->fecha_comienzo->format('d/m/Y') }})
                                {{ $inscripcion->paciente->apellido }}, {{ $inscripcion->paciente->nombre }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
            
            <div class="fila-formulario">

                <div class="columna-campo flex-1">
                    <h3 class="etiqueta-formulario">Fecha emisión órden médica</h3>
                    <div class="flex gap-2">
                        <select class="entrada flex-1" name="mes" id="mes-select" required>
                            <option value="" disabled selected>Seleccione un mes</option>
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
                        <select class="entrada-deshabilitada flex-1" name="dia" id="dia-select" disabled required>
                            <option value="" disabled selected>Seleccione un día</option>
                        </select>
                    </div>
                </div>

                <div class="columna-campo flex-1">
                    <label for="cantidad-select" class="etiqueta-formulario">Sesiones que cubre</label>
                    <select class="entrada w-full" name="sesiones_cubiertas" id="cantidad-select" required>
                        <option value="" disabled selected>Seleccione una cantidad</option>
                        <option value="5">5</option>
                        <option value="10">10</option>
                    </select>
                </div>

            </div>

            <div class="fila-formulario">
                <div class="columna-campo">
                    <label for="sesiones-input" class="etiqueta-formulario">Sesiones a favor del paciente luego de aplicar la orden</label>
                    <input class="entrada-deshabilitada rounded-none text-white appearance-none" value="-" id="sesiones-input" readonly required>
                </div>
            </div>

            <div class="fila-formulario p-4 text-yellow-800 border-t-4 border-yellow-300 bg-yellow-50 hidden" role="alert" id="contenedor-alerta">
                <svg class="flex-shrink-0 w-6 h-6 text-yellow-800" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 13V8m0 8h.01M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                </svg>
                <div id="texto-insuficiente" class="text-md leading-relaxed"></div>
            </div>

            <button type="submit" class="boton-registrar cursor-not-allowed opacity-50" id="boton-registrar" disabled>Aplicar orden médica</button>

            @if ($errors->any())
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mt-4">
                    <strong class="font-bold">¡Ups! Algo salió mal:</strong>
                    <ul class="mt-2">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

        </form>
    </div>
@endsection

@push('scripts')
    @vite('resources/js/pages/actividades-pacientes/aplicar-orden.js')
@endpush
