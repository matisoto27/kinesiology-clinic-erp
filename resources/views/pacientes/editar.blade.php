@extends('layouts.app')

@section('content')
    <div class="contenedor max-w-lg">
        <form action="{{ route('pacientes.actualizar', $paciente) }}" method="POST" class="formulario">
            @csrf
            @method('PUT')

            <h2 class="titulo-formulario">Editar información del paciente</h2>

            <div class="mb-4 grid grid-cols-1 gap-y-5">
                <x-input-formulario label="ID" :value="$paciente->id" name="id" :disabled="true" />

                <x-input-formulario label="DNI" placeholder="Ingrese el DNI" :value="$paciente->dni" name="dni" :disabled="true" />

                <x-input-formulario label="Nombre" placeholder="Ingrese el nombre" :value="$paciente->nombre" name="nombre" />

                <x-input-formulario label="Apellido" placeholder="Ingrese el apellido" :value="$paciente->apellido" name="apellido" />

                <x-input-formulario label="Fecha de nacimiento" type="date" :value="$paciente->fecha_nac->format('Y-m-d')" name="fecha_nac" />

                <x-input-formulario label="Domicilio" placeholder="Ejemplo: Pueyrredon 1586" :value="$paciente->domicilio" name="domicilio" />

                <x-input-formulario label="Teléfono" placeholder="Ingrese el teléfono" :value="$paciente->telefono" name="telefono" />

                <x-select-formulario
                    label="Profesión"
                    opcionPorDefecto="Seleccione una profesión"
                    :value="$paciente->profesion"
                    name="profesion"
                    :opciones="['Estudiante', 'Desempleado', 'Empleado', 'Ama de casa', 'Trabajo independiente', 'Jubilado/Pensionado']"
                />

                <x-select-formulario
                    label="Actividad física"
                    opcionPorDefecto="Seleccione una frecuencia"
                    :value="$paciente->actividad_fisica"
                    name="actividad_fisica"
                    :opciones="['Sedentario', 'Ocasional', 'Moderada', 'Intensa', 'Alto rendimiento/Competencia']"
                />

                <livewire:pacientes.formulario-contactos
                    :esAdultoInicial="$esAdultoMayor"
                    :viveSoloInicial="$viveSolo"
                    :contactosInicial="$contactos"
                    :pacienteInicial="$paciente"
                />

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
                                                    name="sintomas[]"
                                                    id="sintoma-{{ $sintoma->id }}"
                                                    @checked(in_array($sintoma->id, $sintomas))
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

            <button type="submit" class="mt-4 boton-registrar">Actualizar</button>

            @if (session('error'))
                <div class="mt-4 px-4 py-3 bg-red-100 border-red-400 border text-red-700 rounded relative" role="alert">
                    <strong class="font-bold">¡Ups! Algo salió mal</strong>
                    <span class="block">{{ session('error') }}</span>
                </div>
            @endif

        </form>
    </div>
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" crossorigin="anonymous" defer></script>
@endpush
