@php
    $gympassBloqueado = $gympassBloqueado ?? false;
@endphp

<div class="columna-campo">
    <div class="flex items-center gap-1">
        <input
            id="checkbox-gympass"
            type="checkbox"
            class="checkbox-formulario"
            @checked($esGympass)
            @disabled($gympassBloqueado)
            @if (!$gympassBloqueado)
                x-on:change="
                    if (!$event.target.checked) {
                        $wire.set('esGympass', false);
                        return;
                    }

                    if (!confirm('¿Estás seguro de que deseas marcar a este paciente como Gympass? Esta decisión no se podrá modificar luego.')) {
                        $event.target.checked = false;
                        return;
                    }

                    $wire.set('esGympass', true);
                "
            @endif
        >
        <label for="checkbox-gympass" class="etiqueta-formulario">¿Es paciente Gympass?</label>
    </div>
    @if ($gympassBloqueado)
        <p class="mt-1 text-sm text-gray-400">Esta marca no se puede deshacer.</p>
    @endif
</div>
