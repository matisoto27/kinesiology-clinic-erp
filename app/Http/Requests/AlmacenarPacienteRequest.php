<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AlmacenarPacienteRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation()
    {
        $esAdulto = $this->boolean('es_adulto_mayor');

        if (!$esAdulto) {
            $this->merge([
                'es_adulto_mayor' => false,
                'vive_solo' => true,
                'vive_con' => null,
                'contactos' => []
            ]);
        } else {
            if (!$this->has('vive_con') || $this->boolean('vive_solo')) {
                $this->merge([
                    'es_adulto_mayor' => true,
                    'vive_solo' => true,
                    'vive_con' => 'SOLO'
                ]);
            } else {
                $this->merge([
                    'es_adulto_mayor' => true,
                    'vive_solo' => false
                ]);
            }
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        $idPaciente = $this->route('paciente') ? $this->route('paciente')->id : null;

        return [
            'dni' => $idPaciente
                ? 'nullable'
                : 'required|unique:pacientes,dni|numeric|digits_between:7,8',
            'nombre' => 'required|regex:/^[A-Za-záéíóúÁÉÍÓÚñÑ\s]+$/|max:30', // Permite espacios
            'apellido' => 'required|regex:/^[A-Za-záéíóúÁÉÍÓÚñÑ]+$/|max:30', // No permite espacios
            'fecha_nac' => 'required|date',
            'domicilio' => 'required|string|regex:/^[A-Za-z0-9\s.,áéíóúÁÉÍÓÚñÑ#-]+$/|max:100',
            'telefono' => 'required|numeric|digits_between:8,20',
            'profesion' => 'required|string|in:Estudiante,Desempleado,Empleado,Ama de casa,Trabajo independiente,Jubilado/Pensionado',
            'actividad_fisica' => 'required|string|in:Sedentario,Ocasional,Moderada,Intensa,Alto rendimiento/Competencia',
            'es_adulto_mayor' => 'required|boolean',
            'vive_solo' => 'exclude_if:es_adulto_mayor,false|boolean',
            'vive_con' => 'exclude_if:es_adulto_mayor,false|required_if:vive_solo,false|string|regex:/^[A-Za-z0-9\s.,()áéíóúÁÉÍÓÚñÑ]+$/|min:1|max:150',
            'contactos' => 'exclude_if:es_adulto_mayor,false|nullable|array|max:3',
            'contactos.*.id' => 'nullable|integer|exists:contactos_emergencia,id',
            'contactos.*.nombre' => 'required_with:contactos|regex:/^[A-Za-záéíóúÁÉÍÓÚñÑ\s]+$/|max:100',
            'contactos.*.telefono' => 'required_with:contactos|numeric|digits_between:8,20',
            'contactos.*.vinculo' => 'required_with:contactos|string|in:Hijo/a,Cónyuge,Hermano/a,Otro',
            'patologias' => 'nullable|array',
            'patologias.*' => 'numeric|exists:patologias,id',
            'sintomas' => 'nullable|array',
            'sintomas.*' => 'numeric|exists:sintomas,id'
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.regex' => 'El nombre solo puede contener letras y espacios.',
            'apellido.regex' => 'El apellido solo puede contener letras.',
            'vive_con.required_if' => 'Por favor, especifique con quién vive el paciente.'
        ];
    }

    public function attributes(): array
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
}
