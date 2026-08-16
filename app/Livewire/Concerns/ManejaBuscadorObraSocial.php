<?php

namespace App\Livewire\Concerns;

use App\Models\ObraSocial;
use App\Models\ObraSocialPaciente;
use App\Models\Paciente;
use App\Services\AfiliacionPacienteService;
use App\Support\NormalizadorNombreClinico;
use Closure;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;

trait ManejaBuscadorObraSocial
{
    public string $busquedaObraSocial = '';

    /** @var array{id: int|null, nombre: string}|null */
    public ?array $obraSocialSeleccionada = null;

    #[Computed]
    public function sugerenciasObrasSociales(): Collection
    {
        if (mb_strlen(trim($this->busquedaObraSocial)) < 2) {
            return collect();
        }

        return ObraSocial::query()
            ->where('activo', true)
            ->where('nombre', 'like', '%' . trim($this->busquedaObraSocial) . '%')
            ->orderBy('nombre')
            ->limit(10)
            ->get(['id', 'nombre']);
    }

    public function seleccionarObraSocial(int $id): void
    {
        $obraSocial = ObraSocial::query()->where('activo', true)->find($id);

        if ($obraSocial === null) {
            return;
        }

        $this->obraSocialSeleccionada = [
            'id' => $obraSocial->id,
            'nombre' => $obraSocial->nombre,
        ];
        $this->busquedaObraSocial = $obraSocial->nombre;
        $this->resetValidation(['busquedaObraSocial', 'obraSocialSeleccionada']);
        unset($this->sugerenciasObrasSociales);
    }

    public function usarObraSocialLibre(): void
    {
        $nombre = trim($this->busquedaObraSocial);

        if (mb_strlen($nombre) < 2) {
            return;
        }

        if (mb_strlen($nombre) > 30) {
            $this->addError('busquedaObraSocial', 'El nombre de la obra social no puede superar los 30 caracteres.');
            return;
        }

        $nombre = NormalizadorNombreClinico::normalizar($nombre);

        $existente = ObraSocial::query()
            ->where('activo', true)
            ->whereRaw('LOWER(nombre) = ?', [mb_strtolower($nombre)])
            ->first();

        if ($existente !== null) {
            $this->seleccionarObraSocial($existente->id);
            return;
        }

        $this->obraSocialSeleccionada = [
            'id' => null,
            'nombre' => $nombre,
        ];
        $this->busquedaObraSocial = $nombre;
        $this->resetValidation(['busquedaObraSocial', 'obraSocialSeleccionada']);
        unset($this->sugerenciasObrasSociales);
    }

    public function limpiarObraSocial(): void
    {
        $this->obraSocialSeleccionada = null;
        $this->busquedaObraSocial = '';
        unset($this->sugerenciasObrasSociales);
    }

    protected function hidratarObraSocialSeleccionada(?ObraSocialPaciente $afiliacion): void
    {
        if ($afiliacion === null) {
            return;
        }

        $nombre = $afiliacion->nombre_mostrable;

        $this->obraSocialSeleccionada = [
            'id' => $afiliacion->id_obra_social,
            'nombre' => $nombre,
        ];
        $this->busquedaObraSocial = (string) $nombre;
    }

    protected function persistirObraSocial(Paciente $paciente): void
    {
        $seleccion = $this->obraSocialSeleccionada ?? [];
        $id = $seleccion['id'] ?? null;
        $id = $id ? (int) $id : null;
        $nombre = $id === null ? ($seleccion['nombre'] ?? null) : null;

        app(AfiliacionPacienteService::class)->sincronizar($paciente, $id, $nombre);
    }

    protected function reglasObraSocialSeleccionada(): array
    {
        return [
            'obraSocialSeleccionada' => 'nullable|array',
            'obraSocialSeleccionada.id' => 'nullable|integer|exists:obras_sociales,id',
            'obraSocialSeleccionada.nombre' => 'required_with:obraSocialSeleccionada|string|max:30',
            'busquedaObraSocial' => [
                'nullable',
                'string',
                'max:30',
                function (string $atributo, mixed $valor, Closure $fail): void {
                    if ($this->obraSocialSeleccionada === null && mb_strlen(trim((string) $valor)) >= 2) {
                        $fail('Confirmá la obra social de la lista o borrala.');
                    }
                },
            ],
        ];
    }
}
