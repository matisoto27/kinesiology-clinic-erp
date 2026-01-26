@props([
    'label',
    'opcionPorDefecto',
    'opciones'
])

@php $id = 'select-' . Str::random(8); @endphp

<div class="flex flex-col gap-1">
    <label for="{{ $id }}" class="etiqueta-formulario">{{ $label }}</label>

    <select {{ $attributes->merge(['class' => 'entrada-simple']) }} id="{{ $id }}">
        <option value="" disabled selected>{{ $opcionPorDefecto }}</option>
        @foreach($opciones as $op)
            <option value="{{ $op }}">{{ $op }}</option>
        @endforeach
    </select>
</div>
