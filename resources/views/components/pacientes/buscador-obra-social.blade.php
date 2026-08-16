<div class="columna-campo">
    <div class="flex items-center gap-1">
        @if ($obraSocialSeleccionada)
            <button
                type="button"
                class="text-red-500 hover:text-red-300"
                wire:click="limpiarObraSocial"
                aria-label="Quitar obra social"
            >
                <x-iconos.cruz />
            </button>
        @endif
        <label for="input-busqueda-obra-social" class="etiqueta-formulario">
            Obra social (Opcional)
        </label>
    </div>

    <div class="relative">
        <input
            id="input-busqueda-obra-social"
            type="text"
            autocomplete="off"
            placeholder="Escriba para buscar una obra social"
            @class([
                'entrada-simple w-full',
                'bg-[#6BA9A9] text-[#E0F0F0] cursor-not-allowed' => $obraSocialSeleccionada,
                'border-red-500 border-2' => $errors->has('busquedaObraSocial') || $errors->has('obraSocialSeleccionada'),
            ])
            @disabled((bool) $obraSocialSeleccionada)
            wire:model.live.debounce.300ms="busquedaObraSocial"
        >

        @if (!$obraSocialSeleccionada && mb_strlen(trim($busquedaObraSocial)) >= 2)
            <ul class="sugerencias" wire:click.outside="$set('busquedaObraSocial', '')">
                @forelse ($this->sugerenciasObrasSociales as $indice => $item)
                    <li
                        wire:key="obra-social-sug-{{ $item->id }}"
                        wire:click="seleccionarObraSocial({{ $item->id }})"
                        @class([
                            'p-2 bg-white hover:bg-[#F5D500] text-black text-left cursor-pointer',
                            'rounded-b-md' => $indice === ($this->sugerenciasObrasSociales->count() - 1),
                        ])
                    >
                        {{ $item->nombre }}
                    </li>
                @empty
                    <li
                        wire:click="usarObraSocialLibre"
                        class="p-2 bg-white hover:bg-[#F5D500] text-black text-left cursor-pointer rounded-b-md"
                    >
                        Sin coincidencias. ¿Usar “{{ trim($busquedaObraSocial) }}”?
                    </li>
                @endforelse
            </ul>
        @endif
    </div>

    @error('busquedaObraSocial')
        <span class="mt-1 text-red-500 text-sm">{{ $message }}</span>
    @enderror
    @error('obraSocialSeleccionada')
        <span class="mt-1 text-red-500 text-sm">{{ $message }}</span>
    @enderror
    @error('obraSocialSeleccionada.nombre')
        <span class="mt-1 text-red-500 text-sm">{{ $message }}</span>
    @enderror
</div>
