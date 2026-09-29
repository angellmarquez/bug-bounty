<?php

namespace App\Http\Requests;

use App\Abac\AccionesAbac;
use App\Enums\Severidad;
use App\Models\Reporte;
use App\Rules\PocCumpleSchema;
use App\Support\Cvss31;
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
     * Prepara los datos antes de la validación.
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('poc'))) {
            $poc = json_decode((string) $this->input('poc'), true);
            $this->merge(['poc' => is_array($poc) ? $poc : null]);
        }

        // Como al crear: puntuación y severidad salen del vector, nunca del navegador. Si no se
        // cambia el vector, se conservan las que ya tenía el informe.
        /** @var Reporte|null $reporte */
        $reporte = $this->route('reporte');
        if ($this->has('vector_cvss')) {
            $cvss = Cvss31::calcular($this->input('vector_cvss'));
            $this->merge(['puntuacion_cvss' => $cvss['puntuacion'] ?? null, 'severidad' => $cvss['severidad']->value ?? null]);
        } elseif ($reporte instanceof Reporte) {
            $this->merge(['puntuacion_cvss' => $reporte->puntuacion_cvss, 'severidad' => $reporte->severidad?->value]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, string|ValidationRule>>
     */
    public function rules(): array
    {
        // Editar sigue permitiendo guardar progreso incompleto; la PoC completa
        // solo se exige al enviar (ver ReporteController::enviar).
        /** @var Reporte|null $reporte */
        $reporte = $this->route('reporte');
        $schema = $reporte?->programa->poc_schema ?? [];

        return [
            'titulo' => ['sometimes', 'string', 'max:255'],
            'descripcion' => ['sometimes', 'string', 'max:50000'],
            // La categoría que ya tenía un informe antiguo se sigue aceptando al editarlo.
            'categoria' => ['nullable', 'string', Rule::in([...Reporte::CATEGORIAS, ...array_filter([$reporte?->categoria])])],
            'vector_cvss' => ['nullable', 'string', 'regex:'.Cvss31::PATRON],
            'puntuacion_cvss' => ['nullable', 'numeric', 'min:0', 'max:10'],
            'severidad' => ['nullable', Rule::enum(Severidad::class)],
            'poc' => ['sometimes', 'nullable', 'array', new PocCumpleSchema($schema, exigirRequeridos: false)],
        ];
    }

    public function messages(): array
    {
        return [
            'titulo.max' => 'El título no puede exceder 255 caracteres.',
            'vector_cvss.regex' => 'El vector CVSS no es válido: usa la calculadora (formato CVSS:3.1/AV:…/A:…).',
            'categoria.in' => 'Elige una categoría de la lista.',
            'descripcion.max' => 'La descripción no puede exceder 50000 caracteres.',
            'puntuacion_cvss.min' => 'La puntuación CVSS debe ser entre 0 y 10.',
            'puntuacion_cvss.max' => 'La puntuación CVSS debe ser entre 0 y 10.',
        ];
    }
}
