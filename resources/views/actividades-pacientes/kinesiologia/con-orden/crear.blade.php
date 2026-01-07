@extends('layouts.app')

@section('content')
    <div class="w-full max-w-3xl mx-auto py-5">
        <form data-url="{{ route('actividades-pacientes.almacenar') }}" method="POST" class="bg-[#006E6B] rounded-xl shadow-lg p-8" id="formulario">
            @csrf

            <input type="hidden" name="paciente" id="id-paciente-input">

            <h2 class="mb-4 block font-semibold text-2xl text-center text-white">Turnos kinesiología con orden médica</h2>

            <div class="mb-4 flex gap-5 text-white">
                
                <div class="flex-1 flex flex-col gap-1">
                    
                    <div class="flex items-center gap-1">
                        <label for="nombre-input" class="font-medium text-lg">Paciente</label>
                        <button type="button" class="cursor-pointer hidden" id="eliminar-button">
                            <i class="align-middle fa-solid fa-xmark hover:text-red-900 text-red-600 text-xl"></i>
                        </button>
                    </div>

                    <div class="bg-[#3A8F8E] rounded-xl relative" id="nombre-div">
                        <div class="flex items-center">
                            <i class="fa-solid fa-magnifying-glass ml-3 text-xl"></i>
                            <input type="text" placeholder="Ingrese el nombre" class="p-2 text-xl focus:outline-none w-full" id="nombre-input" required>
                        </div>
                        <ul id="sugerencias" class="absolute left-0 right-0 max-h-60 overflow-auto z-10 hidden">
                            <!-- Pacientes sugeridos -->
                        </ul>
                    </div>
                </div>

                <div class="flex-1 flex flex-col gap-1">
                    <label for="actividad-select" class="font-medium text-lg">Tratamiento a realizar</label>
                    <select class="bg-[#3A8F8E] p-2 rounded-xl text-xl w-full" id="actividad-select" required>
                        <option value="" disabled selected>Seleccione el tratamiento</option>
                    </select>
                </div>

            </div>

            <div class="mb-4 flex gap-5">

                <div class="flex-1 flex flex-col gap-1">

                    <h1 class="font-medium text-xl text-white">Fecha emisión órden médica</h1>

                    <div class="flex gap-2">

                        <select class="flex-1 bg-[#3A8F8E] p-2 rounded-xl text-xl text-white" id="mes-select" required>
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

                        <select class="flex-1 bg-[#6BA9A9] cursor-not-allowed text-[#E0F0F0] p-2 rounded-xl text-xl" id="dia-select" disabled required>
                            <option value="" disabled selected>Seleccione día</option>
                        </select>

                    </div>

                </div>

                <div class="flex-1 flex flex-col gap-1">
                    <label for="cantidad-select" class="font-medium text-lg text-white">Sesiones que cubre</label>
                    <select class="bg-[#3A8F8E] p-2 rounded-xl text-xl text-white w-full" id="cantidad-select" required>
                        <option value="" disabled selected>Seleccione una cantidad</option>
                        <option value="5">5</option>
                        <option value="10">10</option>
                    </select>
                </div>

            </div>

            <div class="mb-4 flex gap-5">

                <div class="flex flex-col gap-1 w-2/4">
                    <label for="frecuencia-select" class="font-medium text-lg text-white">Frecuencia semanal de turnos</label>
                    <select class="bg-[#6BA9A9] cursor-not-allowed text-[#E0F0F0] p-2 rounded-xl text-xl" id="frecuencia-select" disabled required>
                        <option value="" disabled selected>Seleccione una frecuencia</option>
                        <option value="1">1 vez por semana</option>
                        <option value="2">2 veces por semana</option>
                        <option value="3">3 veces por semana</option>
                        <option value="4">4 veces por semana</option>
                        <option value="5">5 veces por semana</option>
                    </select>
                </div>

                <div class="w-2/4"></div>

            </div>

            <div class="mb-4 flex items-center gap-1">
                <input class="accent-[#F5D500] h-4 w-4" type="checkbox" id="turnos-checkbox" checked>
                <label class="font-medium text-lg text-white">Generar turnos automáticamente</label>
            </div>

            <div id="contenedor-turnos"></div>

            <button type="submit" class="py-2 active:scale-95 bg-[#3A8F8E] cursor-pointer font-semibold text-white hover:bg-[#F5D500] hover:scale-105 hover:text-black transform transition-all ease-in-out duration-300 rounded-md w-full">Registrar</button>

        </form>
    </div>
@endsection

@push('scripts')
    @vite('resources/js/pages/actividades-pacientes/kinesiologia/crear.js')
    <script src="https://kit.fontawesome.com/a186e728b7.js" crossorigin="anonymous"></script> <!-- Icono Lupa -->
@endpush
