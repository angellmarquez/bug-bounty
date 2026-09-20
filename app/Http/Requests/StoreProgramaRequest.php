<?php

namespace App\Http\Requests;

use App\Abac\AccionesAbac;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreProgramaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('abac', [AccionesAbac::ProgramaCrear]);
    }

    /**
     * @return array<string, array<int, string|ValidationRule>>
     */
    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:255'],
            'descripcion' => ['required', 'string', 'max:5000'],
            'bugs_buscados' => ['nullable', 'string', 'max:3000'],
            'recompensa_min' => ['required', 'numeric', 'min:0'],
            'recompensa_max' => ['required', 'numeric', 'gte:recompensa_min'],
            'moneda' => ['required', 'string', 'size:3'],
            'requiere_poc' => ['boolean'],
            'es_publico' => ['boolean'],
            'reputacion_minima' => ['sometimes', 'integer', 'min:0'],
            'poc_schema' => ['nullable', 'array'],
            'poc_schema.*.name' => ['required_with:poc_schema', 'string', 'max:100'],
            'poc_schema.*.label' => ['required_with:poc_schema', 'string', 'max:255'],
            'poc_schema.*.type' => ['required_with:poc_schema', Rule::in(['text', 'textarea', 'select', 'number', 'url', 'code'])],
            'poc_schema.*.required' => ['boolean'],
            'poc_schema.*.placeholder' => ['nullable', 'string', 'max:255'],
            'poc_schema.*.help' => ['nullable', 'string', 'max:500'],
            'poc_schema.*.options' => ['nullable', 'array'],
            'poc_schema.*.repeatable' => ['boolean'],
            'poc_schema.*.defaultValue' => ['nullable', 'string'],
            'inicia_en' => ['nullable', 'date'],
            'termina_en' => ['nullable', 'date', 'after_or_equal:inicia_en'],
            // Una empresa no puede crear un programa sin alcance: necesita al menos un objetivo.
            'objetivos' => [
                Rule::requiredIf(fn () => (bool) $this->user()?->roles()->where('slug', 'empresa')->exists()),
                'nullable',
                'array',
                'min:1',
            ],
            'objetivos.*.tipo' => ['required_with:objetivos', Rule::in(['web', 'api', 'movil', 'otro'])],
            'objetivos.*.valor' => ['required_with:objetivos', 'string', 'max:255'],
            'objetivos.*.descripcion' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'objetivos.required' => 'Agrega al menos un objetivo: define qué sistemas pueden investigar los investigadores.',
            'objetivos.min' => 'Agrega al menos un objetivo: define qué sistemas pueden investigar los investigadores.',
            'objetivos.*.valor.required' => 'Indica el objetivo (por ejemplo un dominio, una API o una aplicación).',
            'nombre.required' => 'El nombre es obligatorio.',
            'nombre.max' => 'El nombre no puede exceder 255 caracteres.',
            'descripcion.required' => 'La descripcion es obligatoria.',
            'descripcion.max' => 'La descripcion no puede exceder 5000 caracteres.',
            'recompensa_min.required' => 'La recompensa minima es obligatoria.',
            'recompensa_min.min' => 'La recompensa minima debe ser mayor o igual a 0.',
            'recompensa_max.required' => 'La recompensa maxima es obligatoria.',
            'recompensa_max.gte' => 'La recompensa maxima debe ser mayor o igual a la minima.',
            'moneda.required' => 'La moneda es obligatoria.',
            'moneda.size' => 'La moneda debe tener 3 caracteres (ej. USD).',
            'inicia_en.date' => 'La fecha de inicio debe ser una fecha valida.',
            'termina_en.after_or_equal' => 'La fecha de fin debe ser igual o posterior a la de inicio.',
        ];
    }
}
