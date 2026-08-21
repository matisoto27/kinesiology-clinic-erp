<?php

namespace App\Livewire\Forms;

use App\Models\PacienteCasual;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Form;

class PacienteCasualForm extends Form
{
    #[Locked]
    public ?PacienteCasual $paciente = null;

    public string $nombre = '';
    public string $apellido = '';
    public string $telefono = '';

    protected function rules()
    {
        return [
            'nombre' => 'required|regex:/^[A-Za-záéíóúÁÉÍÓÚñÑ\s]+$/|max:30',
            'apellido' => 'required|regex:/^[A-Za-záéíóúÁÉÍÓÚñÑ\s]+$/|max:30',
            'telefono' => [
                'required',
                'numeric',
                'digits_between:8,20',
                // Solo activos: un soft-deleted se restaura en el alta.
                Rule::unique('pacientes_casuales', 'telefono')
                    ->whereNull('deleted_at')
                    ->ignore($this->paciente?->id),
            ],
        ];
    }

    protected function messages()
    {
        return [
            'nombre.regex' => 'El nombre solo puede contener letras y espacios.',
            'apellido.regex' => 'El apellido solo puede contener letras y espacios.',
            'telefono.unique' => 'Ya existe un paciente casual con ese teléfono.',
        ];
    }

    protected function validationAttributes()
    {
        return [
            'telefono' => 'teléfono'
        ];
    }

    public function establecerPaciente(PacienteCasual $paciente)
    {
        $this->paciente = $paciente;
        $this->nombre = $paciente->nombre;
        $this->apellido = $paciente->apellido;
        $this->telefono = $paciente->telefono;
    }

    public function transformarDatos(): array
    {
        return [
            'nombre' => mb_convert_case(mb_strtolower(trim($this->nombre)), MB_CASE_TITLE, "UTF-8"),
            'apellido' => mb_convert_case(mb_strtolower(trim($this->apellido)), MB_CASE_TITLE, "UTF-8"),
            'telefono' => $this->telefono
        ];
    }
}
