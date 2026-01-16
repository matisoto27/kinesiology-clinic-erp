@props(['name', 'label', 'type' => 'text', 'placeholder' => ''])

@php
    $id = 'input-' . str_replace('_', '-', $name);
@endphp

<div class="flex flex-col gap-1">
    <label for="{{ $id }}" class="etiqueta-formulario">{{ $label }}</label>

    <input
        id="{{ $id }}"
        name="{{ $name }}"
        type="{{ $type }}"
        value="{{ old($name) }}"
        placeholder="{{ $placeholder }}"
        class="entrada-simple @error($name) border-2 border-red-500 @enderror"
        required
    >

    @error($name)
        <div class="text-red-500 text-sm mt-1">{{ $message }}</div>
    @enderror
</div>
