<?php

namespace App\Http\Requests;

use App\Abac\AccionesAbac;
use App\Enums\Severidad;
use App\Models\Programa;
use App\Rules\PocCumpleSchema;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreReporteRequest extends FormRequest
{
    public function authorize(): bool
    {
        $programaId = $this->input('programa_id');

        if ($programaId === null) {
            return true;
        }

        $programa = Programa::find($programaId);

        if (! $programa) {
            return true;
        }

        return Gate::allows('abac', [AccionesAbac::ReporteCrear, $programa]);
    }

    /**
     * Prepara los datos antes de la validación.
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('poc'))) {
            $poc = json_decode((string) $this->input('poc'), true);
            $this->merge(['poc' => is_array($poc) ? $poc : null]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, string|ValidationRule>>
     */
    public function rules(): array
    {
        // Con el borrador la PoC puede ir incompleta (se guarda progreso); al enviar
        // ("Guardar y enviar") se exige completa: todo programa requiere PoC verificable.
        $enviar = $this->boolean('enviar');
        $programa = Programa::find((int) $this->input('programa_id'));
        $schema = $programa === null ? [] : ($programa->poc_schema ?? []);

        return [
            'programa_id' => ['required', 'exists:programas,id'],
            'titulo' => ['required', 'string', 'max:255'],
            'descripcion' => ['required', 'string', 'max:50000'],
            'categoria' => ['nullable', 'string', 'max:100'],
            'vector_cvss' => ['nullable', 'string', 'max:100'],
            'puntuacion_cvss' => ['nullable', 'numeric', 'min:0', 'max:10'],
            'severidad' => ['nullable', Rule::enum(Severidad::class)],
            'poc' => [
                $enviar ? 'required' : 'nullable',
                'array',
                new PocCumpleSchema($schema, exigirRequeridos: $enviar),
            ],
            'enviar' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'programa_id.required' => 'Debe seleccionar un programa.',
            'programa_id.exists' => 'El programa seleccionado no existe.',
            'titulo.required' => 'El título es obligatorio.',
            'titulo.max' => 'El título no puede exceder 255 caracteres.',
            'descripcion.required' => 'La descripción es obligatoria.',
            'descripcion.max' => 'La descripción no puede exceder 50000 caracteres.',
            'puntuacion_cvss.min' => 'La puntuación CVSS debe ser entre 0 y 10.',
            'puntuacion_cvss.max' => 'La puntuación CVSS debe ser entre 0 y 10.',
            'poc.required' => 'La prueba de concepto es obligatoria para enviar el reporte.',
        ];
    }
}
