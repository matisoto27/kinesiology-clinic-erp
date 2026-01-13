@extends('layouts.app')

@section('content')
    <div class="contenedor max-w-4xl">
        <form action="{{ route('pagos.almacenar') }}" method="POST" class="formulario">
            @csrf
            <input type="hidden" name="monto" id="monto-para-enviar">

            <h2 class="titulo-formulario">Pago de una actividad</h2>

            <div class="fila-formulario">
                <div class="columna-campo flex-1">
                    <label for="actpac-select" class="etiqueta-formulario">Actividad contratada</label>
                    <select class="entrada" name="id_act_pac" id="act-pac-select" required>
                        <option value="" disabled selected>Seleccione una opción</option>
                        @foreach($pendientesDePago as $inscripcion)
                            <option data-deuda="{{ $inscripcion->total_a_pagar - ($inscripcion->pagos_sum_monto ?? 0) }}" value="{{ $inscripcion->id }}" @selected($inscripcion->id == $idActPac)>
                                {{ $inscripcion->actividad->nombre }} ({{ $inscripcion->fecha_comienzo->format('d/m/Y') }}) -
                                {{ $inscripcion->paciente->apellido }}, {{ $inscripcion->paciente->nombre }}
                            </option>
                        @endforeach
                    </select>
                    <div class="flex font-semibold italic text-yellow-300 hidden" id="contenedor-deuda">
                        <p class="text-lg">Deuda total del paciente: $</p>
                        <p class="text-xl" id="deuda-texto"></p>
                    </div>
                </div>
                <div class="columna-campo flex-1">
                    <label for="profesional-select" class="etiqueta-formulario">Profesional que lo registra</label>
                    <select class="entrada" name="id_profesional" id="profesional-select" required>
                        <option value="" disabled selected>Seleccione un profesional</option>
                        @foreach($profesionales as $profesional)
                            <option value="{{ $profesional->id }}">
                                {{ $profesional->apellido }}, {{ $profesional->nombre }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="fila-formulario pb-4">
                <div class="columna-campo flex-1">
                    <label for="monto-input" class="etiqueta-formulario">Monto abonado</label>
                    <input type="text" placeholder="Ejemplo: 75000,42" class="entrada-deshabilitada" id="monto-input" disabled required>
                    <p class="hidden text-s text-red-500 mt-1 font-bold italic animate-bounce" id="alerta-exceso"></p>
                </div>
                <div class="columna-campo flex-1">
                    <label for="metodo-select" class="etiqueta-formulario">Método de pago</label>
                    <select class="entrada" name="metodo" id="metodo-select" required>
                        <option value="" disabled selected>Seleccione un método</option>
                        <option value="Efectivo">Efectivo</option>
                        <option value="Transferencia">Transferencia</option>
                    </select>
                </div>
            </div>

            <button type="submit" class="boton-registrar cursor-not-allowed opacity-50" id="boton-registrar" disabled>Registrar pago</button>

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
    @vite('resources/js/pages/pagos/crear.js')
@endpush
