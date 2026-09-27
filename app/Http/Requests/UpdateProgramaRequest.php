<?php

namespace App\Http\Requests;

use App\Abac\AccionesAbac;
use App\Enums\NivelAcceso;
use App\Models\Programa;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateProgramaRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Programa $programa */
        $programa = $this->route('programa');

        $empresa = $this->user()?->empresas()
            ->where('empresas.estado', 'aprobada')
            ->where('empresa_usuario.estado', 'activo')
            ->first();

        return Gate::allows('abac', [
            AccionesAbac::ProgramaEditar,
            $programa,
            $empresa === null ? [] : ['empresa_id' => $empresa->id],
        ]);
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('es_publico')) {
            $this->merge([
                'es_publico' => $this->boolean('es_publico'),
            ]);
        }
        if ($this->has('tiene_recompensas')) {
            $this->merge([
                'tiene_recompensas' => $this->boolean('tiene_recompensas'),
            ]);
        }
        if ($this->has('solo_verificados')) {
            $this->merge([
                'solo_verificados' => $this->boolean('solo_verificados'),
            ]);
        }
    }

    /**
     * @return array<string, array<int, string|ValidationRule>>
     */
    public function rules(): array
    {
        return [
            'nombre' => ['sometimes', 'required', 'string', 'max:255'],
            'descripcion' => ['sometimes', 'required', 'string', 'max:5000'],
            'bugs_buscados' => ['nullable', 'string', 'max:3000'],
            'es_publico' => ['boolean'],
            'tiene_recompensas' => ['boolean'],
            'solo_verificados' => ['boolean'],
            'recompensa_min' => ['nullable', 'numeric', 'min:0'],
            'recompensa_max' => ['nullable', 'numeric', 'gte:recompensa_min'],
            'moneda' => ['nullable', 'string', 'max:10'],
            'tabla_recompensas' => ['nullable', 'array'],
            'nivel_acceso' => ['sometimes', 'required', Rule::enum(NivelAcceso::class)],
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
            'objetivos' => ['nullable', 'array'],
            'objetivos.*.id' => ['nullable', 'integer'],
            'objetivos.*.tipo' => ['required_with:objetivos', Rule::in(['web', 'api', 'movil', 'otro'])],
            'objetivos.*.valor' => ['required_with:objetivos', 'string', 'max:255'],
            'objetivos.*.descripcion' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * Sin Plan Profesional no se puede SUBIR la exigencia de un programa (hacerlo privado,
     * solo para verificados o de nivel élite), pero sí editar uno que ya lo era: así una
     * empresa que perdió el plan no queda sin poder corregir sus programas.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $empresa = $this->user()?->empresas()->wherePivot('estado', 'activo')->first();
            $programa = $this->route('programa');

            if ($empresa === null || $empresa->puedeAccederElite() || ! $programa instanceof Programa) {
                return;
            }

            if ($this->has('es_publico') && $this->boolean('es_publico') === false && $programa->es_publico) {
                $validator->errors()->add('es_publico', 'Los programas privados son exclusivos del Plan Profesional. Actualiza tu suscripción para invitar a investigadores seleccionados.');
            }
            if ($this->has('solo_verificados') && $this->boolean('solo_verificados') === true && ! $programa->solo_verificados) {
                $validator->errors()->add('solo_verificados', 'El filtro de Investigadores Verificados es exclusivo del Plan Profesional.');
            }
            $nivel = $this->input('nivel_acceso');
            if (in_array($nivel, ['medio', 'alto'], true) && $nivel !== $programa->nivel_acceso->value) {
                $validator->errors()->add('nivel_acceso', 'Restringir programas a investigadores de élite (Plata u Oro) requiere el Plan Profesional.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'nombre.required' => 'El nombre es obligatorio.',
            'nombre.max' => 'El nombre no puede exceder 255 caracteres.',
            'descripcion.required' => 'La descripción es obligatoria.',
            'descripcion.max' => 'La descripción no puede exceder 5000 caracteres.',
            'termina_en.after_or_equal' => 'La fecha de fin debe ser igual o posterior a la de inicio.',
        ];
    }
}
