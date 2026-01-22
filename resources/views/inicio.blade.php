@php use Carbon\Carbon; @endphp

@extends('layouts.app')

@section('content')
    <div class="contenedor bg-[#006E6B] max-w-screen-lg my-5 px-8 rounded-3xl">

        <div class="mb-4 flex justify-between text-white">
            <div class="font-bold text-3xl">
                <h2>Asistencia de hoy</h2>
                <p id="fecha">{{ Carbon::now()->format('d/m/Y') }}</p>
            </div>
            <p class="text-6xl" id="hora-actual">{{ Carbon::now()->format('H:i') }}</p>
        </div>
        
        <form method="GET" id="filtros-form">

            <input type="hidden" value="{{ $paciente->id ?? 0 }}" name="paciente" id="id-paciente-input">

            <div class="mb-4 flex justify-between">

                <div class="columna-campo">
                    <x-buscador nombre="paciente" :seleccionado="$paciente ? $paciente->apellido . ' ' . $paciente->nombre : null" />
                </div>

                <div class="columna-campo">
                    <label for="actividad-select" class="etiqueta-formulario">Actividad</label>
                    <select class="entrada" name="actividad" id="actividad-select">
                        <option value="0">Todas</option>
                        @foreach($actividades as $act)
                            <option value="{{ $act->id }}" @if ($act->id == request('actividad', 0)) selected @endif>
                                {{ $act->nombre }}
                            </option>
                        @endforeach
                    </select>
                </div>

            </div>

        </form>

        <table class="my-5 overflow-hidden rounded-xl w-full">

            <thead class="bg-[#014745] text-white">
                <tr>
                    <th class="w-2/11 text-center py-3">Hora de ingreso</th>
                    <th class="w-3/11 text-center py-3">Paciente</th>
                    <th class="w-3/11 text-center py-3">Actividad</th>
                    <th class="w-3/11 text-center py-3">Asistencia</th>
                </tr>
            </thead>

            <tbody class="bg-white" id="turnos-tbody">
                @if($turnos->count())
                    @foreach($turnos as $turno)
                        <tr class="border-b last:border-b-0">
                            <td class="w-2/11 text-center py-3">{{ $turno->fecha_hora->format('H:i') }}</td>
                            <td class="w-3/11 text-center py-3">{{ $turno->actividadPaciente->paciente->apellido . ' ' . $turno->actividadPaciente->paciente->nombre }}</td>
                            <td class="w-3/11 text-center py-3">{{ $turno->actividadPaciente->actividad->nombre }}</td>
                            <td class="w-3/11 text-center py-3">
                                @if($turno->asiste)
                                    <button class="bg-green-300 text-black py-2 px-4 rounded-full transition-colors" disabled>Confirmada</button>
                                @else
                                    <button class="turno-button py-2 px-4 rounded-full transition-colors bg-[#F5D500]" data-url="{{ route('turnos.confirmar-asistencia', $turno->id) }}">Confirmar</button>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                @else
                    <tr>
                        <td colspan="4" class="text-center text-lg py-4">No encontramos turnos para la actividad y paciente seleccionados.</td>
                    </tr>
                @endif
            </tbody>

        </table>

        {{ $turnos->links() }}

        @if (session('exito'))
            <div class="mb-4 p-4 bg-green-100 border-l-4 border-green-500 text-green-700 font-bold shadow-md animate-fade-in">
                <div class="flex items-start">
                    <div class="flex-shrink-0">
                        <svg class="w-6 h-6 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                        </svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <span class="block break-words font-bold">{{ session('exito') }}</span>
                    </div>
                </div>
            </div>
        @endif

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

    </div>
@endsection

@push('scripts')
    @vite('resources/js/pages/inicio.js')
@endpush
