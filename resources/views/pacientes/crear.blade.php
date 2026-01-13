@extends('layouts.app')

@section('content')
    <div class="w-full max-w-lg mx-auto my-5">

        <form method="POST" action="/pacientes" class="formulario" id="formulario">

            @csrf

            <h1 class="mb-4 block font-semibold text-3xl text-center text-white">Registrar paciente</h1>

            <div class="mb-4">
                <label class="mb-2 block font-medium text-lg text-white" for="dni">DNI</label>
                <input type="text" class="bg-[#3A8F8E] px-3 py-2 rounded text-white w-full" placeholder="Ingrese el DNI" value="{{ old('dni') }}" name="dni" id="dni">
                @error('dni')
                    <div class="text-red-500 text-md">{{ $message }}</div>
                @enderror
            </div>

            <div class="mb-4">
                <label class="mb-2 block font-medium text-lg text-white" for="nombre">Nombre</label>
                <input type="text" class="bg-[#3A8F8E] px-3 py-2 rounded text-white w-full" placeholder="Ingrese el nombre" value="{{ old('nombre') }}" name="nombre" id="nombre">
                @error('nombre')
                    <div class="text-red-500 text-md">{{ $message }}</div>
                @enderror
            </div>

            <div class="mb-4">
                <label class="mb-2 block font-medium text-lg text-white" for="apellido">Apellido</label>
                <input type="text" class="bg-[#3A8F8E] px-3 py-2 rounded text-white w-full" placeholder="Ingrese el apellido" value="{{ old('apellido') }}" name="apellido" id="apellido">
                @error('apellido')
                    <div class="text-red-500 text-md">{{ $message }}</div>
                @enderror
            </div>

            <div class="mb-4">
                <label class="mb-2 block font-medium text-lg text-white" for="fecha-nacimiento">Fecha de nacimiento</label>
                <input type="date" class="bg-[#3A8F8E] px-3 py-2 rounded text-white w-full" value="{{ old('fecha_nac') }}" name="fecha_nac" id="fecha-nacimiento">
                @error('fecha_nac')
                    <div class="text-red-500 text-md">{{ $message }}</div>
                @enderror
            </div>

            <div class="mb-4">
                <label class="mb-2 block font-medium text-lg text-white" for="telefono">Teléfono</label>
                <input type="text" class="bg-[#3A8F8E] px-3 py-2 rounded text-white w-full" placeholder="Ingrese el teléfono" value="{{ old('telefono') }}" name="telefono" id="telefono">
                @error('telefono')
                    <div class="text-red-500 text-md">{{ $message }}</div>
                @enderror
            </div>

            <div class="mb-8">
                <label class="mb-2 block font-medium text-lg text-white">¿Cuáles síntomas presenta el paciente? (Opcional)</label>
                <div class="flex flex-wrap gap-[8px]">
                    @foreach ($tipos_sintomas as $tipo)
                        @if (!$tipo->sintomas->isEmpty())
                            <div class="w-full bg-[#3A8F8E] sm:w-[calc((100%_-_8px)/2)] p-4 rounded-md">

                                <h3 class="text-white font-semibold text-xl mb-2">{{ $tipo->nombre }}</h3>

                                <div class="space-y-4">
                                    @foreach ($tipo->sintomas as $sintoma)
                                        <div class="flex items-center gap-2">
                                            <input type="checkbox" class="accent-[#F5D500] h-4 w-4" value="{{ $sintoma->id }}" name="sintomas[]" id="{{ $sintoma->id }}">
                                            <label for="{{ $sintoma->id }}" class="text-white">{{ $sintoma->nombre }}</label>
                                        </div>
                                    @endforeach
                                </div>

                            </div>
                        @endif
                    @endforeach
                </div>
            </div>
            @error('sintomas')
                <div class="text-red-500 text-md">{{ $message }}</div>
            @enderror

            <button type="submit" class="py-2 active:scale-95 bg-[#3A8F8E] cursor-pointer font-semibold text-white hover:bg-[#F5D500] hover:scale-105 hover:text-black transform transition-all ease-in-out duration-300 rounded-md w-full">Registrar</button>

        </form>

    </div>

    @if(session('titulo') && session('mensaje'))
        <div class="fixed inset-0 bg-black/30 backdrop-blur-sm flex justify-center items-center z-100" id="modal-exito">
            <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg p-6 relative transform transition-all duration-300 ease-out scale-95 hover:scale-100">
                <h2 class="text-2xl font-semibold text-gray-800 mb-4">{{ session('titulo') }}</h2>
                <p class="text-lg text-gray-700">{{ session('mensaje') }}</p>
                <div class="mt-6 flex justify-end">
                    <button class="px-4 py-2 bg-blue-600 hover:bg-blue-700 rounded-lg text-white transition" id="volver-button">Cerrar</button>
                </div>
            </div>
        </div>
    @endif

    @if(session('error'))
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                Swal.fire({
                    icon: 'error',
                    title: '¡Error!',
                    text: "{{ session('error') }}",
                    confirmButtonText: 'Aceptar'
                });
            });
        </script>
    @endif

@endsection

@push('scripts')
    @vite('resources/js/pages/pacientes/crear.js')
@endpush
