@props([
    'frecuenciaSemanal',
    'slotsCount',
    'precio',
    'horarios',
])

<div class="fila-formulario">
    <div class="columna-campo flex-1">
        <label class="etiqueta-formulario">Frecuencia semanal</label>
        <select class="entrada" wire:model.live="frecuenciaSemanal" required>
            @for ($i = 1; $i <= 5; $i++)
                <option value="{{ $i }}" @selected($frecuenciaSemanal === $i)>{{ $i }} {{ $i === 1 ? 'vez' : 'veces' }} por semana</option>
            @endfor
        </select>
        @error('frecuenciaSemanal')
            <span class="mt-1 text-red-500 text-sm italic">{{ $message }}</span>
        @enderror
        @error('horarios')
            <span class="mt-1 text-red-500 text-sm italic">{{ $message }}</span>
        @enderror
    </div>

    <div class="columna-campo flex-1">
        <label class="etiqueta-formulario">Seleccionados</label>
        <p class="entrada-info">{{ $slotsCount }} / {{ $frecuenciaSemanal }}</p>
    </div>

    <div class="columna-campo flex-1">
        <label class="etiqueta-formulario">Precio de referencia</label>
        <p class="entrada-info">
            @if ($precio !== null)
                ${{ number_format($precio, 2, ',', '.') }}
            @else
                ---
            @endif
        </p>
    </div>
</div>

<div class="mt-4 grid grid-cols-1 md:grid-cols-5 gap-3">
    @foreach (\App\Models\Actividad::diasSemanaDisponibles() as $dia)
        <div class="p-3 border border-white/10 rounded-lg" wire:key="dia-{{ $dia }}">
            <h3 class="etiqueta-formulario mb-2 text-center">{{ $dia }}</h3>

            <div class="mb-3 columna-campo">
                <label class="etiqueta-formulario">Gimnasio</label>
                @php($valorGym = $horarios[$dia][\App\Models\Actividad::GIMNASIO] ?? '')
                @php($opcionesGym = $this->opcionesHorario($dia, \App\Models\Actividad::GIMNASIO))
                <select
                    class="entrada"
                    wire:model.live="horarios.{{ $dia }}.{{ \App\Models\Actividad::GIMNASIO }}"
                    @disabled($this->horarioDeshabilitado($dia, \App\Models\Actividad::GIMNASIO))
                >
                    <option value="">No asignar</option>
                    @if ($valorGym !== '' && !collect($opcionesGym)->contains(substr($valorGym, 0, 5)))
                        <option value="{{ $valorGym }}">{{ substr($valorGym, 0, 5) }}</option>
                    @endif
                    @foreach ($opcionesGym as $hora)
                        <option value="{{ $hora }}:00">{{ $hora }}</option>
                    @endforeach
                </select>
            </div>

            <div class="columna-campo">
                <label class="etiqueta-formulario">Pilates</label>
                @php($valorPilates = $horarios[$dia][\App\Models\Actividad::PILATES] ?? '')
                @php($opcionesPilates = $this->opcionesHorario($dia, \App\Models\Actividad::PILATES))
                <select
                    class="entrada"
                    wire:model.live="horarios.{{ $dia }}.{{ \App\Models\Actividad::PILATES }}"
                    @disabled($this->horarioDeshabilitado($dia, \App\Models\Actividad::PILATES))
                >
                    <option value="">No asignar</option>
                    @if ($valorPilates !== '' && !collect($opcionesPilates)->contains(substr($valorPilates, 0, 5)))
                        <option value="{{ $valorPilates }}">{{ substr($valorPilates, 0, 5) }}</option>
                    @endif
                    @foreach ($opcionesPilates as $hora)
                        <option value="{{ $hora }}:00">{{ $hora }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    @endforeach
</div>
