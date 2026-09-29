<?php

namespace App\Http\Requests;

use App\Abac\AccionesAbac;
use App\Concerns\PocSchemaValidationRules;
use App\Concerns\RecompensasValidationRules;
use App\Enums\EstadoPrograma;
use App\Enums\NivelAcceso;
use App\Models\Programa;
use App\Rules\FechaNoPasada;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateProgramaRequest extends FormRequest
{
    use PocSchemaValidationRules, RecompensasValidationRules;

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
        $this->normalizarTablaRecompensas();
    }

    private function programaActual(): ?Programa
    {
        $programa = $this->route('programa');

        return $programa instanceof Programa ? $programa : null;
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
            ...$this->recompensasRules(),
            'nivel_acceso' => ['sometimes', 'required', Rule::enum(NivelAcceso::class)],
            ...$this->pocSchemaRules(),
            'inicia_en' => ['nullable', 'date', new FechaNoPasada($this->programaActual()?->inicia_en, 'La fecha de inicio')],
            'termina_en' => ['nullable', 'date', 'after_or_equal:inicia_en', new FechaNoPasada($this->programaActual()?->termina_en, 'La fecha de fin')],
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
        $validator->after(fn (Validator $validator) => $this->validarFechas($validator));
        $validator->after(fn (Validator $validator) => $this->validarCoherenciaDeRecompensas($validator));

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

    /**
     * Un programa publicado no puede quedarse sin fechas, y un periodo que se cambia debe durar
     * el mínimo de días. Si las fechas no cambian no se vuelven a medir: un programa ya en
     * curso se sigue pudiendo editar aunque le queden menos días.
     */
    private function validarFechas(Validator $validator): void
    {
        $programa = $this->programaActual();

        if ($programa === null || $validator->errors()->hasAny(['inicia_en', 'termina_en'])) {
            return;
        }

        $inicioActual = $programa->inicia_en?->toDateString();
        $finActual = $programa->termina_en?->toDateString();
        $inicio = $this->has('inicia_en') ? ($this->input('inicia_en') ?: null) : $inicioActual;
        $fin = $this->has('termina_en') ? ($this->input('termina_en') ?: null) : $finActual;

        if ($programa->estado === EstadoPrograma::Activo) {
            foreach (['inicia_en' => $inicio, 'termina_en' => $fin] as $campo => $valor) {
                if ($valor === null) {
                    $validator->errors()->add($campo, 'Un programa publicado necesita fecha de inicio y de fin.');
                }
            }
        }

        if (($inicio !== $inicioActual || $fin !== $finActual)
            && ($error = Programa::errorDeDuracion($inicio, $fin)) !== null) {
            $validator->errors()->add('termina_en', $error);
        }
    }

    public function messages(): array
    {
        return [
            ...$this->pocSchemaMessages(),
            ...$this->recompensasMessages(),
            'nombre.required' => 'El nombre es obligatorio.',
            'nombre.max' => 'El nombre no puede exceder 255 caracteres.',
            'descripcion.required' => 'La descripción es obligatoria.',
            'descripcion.max' => 'La descripción no puede exceder 5000 caracteres.',
            'termina_en.after_or_equal' => 'La fecha de fin debe ser igual o posterior a la de inicio.',
        ];
    }
}
