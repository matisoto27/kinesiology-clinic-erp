@php use Carbon\Carbon; @endphp

@extends('layouts.app')

@section('content')

    <div class="bg-white border max-w-screen-xl mx-auto mt-5 w-full">

        <form method="GET" id="filtros-form">

            <input type="hidden" value="{{ ($cantSemanas ?? 0) }}" name="semana" id="semana-input">

            <div class="bg-[#014745] flex h-26 items-center px-5 text-white">

                <div class="w-1/3 flex">

                    <div>
                        <label class="mr-1 text-xl">Actividad</label>
                        <select class="p-2 text-xl bg-[#3A8F8E] rounded-lg focus:outline-none" name="actividad" id="actividad-select">
                            <option value="0">Todas</option>
                            @foreach($actividades as $act)
                                <option value="{{ $act->id }}" @if ($act->id == request('actividad', 0)) selected @endif>
                                    {{ $act->nombre }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="mr-1 text-xl">Franja horaria</label>
                        <select class="p-2 text-xl bg-[#3A8F8E] rounded-lg focus:outline-none" name="horario" id="horario-select">
                            <option value="0">Cualquier horario</option>
                            <option value="1" @if(request('horario', 0) == 1) selected @endif>Turno mañana</option>
                            <option value="2" @if(request('horario', 0) == 2) selected @endif>Turno tarde</option>
                        </select>
                    </div>

                </div>

                <div class="w-1/3 flex justify-center">
                    <p class="font-bold text-3xl">{{ ucwords($diaInicio->isoFormat('MMMM YYYY')) }}</p>
                </div>

                <div class="w-1/3 flex justify-end gap-5">
                    <button class="bg-[#3A8F8E] duration-300 hover:bg-[#F5D500] px-5 py-2 rounded-lg" id="anterior-button">
                        <i class="fa-solid fa-arrow-left"></i>
                    </button>
                    <button class="bg-[#3A8F8E] duration-300 hover:bg-[#F5D500] px-5 py-2 rounded-lg" id="siguiente-button">
                        <i class="fa-solid fa-arrow-right"></i>
                    </button>
                </div>

            </div>
        </form>

        @php
            $coloresActividades = ['bg-indigo-600', 'bg-teal-500', 'bg-amber-500'];
            $colores = ['bg-[#4E79A7]','bg-[#F28E2B]','bg-[#59A14F]','bg-[#E15759]', 'bg-[#B07AA1]', 'bg-[#EDC948]'];
            $diasSemana = collect(range(0, 4))->map(fn($d) => $diaInicio->copy()->addDays($d)->format('Y-m-d'));
            $horarios = $horarios == null ? ['08:00', '08:30', '09:00', '09:30', '10:00', '10:30', '11:00', '11:30', '16:00', '16:30', '17:00', '17:30', '18:00', '18:30', '19:00', '19:30'] : $horarios;
        @endphp

        <div class="divide-x divide-black flex">

            <div class="flex-1 flex-col">
                <div class="bg-[#3A8F8E] flex font-semibold h-14 items-center justify-center text-lg text-white">Hora de ingreso</div>
                @foreach($horarios as $horaInicio)
                    <div class="border-b content-center h-46 text-center">{{ $horaInicio }}hs</div>
                @endforeach
            </div>

            @foreach ($diasSemana as $dia)
                <div class="flex-1 flex-col">

                    <div class="bg-[#3A8F8E] flex font-semibold h-14 items-center justify-center text-lg text-white">
                        {{ ucwords(Carbon::parse($dia)->translatedFormat('l d')) }}
                    </div>

                    @foreach($horarios as $horaInicio)
                        @if($turnosPorDia[$dia][$horaInicio]->isEmpty())
                                <div class="border-b content-center h-46 text-center"></div>
                        @else
                            <div class="border-b flex flex-col h-46 space-y-1 justify-center">
                                @foreach($turnosPorDia[$dia][$horaInicio] as $turno)
                                    @php
                                        if ((request('actividad', 0)) != 0) {
                                            $color = $colores[$loop->index % count($colores)];
                                        } else {
                                            switch ($turno->actividadPaciente->id_actividad) {
                                                case 1:
                                                    $color = $coloresActividades[0];
                                                    break;

                                                case 2:
                                                    $color = $coloresActividades[1];
                                                    break;

                                                case 3:
                                                    $color = $coloresActividades[2];
                                                    break;

                                                default:
                                                    $color = 'bg-gray-700';
                                                    break;
                                            }
                                        }
                                    @endphp
                                    <div class="{{ $color }} ml-1 px-2 rounded-lg text-lg text-white w-fit">
                                        <button class="turno-button flex items-center gap-2" data-id-turno="{{ $turno->id }}">
                                            {{ $turno->actividadPaciente->paciente->apellido }}, {{ $turno->actividadPaciente->paciente->nombre }}
                                            @if($turno->notas->count() > 0)
                                                <div class="contador relative">
                                                    <i class="fa-solid fa-comment"></i>
                                                    <span class="absolute inset-0 flex justify-center items-center text-sm text-gray-800 font-bold">
                                                        {{ $turno->notas()->count() }}
                                                    </span>
                                                </div>
                                            @endif
                                        </button>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    @endforeach

                </div>
            @endforeach

        </div>

    </div>

    <div class="fixed inset-0 bg-black/30 backdrop-blur-sm flex justify-center items-center z-50 hidden" id="modal-notas-turno">
        <div class="bg-white rounded-2xl shadow-lg w-full max-w-lg p-6 relative">

            <button class="modal-notas-cerrar absolute top-3 right-3 text-gray-500 hover:text-gray-700">
                <i class="fa-solid fa-xmark text-xl"></i>
            </button>

            <h2 class="text-2xl font-semibold text-gray-800 mb-4">Notas del turno</h2>

            <div class="space-y-3 max-h-80 overflow-y-auto" id="modal-notas-lista">
                <!-- Notas del turno -->
            </div>

            <div class="mt-6 flex justify-end gap-3">
                <button class="px-4 py-2 bg-[#3A8F8E] hover:bg-[#014745] rounded-lg text-white transition" id="modal-notas-agregar">Agregar nueva nota</button>
                <button class="modal-notas-cerrar px-4 py-2 bg-blue-600 hover:bg-blue-700 rounded-lg text-white transition">Cerrar</button>
            </div>

        </div>
    </div>

    <div class="fixed inset-0 bg-black/30 backdrop-blur-sm flex justify-center items-center z-100 hidden" id="modal-agregar-nota">
        <div class="bg-white rounded-2xl shadow-lg w-full max-w-lg p-6 relative">

            <h2 class="text-2xl font-semibold text-gray-800 mb-4">Agregar nueva nota</h2>

            <div class="space-y-3 max-h-80 overflow-y-auto">
                <textarea class="w-full p-3 border border-gray-300 resize-none rounded-lg" placeholder="Ingrese el contenido de la nueva nota" rows="5" id="contenido-nota-textarea"></textarea>
            </div>

            <div class="mt-6 flex justify-end gap-3">
                <button class="px-4 py-2 bg-[#3A8F8E] hover:bg-[#014745] rounded-lg text-white transition" id="registrar-button">Registrar</button>
                <button class="px-4 py-2 bg-blue-600 hover:bg-blue-700 rounded-lg text-white transition" id="volver-button">Volver</button>
            </div>

        </div>
    </div>

@endsection

@push('scripts')
    @vite('resources/js/pages/calendario.js')
    <script src="https://kit.fontawesome.com/a186e728b7.js" crossorigin="anonymous"></script> <!-- Flechas -->
@endpush
