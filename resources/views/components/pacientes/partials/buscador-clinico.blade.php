@props([
    'etiqueta',
    'inputId',
    'placeholder',
    'campoBusqueda',
    'propiedadSugerencias',
    'propiedadSeleccionados',
    'metodoSeleccion',
    'metodoPendiente',
    'metodoQuitar',
    'chipKeyPrefix',
    'campoErrorBusqueda',
    'campoErrorSeleccionados',
])

@php
    $sugerencias = $this->{$propiedadSugerencias};
    $seleccionados = $this->{$propiedadSeleccionados};
    $textoBusqueda = $this->{$campoBusqueda};
@endphp

<div class="columna-campo">
    <label for="{{ $inputId }}" class="etiqueta-formulario">
        {{ $etiqueta }}
    </label>

    <div class="relative">
        <input
            id="{{ $inputId }}"
            type="text"
            autocomplete="off"
            placeholder="{{ $placeholder }}"
            @class([
                'entrada-simple w-full',
                'border-red-500 border-2' => $errors->has($campoErrorBusqueda),
            ])
            wire:model.live.debounce.300ms="{{ $campoBusqueda }}"
        >

        @if (mb_strlen(trim($textoBusqueda)) >= 2)
            <ul class="sugerencias" wire:click.outside="$set('{{ $campoBusqueda }}', '')">
                @forelse ($sugerencias as $indice => $item)
                    <li
                        wire:key="{{ $chipKeyPrefix }}-sug-{{ $item->id }}"
                        wire:click="{{ $metodoSeleccion }}({{ $item->id }})"
                        @class([
                            'p-2 bg-white hover:bg-[#F5D500] text-black text-left cursor-pointer',
                            'rounded-b-md' => $indice === ($sugerencias->count() - 1),
                        ])
                    >
                        {{ $item->nombre }}
                    </li>
                @empty
                    <li
                        wire:click="{{ $metodoPendiente }}"
                        class="p-2 bg-white hover:bg-[#F5D500] text-black text-left cursor-pointer rounded-b-md"
                    >
                        Sin coincidencias ¿Registrar?
                    </li>
                @endforelse
            </ul>
        @endif
    </div>

    @error($campoErrorBusqueda)
        <span class="mt-1 text-red-500 text-sm">{{ $message }}</span>
    @enderror

    @if (count($seleccionados) > 0)
        <ul class="mt-3 flex flex-wrap gap-2">
            @foreach ($seleccionados as $indice => $item)
                <li
                    wire:key="{{ $chipKeyPrefix }}-chip-{{ $indice }}-{{ $item['id'] ?? 'nuevo' }}"
                    @class([
                        'inline-flex items-center gap-1 rounded-full px-3 py-1 text-sm text-white',
                        'bg-[#2a6b6a]' => $item['bloqueada'] ?? false,
                        'bg-[#3A8F8E]' => !($item['bloqueada'] ?? false),
                    ])
                >
                    <span>{{ $item['nombre'] }}</span>
                    @if (!($item['bloqueada'] ?? false))
                        <button
                            type="button"
                            class="text-white/90 transition-colors hover:text-red-400"
                            wire:click="{{ $metodoQuitar }}({{ $indice }})"
                            aria-label="Quitar {{ $item['nombre'] }}"
                        >
                            <x-iconos.cruz />
                        </button>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif

    @error($campoErrorSeleccionados) <div class="text-red-500 text-md">{{ $message }}</div> @enderror
    @error($campoErrorSeleccionados . '.*.nombre') <div class="text-red-500 text-md">{{ $message }}</div> @enderror
</div>
