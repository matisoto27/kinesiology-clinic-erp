<?php

use App\Models\Paciente;
use App\Livewire\Concerns\ManejaBuscadorObraSocial;
use App\Livewire\Concerns\ManejaBuscadorPatologias;
use App\Livewire\Concerns\ManejaBuscadorSintomas;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Livewire\Component;

new class extends Component
{
    use ManejaBuscadorObraSocial;
    use ManejaBuscadorPatologias;
    use ManejaBuscadorSintomas;

    public string $dni = '';
    public string $nombre = '';
    public string $apellido = '';
    public string $fechaNac = '';
    public string $domicilio = '';
    public string $telefono = '';
    public string $profesion = '';
    public string $actividadFisica = '';
    public bool $esAdultoMayor = false;
    public bool $viveSolo = true;
    public ?string $viveCon = null;
    public array $contactos = [];

    protected function rules()
    {
        return array_merge([
            'dni' => [
                'required',
                'numeric',
                'digits_between:7,8',
                // Solo activos: un soft-deleted se restaura en el alta.
                Rule::unique('pacientes', 'dni')->whereNull('deleted_at'),
            ],
            'nombre' => 'required|regex:/^[A-Za-záéíóúÁÉÍÓÚñÑ\s]+$/|max:30',
            'apellido' => 'required|regex:/^[A-Za-záéíóúÁÉÍÓÚñÑ\s]+$/|max:30',
            'fechaNac' => 'required|date|before:today',
            'domicilio' => 'required|string|regex:/^[A-Za-z0-9\s.,áéíóúÁÉÍÓÚñÑ#-]+$/|max:100',
            'telefono' => 'required|numeric|digits_between:8,20',
            'profesion' => 'required|string|max:40',
            'actividadFisica' => 'required|string|in:Sedentario,Ocasional,Moderada,Intensa,Alto rendimiento/Competencia',
            'esAdultoMayor' => 'required|boolean',
            'viveSolo' => 'exclude_if:esAdultoMayor,false|boolean',
            'viveCon' => 'exclude_if:esAdultoMayor,false|required_if:viveSolo,false|nullable|string|regex:/^[A-Za-z0-9\s.,()áéíóúÁÉÍÓÚñÑ]+$/|min:1|max:150',
            'contactos' => 'exclude_if:esAdultoMayor,false|nullable|array|max:3',
            'contactos.*.nombre' => 'required_with:contactos|regex:/^[A-Za-záéíóúÁÉÍÓÚñÑ\s]+$/|max:100',
            'contactos.*.telefono' => 'required_with:contactos|numeric|digits_between:8,20',
            'contactos.*.vinculo' => 'required_with:contactos|string|in:Cónyuge,Hijo/a,Hermano/a,Otro',
        ], $this->reglasPatologiasSeleccionadas(), $this->reglasSintomasSeleccionados(), $this->reglasObraSocialSeleccionada());
    }

    protected function messages()
    {
        return [
            'dni.unique' => 'Ya existe un paciente con ese DNI.',
            'nombre.regex' => 'El nombre solo puede contener letras y espacios.',
            'apellido.regex' => 'El apellido solo puede contener letras y espacios.',
            'viveCon.required_if' => 'Por favor, especifique con quién vive el paciente.',
        ];
    }

    protected function validationAttributes()
    {
        return [
            'dni' => 'DNI',
            'fechaNac' => 'fecha de nacimiento',
            'telefono' => 'teléfono',
            'profesion' => 'profesión',
            'actividadFisica' => 'actividad física',
            'viveCon' => 'detalle con quién vive',
            'contactos' => 'contactos de emergencia',
            'contactos.*.nombre' => 'nombre del contacto',
            'contactos.*.telefono' => 'teléfono del contacto',
            'contactos.*.vinculo' => 'vínculo del contacto',
            'patologiasSeleccionadas' => 'patologías',
            'patologiasSeleccionadas.*.nombre' => 'nombre de la patología',
            'sintomasSeleccionados' => 'síntomas',
            'sintomasSeleccionados.*.nombre' => 'nombre del síntoma',
            'obraSocialSeleccionada' => 'obra social',
            'obraSocialSeleccionada.nombre' => 'obra social',
            'busquedaObraSocial' => 'obra social',
        ];
    }

    public function updatedEsAdultoMayor($value)
    {
        if (!$value) {
            $this->viveSolo = true;
            $this->viveCon = null;
            $this->contactos = [];

            $this->resetValidation([
                'viveSolo',
                'viveCon',
                'contactos',
                'contactos.*'
            ]);
        }
    }

    public function updatedViveSolo($value)
    {
        if ($value) {
            $this->viveCon = null;
            $this->resetValidation('viveCon');
        }
    }

    public function agregarContacto()
    {
        if (count($this->contactos) < 3) {
            $this->contactos[] = [
                'clave' => uniqid(),
                'nombre' => '',
                'telefono' => '',
                'vinculo' => ''
            ];
        }
    }

    public function eliminarContacto($indice)
    {
        unset($this->contactos[$indice]);
        $this->contactos = array_values($this->contactos);
        $this->resetValidation('contactos.*');
    }

    public function almacenar()
    {
        $this->validate();

        $this->nombre = mb_convert_case(mb_strtolower(trim($this->nombre)), MB_CASE_TITLE, "UTF-8");
        $this->apellido = mb_convert_case(mb_strtolower(trim($this->apellido)), MB_CASE_TITLE, "UTF-8");
        $this->domicilio = mb_convert_case(mb_strtolower(trim($this->domicilio)), MB_CASE_TITLE, "UTF-8");
        $this->profesion = mb_convert_case(mb_strtolower(trim($this->profesion)), MB_CASE_TITLE, "UTF-8");

        $idsPatologias = $this->persistirPatologiasSeleccionadas();
        $idsSintomas = $this->persistirSintomasSeleccionados();

        try {
            $fueRestaurado = false;

            DB::transaction(function () use ($idsPatologias, $idsSintomas, &$fueRestaurado) {
                $existente = Paciente::withTrashed()
                    ->where('dni', $this->dni)
                    ->lockForUpdate()
                    ->first();

                if ($existente?->trashed()) {
                    $existente->restore();
                    $this->actualizarPacienteRestaurado($existente, $idsPatologias, $idsSintomas);
                    $fueRestaurado = true;

                    return;
                }

                if ($existente !== null) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'dni' => 'Ya existe un paciente con ese DNI.',
                    ]);
                }

                $paciente = Paciente::create([
                    'dni' => $this->dni,
                    'nombre' => $this->nombre,
                    'apellido' => $this->apellido,
                    'fecha_nac' => $this->fechaNac,
                    'domicilio' => $this->domicilio,
                    'telefono' => $this->telefono,
                    'profesion' => $this->profesion,
                    'actividad_fisica' => $this->actividadFisica,
                    'es_adulto_mayor' => $this->esAdultoMayor,
                    'vive_con' => $this->esAdultoMayor
                        ? ($this->viveSolo ? 'SOLO' : $this->viveCon)
                        : null
                ]);

                if ($this->esAdultoMayor && !empty($this->contactos)) {
                    $datosContactos = array_map(function ($cont) {
                        unset($cont['clave']);
                        $cont['nombre'] = mb_convert_case(mb_strtolower(trim($cont['nombre'])), MB_CASE_TITLE, "UTF-8");
                        return $cont;
                    }, $this->contactos);
                    $paciente->contactosEmergencia()->createMany($datosContactos);
                }

                $ahora = Carbon::now();

                if ($idsPatologias !== []) {
                    $paciente->patologias()->attach($idsPatologias, ['fecha_desde' => $ahora]);
                }

                if ($idsSintomas !== []) {
                    $paciente->sintomas()->attach($idsSintomas, ['fecha_desde' => $ahora]);
                }

                $this->persistirObraSocial($paciente);
            });

            $mensaje = $fueRestaurado
                ? 'Se restauró el paciente eliminado previamente con ese DNI.'
                : '¡El paciente ha sido registrado con éxito!';

            return redirect()->route('pacientes.inicio')->with('exito', $mensaje);
        } catch (\Illuminate\Validation\ValidationException $ex) {
            throw $ex;
        } catch (\Throwable $ex) {
            Log::error('[components.pacientes.crear@almacenar] Error al registrar el paciente', ['excepción' => $ex->getMessage()]);
            session()->flash('error', 'Ocurrió un error inesperado al intentar registrar el paciente.');
        }
    }

    /**
     * @param  list<int>  $idsPatologias
     * @param  list<int>  $idsSintomas
     */
    private function actualizarPacienteRestaurado(Paciente $paciente, array $idsPatologias, array $idsSintomas): void
    {
        $paciente->update([
            'dni' => $this->dni,
            'nombre' => $this->nombre,
            'apellido' => $this->apellido,
            'fecha_nac' => $this->fechaNac,
            'domicilio' => $this->domicilio,
            'telefono' => $this->telefono,
            'profesion' => $this->profesion,
            'actividad_fisica' => $this->actividadFisica,
            'es_adulto_mayor' => $this->esAdultoMayor,
            'vive_con' => $this->esAdultoMayor
                ? ($this->viveSolo ? 'SOLO' : $this->viveCon)
                : null,
        ]);

        $paciente->contactosEmergencia()->delete();

        if ($this->esAdultoMayor && !empty($this->contactos)) {
            $datosContactos = array_map(function ($cont) {
                unset($cont['clave']);
                $cont['nombre'] = mb_convert_case(mb_strtolower(trim($cont['nombre'])), MB_CASE_TITLE, "UTF-8");

                return $cont;
            }, $this->contactos);
            $paciente->contactosEmergencia()->createMany($datosContactos);
        }

        if ($idsPatologias !== []) {
            $paciente->patologias()->syncWithoutDetaching(
                collect($idsPatologias)->mapWithKeys(fn ($id) => [$id => ['fecha_desde' => now()]])->toArray()
            );
        }

        $sintomasActivosPaciente = $paciente->sintomasActivos()->pluck('sintomas.id')->toArray();
        $sintomasParaCrear = array_diff($idsSintomas, $sintomasActivosPaciente);

        if ($sintomasParaCrear !== []) {
            $paciente->sintomas()->attach(
                collect($sintomasParaCrear)->mapWithKeys(
                    fn ($id) => [$id => ['fecha_desde' => now()]]
                )->toArray()
            );
        }

        $this->persistirObraSocial($paciente);
    }
};
?>

<div class="contenedor max-w-lg">
    <form class="formulario" wire:submit="almacenar">
        <x-alerta tipo="error" />

        <h2 class="titulo-formulario">Registrar paciente</h2>

        <div class="mb-4 grid grid-cols-1 gap-y-5">
            <div class="columna-campo">
                <label for="input-dni" class="etiqueta-formulario">DNI</label>
                <input
                    id="input-dni"
                    type="text"
                    placeholder="Ingrese el DNI"
                    @class([
                        'entrada-simple',
                        'border-red-500 border-2' => $errors->has('dni')
                    ])
                    wire:model="dni"
                >
                @error('dni') <span class="mt-1 text-red-500 text-sm">{{ $message }}</span> @enderror
            </div>

            <div class="columna-campo">
                <label for="input-nombre" class="etiqueta-formulario">Nombre</label>
                <input
                    id="input-nombre"
                    type="text"
                    placeholder="Ingrese el nombre"
                    @class([
                        'entrada-simple',
                        'border-red-500 border-2' => $errors->has('nombre')
                    ])
                    wire:model="nombre"
                >
                @error('nombre') <span class="mt-1 text-red-500 text-sm">{{ $message }}</span> @enderror
            </div>

            <div class="columna-campo">
                <label for="input-apellido" class="etiqueta-formulario">Apellido</label>
                <input
                    id="input-apellido"
                    type="text"
                    placeholder="Ingrese el apellido"
                    @class([
                        'entrada-simple',
                        'border-red-500 border-2' => $errors->has('apellido')
                    ])
                    wire:model="apellido"
                >
                @error('apellido') <span class="mt-1 text-red-500 text-sm">{{ $message }}</span> @enderror
            </div>

            <div class="columna-campo">
                <label for="input-fecha-nac" class="etiqueta-formulario">Fecha de nacimiento</label>
                <input
                    id="input-fecha-nac"
                    type="date"
                    @class([
                        'entrada-simple',
                        'border-red-500 border-2' => $errors->has('fechaNac')
                    ])
                    wire:model="fechaNac"
                >
                @error('fechaNac') <span class="mt-1 text-red-500 text-sm">{{ $message }}</span> @enderror
            </div>

            <div class="columna-campo">
                <label for="input-domicilio" class="etiqueta-formulario">Domicilio</label>
                <input
                    id="input-domicilio"
                    type="text"
                    placeholder="Ejemplo: Pueyrredon 1586"
                    @class([
                        'entrada-simple',
                        'border-red-500 border-2' => $errors->has('domicilio')
                    ])
                    wire:model="domicilio"
                >
                @error('domicilio') <span class="mt-1 text-red-500 text-sm">{{ $message }}</span> @enderror
            </div>

            <div class="columna-campo">
                <label for="input-telefono" class="etiqueta-formulario">Teléfono</label>
                <input
                    id="input-telefono"
                    type="text"
                    placeholder="Ingrese el teléfono"
                    @class([
                        'entrada-simple',
                        'border-red-500 border-2' => $errors->has('telefono')
                    ])
                    wire:model="telefono"
                >
                @error('telefono') <span class="mt-1 text-red-500 text-sm">{{ $message }}</span> @enderror
            </div>

            <div class="columna-campo">
                <label for="input-profesion" class="etiqueta-formulario">Profesión</label>
                <input
                    id="input-profesion"
                    type="text"
                    placeholder="¿A qué se dedica?"
                    @class([
                        'entrada-simple',
                        'border-red-500 border-2' => $errors->has('profesion')
                    ])
                    wire:model="profesion"
                >
                @error('profesion') <span class="mt-1 text-red-500 text-sm">{{ $message }}</span> @enderror
            </div>

            <div class="columna-campo">
                <label for="input-actividad-fisica" class="etiqueta-formulario">Actividad física</label>
                <select
                    id="input-actividad-fisica"
                    @class([
                        'entrada-simple',
                        'border-red-500 border-2' => $errors->has('actividadFisica')
                    ])
                    wire:model="actividadFisica"
                >
                    <option value="">Seleccione una frecuencia</option>
                    @foreach(['Sedentario', 'Ocasional', 'Moderada', 'Intensa', 'Alto rendimiento/Competencia'] as $op)
                        <option value="{{ $op }}">{{ $op }}</option>
                    @endforeach
                </select>
                @error('actividadFisica') <span class="mt-1 text-red-500 text-sm">{{ $message }}</span> @enderror
            </div>

            @include('components.pacientes.buscador-obra-social')

            <div class="space-y-5">
                <div class="flex items-center gap-1">
                    <input
                        id="checkbox-adulto-mayor"
                        type="checkbox"
                        class="checkbox-formulario"
                        wire:model.live="esAdultoMayor"
                    >
                    <label for="checkbox-adulto-mayor" class="etiqueta-formulario">¿Es adulto mayor?</label>
                </div>

                @if($esAdultoMayor)
                    <div class="space-y-5">
                        <div class="flex items-center gap-1">
                            <input id="checkbox-vive-solo" class="checkbox-formulario" type="checkbox" wire:model.live="viveSolo">
                            <label for="checkbox-vive-solo" class="etiqueta-formulario">¿Vive solo?</label>
                        </div>

                        @if(!$viveSolo)
                            <div class="columna-campo">
                                <label for="input-vive-con" class="etiqueta-formulario">¿Con quién vive?</label>
                                <input
                                    id="input-vive-con"
                                    type="text"
                                    placeholder="Ejemplo: Juan (esposo), Mariana (hija)"
                                    @class([
                                        'entrada-simple',
                                        'border-red-500 border-2' => $errors->has('viveCon')
                                    ])
                                    wire:model="viveCon"
                                >
                                @error('viveCon') <span class="mt-1 text-red-500 text-sm">{{ $message }}</span> @enderror
                            </div>
                        @endif

                        @foreach($contactos as $indice => $contacto)
                            <div class="mb-5 pb-5 border-[#F5D500] border-b" wire:key="contacto-{{ $contacto['clave'] }}">
                                <div class="mb-4 flex items-center justify-between">
                                    <h3 class="text-[#F5D500] text-xl font-medium">Contacto de emergencia {{ $indice + 1 }}</h3>
                                    <button type="button" class="text-red-500 text-md hover:text-red-400" wire:click="eliminarContacto({{ $indice }})">Eliminar</button>
                                </div>

                                <div class="mb-4 columna-campo">
                                    <label for="contacto_{{ $indice }}_nombre" class="etiqueta-formulario">Nombre</label>
                                    <input
                                        id="contacto_{{ $indice }}_nombre"
                                        type="text"
                                        placeholder="Ingrese nombre del contacto"
                                        @class([
                                            'entrada-simple',
                                            'border-red-500 border-2' => $errors->has("contactos.{$indice}.nombre")
                                        ])
                                        wire:model="contactos.{{ $indice }}.nombre"
                                    >
                                    @error("contactos.{$indice}.nombre") <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                                </div>

                                <div class="mb-4 columna-campo">
                                    <label for="contacto_{{ $indice }}_telefono" class="etiqueta-formulario">Teléfono</label>
                                    <input
                                        id="contacto_{{ $indice }}_telefono"
                                        type="text"
                                        placeholder="Ingrese teléfono del contacto"
                                        @class([
                                            'entrada-simple',
                                            'border-red-500 border-2' => $errors->has("contactos.{$indice}.telefono")
                                        ])
                                        wire:model="contactos.{{ $indice }}.telefono"
                                    >
                                    @error("contactos.{$indice}.telefono") <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                                </div>

                                <div class="columna-campo">
                                    <label for="contacto_{{ $indice }}_vinculo" class="etiqueta-formulario">Vínculo</label>
                                    <select
                                        id="contacto_{{ $indice }}_vinculo"
                                        @class([
                                            'entrada-simple',
                                            'border-red-500 border-2' => $errors->has("contactos.{$indice}.vinculo")
                                        ])
                                        wire:model="contactos.{{ $indice }}.vinculo"
                                    >
                                        <option value="">¿Qué vínculo tiene con el paciente?</option>
                                        @foreach(['Cónyuge', 'Hijo/a', 'Hermano/a', 'Otro'] as $opcion)
                                            <option value="{{ $opcion }}">{{ $opcion }}</option>
                                        @endforeach
                                    </select>
                                    @error("contactos.{$indice}.vinculo") <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                                </div>
                            </div>
                        @endforeach

                        <div class="flex justify-center">
                            @if (count($contactos) < 3)
                                <button type="button" class="px-4 py-2 bg-blue-500 hover:bg-blue-700 text-white rounded" wire:click="agregarContacto">Añadir Contacto de Emergencia</button>
                            @else
                                <p class="mt-2 text-red-500 text-sm">Has alcanzado el máximo de contactos de emergencia.</p>
                            @endif
                        </div>
                    </div>
                @endif
            </div>

            <x-pacientes.buscador-patologias />

            <x-pacientes.buscador-sintomas />
        </div>

        <button type="submit" class="boton-registrar">Registrar</button>

        @if ($errors->any())
            <div class="mt-4 px-4 py-3 relative bg-red-100 border border-red-400 text-red-700 rounded">
                <strong class="font-bold">Errores de validación:</strong>
                <ul class="mt-2">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
    </form>
</div>
