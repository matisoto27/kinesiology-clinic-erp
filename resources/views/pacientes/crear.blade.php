@extends('layouts.app')

@push('styles')
    <style>
        [x-cloak] { display: none !important; }
    </style>
@endpush

@section('content')
    <div class="contenedor max-w-lg">
        <form action="{{ route('pacientes.almacenar') }}" method="POST" class="formulario" id="formulario">
            @csrf

            <h2 class="titulo-formulario">Registrar paciente</h2>

            @if (session('error'))
                <div class="mb-4 p-4 bg-red-100 border-l-4 border-red-500 text-red-700 font-bold shadow-md animate-fade-in">
                    <div class="flex items-start">
                        <div class="flex-shrink-0">
                            <svg class="w-6 h-6 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                            </svg>
                        </div>
                        <div class="flex-1 min-w-0">
                            <span class="block break-words font-bold">{{ session('error') }}</span>
                        </div>
                    </div>
                </div>
            @endif

            <div class="mb-4 grid grid-cols-1 gap-y-5">
                <x-input-formulario label="DNI" placeholder="Ingrese el DNI" name="dni" />

                <x-input-formulario label="Nombre" placeholder="Ingrese el nombre" name="nombre" />

                <x-input-formulario label="Apellido" placeholder="Ingrese el apellido" name="apellido" />

                <x-input-formulario label="Fecha de nacimiento" type="date" name="fecha_nac" />

                <x-input-formulario label="Domicilio" placeholder="Ejemplo: Pueyrredon 1586" name="domicilio" />

                <x-input-formulario label="Teléfono" placeholder="Ingrese el teléfono" name="telefono" />

                <x-select-formulario label="Profesión" opcionPorDefecto="Seleccione una profesión" name="profesion" :opciones="['Estudiante', 'Desempleado', 'Empleado', 'Ama de casa', 'Trabajo independiente', 'Jubilado/Pensionado']" />

                <x-select-formulario label="Actividad física" opcionPorDefecto="Seleccione una frecuencia" name="actividad_fisica" :opciones="['Sedentario', 'Ocasional', 'Moderada', 'Intensa', 'Alto rendimiento/Competencia']" />

                <div x-data="formularioPaciente(@js(old('es_adulto_mayor') === 'on' ? true : false), @js(old('vive_solo', 'on') === 'on' ? true : false), @js(old('contactos', [])))" class="space-y-5">
                    <div class="flex items-center gap-1">
                        <input type="checkbox" class="checkbox-formulario" name="es_adulto_mayor" id="checkbox-adulto-mayor" x-model="esAdulto">
                        <label for="checkbox-adulto-mayor" class="etiqueta-formulario">¿Es adulto mayor?</label>
                    </div>

                    <div x-show="esAdulto" class="space-y-5" x-transition x-cloak>
                        <div class="flex items-center gap-1">
                            <input type="checkbox" class="checkbox-formulario" name="vive_solo" id="checkbox-vive-solo" x-model="viveSolo">
                            <label for="checkbox-vive-solo" class="etiqueta-formulario">¿Vive solo?</label>
                        </div>

                        <div x-show="!viveSolo" x-transition x-cloak>
                            <x-input-formulario label="¿Con quién vive?" placeholder="Ejemplo: Juan (esposo), Mariana (hija)" name="vive_con" :required="false" />
                        </div>

                        <template x-for="(contacto, indice) in contactos" :key="indice">
                            <div class="mb-5 pb-5 border-[#F5D500] border-b" x-transition>
                                <div class="mb-4 flex items-center justify-between">
                                    <h3 class="font-medium text-[#F5D500] text-xl" x-text="'Contacto de emergencia ' + (indice + 1)"></h3>
                                    <button type="button" class="text-red-500 text-md hover:text-red-400" @click="contactos.splice(indice, 1)">Eliminar</button>
                                </div>

                                <x-input-dinamico label="Nombre" placeholder="Ingrese nombre del contacto" x-bind:name="`contactos[${indice}][nombre]`" x-model="contacto.nombre" />
                                <x-input-dinamico label="Teléfono" placeholder="Ingrese teléfono del contacto" x-bind:name="`contactos[${indice}][telefono]`" x-model="contacto.telefono" />
                                <x-select-dinamico label="Vínculo" opcionPorDefecto="¿Qué vínculo tiene con el paciente?" x-bind:name="`contactos[${indice}][vinculo]`" :opciones="['Hijo/a', 'Cónyuge', 'Hermano/a', 'Otro']" x-model="contacto.vinculo" />
                            </div>
                        </template>

                        <div x-show="contactos.length < 3" class="flex justify-center" x-transition>
                            <button type="button" @click="agregarContacto()" class="px-4 py-2 bg-blue-500 hover:bg-blue-700 text-white rounded">Añadir Contacto de Emergencia</button>
                        </div>

                        <div x-show="contactos.length >= 3" class="flex justify-center" x-transition x-cloak>
                            <p class="mt-2 text-red-500 text-sm">Has alcanzado el máximo de contactos de emergencia.</p>
                        </div>
                    </div>
                </div>

                <div class="flex flex-col gap-1">
                    <label class="etiqueta-formulario">¿Cuáles síntomas presenta el paciente? (Opcional)</label>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        @foreach ($tipos_sintomas as $tipo)
                            @if (!$tipo->sintomas->isEmpty())
                                <div class="bg-[#3A8F8E] p-4 rounded-md shadow-lg">

                                    <h3 class="mb-2 font-semibold text-xl text-white">{{ $tipo->nombre }}</h3>

                                    <div class="space-y-4">
                                        @foreach ($tipo->sintomas as $sintoma)
                                            <div class="flex items-center gap-2">
                                                <input
                                                    type="checkbox"
                                                    value="{{ $sintoma->id }}"
                                                    class="checkbox-formulario"
                                                    @checked(in_array($sintoma->id, old('sintomas', [])))
                                                    name="sintomas[]"
                                                    id="sintoma-{{ $sintoma->id }}"
                                                >
                                                <label for="sintoma-{{ $sintoma->id }}" class="text-white">{{ $sintoma->nombre }}</label>
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
            </div>

            <button type="submit" class="boton-registrar">Registrar</button>

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
    @vite('resources/js/pages/pacientes/crear.js')
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" crossorigin="anonymous" defer></script>
@endpush
