<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class TransitionReporteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, string|ValidationRule>>
     */
    public function rules(): array
    {
        return [
            'nota' => ['nullable', 'string', 'max:2000'],
            'reporte_duplicado_id' => ['nullable', 'integer', 'exists:reportes,id'],
            'asignado_a' => ['nullable', 'integer', 'exists:users,id'],
            'sancionar' => ['nullable', 'boolean'],
            'gravedad_sancion' => ['nullable', 'string', 'in:leve,media,grave'],
        ];
    }

    public function messages(): array
    {
        return [
            'nota.max' => 'La nota no puede exceder 2000 caracteres.',
            'reporte_duplicado_id.exists' => 'El reporte duplicado no existe.',
            'asignado_a.exists' => 'El usuario seleccionado no existe.',
            'gravedad_sancion.in' => 'La gravedad de la sancion no es valida.',
        ];
    }
}
