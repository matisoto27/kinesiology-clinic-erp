<?php

use App\Models\RegistroHoras;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    #[Url(as: 'profesional')]
    public string $consultaProfesional = '';

    public bool $mostrarModal = false;
    public ?RegistroHoras $registroSeleccionado = null;
    public int $nuevaCantidadHoras = 0;

    #[Computed]
    public function registros()
    {
        return RegistroHoras::query()
            ->with('profesional')
            ->when(!empty($this->consultaProfesional), function ($consulta) {
                $consulta->whereHas('profesional', function ($subconsulta) {
                    $subconsulta->where(DB::raw("CONCAT(apellido, ' ', nombre)"), 'LIKE', "%{$this->consultaProfesional}%");
                });
            })
            ->orderByDesc('fecha_trabajada')
            ->paginate(10);
    }

    #[Computed]
    public function totalEstimado(): int
    {
        if (!$this->registroSeleccionado) return 0;
        return (int) $this->nuevaCantidadHoras * (int) $this->registroSeleccionado->valor_hora_profesional;
    }

    public function abrirModal(int $idRegistro)
    {
        $this->registroSeleccionado = RegistroHoras::with('profesional')->find($idRegistro);
        $this->nuevaCantidadHoras = (int) $this->registroSeleccionado->cantidad_horas;
        $this->mostrarModal = true;
    }

    public function actualizar()
    {
        $this->validate(['nuevaCantidadHoras' => 'required|integer|min:1|max:8']);

        try {
            DB::transaction(function () {
                $this->registroSeleccionado->update([
                    'cantidad_horas' => (int) $this->nuevaCantidadHoras,
                    'total_a_cobrar' => $this->totalEstimado
                ]);
            });

            $this->cerrarModal();
            session()->flash('exito', '¡Horas actualizadas con éxito!');

        } catch (\Throwable $ex) {
            Log::error('[(Livewire)profesionales.horas-trabajadas.inicio@actualizarRegistro] Error al actualizar las horas del registro.', [
                'id' => $this->registroSeleccionado?->id,
                'excepción' => $ex->getMessage()
            ]);
            session()->flash('error', 'Error interno del servidor. Si el error persiste contactar con el Equipo de Soporte (Matías).');
        }
    }

    public function cerrarModal()
    {
        $this->reset(['mostrarModal', 'registroSeleccionado', 'nuevaCantidadHoras']);
    }
};
?>

<div class="contenedor-listado max-w-screen-3xl">
    <h2 class="titulo-formulario">Registros de horas trabajadas</h2>

    <div class="fila-formulario">
        <div class="columna-campo">
            <label for="buscar-profesional" class="etiqueta-formulario">Buscar Profesional</label>
            <input
                id="buscar-profesional"
                type="text"
                placeholder="Ingrese nombre o apellido"
                class="entrada w-xs"
                wire:model.live.debounce.300ms="consultaProfesional"
            >
        </div>
    </div>

    <x-alerta tipo="exito" />
    <x-alerta tipo="error" />

    <table class="tabla-listado">
        <thead>
            <tr class="tabla-listado__cabecera">
                <th>Fecha</th>
                <th>Profesional</th>
                <th>Valor por hora aplicado</th>
                <th>Cantidad de horas</th>
                <th>Total a cobrar</th>
                <th>Acciones</th>
            </tr>
        </thead>

        <tbody>
            @forelse($this->registros as $reg)
                <tr class="tabla-listado__fila">
                    <td class="text-gray-400 font-bold">
                        {{ $reg->fecha_trabajada->format('d/m/Y') }}
                    </td>
                    <td>
                        {{ $reg->profesional->nombre }}
                        {{ $reg->profesional->apellido }}
                    </td>
                    <td>
                        ${{ number_format($reg->valor_hora_profesional, 0, ',', '.') }}
                    </td>
                    <td class="text-emerald-400 font-semibold">
                        {{ $reg->cantidad_horas }} hs
                    </td>
                    <td>
                        ${{ number_format($reg->total_a_cobrar, 0, ',', '.') }}
                    </td>
                    <td>
                        <div class="centrado-total">
                            <button type="button" wire:click="abrirModal({{ $reg->id }})">
                                <x-iconos.lapiz />
                            </button>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="py-10 text-gray-300 text-center italic">No se encontraron registros.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    {{ $this->registros->links() }}

    @if($mostrarModal && $registroSeleccionado)
        <div class="modal-informativo" wire:keydown.escape.window="cerrarModal">
            <div class="modal-informativo__ventana" wire:click.outside="cerrarModal">
                <button class="modal-informativo__cerrar" wire:click="cerrarModal">
                    <x-iconos.cruz />
                </button>

                <h2 class="modal-informativo__titulo text-center">Modificar registro</h2>

                <div class="mb-6">
                    <p class="text-emerald-400 text-lg font-semibold">
                        {{ $registroSeleccionado->profesional->nombre }}
                        {{ $registroSeleccionado->profesional->apellido }}
                    </p>
                    <p class="text-gray-400 text-base">
                        {{ ucfirst($registroSeleccionado->fecha_trabajada->translatedFormat('l d/m/Y')) }}
                    </p>
                </div>

                <div class="mb-8 space-y-3">
                    <div class="modal-informativo__seccion">
                        <label for="cantidad-horas" class="modal-informativo__etiqueta mb-1 block">Cantidad de horas</label>
                        <input
                            id="cantidad-horas"
                            type="number"
                            min="1"
                            max="8"
                            class="entrada w-full @error('nuevaCantidadHoras') border-red-500 @enderror"
                            wire:model.live.debounce.350ms="nuevaCantidadHoras">
                        @error('nuevaCantidadHoras')
                            <span class="text-red-500 text-xs">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="modal-informativo__seccion">
                        <p class="modal-informativo__etiqueta mb-1 block">Nuevo total calculado</p>
                        <p class="entrada-info rounded-xl w-full">
                            Total: ${{ number_format($this->totalEstimado, 0, ',', '.') }}
                        </p>
                    </div>
                </div>

                <div class="flex gap-3">
                    <button class="modal-informativo__accion flex-1 bg-gray-100 hover:bg-gray-200 text-gray-700 transition-all" wire:click="cerrarModal">
                        Cancelar
                    </button>
                    <button
                        class="modal-informativo__accion flex-1 bg-emerald-600 hover:bg-emerald-700 text-white transition-all"
                        wire:click="actualizar"
                        wire:loading.attr="disabled">
                        Guardar Cambios
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
