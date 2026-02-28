@extends('layouts.app')

@section('content')
    <div class="contenedor max-w-3xl">
        <form action="{{ route('precios.almacenar') }}" method="POST" class="formulario" id="formulario">
            @csrf
            <input type="hidden" name="valor" id="valor-para-enviar" required>

            <h2 class="titulo-formulario">Actualizar precio de un combo</h2>

            <div class="fila-formulario">
                <div class="columna-campo">
                    <label for="combo-select" class="etiqueta-formulario">Combo</label>
                    <select class="entrada" name="id_actividad_combo" id="actcom-select" required>
                        <option value="" disabled @selected(old('id_actividad_combo') === null)>Seleccione un combo</option>
                        @foreach($actividadesCombos as $actCom)
                            <option data-precio="{{ $actCom->precio_vigente }}" value="{{ $actCom->id }}" @selected($actCom->id == old('id_actividad_combo'))>
                                {{ $actCom->actividad->nombre }} - {{ $actCom->combo->nombre }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="fila-formulario">
                <div class="columna-campo flex-1">
                    <label for="precio-vigente-input" class="etiqueta-formulario">Precio vigente ($)</label>
                    <input type="text" value="-" class="entrada-info" id="precio-vigente-input" disabled>
                </div>

                <div class="columna-campo flex-1">
                    <label for="nuevo-precio-input" class="etiqueta-formulario">Nuevo precio ($)</label>
                    <input type="text" placeholder="Ejemplo: 75000,00" class="entrada" id="nuevo-precio-input" required>
                    <p class="alerta hidden" id="texto-alerta"></p>
                </div>
            </div>

            <button type="submit" class="boton-registrar" id="boton-registrar" disabled>Actualizar precio</button>

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
    @vite('resources/js/pages/precios/crear.js')
@endpush
