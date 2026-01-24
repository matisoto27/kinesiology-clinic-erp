@extends('layouts.app')

@section('content')
    <div class="contenedor max-w-sm">
        <form action="{{ route('obras-sociales-pacientes.almacenar') }}" method="POST" class="formulario">
            @csrf

            <h2 class="titulo-formulario">Actualizar obra social de un paciente</h2>

            <div class="fila-formulario">
                <div class="columna-campo flex-1">
                    <x-buscador entidad="paciente" />
                </div>
            </div>

            <div class="fila-formulario mb-10">
                <div class="columna-campo flex-1">
                    <x-buscador entidad="obra-social" :deshabilitado="true" />
                </div>
            </div>

            <button type="submit" class="boton-registrar" id="boton-registrar" disabled>Actualizar</button>

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
    @vite('resources/js/pages/obras-sociales-pacientes/crear.js')
@endpush
