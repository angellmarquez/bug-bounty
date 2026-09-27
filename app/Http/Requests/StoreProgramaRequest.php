<?php

namespace App\Http\Requests;

use App\Abac\AccionesAbac;
use App\Enums\NivelAcceso;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreProgramaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('abac', [AccionesAbac::ProgramaCrear]);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'es_publico' => $this->has('es_publico') ? $this->boolean('es_publico') : true,
            'tiene_recompensas' => $this->has('tiene_recompensas') ? $this->boolean('tiene_recompensas') : false,
            'solo_verificados' => $this->has('solo_verificados') ? $this->boolean('solo_verificados') : false,
        ]);
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
            'es_publico' => ['boolean'],
            'tiene_recompensas' => ['boolean'],
            'solo_verificados' => ['boolean'],
            'recompensa_min' => ['nullable', 'numeric', 'min:0'],
            'recompensa_max' => ['nullable', 'numeric', 'gte:recompensa_min'],
            'moneda' => ['nullable', 'string', 'max:10'],
            'tabla_recompensas' => ['nullable', 'array'],
            'nivel_acceso' => ['sometimes', Rule::enum(NivelAcceso::class)],
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
                Rule::requiredIf(fn () => (bool) $this->user()?->empresas()->wherePivot('estado', 'activo')->exists()),
                'nullable',
                'array',
                'min:1',
            ],
            'objetivos.*.tipo' => ['required_with:objetivos', Rule::in(['web', 'api', 'movil', 'otro'])],
            'objetivos.*.valor' => ['required_with:objetivos', 'string', 'max:255'],
            'objetivos.*.descripcion' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $empresa = $this->user()?->empresas()->wherePivot('estado', 'activo')->first();
            if ($empresa !== null && ! $empresa->puedeAccederElite()) {
                if ($this->boolean('es_publico') === false) {
                    $validator->errors()->add('es_publico', 'Los programas privados son exclusivos del Plan Profesional. Actualiza tu suscripción para invitar a investigadores seleccionados.');
                }
                if ($this->boolean('solo_verificados') === true) {
                    $validator->errors()->add('solo_verificados', 'El filtro de Investigadores Verificados es exclusivo del Plan Profesional.');
                }
                $nivel = $this->input('nivel_acceso');
                if (in_array($nivel, ['medio', 'alto'], true)) {
                    $validator->errors()->add('nivel_acceso', 'Restringir programas a investigadores de élite (Plata u Oro) requiere el Plan Profesional.');
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'objetivos.required' => 'Agrega al menos un objetivo: define qué sistemas pueden investigar los investigadores.',
            'objetivos.min' => 'Agrega al menos un objetivo: define qué sistemas pueden investigar los investigadores.',
            'objetivos.*.valor.required' => 'Indica el objetivo (por ejemplo un dominio, una API o una aplicación).',
            'nombre.required' => 'El nombre es obligatorio.',
            'nombre.max' => 'El nombre no puede exceder 255 caracteres.',
            'descripcion.required' => 'La descripción es obligatoria.',
            'descripcion.max' => 'La descripción no puede exceder 5000 caracteres.',
            'inicia_en.date' => 'La fecha de inicio debe ser una fecha válida.',
            'termina_en.after_or_equal' => 'La fecha de fin debe ser igual o posterior a la de inicio.',
        ];
    }
}
