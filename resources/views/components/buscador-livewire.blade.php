@props([
    'busqueda',
    'idSeleccionado',
    'sugerencias',
    'etiquetaBuscador',
    'campoError'
])

<div class="relative w-xs">
    <div class="flex items-center gap-1">
        @if ($idSeleccionado)
            <button
                type="button"
                class="text-red-500 hover:text-red-300"
                wire:click="limpiarSeleccion"
            >
                <x-iconos.cruz />
            </button>
        @else
            <x-iconos.lupa />
        @endif
        <label for="input-buscador" class="etiqueta-formulario">
            {{ $etiquetaBuscador }}
        </label>
    </div>

    <input
        id="input-buscador"
        type="text"
        placeholder="Ingrese nombre y/o apellido"
        @class([
            'p-2 w-full text-xl rounded-t-lg focus:outline-none',
            'rounded-b-lg' => strlen($busqueda) < 2 || $idSeleccionado,
            'bg-[#3A8F8E] text-white' => !$idSeleccionado,
            'bg-[#6BA9A9] text-[#E0F0F0] cursor-not-allowed' => $idSeleccionado
        ])
        @disabled($idSeleccionado)
        wire:model.live.debounce.300ms="busqueda"
    >

    @if(strlen($busqueda) >= 2 && !$idSeleccionado)
        <ul class="sugerencias" wire:click.outside="$set('busqueda', '')">
            @if($sugerencias->isNotEmpty())
                @foreach($sugerencias as $indice => $sug)
                    <li
                        @class([
                            'p-2 bg-white hover:bg-[#F5D500] text-black text-left cursor-pointer',
                            'rounded-b-md' => $indice === ($sugerencias->count() - 1)
                        ])
                        wire:click="seleccionarSugerencia({{ $sug->id }}, '{{ $sug->apellido_nombre }}')"
                    >
                        {{ $sug->apellido_nombre }}
                    </li>
                @endforeach
            @else
                <li class="p-2 flex items-center bg-white text-gray-500 text-left rounded-b-lg">
                    <x-iconos.circulo-informacion />
                    <span class="ml-1">Sin coincidencias</span>
                </li>
            @endif
        </ul>
    @endif
</div>
@error($campoError)
    <span class="mt-1 text-red-500 text-sm italic">{{ $message }}</span>
@enderror
