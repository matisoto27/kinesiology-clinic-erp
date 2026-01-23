@props(['nombre', 'seleccionado' => null, 'deshabilitado' => false])

@php
    $idSeleccionado = 'id-' . $nombre . '-seleccionado';
    $idName = 'id_' . str_replace('-', '_', $nombre);
    $textoLabel = ucfirst($nombre);
    $idQuitarButton = 'quitar-' . $nombre . '-button';
    $idInput = $nombre . '-input';
    $idDiv = 'buscador-' . $nombre;
    $idSugerencias = 'sugerencias-' . $nombre;
@endphp

<input type="hidden" name="{{ $idName }}" id="{{ $idSeleccionado }}">

<div class="flex items-center gap-1">
    <label for="{{ $idInput }}" class="etiqueta-formulario">{{ $textoLabel }}</label>
    <button type="button" @class(['cursor-pointer', 'hidden' => !$seleccionado]) id="{{ $idQuitarButton }}">
        <i class="fa-solid fa-xmark icono-quitar"></i>
    </button>
</div>

<div @class(['buscador', 'bg-[#6BA9A9]' => $seleccionado || $deshabilitado]) id="{{ $idDiv }}">
    <div class="flex items-center">
        <i class="fa-solid fa-magnifying-glass icono-lupa"></i>
        <input
            type="text"
            {{ $attributes->merge(['placeholder' => 'Ingrese el nombre']) }}
            value="{{ $seleccionado }}"
            {{ $seleccionado || $deshabilitado ? 'disabled' : '' }}
            id="{{ $idInput }}"
            autocomplete="off"
            required
        >
    </div>
    <ul class="sugerencias hidden" id="{{ $idSugerencias }}">
        <!-- Sugerencias -->
    </ul>
</div>

@once
    @push('scripts')
        <script src="https://kit.fontawesome.com/a186e728b7.js" crossorigin="anonymous" defer></script>
    @endpush
@endonce
