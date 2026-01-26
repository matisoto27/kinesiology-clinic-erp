@props([
    'label',
    'placeholder'
])

@php $id = 'input-' . Str::random(8); @endphp

<div class="flex flex-col gap-1">
    <label for="{{ $id }}" class="etiqueta-formulario">{{ $label }}</label>

    <input
        type="text"
        placeholder="{{ $placeholder }}"
        {{ $attributes->merge(['class' => 'entrada-simple' ]) }}
        id="{{ $id }}"
        required
    >
</div>
