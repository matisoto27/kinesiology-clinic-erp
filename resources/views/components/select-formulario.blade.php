@props([
    'label',
    'opcionPorDefecto' => 'Seleccione una opción',
    'value' => null,
    'name',
    'opciones'
])

@php
    $id = 'input-' . str_replace('_', '-', $name);
    $valorActual = old($name, $value);
@endphp

<div class="flex flex-col gap-1">
    <label for="{{ $id }}" class="etiqueta-formulario">{{ $label }}</label>

    <select class="entrada" name="{{ $name }}" id="{{ $id }}" required>
        <option value="" disabled @selected($valorActual === null)>{{ $opcionPorDefecto }}</option>
        @foreach($opciones as $op)
            <option value="{{ $op }}" @selected($op == $valorActual)>{{ $op }}</option>
        @endforeach
    </select>

    @error($name)
        <span class="mt-1 text-red-500 text-sm">{{ $message }}</span>
    @enderror
</div>
