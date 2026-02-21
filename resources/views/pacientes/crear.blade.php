@extends('layouts.app')

@section('content')
    <div class="contenedor max-w-lg">
        <form action="{{ route('pacientes.almacenar') }}" method="POST" class="formulario" id="formulario">
            @csrf

            <x-alerta tipo="error" />

            <h2 class="titulo-formulario">Registrar paciente</h2>

            <div class="mb-4 grid grid-cols-1 gap-y-5">
                <x-input-formulario label="DNI" placeholder="Ingrese el DNI" name="dni" />

                <x-input-formulario label="Nombre" placeholder="Ingrese el nombre" name="nombre" />

                <x-input-formulario label="Apellido" placeholder="Ingrese el apellido" name="apellido" />

                <x-input-formulario label="Fecha de nacimiento" type="date" name="fecha_nac" />

                <x-input-formulario label="Domicilio" placeholder="Ejemplo: Pueyrredon 1586" name="domicilio" />

                <x-input-formulario label="Teléfono" placeholder="Ingrese el teléfono" name="telefono" />

                <x-select-formulario label="Profesión" opcionPorDefecto="Seleccione una profesión" name="profesion" :opciones="['Estudiante', 'Desempleado', 'Empleado', 'Ama de casa', 'Trabajo independiente', 'Jubilado/Pensionado']" />

                <x-select-formulario label="Actividad física" opcionPorDefecto="Seleccione una frecuencia" name="actividad_fisica" :opciones="['Sedentario', 'Ocasional', 'Moderada', 'Intensa', 'Alto rendimiento/Competencia']" />

                <livewire:pacientes.formulario-contactos />

                <div class="flex flex-col gap-1">
                    <h3 class="mb-2 text-white text-xl font-semibold">Patologías (Opcional)</h3>

                    <div class="space-y-4">
                        @foreach ($todasPatologias as $pat)
                            <div class="flex items-center gap-2">
                                <input
                                    id="patologia-{{ $pat->id }}"
                                    name="patologias[]"
                                    type="checkbox"
                                    class="checkbox-formulario"
                                    value="{{ $pat->id }}"
                                    @checked(in_array($pat->id, old('patologias', [])))
                                >
                                <label for="patologia-{{ $pat->id }}" class="text-white">{{ $pat->nombre }}</label>
                            </div>
                        @endforeach
                    </div>
                </div>
                @error('patologias')
                    <div class="text-red-500 text-md">{{ $message }}</div>
                @enderror
                @error('patologias.*')
                    <div class="text-red-500 text-md">{{ $message }}</div>
                @enderror

                <div class="flex flex-col gap-1">
                    <label class="etiqueta-formulario">¿Cuáles síntomas presenta el paciente? (Opcional)</label>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        @foreach ($tiposSintoma as $tipo)
                            @if (!$tipo->sintomasActivos->isEmpty())
                                <div class="bg-[#3A8F8E] p-4 rounded-md shadow-lg">

                                    <h3 class="mb-2 font-semibold text-xl text-white">{{ $tipo->nombre }}</h3>

                                    <div class="space-y-4">
                                        @foreach ($tipo->sintomasActivos as $sintoma)
                                            <div class="flex items-center gap-2">
                                                <input
                                                    type="checkbox"
                                                    value="{{ $sintoma->id }}"
                                                    class="checkbox-formulario"
                                                    name="sintomas[]"
                                                    id="sintoma-{{ $sintoma->id }}"
                                                    @checked(in_array($sintoma->id, old('sintomas', [])))
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
                @error('sintomas.*')
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
