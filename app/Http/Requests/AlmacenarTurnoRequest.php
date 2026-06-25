<?php

namespace App\Http\Requests;

use App\Models\Actividad;
use App\Models\ActividadCombo;
use App\Services\PlanDualService;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class AlmacenarTurnoRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        $turnosAutogenerados = $this->boolean('autogenerados');
        $esConOrden = $this->esConOrden();
        $esPlanDual = $this->boolean('plan_dual');

        return [
            'id_actividad' => 'required|integer|exists:actividades,id',
            'id_paciente' => 'required|integer|exists:pacientes,id',
            'plan_dual' => ['sometimes', 'boolean'],
            'autogenerados' => 'required|boolean',
            'fecha_ancla' => [
                Rule::requiredIf($turnosAutogenerados),
                Rule::prohibitedIf(!$turnosAutogenerados),
                'nullable',
                'date_format:Y-m-d',
            ],
            'frecuencia_semanal' => 'required|integer|min:1|max:5',

            'sesiones_cubiertas' => [Rule::requiredIf($esConOrden), 'integer', 'in:5,10'],
            'mes' => [Rule::requiredIf($esConOrden), 'integer', 'min:1', 'max:12'],
            'dia' => [Rule::requiredIf($esConOrden), 'integer', 'min:1', 'max:31'],

            'cant_sesiones' => [Rule::requiredIf(!$esConOrden), 'integer', 'min:1', 'max:20'],
            'id_actividad_combo' => [
                Rule::requiredIf($this->esActividadGeneral() && !$esPlanDual),
                'nullable',
                'integer',
                'exists:actividades_combos,id',
                function (string $attribute, mixed $value, Closure $fail) {
                    if ($value === null) {
                        return;
                    }

                    $pertenece = ActividadCombo::activo()
                        ->where('id', $value)
                        ->where('id_actividad', $this->input('id_actividad'))
                        ->exists();

                    if (!$pertenece) {
                        $fail('El combo seleccionado no pertenece a la actividad indicada.');
                    }
                },
            ],

            'turnos' => [
                'required',
                'array',
                function (string $attribute, mixed $value, Closure $fail) use ($turnosAutogenerados, $esConOrden) {
                    $mensajeError = "Cantidad de turnos no válida.";
                    $cantidad = count($value);

                    if ($turnosAutogenerados) {
                        if ($cantidad < 1 || $cantidad > 5) {
                            $fail($mensajeError);
                        }
                    } else {
                        $valorEsperado = $esConOrden
                            ? ($this->sesiones_cubiertas ?? 0)
                            : ($this->cant_sesiones ?? 0);
                        if ($cantidad != $valorEsperado) {
                            $fail($mensajeError);
                        }
                    }
                }
            ],

            'turnos.*' => Rule::unless($turnosAutogenerados, 'date_format:Y-m-d H:i:s'),
            'turnos.*.dia_semana' => 'required_if:autogenerados,true|string|in:Lunes,Martes,Miércoles,Jueves,Viernes',
            'turnos.*.hora_inicio' => 'required_if:autogenerados,true|string|date_format:H:i:s'
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $this->validarPlanDual($validator);
        });
    }

    private function validarPlanDual(Validator $validator): void
    {
        $planDual = $this->boolean('plan_dual');
        $idActividad = (int) $this->input('id_actividad');
        $idPaciente = (int) $this->input('id_paciente');
        $planDualService = app(PlanDualService::class);

        if ($planDual && !$this->esActividadGeneral()) {
            $validator->errors()->add(
                'plan_dual',
                'El plan dual solo aplica a inscripciones de Gimnasio o Pilates.'
            );

            return;
        }

        $pendiente = $planDualService->obtenerDualPendiente($idPaciente);

        if ($pendiente && !$planDual) {
            $validator->errors()->add(
                'plan_dual',
                PlanDualService::MENSAJE_COMPLETAR_PLAN_DUAL
            );

            return;
        }

        if (!$planDual) {
            return;
        }

        if ($pendiente) {
            $idActividadFaltante = $planDualService->idActividadFaltante((int) $pendiente->id_actividad);

            if ($idActividad !== $idActividadFaltante) {
                $validator->errors()->add(
                    'id_actividad',
                    'Debe registrar la actividad faltante del plan dual pendiente.'
                );
            }

            $permitidas = $planDualService->frecuenciasPermitidasSegundaInscripcion($pendiente);
            $frecuencia = (int) $this->input('frecuencia_semanal');

            if (!in_array($frecuencia, $permitidas, true)) {
                $validator->errors()->add(
                    'frecuencia_semanal',
                    'La frecuencia seleccionada no es válida para completar el plan dual.'
                );
            }

            return;
        }

        $frecuenciaSemanal = (int) $this->input('frecuencia_semanal');

        if ($frecuenciaSemanal < 1 || $frecuenciaSemanal > 4) {
            $validator->errors()->add(
                'frecuencia_semanal',
                'La frecuencia semanal del plan dual debe estar entre 1 y 4 en la primera visita.'
            );
        }
    }

    public function messages(): array
    {
        return [
            'id_actividad.exists' => 'La actividad ingresada no existe.',
            'id_paciente.exists' => 'El paciente ingresado no existe.'
        ];
    }

    public function attributes(): array
    {
        return [
            'id_actividad' => 'actividad',
            'id_paciente' => 'paciente',
            'cant_sesiones' => 'cantidad de sesiones',
            'id_actividad_combo' => 'combo de la actividad',
            'plan_dual' => 'inscripción dual',
        ];
    }

    private function esConOrden(): bool
    {
        return $this->filled('sesiones_cubiertas')
            || $this->filled('mes')
            || $this->filled('dia');
    }

    private function esActividadGeneral(): bool
    {
        $idActividad = $this->input('id_actividad');

        if (!$idActividad) {
            return false;
        }

        return Actividad::find($idActividad)?->esActividadGeneral() ?? false;
    }
}
