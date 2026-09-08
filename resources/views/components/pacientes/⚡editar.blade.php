<?php

use App\Models\Paciente;
use App\Livewire\Concerns\ManejaBuscadorObraSocial;
use App\Livewire\Concerns\ManejaBuscadorPatologias;
use App\Livewire\Concerns\ManejaBuscadorSintomas;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;

new class extends Component
{
    use ManejaBuscadorObraSocial;
    use ManejaBuscadorPatologias;
    use ManejaBuscadorSintomas;

    #[Locked]
    public Paciente $paciente;

    public $dni;
    public $nombre;
    public $apellido;
    public $fechaNac;
    public $domicilio;
    public $telefono;
    public $profesion;
    public $actividadFisica;
    public $esAdultoMayor;
    public $viveSolo;
    public $viveCon;
    public $contactos = [];
    public $erroresJs = [];

    public function mount(Paciente $paciente)
    {
        $this->paciente = $paciente;
        $this->dni = $paciente->dni;
        $this->nombre = $paciente->nombre;
        $this->apellido = $paciente->apellido;
        $this->fechaNac = $paciente->fecha_nac->format('Y-m-d');
        $this->domicilio = $paciente->domicilio;
        $this->telefono = $paciente->telefono;
        $this->profesion = $paciente->profesion;
        $this->actividadFisica = $paciente->actividad_fisica;
        $this->esAdultoMayor = (bool) $paciente->es_adulto_mayor;

        if ($this->esAdultoMayor) {
            $this->viveSolo = $paciente->vive_con === 'SOLO';
            $this->viveCon = $this->viveSolo ? null : $paciente->vive_con;
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
            $this->viveSolo = true;
            $this->viveCon = null;
            $this->contactos = [];
        }

        $this->patologiasPreexistentesIds = $paciente->patologias->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
        $this->patologiasSeleccionadas = $paciente->patologias->map(fn ($patologia) => [
            'id' => $patologia->id,
            'nombre' => $patologia->nombre,
            'es_nuevo' => false,
            'bloqueada' => true,
        ])->values()->all();
        $this->sintomasSeleccionados = $paciente->sintomasActivos->map(fn ($sintoma) => [
            'id' => $sintoma->id,
            'nombre' => $sintoma->nombre,
            'es_nuevo' => false,
        ])->values()->all();

        $this->hidratarObraSocialSeleccionada(
            $paciente->afiliacionVigente()->with('obraSocial')->first()
        );
    }

    protected function rules()
    {
        return array_merge([
            'dni' => [
                'required',
                'digits_between:7,8',
                Rule::unique('pacientes', 'dni')
                    ->whereNull('deleted_at')
                    ->ignore($this->paciente->id),
            ],
            'nombre' => 'required|regex:/^[A-Za-záéíóúÁÉÍÓÚñÑ\s]+$/|max:30',
            'apellido' => 'required|regex:/^[A-Za-záéíóúÁÉÍÓÚñÑ\s]+$/|max:30',
            'fechaNac' => 'required|date|before:today',
            'domicilio' => 'required|string|regex:/^[A-Za-z0-9\s.,áéíóúÁÉÍÓÚñÑ#-]+$/|max:100',
            'telefono' => 'required|digits_between:8,20',
            'profesion' => 'required|string|max:40',
            'actividadFisica' => 'required|string|in:Sedentario,Ocasional,Moderada,Intensa,Alto rendimiento/Competencia',
            'esAdultoMayor' => 'required|boolean',
            'viveSolo' => 'exclude_if:esAdultoMayor,false|boolean',
            'viveCon' => 'exclude_if:esAdultoMayor,false|required_if:viveSolo,false|nullable|string|regex:/^[A-Za-z0-9\s.,()áéíóúÁÉÍÓÚñÑ]+$/|min:1|max:150',
            'contactos' => 'exclude_if:esAdultoMayor,false|nullable|array|max:3',
            'contactos.*.nombre' => 'required_with:contactos|regex:/^[A-Za-záéíóúÁÉÍÓÚñÑ\s]+$/|max:100',
            'contactos.*.telefono' => 'required_with:contactos|digits_between:8,20',
            'contactos.*.vinculo' => 'required_with:contactos|string|in:Cónyuge,Hijo/a,Hermano/a,Otro',
        ], $this->reglasPatologiasSeleccionadas(), $this->reglasSintomasSeleccionados(), $this->reglasObraSocialSeleccionada());
    }

    protected function messages()
    {
        return [
            'dni.unique' => 'Ya existe un paciente con ese DNI.',
            'nombre.regex' => 'El nombre solo puede contener letras y espacios.',
            'apellido.regex' => 'El apellido solo puede contener letras y espacios.',
            'viveCon.required_if' => 'Por favor, especifique con quién vive el paciente.'
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

    public function actualizar()
    {
        if (!$this->esAdultoMayor) {
            $this->viveSolo = true;
            $this->viveCon = null;
            $this->contactos = [];
        } elseif ($this->viveSolo) {
            $this->viveCon = null;
        }

        $this->validate();

        $this->nombre = mb_convert_case(mb_strtolower(trim($this->nombre)), MB_CASE_TITLE, "UTF-8");
        $this->apellido = mb_convert_case(mb_strtolower(trim($this->apellido)), MB_CASE_TITLE, "UTF-8");
        $this->domicilio = mb_convert_case(mb_strtolower(trim($this->domicilio)), MB_CASE_TITLE, "UTF-8");
        $this->profesion = mb_convert_case(mb_strtolower(trim($this->profesion)), MB_CASE_TITLE, "UTF-8");

        $idsPatologias = $this->persistirPatologiasSeleccionadas();
        $idsSintomas = $this->persistirSintomasSeleccionados();

        try {
            DB::transaction(function () use ($idsPatologias, $idsSintomas) {
                if ($this->esAdultoMayor) {
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

                if ($idsPatologias !== []) {
                    $this->paciente->patologias()->syncWithoutDetaching(
                        collect($idsPatologias)->mapWithKeys(fn ($id) => [$id => ['fecha_desde' => now()]])->toArray()
                    );
                }

                $sintomasActivosPaciente = $this->paciente->sintomasActivos()->pluck('sintomas.id')->toArray();

                $sintomasAFinalizar = array_diff($sintomasActivosPaciente, $idsSintomas);
                if (!empty($sintomasAFinalizar)) {
                    foreach ($sintomasAFinalizar as $idSintoma) {
                        $this->paciente->sintomas()
                            ->wherePivotNull('fecha_hasta')
                            ->updateExistingPivot($idSintoma, [
                                'fecha_hasta' => now()
                            ]);
                    }
                }

                $sintomasParaCrear = array_diff($idsSintomas, $sintomasActivosPaciente);
                if (!empty($sintomasParaCrear)) {
                    $this->paciente->sintomas()->attach(
                        collect($sintomasParaCrear)->mapWithKeys(function ($id) {
                            return [$id => ['fecha_desde' => now()]];
                        })->toArray()
                    );
                }

                $this->persistirObraSocial($this->paciente);
            });

            return redirect()->route('pacientes.inicio')->with('exito', '¡La información del paciente ha sido actualizada con éxito!');
        } catch (\Illuminate\Validation\ValidationException $ex) {
            throw $ex;
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

    public function rendering(): void
    {
        $this->erroresJs = collect($this->getErrorBag()->messages())
            ->map(fn ($mensajes) => $mensajes[0])
            ->toArray();
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

            @include('components.pacientes.partials.adulto-mayor')

            <x-pacientes.buscador-patologias />

            <x-pacientes.buscador-sintomas />
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
