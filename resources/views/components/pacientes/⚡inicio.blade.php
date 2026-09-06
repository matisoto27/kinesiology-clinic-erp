<?php

use App\Http\Resources\PacienteResource;
use App\Models\Paciente;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    #[Url(as: 'paciente')]
    public string $consultaPaciente = '';

    public ?int $idPacienteSeleccionado = null;

    public function updatingConsultaPaciente(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function pacientes()
    {
        return Paciente::query()
            ->select(['id', 'dni', 'nombre', 'apellido', 'fecha_nac', 'domicilio', 'telefono', 'profesion', 'created_at'])
            ->when(!empty($this->consultaPaciente), fn($consulta) => $consulta->buscarPorApNom($this->consultaPaciente))
            ->latest()
            ->paginate(10);
    }

    public function verDetalle(int $id): void
    {
        $this->idPacienteSeleccionado = $id;
    }

    public function cerrarDetalle(): void
    {
        $this->idPacienteSeleccionado = null;
    }

    #[Computed]
    public function detallePaciente(): ?array
    {
        if ($this->idPacienteSeleccionado === null) {
            return null;
        }

        $paciente = Paciente::query()
            ->with([
                'afiliacionVigente.obraSocial',
                'contactosEmergencia:id,nombre,telefono,vinculo,id_paciente',
                'patologias:id,nombre',
                'sintomasActivos:id,nombre',
            ])
            ->find($this->idPacienteSeleccionado);

        return $paciente ? (new PacienteResource($paciente))->resolve() : null;
    }
};
?>

<div class="contenedor-listado max-w-screen-3xl">
    <h2 class="titulo-formulario">Listado de Pacientes</h2>

    <div class="fila-formulario">
        <div class="columna-campo">
            <label for="buscar-paciente" class="etiqueta-formulario">Buscar Paciente</label>
            <input
                id="buscar-paciente"
                type="text"
                class="entrada w-[28ch]"
                placeholder="Ingrese nombre y/o apellido"
                wire:model.live.debounce.300ms="consultaPaciente"
            >
        </div>
    </div>

    <x-alerta tipo="exito" />

    <div>
        <table class="tabla-listado">
            <thead>
                <tr class="tabla-listado__cabecera">
                    <th>DNI</th>
                    <th>Nombre completo</th>
                    <th>Nacimiento</th>
                    <th>Edad</th>
                    <th>Domicilio</th>
                    <th>Teléfono</th>
                    <th>Profesión</th>
                    <th>Fecha de ingreso</th>
                    <th>Ver más</th>
                    <th colspan="2">Acciones</th>
                </tr>
            </thead>

            <tbody>
                @forelse($this->pacientes as $pac)
                    <tr class="tabla-listado__fila" wire:key="paciente-{{ $pac->id }}">
                        <td>{{ $pac->dni }}</td>
                        <td>{{ $pac->apellido_nombre }}</td>
                        <td>{{ $pac->fecha_nacimiento }}</td>
                        <td>{{ $pac->edad }}</td>
                        <td>{{ $pac->domicilio }}</td>
                        <td>{{ $pac->telefono }}</td>
                        <td class="break-words">
                            {{ $pac->profesion }}
                        </td>
                        <td>{{ $pac->fecha_ingreso }}</td>
                        <td>
                            <div class="flex justify-center items-center">
                                <button type="button" wire:click="verDetalle({{ $pac->id }})">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <circle cx="12" cy="12" r="10"></circle>
                                        <line x1="12" y1="16" x2="12" y2="12"></line>
                                        <line x1="12" y1="8" x2="12.01" y2="8"></line>
                                    </svg>
                                </button>
                            </div>
                        </td>
                        <td colspan="2" class="py-3">
                            <div class="flex justify-center items-center gap-25">
                                <a href="{{ route('pacientes.editar', ['paciente' => $pac->id]) }}" class="accion-editar">
                                    <x-iconos.lapiz />
                                </a>
                                <form action="{{ route('pacientes.eliminar', ['paciente' => $pac->id]) }}" method="POST" onsubmit="return confirm('¿Desea eliminar a este paciente?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-white hover:text-red-400 transition-colors duration-200">
                                        <x-iconos.basura />
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="10" class="py-10 text-center text-gray-300 italic">
                            {{ $this->consultaPaciente !== '' ? 'No se encontraron pacientes.' : 'No hay registros disponibles.' }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <div class="mt-4">
            {{ $this->pacientes->links(data: ['scrollTo' => false]) }}
        </div>

        @if($this->detallePaciente)
            @php($datos = $this->detallePaciente)
            <div class="modal-informativo" wire:keydown.escape.window="cerrarDetalle">
                <div class="modal-informativo__ventana" wire:click.outside="cerrarDetalle">
                    <button class="modal-informativo__cerrar" wire:click="cerrarDetalle">
                        <x-iconos.cruz />
                    </button>

                    <h2 class="modal-informativo__titulo">Información de {{ $datos['apellido_nombre'] }}</h2>

                    <div class="space-y-3">
                        <div class="modal-informativo__seccion flex justify-between items-center">
                            <div>
                                <p class="modal-informativo__etiqueta">¿Realiza actividad física?</p>
                                <p class="modal-informativo__valor">{{ $datos['actividad_fisica'] }}</p>
                            </div>
                            <div class="text-[#006E6B]">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>
                            </div>
                        </div>

                        <div class="modal-informativo__seccion">
                            <p class="modal-informativo__etiqueta">¿Es adulto mayor?</p>
                            <p class="modal-informativo__valor">{{ $datos['es_adulto_mayor'] ? 'Si' : 'No' }}</p>

                            @if($datos['es_adulto_mayor'])
                                <div class="mt-2 pt-2 space-y-2 border-gray-200 border-t">
                                    <div>
                                        <p class="modal-informativo__etiqueta">¿Con quién vive?</p>
                                        <p class="modal-informativo__valor">{{ $datos['vive_con'] }}</p>
                                    </div>
                                    <div>
                                        <p class="mb-1 modal-informativo__etiqueta">Contactos de Emergencia</p>
                                        <div class="space-y-2">
                                            @forelse($datos['contactos_emergencia'] ?? [] as $contacto)
                                                <div class="p-2 bg-gray-50 border-gray-200 border rounded-lg" wire:key="contacto-{{ $contacto['id'] }}">
                                                    <div class="flex justify-between">
                                                        <div>
                                                            <p class="text-[#3A8F8E] text-sm font-semibold">{{ $contacto['vinculo'] }}</p>
                                                            <p class="modal-informativo__valor">{{ $contacto['nombre'] }}</p>
                                                        </div>
                                                        <div class="text-right">
                                                            <p class="modal-informativo__etiqueta">Teléfono</p>
                                                            <p class="modal-informativo__valor">{{ $contacto['telefono'] }}</p>
                                                        </div>
                                                    </div>
                                                </div>
                                            @empty
                                                <div class="modal-informativo__sin-valor">
                                                    No hay contactos de emergencia registrados.
                                                </div>
                                            @endforelse
                                        </div>
                                    </div>
                                </div>
                            @endif
                        </div>

                        <div class="modal-informativo__seccion">
                            <p class="mb-2 modal-informativo__etiqueta">Obra Social</p>

                            @if($datos['obra_social'])
                                <p class="modal-informativo__valor">{{ $datos['obra_social'] }}</p>
                            @else
                                <p class="modal-informativo__sin-valor">Sin una obra social registrada.</p>
                            @endif
                        </div>

                        <div class="modal-informativo__seccion">
                            <p class="mb-2 modal-informativo__etiqueta">¿Tiene algún antecedente patológico?</p>

                            <div class="space-y-3">
                                @foreach($datos['patologias'] ?? [] as $patologia)
                                    <div class="modal-informativo__elemento-lista" wire:key="patologia-{{ $patologia['id'] }}">
                                        <p class="modal-informativo__etiqueta">{{ $patologia['fecha_desde'] }}</p>
                                        <p class="modal-informativo__valor">{{ $patologia['nombre'] }}</p>
                                    </div>
                                @endforeach
                            </div>

                            @if(empty($datos['patologias']))
                                <div class="modal-informativo__sin-valor">
                                    Sin antecedentes patológicos.
                                </div>
                            @endif
                        </div>

                        <div class="modal-informativo__seccion">
                            <p class="mb-2 modal-informativo__etiqueta">¿Presenta algún síntoma?</p>

                            <div class="space-y-3">
                                @foreach($datos['sintomas'] ?? [] as $sintoma)
                                    <div class="modal-informativo__elemento-lista" wire:key="sintoma-{{ $sintoma['id'] }}">
                                        <p class="modal-informativo__etiqueta">{{ $sintoma['fecha_desde'] }}</p>
                                        <p class="modal-informativo__valor">{{ $sintoma['nombre'] }}</p>
                                    </div>
                                @endforeach
                            </div>

                            @if(empty($datos['sintomas']))
                                <div class="modal-informativo__sin-valor">
                                    No registra síntomas activos.
                                </div>
                            @endif
                        </div>
                    </div>

                    <div class="modal-informativo__seccion-acciones">
                        <button
                            class="modal-informativo__accion bg-gray-100 hover:bg-gray-200 text-gray-700"
                            wire:click="cerrarDetalle"
                        >
                                Cerrar
                        </button>
                        <a
                            href="{{ route('pacientes.editar', ['paciente' => $datos['id']]) }}"
                            class="modal-informativo__accion bg-[#3A8F8E] hover:bg-[#014745] text-white"
                        >
                            Editar paciente
                        </a>
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>
