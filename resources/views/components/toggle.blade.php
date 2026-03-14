@props([
    'activo',
    'etiquetaIzquierda',
    'etiquetaDerecha',
    'colorActivo' => 'bg-blue-600',
    'colorTextoActivo' => 'text-blue-400'
])

<div class="p-2 w-fit flex items-center gap-4 bg-gray-800 rounded-lg">
    <span class="transition-colors duration-200 {{ !$activo ? $colorTextoActivo . ' font-bold' : 'text-gray-400' }}">
        {{ $etiquetaIzquierda }}
    </span>

    <button
        type="button"
        {{ $attributes }}
        class="h-6 w-11 relative inline-flex items-center rounded-full transition-colors duration-200 {{ $activo ? $colorActivo : 'bg-gray-600' }}"
        wire:loading.attr="disabled"
    >
        <span class="h-4 w-4 inline-block bg-white rounded-full transform transition-transform duration-200 {{ $activo ? 'translate-x-6' : 'translate-x-1' }}"></span>
    </button>

    <span class="transition-colors duration-200 {{ $activo ? $colorTextoActivo . ' font-bold' : 'text-gray-400' }}">
        {{ $etiquetaDerecha }}
    </span>
</div>
