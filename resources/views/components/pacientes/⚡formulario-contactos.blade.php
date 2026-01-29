<?php

use Livewire\Component;

new class extends Component
{
    public $esAdulto;
    public $viveSolo;
    public $contactos;
    public $paciente;

    public function mount($esAdultoInicial = false, $viveSoloInicial = true, $contactosInicial = null, $pacienteInicial = null)
    {
        $huboError = old('_token') !== null;

        $this->esAdulto = $huboError ? old('es_adulto_mayor') === 'on' : $esAdultoInicial;
        $this->viveSolo = $huboError ? old('vive_solo') === 'on' : $viveSoloInicial;
        $this->paciente = $pacienteInicial;

        $contactosParaCargar = old('contactos', $contactosInicial);

        if ($contactosParaCargar === null) {
            $this->contactos = [];
        } else {
            $this->contactos = collect($contactosParaCargar)->map(function ($contacto) {
                return [
                    'id' => $contacto['id'] ?? null,
                    'clave'    => $contacto['id'] ?? uniqid(),
                    'nombre'   => $contacto['nombre'] ?? '',
                    'telefono' => $contacto['telefono'] ?? '',
                    'vinculo'  => $contacto['vinculo'] ?? ''
                ];
            })->toArray();
        }
        Log::info('Valores', ['esAdulto' => $this->esAdulto, 'viveSolo' => $this->viveSolo, 'contactos' => $this->contactos]);
    }

    public function updatedEsAdulto($value)
    {
        if (!$value) {
            $this->viveSolo = true;
            $this->contactos = [];
        }
    }

    public function agregarContacto()
    {
        if (count($this->contactos) < 3) {
            $this->contactos[] = [
                'id' => null,
                'clave' => uniqid(),
                'nombre' => '',
                'telefono' => '',
                'vinculo' => ''
            ];
        }
    }

    public function eliminarContacto($clave)
    {
        $indice = collect($this->contactos)->search(function ($contacto) use ($clave) {
            return $contacto['clave'] == $clave;
        });

        unset($this->contactos[$indice]);
        $this->contactos = array_values($this->contactos);
        Log::info('Aa', ['Bb' => $this->contactos]);
    }
};
?>

<div class="space-y-5">
    <div class="flex items-center gap-1">
        <input type="checkbox" class="checkbox-formulario" name="es_adulto_mayor" id="checkbox-adulto-mayor" wire:model.live="esAdulto">
        <label for="checkbox-adulto-mayor" class="etiqueta-formulario">¿Es adulto mayor?</label>
    </div>

    @if($esAdulto)
        <div class="space-y-5">
            <div class="flex items-center gap-1">
                <input type="checkbox" class="checkbox-formulario" name="vive_solo" id="checkbox-vive-solo" wire:model.live="viveSolo">
                <label for="checkbox-vive-solo" class="etiqueta-formulario">¿Vive solo?</label>
            </div>

            @if(!$viveSolo)
                <x-input-formulario
                    label="¿Con quién vive?"
                    placeholder="Ejemplo: Juan (esposo), Mariana (hija)"
                    :value="$paciente === null ? '' : ($paciente->vive_con === 'SOLO' ? '' : $paciente->vive_con)"
                    name="vive_con"
                    :required="false"
                />
            @endif

            @foreach($contactos as $indice => $contacto)
                <div class="mb-5 pb-5 border-[#F5D500] border-b" wire:key="contacto-{{ $contacto['clave'] }}">
                    <input type="hidden" value="{{ $contacto['id'] }}" name="contactos[{{ $indice }}][id]">

                    <div class="mb-4 flex items-center justify-between">
                        <h3 class="font-medium text-[#F5D500] text-xl">
                            Contacto de emergencia {{ $indice + 1 }}
                        </h3>
                        <button type="button" class="text-red-500 text-md hover:text-red-400" wire:click="eliminarContacto('{{ $contacto['clave'] }}')">Eliminar</button>
                    </div>

                    <div class="flex flex-col gap-1">
                        <label for="contacto_{{ $indice }}_nombre" class="etiqueta-formulario">Nombre</label>
                        <input
                            type="text"
                            placeholder="Ingrese nombre del contacto"
                            @class([
                                'entrada-simple',
                                'border-red-500 border-2' => $errors->has("contactos.{$indice}.nombre")
                            ])
                            name="contactos[{{ $indice }}][nombre]"
                            id="contacto_{{ $indice }}_nombre"
                            wire:model="contactos.{{ $indice }}.nombre"
                            required
                        >
                        @error("contactos.{$indice}.nombre")
                            <span class="text-red-500 text-sm">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="flex flex-col gap-1">
                        <label for="contacto_{{ $indice }}_telefono" class="etiqueta-formulario">Teléfono</label>
                        <input
                            type="text"
                            placeholder="Ingrese teléfono del contacto"
                            @class([
                                'entrada-simple',
                                'border-red-500 border-2' => $errors->has("contactos.{$indice}.telefono")
                            ])
                            name="contactos[{{ $indice }}][telefono]"
                            id="contacto_{{ $indice }}_telefono"
                            wire:model="contactos.{{ $indice }}.telefono"
                            required
                        >
                        @error("contactos.{$indice}.telefono")
                            <span class="text-red-500 text-sm">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="flex flex-col gap-1">
                        <label for="contacto_{{ $indice }}_vinculo" class="etiqueta-formulario">Vínculo</label>
                        <select
                            @class([
                                'entrada-simple',
                                'border-red-500 border-2' => $errors->has("contactos.{$indice}.vinculo")
                            ])
                            name="contactos[{{ $indice }}][vinculo]"
                            id="contacto_{{ $indice }}_vinculo"
                            wire:model="contactos.{{ $indice }}.vinculo"
                            required
                        >
                            <option value="" disabled>¿Qué vínculo tiene con el paciente?</option>
                            @foreach(['Hijo/a', 'Cónyuge', 'Hermano/a', 'Otro'] as $opcion)
                                <option value="{{ $opcion }}">{{ $opcion }}</option>
                            @endforeach
                        </select>
                        @error("contactos.{$indice}.vinculo")
                            <span class="text-red-500 text-sm">{{ $message }}</span>
                        @enderror
                    </div>
                </div>
            @endforeach

            @if (count($contactos) < 3)
                <div class="flex justify-center">
                    <button type="button" wire:click="agregarContacto" class="px-4 py-2 bg-blue-500 hover:bg-blue-700 text-white rounded">Añadir Contacto de Emergencia</button>
                </div>
            @else
                <div class="flex justify-center">
                    <p class="mt-2 text-red-500 text-sm">Has alcanzado el máximo de contactos de emergencia.</p>
                </div>
            @endif
        </div>
    @endif
</div>
