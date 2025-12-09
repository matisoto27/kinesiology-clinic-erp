@php use Carbon\Carbon; @endphp

@extends('layouts.app')

@section('content')
    <div class="bg-[#006E6B] max-w-screen-lg mx-auto my-5 px-8 pt-7 pb-5 rounded-3xl w-full">

        <div class="flex justify-between text-white">
            <div class="font-bold text-3xl">
                <h2>Asistencia de hoy</h2>
                <p id="fecha">{{ Carbon::now()->format('d/m/Y') }}</p>
            </div>
            <p class="text-6xl" id="hora-actual">{{ Carbon::now()->format('H:i') }}</p>
        </div>
        
        <form method="GET" id="filtros-form">
            <div class="flex justify-between mt-3 pt-3 text-white">

                <div>
                    <label class="mr-1 text-xl">Actividad:</label>
                    <select class="rounded-md bg-[#3A8F8E] text-xl p-2 focus:outline-none" name="actividad" id="actividad-select">
                        <option value="0">Todas</option>
                        @foreach($actividades as $act)
                            <option value="{{ $act->id }}" @if ($act->id == request('actividad', 0)) selected @endif>
                                {{ $act->nombre }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <div class="relative inline-block">
                        @if($paciente)
                            <button type="button" class="cursor-pointer" id="eliminar-button">
                                <i class="fa-solid fa-xmark hover:text-red-900 p-1 text-red-600 text-xl"></i>
                            </button>
                        @endif
                        <input type="hidden" name="paciente" id="id-paciente-input" value="{{ $paciente->id ?? 0 }}">
                        <input placeholder="Ingrese el nombre" class="{{ $paciente ? 'bg-[#6BA9A9]' : 'bg-[#3A8F8E]' }} rounded-md text-xl p-2 focus:outline-none" value="{{ $paciente ? $paciente->apellido . ' ' . $paciente->nombre : '' }}" id="nombre-input" {{ $paciente ? 'disabled' : '' }}>
                        <i class="fa-solid fa-magnifying-glass absolute right-4 top-1/2 -translate-y-1/2 text-xl"></i>
                        <ul id="sugerencias" class="absolute left-0 right-0 max-h-60 overflow-auto z-10 hidden">
                            <!-- Pacientes sugeridos -->
                        </ul>
                    </div>
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
                                    <button class="turno-button py-2 px-4 rounded-full transition-colors bg-[#F5D500]" data-id-turno="{{ $turno->id }}">Confirmar</button>
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

    </div>
@endsection

@push('scripts')
    @vite('resources/js/pages/inicio.js')
    @vite('resources/js/shared.js')
    <script src="https://kit.fontawesome.com/a186e728b7.js" crossorigin="anonymous"></script> <!-- Icono Lupa -->
@endpush