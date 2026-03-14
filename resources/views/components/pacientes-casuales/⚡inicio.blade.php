<?php

use App\Models\PacienteCasual;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    #[Url(as: 'busqueda')]
    public string $busqueda = '';

    public function updatingBusqueda()
    {
        $this->resetPage();
    }

    #[Computed]
    public function pacientesCasuales()
    {
        return PacienteCasual::query()
            ->when(!empty($this->busqueda), function($consulta) {
                $consulta->buscarPorApNom($this->busqueda);
            })
            ->orderBy('apellido')
            ->orderBy('nombre')
            ->paginate(10);
    }

    public function eliminar(int $id)
    {
        try {
            DB::transaction(function () use ($id) {
                $paciente = PacienteCasual::findOrFail($id);
                $paciente->delete();
            });

            session()->flash('exito', 'El paciente ha sido eliminado correctamente.');

        } catch (\Throwable $th) {
            Log::error('[(Livewire) pacientes-casuales.inicio@eliminar] Error al eliminar el paciente.', [
                'id' => $id,
                'excepción' => $th->getMessage()
            ]);
            session()->flash('error', 'Error interno del servidor. Si el error persiste contactar con el Equipo de Soporte (Matías).');
        }
    }
};
?>

<div class="contenedor-listado">
    <h2 class="titulo-formulario">Listado de Pacientes Casuales</h2>

    <x-alerta tipo="exito" />
    <x-alerta tipo="error" />

    <div class="fila-formulario">
        <div class="columna-campo">
            <label for="buscar-paciente" class="etiqueta-formulario">Buscar Paciente</label>
            <input
                id="buscar-paciente"
                type="text"
                placeholder="Ingrese nombre y/o apellido"
                class="entrada w-xs"
                wire:model.live.debounce.300ms="busqueda"
            >
        </div>
    </div>

    <table class="tabla-listado">
        <thead>
            <tr class="tabla-listado__cabecera">
                <th>Nombre y Apellido</th>
                <th>Teléfono</th>
                <th>Fecha de registro</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            @forelse($this->pacientesCasuales as $pac)
                <tr class="tabla-listado__fila" wire:key="paciente-casual-{{ $pac->id }}">
                    <td>{{ $pac->nombre }}, {{ $pac->apellido }}</td>
                    <td>{{ $pac->telefono }}</td>
                    <td>{{ $pac->created_at->format('d/m/Y') }}</td>
                    <td>
                        <div class="centrado-total space-x-4">
                            <a href="{{ route('pacientes-casuales.editar', ['paciente' => $pac->id]) }}" class="accion-editar">
                                <x-iconos.lapiz />
                            </a>
                            <button
                                type="button"
                                class="text-white hover:text-red-400 transition-colors duration-200"
                                wire:click="eliminar({{ $pac->id }})"
                                wire:confirm="¿Estás seguro de que deseas eliminar a este paciente?">
                                <x-iconos.basura />
                            </button>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" class="py-10 text-center text-gray-300 italic">No se encontraron pacientes casuales.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="mt-4">
        {{ $this->pacientesCasuales->links(data: ['scrollTo' => false]) }}
    </div>
</div>
