<?php

namespace App\Http\Requests;

use App\Abac\AccionesAbac;
use App\Enums\Severidad;
use App\Models\Programa;
use App\Models\Reporte;
use App\Rules\PocCumpleSchema;
use App\Services\Adjuntos\AdjuntoService;
use App\Support\Cvss31;
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

        // La puntuación y la severidad reparten los puntos de reputación: nunca se aceptan las
        // del navegador, se calculan aquí desde el vector (sin vector, el informe no tiene CVSS).
        $cvss = Cvss31::calcular($this->input('vector_cvss'));
        $this->merge([
            'puntuacion_cvss' => $cvss['puntuacion'] ?? null,
            'severidad' => $cvss['severidad']->value ?? null,
        ]);
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
            // Los mínimos del formulario se exigen al enviar; un borrador puede ir incompleto.
            'titulo' => ['required', 'string', ...($enviar ? ['min:'.Reporte::TITULO_MINIMO] : []), 'max:255'],
            'descripcion' => ['required', 'string', ...($enviar ? ['min:'.Reporte::DESCRIPCION_MINIMA] : []), 'max:50000'],
            'categoria' => ['nullable', 'string', Rule::in(Reporte::CATEGORIAS)],
            'vector_cvss' => ['nullable', 'string', 'regex:'.Cvss31::PATRON],
            'puntuacion_cvss' => ['nullable', 'numeric', 'min:0', 'max:10'],
            'severidad' => ['nullable', Rule::enum(Severidad::class)],
            'poc' => [
                $enviar ? 'required' : 'nullable',
                'array',
                new PocCumpleSchema($schema, exigirRequeridos: $enviar),
            ],
            'enviar' => ['sometimes', 'boolean'],
            ...AdjuntoService::reglas(),
        ];
    }

    public function messages(): array
    {
        return [
            'programa_id.required' => 'Debe seleccionar un programa.',
            'programa_id.exists' => 'El programa seleccionado no existe.',
            'titulo.required' => 'El título es obligatorio.',
            'titulo.min' => 'El título debe tener al menos '.Reporte::TITULO_MINIMO.' caracteres descriptivos.',
            'titulo.max' => 'El título no puede exceder 255 caracteres.',
            'descripcion.required' => 'La descripción es obligatoria.',
            'descripcion.min' => 'La descripción debe tener al menos '.Reporte::DESCRIPCION_MINIMA.' caracteres detallando el hallazgo.',
            'descripcion.max' => 'La descripción no puede exceder 50000 caracteres.',
            'vector_cvss.regex' => 'El vector CVSS no es válido: usa la calculadora (formato CVSS:3.1/AV:…/A:…).',
            'categoria.in' => 'Elige una categoría de la lista.',
            'puntuacion_cvss.min' => 'La puntuación CVSS debe ser entre 0 y 10.',
            'puntuacion_cvss.max' => 'La puntuación CVSS debe ser entre 0 y 10.',
            'poc.required' => 'La prueba de concepto es obligatoria para enviar el reporte.',
            ...AdjuntoService::mensajes(),
        ];
    }
}
