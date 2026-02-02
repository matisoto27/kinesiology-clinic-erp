@extends('layouts.app')

@push('styles')
    <style>
        [x-cloak] { display: none !important; }
    </style>
@endpush

@section('content')
    <div class="mx-auto mt-10 mb-5 px-8 py-6 bg-[#006E6B] max-w-screen-3xl w-full">

        <h2 class="titulo-formulario">Listado de Pacientes</h2>

        <div x-data="{ mostrarInfo: false, datos: {} }" @keydown.escape.window="mostrarInfo = false; datos = {}">
            <table class="table-fixed bg-[#014745] text-white text-center overflow-hidden rounded-xl w-full">
                <thead>
                    <tr class="bg-white text-[#014745]">
                        <th class="py-3">DNI</th>
                        <th class="py-3">Nombre completo</th>
                        <th class="py-3">Nacimiento</th>
                        <th class="py-3">Edad</th>
                        <th class="py-3">Domicilio</th>
                        <th class="py-3">Teléfono</th>
                        <th class="py-3">Profesión</th>
                        <th class="py-3">Fecha de ingreso</th>
                        <th class="py-3">Ver más</th>
                        <th colspan="2" class="py-3">Acciones</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($pacientes as $pac)
                        <tr class="hover:bg-[#F5D500] hover:font-bold hover:text-emerald-900 transition-colors duration-100">
                            <td class="py-3">{{ $pac['dni'] }}</td>
                            <td class="py-3">{{ $pac['nombre_completo'] }}</td>
                            <td class="py-3">{{ $pac['fecha_nacimiento'] }}</td>
                            <td class="py-3">{{ $pac['edad'] }}</td>
                            <td class="py-3">{{ $pac['domicilio'] }}</td>
                            <td class="py-3">{{ $pac['telefono'] }}</td>
                            <td class="py-3">{{ $pac['profesion'] }}</td>
                            <td class="py-3">{{ $pac['fecha_ingreso'] }}</td>
                            <td class="py-3">
                                <div class="flex justify-center items-center">
                                    <button
                                        type="button"
                                        @click="mostrarInfo = true; datos = @js($pac)"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <circle cx="12" cy="12" r="10"></circle>
                                            <line x1="12" y1="16" x2="12" y2="12"></line>
                                            <line x1="12" y1="8" x2="12.01" y2="8"></line>
                                        </svg>
                                    </button>
                                </div>
                            </td>
                            <td colspan="2" class="py-3">
                                <div class="flex justify-center items-center gap-25">
                                    <a href="{{ route('pacientes.editar', ['paciente' => $pac['id']]) }}">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
                                        </svg>
                                    </a>
                                    <form action="{{ route('pacientes.eliminar', ['paciente' => $pac['id']]) }}" method="POST" onsubmit="return confirm('¿Desea eliminar a este paciente?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                            </svg>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="py-10 text-center text-gray-300 italic">No hay registros disponibles.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            <div class="mt-4">
                {{ $pacientes->links() }}
            </div>

            <div
                class="fixed inset-0 bg-black/30 backdrop-blur-sm flex justify-center items-center z-50"
                x-show="mostrarInfo"
                x-cloak
                x-transition.opacity
            >
                <div class="bg-white rounded-2xl shadow-lg w-full max-w-lg p-6 relative" @click.outside="mostrarInfo = false; datos = {}">
                    <button
                        @click="mostrarInfo = false; datos = {}"
                        class="absolute top-4 right-4 text-gray-400 hover:text-gray-600 transition"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>

                    <h2 x-text="'Información de ' + datos.apellido + ' ' + datos.nombre" class="mb-6 pb-2 border-b text-gray-800 text-2xl font-semibold"></h2>

                    <div class="space-y-3">
                        <div class="p-3 flex justify-between items-center bg-gray-50 border-gray-100 border rounded-lg">
                            <div>
                                <p class="text-gray-400 text-xs font-bold uppercase tracking-wider">¿Realiza actividad física?</p>
                                <p class="text-gray-700 font-medium" x-text="datos.actividad_fisica"></p>
                            </div>
                            <div class="text-[#006E6B]">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>
                            </div>
                        </div>

                        <div class="p-3 bg-gray-50 border-gray-100 border rounded-lg">
                            <p class="text-gray-400 text-xs font-bold uppercase tracking-wider">¿Es adulto mayor?</p>
                            <p class="text-gray-700 font-medium" x-text="datos.es_adulto_mayor ? 'Si' : 'No'"></p>

                            <template x-if="datos.es_adulto_mayor">
                                <div class="mt-2 pt-2 border-gray-200 border-t">
                                    <p class="text-gray-400 text-xs">¿Con quién vive?</p>
                                    <p class="text-gray-600" x-text="datos.vive_con"></p>
                                </div>
                            </template>
                        </div>

                        <div class="p-3 bg-gray-50 border-gray-100 border rounded-lg">
                            <p class="mb-2 text-gray-400 text-xs font-bold uppercase tracking-wider">¿Tiene algún antecedente patológico?</p>

                            <div class="space-y-3">
                                <template x-for="patologia in datos.patologias" :key="patologia.id">
                                    <div class="border-l-2 border-[#006E6B] pl-2">
                                        <p class="text-gray-400 text-xs font-semibold uppercase" x-text="patologia.fecha_desde"></p>
                                        <p class="text-gray-700" x-text="patologia.nombre"></p>
                                    </div>
                                </template>
                            </div>

                            <div class="py-1 text-gray-400 text-sm italic" x-show="!datos.patologias || datos.patologias.length === 0">
                                Sin antecedentes patológicos.
                            </div>
                        </div>

                        <div class="p-3 bg-gray-50 border-gray-100 border rounded-lg">
                            <p class="mb-2 text-gray-400 text-xs font-bold uppercase tracking-wider">¿Presenta algún síntoma?</p>

                            <div class="space-y-3">
                                <template x-for="sintoma in datos.sintomas" :key="sintoma.id">
                                    <div class="border-l-2 border-[#006E6B] pl-2">
                                        <p class="text-gray-400 text-xs font-semibold uppercase" x-text="sintoma.fecha_desde"></p>
                                        <p class="text-gray-700" x-text="sintoma.nombre"></p>
                                    </div>
                                </template>
                            </div>

                            <div class="py-1 text-gray-400 text-sm italic" x-show="!datos.sintomas || datos.sintomas.length === 0">
                                No registra síntomas activos.
                            </div>
                        </div>

                        <div class="p-3 flex justify-between items-center bg-[#e6f4f4] border-[#3A8F8E]/20 border rounded-lg">
                            <div>
                                <p class="text-[#3A8F8E] text-xs font-bold uppercase tracking-wider">Sesiones a favor</p>
                                <p class="text-[#014745] text-xl font-bold" x-text="datos.sesiones_a_favor"></p>
                            </div>
                            <span class="px-2 py-1 bg-white/50 text-[#014745] text-xs font-bold rounded">DISPONIBLES</span>
                        </div>
                    </div>
                    <div class="mt-8 flex justify-end gap-3">
                        <button
                            @click="mostrarInfo = false; datos = {}"
                            class="px-5 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium rounded-xl transition"
                        >
                                Cerrar
                        </button>
                        <a
                            x-show="datos.id"
                            :href="'{{ route('pacientes.editar', ['paciente' => 'ID_P']) }}'.replace('ID_P', datos.id)"
                            class="px-5 py-2 bg-[#3A8F8E] hover:bg-[#014745] text-white font-medium rounded-xl shadow-sm transition"
                        >
                            Editar paciente
                        </a>
                    </div>
                </div>
            </div>
        </div>

        @if (session('exito'))
            <div class="mt-4 p-4 bg-green-100 border-l-4 border-green-500 text-green-700 font-bold shadow-md animate-fade-in">
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
    </div>
@endsection

@push('scripts')
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.15.5/dist/cdn.min.js"></script>
@endpush
