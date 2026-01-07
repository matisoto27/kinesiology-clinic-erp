@extends('layouts.app')

@section('content')
    <div class="mx-auto py-5 max-w-4xl w-full">
        <form data-url="{{ route('actividades-pacientes.almacenar') }}" method="POST" class="bg-[#006E6B] rounded-xl shadow-lg p-8" id="formulario">
            @csrf

            <input type="hidden" name="paciente" id="id-paciente-input">

            <h2 class="mb-4 block font-semibold text-2xl text-center text-white">Registrar comienzo de actividad</h2>

            <div class="mb-4 flex text-white">

                <div class="flex flex-col gap-1">

                    <div class="flex gap-1">
                        <label class="font-medium text-lg">Paciente</label>
                        <button type="button" class="cursor-pointer hidden" id="eliminar-button">
                            <i class="align-middle fa-solid fa-xmark hover:text-red-900 text-red-600 text-xl"></i>
                        </button>
                    </div>

                    <div class="bg-[#3A8F8E] rounded-xl relative" id="nombre-div">
                        <i class="fa-solid fa-magnifying-glass ml-3 text-xl"></i>
                        <input type="text" placeholder="Ingrese el nombre" class="text-xl p-2 focus:outline-none" id="nombre-input" required>
                        <ul id="sugerencias" class="absolute left-0 right-0 max-h-60 overflow-auto z-10 hidden">
                            <!-- Pacientes sugeridos -->
                        </ul>
                    </div>

                </div>

            </div>

            <div class="mb-4 flex gap-5">

                <div class="flex flex-col gap-1">
                    <label class="font-medium text-lg text-white">Actividad</label>
                    <select class="bg-[#6BA9A9] rounded-md text-xl p-2 focus:outline-none focus:ring-2 focus:ring-[#F6BA00] focus:ring-offset-2 cursor-not-allowed text-[#E0F0F0]" id="actividad-select" disabled required>
                        <option value="" disabled selected>Seleccione una actividad</option>
                    </select>
                </div>

                <div class="flex flex-col gap-1">
                    <label class="font-medium text-lg text-white">Frecuencia semanal</label>
                    <select class="bg-[#6BA9A9] rounded-md text-xl p-2 focus:outline-none focus:ring-2 focus:ring-[#F6BA00] focus:ring-offset-2 cursor-not-allowed text-[#E0F0F0]" id="cantidad-select" disabled required>
                        <option value="" disabled selected>Seleccione una frecuencia</option>
                    </select>
                </div>

                <div class="flex flex-col gap-1">
                    <label class="font-medium text-lg text-white">Precio</label>
                    <input class="appearance-none bg-[#6BA9A9] cursor-not-allowed p-3 text-white" value="$0,00" id="precio-input" disabled>
                </div>

            </div>

            <div class="mb-4 flex gap-1 items-center">
                <input class="accent-[#F5D500] h-4 w-4" type="checkbox" id="turnos-checkbox" checked>
                <label class="font-medium text-lg text-white">Generar turnos automaticamente</label>
            </div>

            <div id="contenedor-turnos"></div>

            <button type="submit" class="mt-4 py-2 active:scale-95 bg-[#3A8F8E] cursor-pointer font-semibold text-white hover:bg-[#F5D500] hover:scale-105 hover:text-black transform transition-all ease-in-out duration-300 rounded-md w-full">Registrar</button>

        </form>

    </div>
@endsection

@push('scripts')
    @vite('resources/js/shared.js')
    @vite('resources/js/pages/actividades-pacientes/general/crear.js')
    <script src="https://kit.fontawesome.com/a186e728b7.js" crossorigin="anonymous"></script> <!-- Icono Lupa -->
@endpush
