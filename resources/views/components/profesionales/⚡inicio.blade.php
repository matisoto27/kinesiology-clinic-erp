<?php

use App\Models\Profesional;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    #[Url(as: 'estado')]
    public string $filtroEstado = 'todos';

    public function updatingFiltroEstado(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function profesionales()
    {
        return Profesional::query()
            ->when($this->filtroEstado === 'activos', fn ($consulta) => $consulta->where('activo', true))
            ->when($this->filtroEstado === 'inactivos', fn ($consulta) => $consulta->where('activo', false))
            ->orderByDesc('nombre')
            ->paginate(10);
    }

    public function eliminar(int $id)
    {
        try {
            DB::transaction(function () use ($id) {
                $profesional = Profesional::findOrFail($id);
                $profesional->delete();
            });

            session()->flash('exito', 'El profesional ha sido eliminado correctamente.');
        } catch (\Throwable $ex) {
            Log::error('[(Livewire) profesionales.inicio@eliminar] Error al eliminar el profesional.', [
                'id' => $id,
                'excepción' => $ex->getMessage()
            ]);
            session()->flash('error', 'Error interno del servidor. Si el error persiste contactar con el Equipo de Soporte (Matías).');
        }
    }
};
?>

<div class="contenedor-listado max-w-screen-3xl">
    <h2 class="titulo-formulario">Lista de profesionales</h2>

    <x-alerta tipo="exito" />
    <x-alerta tipo="error" />

    <div class="fila-formulario">
        <div class="columna-campo">
            <label for="filtro-estado" class="etiqueta-formulario">Filtrar por estado</label>
            <select id="filtro-estado" class="entrada" wire:model.live="filtroEstado">
                <option value="todos">Todos</option>
                <option value="activos">Solo Activos</option>
                <option value="inactivos">Solo Inactivos</option>
            </select>
        </div>
    </div>

    <table class="tabla-listado">
        <thead>
            <tr class="tabla-listado__cabecera">
                <th>DNI</th>
                <th>Nombre</th>
                <th>Apellido</th>
                <th>Estado</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            @forelse($this->profesionales as $prof)
                <tr class="tabla-listado__fila" wire:key="profesional-{{ $prof->id }}">
                    <td>{{ $prof->dni }}</td>
                    <td>{{ $prof->nombre }}</td>
                    <td>{{ $prof->apellido }}</td>
                    <td>
                        <span class="px-3 py-1 inline-flex items-center {{ $prof->activo ? 'bg-emerald-500' : 'bg-amber-500' }} text-white text-sm font-semibold rounded">
                            {{ $prof->activo ? 'Activo' : 'Inactivo' }}
                        </span>
                    </td>
                    <td>
                        <div class="flex justify-center items-center space-x-4">
                            <a href="{{ route('profesionales.editar', ['profesional' => $prof->id]) }}" class="accion-editar">
                                <x-iconos.lapiz />
                            </a>
                            <button
                                type="button"
                                class="text-white hover:text-red-400 transition-colors duration-200"
                                wire:click="eliminar({{ $prof->id }})"
                                wire:confirm="¿Estás seguro de que deseas eliminar a este profesional?">
                                <x-iconos.basura />
                            </button>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="py-10 text-center text-gray-300 italic">No se encontraron profesionales para el filtro seleccionado.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="mt-4">
        {{ $this->profesionales->links(data: ['scrollTo' => false]) }}
    </div>
</div>
