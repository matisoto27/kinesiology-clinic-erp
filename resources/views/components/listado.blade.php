@props([
    'entidad',
    'titulo',
    'elementos',
    'columnas',
    'rutaEditar',
    'rutaEliminar'
])

<div class="w-full max-w-screen-2xl mx-auto my-5 bg-[#006E6B] rounded-3xl py-6 px-8">

    <h2 class="titulo-formulario">{{ 'Listado de ' . $titulo }}</h2>

    <table class="table-fixed bg-[#014745] text-white text-center overflow-hidden rounded-xl w-full">

        <thead>
            <tr class="bg-white text-[#014745]">
                @foreach($columnas as $clave => $valor)
                    <th class="py-3">{{ $valor }}</th>
                @endforeach
                <th colspan="2" class="py-3">Acciones</th>
            </tr>
        </thead>

        <tbody>
            @forelse($elementos as $el)
                <tr class="hover:bg-[#F5D500] hover:font-bold hover:text-emerald-900 transition-colors duration-100">
                    @foreach($columnas as $clave => $valor)
                        <td class="py-3">{{ $el[$clave] }}</td>
                    @endforeach
                    <td colspan="2" class="py-3">
                        <div class="flex justify-center items-center gap-25">
                            <a href="{{ route($rutaEditar, ['paciente' => $el['id'] ]) }}">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
                                </svg>
                            </a>
                            <form action="{{ route($rutaEliminar, [$entidad => $el['id'] ]) }}" method="POST" onsubmit="return confirm('¿Desea eliminar a este paciente?')">
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
                    <td colspan="{{ count($columnas) + 2 }}" class="py-10 text-center text-gray-300 italic">No hay registros disponibles.</td>
                </tr>
            @endforelse
        </tbody>

    </table>

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
