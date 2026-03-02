<?php

use App\Models\Paciente;
use App\Models\Patologia;
use App\Models\TipoSintoma;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

new class extends Component
{
    #[Locked]
    public Paciente $paciente;

    public $dni;
    public $nombre;
    public $apellido;
    public $fecha_nac;
    public $domicilio;
    public $telefono;
    public $profesion;
    public $actividad_fisica;
    public $es_adulto_mayor;
    public $vive_solo;
    public $vive_con;
    public $contactos = [];
    public $patologiasPreexistentes = [];
    public $patologias = [];
    public $sintomas = [];

    public function mount(Paciente $paciente)
    {
        $this->paciente = $paciente;
        $this->dni = $paciente->dni;
        $this->nombre = $paciente->nombre;
        $this->apellido = $paciente->apellido;
        $this->fecha_nac = $paciente->fecha_nac->format('Y-m-d');
        $this->domicilio = $paciente->domicilio;
        $this->telefono = $paciente->telefono;
        $this->profesion = $paciente->profesion;
        $this->actividad_fisica = $paciente->actividad_fisica;
        $this->es_adulto_mayor = (bool) $paciente->es_adulto_mayor;

        if ($this->es_adulto_mayor) {
            $this->vive_solo = $paciente->vive_con === 'SOLO';
            $this->vive_con = $this->vive_solo ? null : $paciente->vive_con;
            $this->contactos = $paciente->contactosEmergencia->map(function ($cont) {
                return [
                    'id' => $cont->id ?? null,
                    'clave' => uniqid(),
                    'nombre' => $cont->nombre,
                    'telefono' => $cont->telefono,
                    'vinculo' => $cont->vinculo
                ];
            })->toArray();
        } else {
            $this->vive_solo = true;
            $this->vive_con = null;
            $this->contactos = [];
        }

        $this->patologiasPreexistentes = $paciente->patologias->pluck('id')->toArray();
        $this->patologias = $this->patologiasPreexistentes;
        $this->sintomas = $paciente->sintomasActivos->pluck('id')->toArray();
    }

    #[Computed]
    public function todasPatologias()
    {
        return Patologia::where('activo', true)->orderBy('nombre')->get();
    }

    #[Computed]
    public function tiposSintomas()
    {
        return TipoSintoma::where('activo', true)->with('sintomasActivos')->get();
    }

    protected function rules()
    {
        return [
            'dni' => 'required|numeric|digits_between:7,8|unique:pacientes,dni,' . $this->paciente->id,
            'nombre' => 'required|regex:/^[A-Za-záéíóúÁÉÍÓÚñÑ\s]+$/|max:30',
            'apellido' => 'required|regex:/^[A-Za-záéíóúÁÉÍÓÚñÑ\s]+$/|max:30',
            'fecha_nac' => 'required|date|before:today',
            'domicilio' => 'required|string|regex:/^[A-Za-z0-9\s.,áéíóúÁÉÍÓÚñÑ#-]+$/|max:100',
            'telefono' => 'required|numeric|digits_between:8,20',
            'profesion' => 'required|string|max:40',
            'actividad_fisica' => 'required|string|in:Sedentario,Ocasional,Moderada,Intensa,Alto rendimiento/Competencia',
            'es_adulto_mayor' => 'required|boolean',
            'vive_solo' => 'exclude_if:es_adulto_mayor,false|boolean',
            'vive_con' => 'exclude_if:es_adulto_mayor,false|required_if:vive_solo,false|nullable|string|regex:/^[A-Za-z0-9\s.,()áéíóúÁÉÍÓÚñÑ]+$/|min:1|max:150',
            'contactos' => 'exclude_if:es_adulto_mayor,false|nullable|array|max:3',
            'contactos.*.nombre' => 'required_with:contactos|regex:/^[A-Za-záéíóúÁÉÍÓÚñÑ\s]+$/|max:100',
            'contactos.*.telefono' => 'required_with:contactos|numeric|digits_between:8,20',
            'contactos.*.vinculo' => 'required_with:contactos|string|in:Cónyuge,Hijo/a,Hermano/a,Otro',
            'patologias' => 'nullable|array',
            'patologias.*' => 'numeric|exists:patologias,id',
            'sintomas' => 'nullable|array',
            'sintomas.*' => 'numeric|exists:sintomas,id'
        ];
    }

    protected function messages()
    {
        return [
            'nombre.regex' => 'El nombre solo puede contener letras y espacios.',
            'apellido.regex' => 'El apellido solo puede contener letras y espacios.',
            'vive_con.required_if' => 'Por favor, especifique con quién vive el paciente.'
        ];
    }

    protected function validationAttributes()
    {
        return [
            'dni' => 'DNI',
            'fecha_nac' => 'fecha de nacimiento',
            'telefono' => 'teléfono',
            'profesion' => 'profesión',
            'actividad_fisica' => 'actividad física',
            'vive_con' => 'detalle con quién vive',
            'contactos' => 'contactos de emergencia',
            'contactos.*.nombre' => 'nombre del contacto',
            'contactos.*.telefono' => 'teléfono del contacto',
            'contactos.*.vinculo' => 'vínculo del contacto',
            'sintomas' => 'síntomas'
        ];
    }

    public function updatedEsAdultoMayor($value)
    {
        if (!$value) {
            $this->vive_solo = true;
            $this->vive_con = null;
            $this->contactos = [];

            $this->resetValidation([
                'vive_solo',
                'vive_con',
                'contactos',
                'contactos.*'
            ]);
        }
    }

    public function updatedViveSolo($value)
    {
        if ($value) {
            $this->vive_con = null;
            $this->resetValidation('vive_con');
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

    public function actualizar()
    {
        $this->validate();

        $this->nombre = mb_convert_case(mb_strtolower(trim($this->nombre)), MB_CASE_TITLE, "UTF-8");
        $this->apellido = mb_convert_case(mb_strtolower(trim($this->apellido)), MB_CASE_TITLE, "UTF-8");
        $this->domicilio = mb_convert_case(mb_strtolower(trim($this->domicilio)), MB_CASE_TITLE, "UTF-8");
        $this->profesion = mb_convert_case(mb_strtolower(trim($this->profesion)), MB_CASE_TITLE, "UTF-8");

        try {
            DB::transaction(function () {
                if ($this->es_adulto_mayor) {
                    $contactos = collect($this->contactos);
                    $idsContactos = $contactos->pluck('id')->filter()->toArray();

                    $this->paciente->contactosEmergencia()->whereNotIn('id', $idsContactos)->delete();

                    foreach ($contactos as $cont) {
                        $this->paciente->contactosEmergencia()->updateOrCreate(
                            ['id' => $cont['id'] ?? null],
                            [
                                'nombre'   => mb_convert_case(mb_strtolower(trim($cont['nombre'])), MB_CASE_TITLE, "UTF-8"),
                                'telefono' => $cont['telefono'],
                                'vinculo'  => $cont['vinculo']
                            ]
                        );
                    }
                } else {
                    $this->paciente->contactosEmergencia()->delete();
                }

                $this->paciente->update([
                    'dni' => $this->dni,
                    'nombre' => $this->nombre,
                    'apellido' => $this->apellido,
                    'fecha_nac' => $this->fecha_nac,
                    'domicilio' => $this->domicilio,
                    'telefono' => $this->telefono,
                    'profesion' => $this->profesion,
                    'actividad_fisica' => $this->actividad_fisica,
                    'es_adulto_mayor' => $this->es_adulto_mayor,
                    'vive_con' => $this->es_adulto_mayor
                        ? ($this->vive_solo ? 'SOLO' : $this->vive_con)
                        : null
                ]);

                if (!empty($this->patologias)) {
                    $this->paciente->patologias()->syncWithoutDetaching(
                        collect($this->patologias)->mapWithKeys(function ($id) {
                            return [$id => ['fecha_desde' => now()]];
                        })->toArray()
                    );
                }

                $sintomasEnviados = $this->sintomas ?? [];
                $sintomasActivosPaciente = $this->paciente->sintomasActivos()->pluck('sintomas.id')->toArray();

                $sintomasAFinalizar = array_diff($sintomasActivosPaciente, $sintomasEnviados);
                if (!empty($sintomasAFinalizar)) {
                    foreach ($sintomasAFinalizar as $idSintoma) {
                        $this->paciente->sintomas()
                            ->wherePivotNull('fecha_hasta')
                            ->updateExistingPivot($idSintoma, [
                                'fecha_hasta' => now()
                            ]);
                    }
                }

                $sintomasParaCrear = array_diff($sintomasEnviados, $sintomasActivosPaciente);
                if (!empty($sintomasParaCrear)) {
                    $this->paciente->sintomas()->attach(
                        collect($sintomasParaCrear)->mapWithKeys(function ($id) {
                            return [$id => ['fecha_desde' => now()]];
                        })->toArray()
                    );
                }
            });

            return redirect()->route('pacientes.inicio')->with('exito', '¡La información del paciente ha sido actualizada con éxito!');
        } catch (\Throwable $ex) {
            if ($ex instanceof \Illuminate\Database\QueryException && $ex->errorInfo[1] == 1062) {
                $mensajeError = "No puedes registrar el mismo síntoma dos veces en la misma fecha.";
            } else {
                $mensajeError = $ex->getMessage();
            }

            DB::rollBack();
            Log::error('[components.pacientes.editar@actualizar] Error al actualizar la información del paciente', ['excepción' => $ex->getMessage()]);
            session()->flash('error', $mensajeError);
        }
    }
};
?>

<div class="contenedor max-w-lg">
    <form class="formulario" wire:submit="actualizar">
        <x-alerta tipo="error" />

        <h2 class="titulo-formulario">Editar información del paciente</h2>

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
                        'border-red-500 border-2' => $errors->has('fecha_nac')
                    ])
                    wire:model="fecha_nac"
                >
                @error('fecha_nac') <span class="mt-1 text-red-500 text-sm">{{ $message }}</span> @enderror
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
                        'border-red-500 border-2' => $errors->has('actividad_fisica')
                    ])
                    wire:model="actividad_fisica"
                >
                    <option value="">Seleccione una frecuencia</option>
                    @foreach(['Sedentario', 'Ocasional', 'Moderada', 'Intensa', 'Alto rendimiento/Competencia'] as $op)
                        <option value="{{ $op }}">{{ $op }}</option>
                    @endforeach
                </select>
                @error('actividad_fisica') <span class="mt-1 text-red-500 text-sm">{{ $message }}</span> @enderror
            </div>

            <div class="space-y-5">
                <div class="flex items-center gap-1">
                    <input
                        id="checkbox-adulto-mayor"
                        type="checkbox"
                        class="checkbox-formulario"
                        wire:model.live="es_adulto_mayor"
                    >
                    <label for="checkbox-adulto-mayor" class="etiqueta-formulario">¿Es adulto mayor?</label>
                </div>

                @if($es_adulto_mayor)
                    <div class="space-y-5">
                        <div class="flex items-center gap-1">
                            <input id="checkbox-vive-solo" class="checkbox-formulario" type="checkbox" wire:model.live="vive_solo">
                            <label for="checkbox-vive-solo" class="etiqueta-formulario">¿Vive solo?</label>
                        </div>

                        @if(!$vive_solo)
                            <div class="columna-campo">
                                <label for="input-vive-con" class="etiqueta-formulario">¿Con quién vive?</label>
                                <input
                                    id="input-vive-con"
                                    type="text"
                                    placeholder="Ejemplo: Juan (esposo), Mariana (hija)"
                                    @class([
                                        'entrada-simple',
                                        'border-red-500 border-2' => $errors->has('vive_con')
                                    ])
                                    wire:model="vive_con"
                                >
                                @error('vive_con') <span class="mt-1 text-red-500 text-sm">{{ $message }}</span> @enderror
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

            <div class="columna-campo">
                <h3 class="mb-2 text-white text-xl font-semibold">Patologías (Opcional)</h3>
                <div class="space-y-4">
                    @foreach ($this->todasPatologias as $pat)
                        <div class="flex items-center gap-2">
                            @if (in_array($pat->id, $this->patologiasPreexistentes))
                                <input type="checkbox" class="checkbox-formulario" checked disabled />
                                <label class="text-white">{{ $pat->nombre }}</label>
                            @else
                                <input
                                    id="patologia-{{ $pat->id }}"
                                    type="checkbox"
                                    class="checkbox-formulario"
                                    value="{{ $pat->id }}"
                                    wire:model="patologias"
                                />
                                <label for="patologia-{{ $pat->id }}" class="text-white">
                                    {{ $pat->nombre }}
                                </label>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
            @error('patologias') <div class="text-red-500 text-md">{{ $message }}</div> @enderror
            @error('patologias.*') <div class="text-red-500 text-md">{{ $message }}</div> @enderror

            <div class="columna-campo">
                <label class="etiqueta-formulario">¿Cuáles síntomas presenta el paciente? (Opcional)</label>
                <div class="grid grid-cols-1 gap-4">
                    @foreach ($this->tiposSintomas as $tipo)
                        @if (!$tipo->sintomasActivos->isEmpty())
                            <div class="p-4 bg-[#3A8F8E] rounded-md shadow-lg">
                                <h3 class="mb-2 text-white text-xl font-semibold">{{ $tipo->nombre }}</h3>
                                <div class="space-y-4">
                                    @foreach ($tipo->sintomasActivos as $sintoma)
                                        <div class="flex items-center gap-2">
                                            <input id="sintoma-{{ $sintoma->id }}" type="checkbox" class="checkbox-formulario" value="{{ $sintoma->id }}" wire:model="sintomas">
                                            <label for="sintoma-{{ $sintoma->id }}" class="text-white">{{ $sintoma->nombre }}</label>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    @endforeach
                </div>
            </div>
            @error('sintomas') <div class="text-red-500 text-md">{{ $message }}</div> @enderror
        </div>

        <button type="submit" class="boton-registrar">Actualizar</button>

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
