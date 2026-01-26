@props([
    'entidad',
    'valor' => null,
    'texto' => null,
    'deshabilitado' => false
])

@php
    $idSeleccionado = 'id-' . $entidad . '-seleccionado';
    $nombreInputHidden = 'id_' . str_replace('-', '_', $entidad);
    $textoLabel = ucwords(str_replace('-', ' ', $entidad));
    $idQuitarButton = 'quitar-' . $entidad . '-button';
    $idInput = $entidad . '-input';
    $idDiv = 'buscador-' . $entidad;
    $idSugerencias = 'sugerencias-' . $entidad;
@endphp

<input type="hidden" value="{{ $valor }}" name="{{ $nombreInputHidden }}" id="{{ $idSeleccionado }}">

<div class="flex items-center gap-1">
    <label for="{{ $idInput }}" class="etiqueta-formulario">{{ $textoLabel }}</label>
    <button type="button" @class(['cursor-pointer', 'hidden' => !$texto]) id="{{ $idQuitarButton }}">
        <i class="fa-solid fa-xmark icono-quitar"></i>
    </button>
</div>

<div @class(['buscador', 'bg-[#6BA9A9]' => $texto || $deshabilitado]) id="{{ $idDiv }}">
    <div class="flex items-center">
        <i class="fa-solid fa-magnifying-glass icono-lupa"></i>
        <input
            type="text"
            {{ $attributes->merge(['placeholder' => 'Ingrese el nombre']) }}
            value="{{ $texto }}"
            {{ $texto || $deshabilitado ? 'disabled' : '' }}
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
