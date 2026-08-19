<?php

use App\Models\Caja;
use App\Models\Egreso;
use App\Models\Pago;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Pagination\LengthAwarePaginator;

new class extends Component
{
    use WithPagination;

    public string $filtroMetodo = 'todos';
    public string $filtroTipo = 'todos';

    #[Url(as: 'paciente')]
    public string $consultaPaciente = '';

    public function updatedFiltroMetodo()
    {
        $this->resetPage();
    }

    public function updatedFiltroTipo()
    {
        $this->resetPage();
    }

    public function updatingConsultaPaciente(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function movimientos()
    {
        $ingresos = collect();
        $egresos = collect();

        if ($this->filtroTipo === 'todos' || $this->filtroTipo === 'ingreso') {
            $consulta = Pago::with([
                'actividadPaciente.actividad',
                'actividadPaciente.pacienteRegular',
                'actividadPaciente.pacienteCasual',
                'actividadPaciente.primerTurno',
                'actividadPaciente.actPacDual.primerTurno',
                'profesional'
            ]);

            if ($this->filtroMetodo !== 'todos') {
                $consulta->where('metodo', $this->mapearMetodo($this->filtroMetodo));
            }

            if ($this->consultaPaciente !== '') {
                $consulta->whereHas(
                    'actividadPaciente',
                    fn ($q) => $q->buscarPaciente($this->consultaPaciente)
                );
            }

            $ingresos = $consulta
                ->latest()
                ->take(500)
                ->get()
                ->each(function ($pago) {
                    $pago->tipo = 'ingreso';
                    $pago->fecha = $pago->created_at;
                });
        }

        if ($this->consultaPaciente === '' && ($this->filtroTipo === 'todos' || $this->filtroTipo === 'egreso')) {
            $consultaEgresos = Egreso::with('profesional');

            if ($this->filtroMetodo !== 'todos') {
                $consultaEgresos->where('metodo', $this->mapearMetodo($this->filtroMetodo));
            }

            $egresos = $consultaEgresos
                ->latest()
                ->take(500)
                ->get()
                ->each(function ($egreso) {
                    $egreso->tipo = 'egreso';
                    $egreso->fecha = $egreso->created_at;
                });
        }

        $movimientos = $ingresos
            ->concat($egresos)
            ->sortByDesc('fecha')
            ->values();

        $porPagina = 5;
        $paginaActual = $this->getPage();

        return new LengthAwarePaginator(
            $movimientos->forPage($paginaActual, $porPagina),
            $movimientos->count(),
            $porPagina,
            $paginaActual,
            ['path' => request()->url()]
        );
    }

    #[Computed]
    public function saldoEfectivo()
    {
        return Caja::value('saldo_efectivo') ?? 0;
    }

    #[Computed]
    public function saldoTransferencia()
    {
        return Caja::value('saldo_transferencia') ?? 0;
    }

    private function mapearMetodo(string $filtro): string
    {
        return $filtro === 'transferencia' ? 'Transferencia' : 'Efectivo';
    }
};
?>

<div class="contenedor-listado max-w-screen-3xl">
    <div class="mb-6 flex justify-between items-center">
        <h2 class="titulo-formulario">Historial de Movimientos</h2>

        <div class="flex gap-3">
            <div class="p-4 flex flex-col items-end bg-gray-800 border border-gray-700 rounded-lg shadow-inner">
                <span class="text-gray-400 text-xs font-bold uppercase tracking-wider">Transferencias recibidas</span>
                <span class="text-emerald-400 text-3xl font-bold">
                    ${{ number_format($this->saldoTransferencia, 2, ',', '.') }}
                </span>
            </div>

            <div class="p-4 flex flex-col items-end bg-gray-800 border border-gray-700 rounded-lg shadow-inner">
                <span class="text-gray-400 text-xs font-bold uppercase tracking-wider">Saldo Total en Caja</span>
                <span class="text-emerald-400 text-3xl font-bold">
                    ${{ number_format($this->saldoEfectivo, 2, ',', '.') }}
                </span>
            </div>
        </div>
    </div>

    <div class="fila-formulario">
        <div class="columna-campo">
            <label for="filtro-metodo" class="etiqueta-formulario">Método de pago</label>
            <select
                id="filtro-metodo"
                class="entrada"
                wire:model.live="filtroMetodo"
            >
                <option value="todos">Todos los métodos</option>
                <option value="efectivo">Efectivo (Caja)</option>
                <option value="transferencia">Transferencia</option>
            </select>
        </div>

        <div class="columna-campo">
            <label for="filtro-tipo" class="etiqueta-formulario">Tipo de movimiento</label>
            <select
                id="filtro-tipo"
                class="entrada"
                wire:model.live="filtroTipo"
            >
                <option value="todos">Todos los movimientos</option>
                <option value="ingreso">Ingresos (Pagos de pacientes)</option>
                <option value="egreso">Egresos</option>
            </select>
        </div>

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
    <x-alerta tipo="error" />

    <table class="tabla-listado">
        <thead>
            <tr class="tabla-listado__cabecera">
                <th>Fecha</th>
                <th>Profesional que lo registró</th>
                <th>Concepto / Detalle</th>
                <th>Tipo</th>
                <th>Monto</th>
            </tr>
        </thead>

        <tbody>
            @php $movimientos = $this->movimientos; @endphp
            @forelse($movimientos as $mov)
                <tr class="tabla-listado__fila group">
                    <td>{{ $mov->fecha->format('d/m/Y H:i') }}</td>
                    <td>{{ $mov->profesional->nombre }} {{ $mov->profesional->apellido}}</td>
                    <td>
                        @if($mov->tipo === 'ingreso')
                            @php
                                $actPac = $mov->actividadPaciente;
                                $cantidad = (int) $actPac->cant_sesiones;
                                $par = $actPac->esDualCompleto() ? $actPac->actPacDual : null;
                                $primerTurno = collect([$actPac->primerTurno, $par?->primerTurno])
                                    ->filter()
                                    ->sortBy(fn ($turno) => $turno->fecha_hora->timestamp)
                                    ->first();
                                $fechaPrimerTurno = $primerTurno?->fecha_hora->format('d/m/Y');
                            @endphp

                            <small class="block text-emerald-400 group-hover:text-emerald-900 font-bold tracking-wide uppercase">
                                @if($mov->actividadPaciente->actividad->esActividadGeneral())
                                    @if ($mov->actividadPaciente->esPrimeraDual())
                                        Gym/Pilates (x{{ $mov->actividadPaciente->frecuencia_total_dual }})
                                    @elseif ($mov->actividadPaciente->esRegular())
                                        {{ $mov->actividadPaciente->nombre_actividad }} ({{ (int)($cantidad / 4) }} {{ (int)($cantidad / 4) === 1 ? 'vez' : 'veces' }} por semana)
                                    @else
                                        Prueba de Pilates
                                    @endif
                                @else
                                    {{ $mov->actividadPaciente->nombre_actividad }} ({{ $cantidad }} {{ $cantidad === 1 ? 'sesión' : 'sesiones' }})
                                @endif
                                @if ($fechaPrimerTurno)
                                    (COM {{ $fechaPrimerTurno }})
                                @endif
                            </small>

                            <small class="block text-gray-400 group-hover:text-emerald-900">
                                @if ($mov->es_copago)
                                    <span class="font-bold uppercase">Copago</span>
                                @else
                                    Pago #{{ $mov->nro_pago }}
                                @endif
                            </small>

                            <span class="group-hover:text-emerald-900">
                                {{ $mov->actividadPaciente->ap_nom_paciente }}
                            </span>
                        @else
                            <span class="text-gray-300 italic group-hover:text-emerald-900">
                                {{ $mov->motivo }}
                            </span>
                        @endif
                    </td>
                    <td>
                        @if($mov->tipo === 'ingreso')
                            <span class="badge bg-emerald-500">
                                {{ $mov->metodo === 'Efectivo' ? 'Ingreso de Caja' : 'Transferencia recibida' }}
                            </span>
                        @else
                            <span class="badge bg-red-500">
                                {{ $mov->metodo === 'Efectivo' ? 'Egreso de Caja' : 'Transferencia enviada' }}
                            </span>
                        @endif
                    </td>
                    <td class="{{ $mov->tipo === 'ingreso' ? 'text-emerald-400' : 'text-red-400' }} font-bold">
                        {{ $mov->tipo === 'egreso' ? '-' : '+' }} ${{ number_format($mov->monto, 2, ',', '.') }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="py-10 text-center text-gray-300 italic">No se encontraron movimientos.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="mt-4">
        {{ $movimientos->links(data: ['scrollTo' => false]) }}
    </div>
</div>
