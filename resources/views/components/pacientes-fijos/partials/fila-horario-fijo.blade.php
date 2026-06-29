<div class="fila-formulario" wire:key="{{ $prefijoModelo }}-{{ $indice }}">
    <h2 class="text-white text-lg font-medium">Turno {{ $indice }}</h2>

    <div class="columna-campo">
        <label for="dia-select-{{ $prefijoModelo }}-{{ $indice }}" class="etiqueta-formulario">Día de la semana</label>
        <select id="dia-select-{{ $prefijoModelo }}-{{ $indice }}" class="entrada" wire:model.live="{{ $prefijoModelo }}.{{ $indice }}.dia_semana" required>
            <option value="" disabled selected>Seleccione un día</option>
            <option value="1" @disabled(in_array('1', $diasOcupados ?? [], true))>Lunes</option>
            <option value="2" @disabled(in_array('2', $diasOcupados ?? [], true))>Martes</option>
            <option value="3" @disabled(in_array('3', $diasOcupados ?? [], true))>Miércoles</option>
            <option value="4" @disabled(in_array('4', $diasOcupados ?? [], true))>Jueves</option>
            <option value="5" @disabled(in_array('5', $diasOcupados ?? [], true))>Viernes</option>
        </select>
        @error($prefijoModelo . '.' . $indice . '.dia_semana')
            <span class="mt-1 text-red-500 text-sm italic">{{ $message }}</span>
        @enderror
    </div>

    <div class="columna-campo">
        <label for="hora-select-{{ $prefijoModelo }}-{{ $indice }}" class="etiqueta-formulario">Hora de inicio</label>
        <select
            id="hora-select-{{ $prefijoModelo }}-{{ $indice }}"
            class="entrada"
            wire:model.live="{{ $prefijoModelo }}.{{ $indice }}.hora_inicio"
            @disabled($diaSeleccionado === '')
            required
        >
            <option value="" disabled selected>Seleccione hora de inicio</option>
            @if($diaSeleccionado !== '' && !empty($turnosPorDia[$diaSeleccionado]))
                @foreach($turnosPorDia[$diaSeleccionado] as $opcion)
                    <option value="{{ $opcion['valor'] }}">{{ $opcion['label'] }}</option>
                @endforeach
            @endif
        </select>
        @error($prefijoModelo . '.' . $indice . '.hora_inicio')
            <span class="mt-1 text-red-500 text-sm italic">{{ $message }}</span>
        @enderror
    </div>
</div>
