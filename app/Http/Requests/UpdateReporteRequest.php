<?php

namespace App\Http\Requests;

use App\Abac\AccionesAbac;
use App\Enums\Severidad;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateReporteRequest extends FormRequest
{
    public function authorize(): bool
    {
        $reporte = $this->route('reporte');

        if (! $reporte) {
            return false;
        }

        return Gate::allows('abac', [AccionesAbac::ReporteEditar, $reporte]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, string|ValidationRule>>
     */
    public function rules(): array
    {
        return [
            'titulo' => ['sometimes', 'string', 'max:255'],
            'descripcion' => ['sometimes', 'string', 'max:50000'],
            'categoria' => ['nullable', 'string', 'max:100'],
            'vector_cvss' => ['nullable', 'string', 'max:100'],
            'puntuacion_cvss' => ['nullable', 'numeric', 'min:0', 'max:10'],
            'severidad' => ['nullable', Rule::enum(Severidad::class)],
            'poc' => ['nullable', 'array'],
            'poc.*' => ['nullable', 'string'],
            'clave_pgp_id' => ['nullable', 'exists:claves_pgp,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'titulo.max' => 'El titulo no puede exceder 255 caracteres.',
            'descripcion.max' => 'La descripcion no puede exceder 50000 caracteres.',
            'puntuacion_cvss.min' => 'La puntuacion CVSS debe ser entre 0 y 10.',
            'puntuacion_cvss.max' => 'La puntuacion CVSS debe ser entre 0 y 10.',
            'clave_pgp_id.exists' => 'La clave PGP seleccionada no existe.',
        ];
    }
}
