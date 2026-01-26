@props([
    'label',
    'type' => 'text',
    'placeholder' => null,
    'value' => null,
    'name',
    'disabled' => false,
    'required' => true
])

@php
    $id = 'input-' . str_replace('_', '-', $name);
    $valorActual = old($name, $value);
@endphp

<div class="flex flex-col gap-1">
    <label for="{{ $id }}" class="etiqueta-formulario">{{ $label }}</label>

    <input
        type="{{ $type }}"
        @if($placeholder)
            placeholder="{{ $placeholder }}"
        @endif
        class="entrada-simple{{ $errors->has($name) ? ' border-red-500 border-2' : '' }}"
        @if($valorActual)
            value="{{ $valorActual }}"
        @endif
        name="{{ $name }}"
        id="{{ $id }}"
        {{ $disabled ? 'disabled' : '' }}
        {{ $required ? 'required' : '' }}
    >

    @error($name)
        <div class="mt-1 text-red-500 text-sm">{{ $message }}</div>
    @enderror
</div>
