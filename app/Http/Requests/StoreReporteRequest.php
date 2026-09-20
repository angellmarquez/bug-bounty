<?php

namespace App\Http\Requests;

use App\Abac\AccionesAbac;
use App\Enums\Severidad;
use App\Models\Programa;
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
        return [
            'programa_id' => ['required', 'exists:programas,id'],
            'titulo' => ['required', 'string', 'max:255'],
            'descripcion' => ['required', 'string', 'max:50000'],
            'categoria' => ['nullable', 'string', 'max:100'],
            'vector_cvss' => ['nullable', 'string', 'max:100'],
            'puntuacion_cvss' => ['nullable', 'numeric', 'min:0', 'max:10'],
            'severidad' => ['nullable', Rule::enum(Severidad::class)],
            'poc' => ['nullable', 'array'],
            'enviar' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'programa_id.required' => 'Debe seleccionar un programa.',
            'programa_id.exists' => 'El programa seleccionado no existe.',
            'titulo.required' => 'El titulo es obligatorio.',
            'titulo.max' => 'El titulo no puede exceder 255 caracteres.',
            'descripcion.required' => 'La descripcion es obligatoria.',
            'descripcion.max' => 'La descripcion no puede exceder 50000 caracteres.',
            'puntuacion_cvss.min' => 'La puntuacion CVSS debe ser entre 0 y 10.',
            'puntuacion_cvss.max' => 'La puntuacion CVSS debe ser entre 0 y 10.',
        ];
    }
}
