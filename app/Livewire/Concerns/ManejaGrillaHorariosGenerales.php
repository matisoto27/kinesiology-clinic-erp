<?php

namespace App\Livewire\Concerns;

use App\Models\Actividad;
use App\Models\PrecioMensual;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Throwable;

trait ManejaGrillaHorariosGenerales
{
    private const DURACION_CLASE_MINUTOS = 60;

    public int $frecuenciaSemanal = 1;

    public array $horarios = [];

    public function inicializarGrillaHorarios(): void
    {
        foreach (Actividad::diasSemanaDisponibles() as $dia) {
            $this->horarios[$dia] = [
                Actividad::GIMNASIO => '',
                Actividad::PILATES => '',
            ];
        }
    }

    public function updated(string $property, mixed $value = null): void
    {
        if (!str_starts_with($property, 'horarios.')) {
            return;
        }

        $this->resolverSolapeTrasCambio($property);
    }

    #[Computed]
    public function disponibilidadEstructural(): array
    {
        $dias = Actividad::diasSemanaDisponibles();

        return [
            Actividad::GIMNASIO => Actividad::find(Actividad::GIMNASIO)->horariosEstructuralesDisponibles($dias),
            Actividad::PILATES => Actividad::find(Actividad::PILATES)->horariosEstructuralesDisponibles($dias),
        ];
    }

    public function opcionesHorario(string $dia, int $idActividad): array
    {
        $opciones = $this->disponibilidadEstructural[$idActividad][$dia] ?? [];
        $horaOtra = $this->horaOtraActividadDelDia($dia, $idActividad);

        if ($horaOtra === null) {
            return $opciones;
        }

        return array_values(array_filter(
            $opciones,
            fn (string $hora) => !$this->horariosSeSolapan($hora, $horaOtra)
        ));
    }

    #[Computed]
    public function slotsSeleccionados(): Collection
    {
        $slots = collect();

        foreach ($this->horarios as $dia => $porActividad) {
            foreach ($porActividad as $idActividad => $hora) {
                if ($hora !== '' && $hora !== null) {
                    $slots->push([
                        'dia_semana' => $dia,
                        'id_actividad' => (int) $idActividad,
                        'hora_inicio' => $hora,
                    ]);
                }
            }
        }

        return $slots;
    }

    #[Computed]
    public function diasSeleccionados(): array
    {
        return $this->slotsSeleccionados
            ->pluck('dia_semana')
            ->unique()
            ->sortBy(fn (string $dia) => Actividad::diaSemanaAEntero($dia))
            ->values()
            ->all();
    }

    #[Computed]
    public function totalCompleto(): bool
    {
        return $this->slotsSeleccionados->count() === $this->frecuenciaSemanal;
    }

    #[Computed]
    public function precio(): ?float
    {
        try {
            return PrecioMensual::obtenerVigentePorFrecuencia($this->frecuenciaSemanal);
        } catch (Throwable) {
            return null;
        }
    }

    public function horarioDeshabilitado(string $dia, int $idActividad): bool
    {
        $valor = $this->horarios[$dia][$idActividad] ?? '';

        if ($valor !== '' && $valor !== null) {
            return false;
        }

        return $this->slotsSeleccionados->count() >= $this->frecuenciaSemanal;
    }

    public function prellenarHorariosDesdeFijos(Collection $horariosFijos): void
    {
        $this->inicializarGrillaHorarios();

        foreach ($horariosFijos as $horario) {
            $dia = Actividad::enteroADiaSemana((int) $horario->dia_semana);
            $hora = substr((string) $horario->hora_inicio, 0, 5);

            if (strlen($hora) === 5) {
                $hora .= ':00';
            }

            $this->horarios[$dia][(int) $horario->id_actividad] = $hora;
        }

        $this->frecuenciaSemanal = max(1, $horariosFijos->count());
    }

    public function diasConSolapeEntreActividades(): array
    {
        $dias = [];

        foreach ($this->horarios as $dia => $porActividad) {
            $gym = $porActividad[Actividad::GIMNASIO] ?? '';
            $pilates = $porActividad[Actividad::PILATES] ?? '';

            if ($gym === '' || $gym === null || $pilates === '' || $pilates === null) {
                continue;
            }

            if ($this->horariosSeSolapan((string) $gym, (string) $pilates)) {
                $dias[] = $dia;
            }
        }

        return $dias;
    }

    private function resolverSolapeTrasCambio(string $property): void
    {
        if (!preg_match('/^horarios\.(.+)\.(\d+)$/', $property, $match)) {
            return;
        }

        $dia = $match[1];
        $idActividad = (int) $match[2];
        $hora = $this->horarios[$dia][$idActividad] ?? '';

        if ($hora === '' || $hora === null) {
            return;
        }

        $idOtra = $idActividad === Actividad::GIMNASIO
            ? Actividad::PILATES
            : Actividad::GIMNASIO;
        $horaOtra = $this->horarios[$dia][$idOtra] ?? '';

        if ($horaOtra === '' || $horaOtra === null) {
            return;
        }

        if ($this->horariosSeSolapan((string) $hora, (string) $horaOtra)) {
            $this->horarios[$dia][$idOtra] = '';
        }
    }

    private function horaOtraActividadDelDia(string $dia, int $idActividad): ?string
    {
        $idOtra = $idActividad === Actividad::GIMNASIO
            ? Actividad::PILATES
            : Actividad::GIMNASIO;
        $hora = $this->horarios[$dia][$idOtra] ?? '';

        if ($hora === '' || $hora === null) {
            return null;
        }

        return (string) $hora;
    }

    private function horariosSeSolapan(string $horaA, string $horaB): bool
    {
        return abs($this->horaAMinutos($horaA) - $this->horaAMinutos($horaB)) < self::DURACION_CLASE_MINUTOS;
    }

    private function horaAMinutos(string $hora): int
    {
        $partes = explode(':', $hora);

        return ((int) $partes[0]) * 60 + ((int) ($partes[1] ?? 0));
    }
}
