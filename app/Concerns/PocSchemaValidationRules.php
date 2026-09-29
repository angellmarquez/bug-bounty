<?php

namespace App\Concerns;

use Illuminate\Validation\Rule;

/**
 * Reglas del formulario de prueba de concepto que diseña la empresa (programas.poc_schema),
 * compartidas por crear y editar programa. Lo que el editor marca como obligatorio lo exige
 * también el servidor: un campo mal definido dejaría al investigador sin poder enviar su informe.
 */
trait PocSchemaValidationRules
{
    /**
     * @return array<string, array<int, mixed>>
     */
    protected function pocSchemaRules(): array
    {
        return [
            'poc_schema' => ['nullable', 'array', 'max:30'],
            'poc_schema.*.name' => ['required', 'string', 'max:100', 'regex:/^[a-z][a-z0-9_]*$/', 'distinct'],
            'poc_schema.*.label' => ['required', 'string', 'max:255'],
            'poc_schema.*.type' => ['required', Rule::in(['text', 'textarea', 'select', 'number', 'url', 'code'])],
            'poc_schema.*.required' => ['boolean'],
            'poc_schema.*.placeholder' => ['nullable', 'string', 'max:255'],
            'poc_schema.*.help' => ['nullable', 'string', 'max:500'],
            // Un campo de selección sin opciones no se puede rellenar.
            'poc_schema.*.options' => ['nullable', 'array', 'required_if:poc_schema.*.type,select'],
            'poc_schema.*.options.*.value' => ['required', 'string', 'max:100'],
            'poc_schema.*.options.*.label' => ['required', 'string', 'max:255'],
            'poc_schema.*.repeatable' => ['boolean'],
            'poc_schema.*.defaultValue' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function pocSchemaMessages(): array
    {
        return [
            'poc_schema.max' => 'La prueba de concepto admite como máximo 30 campos.',
            'poc_schema.*.name.required' => 'Escribe el identificador del campo.',
            'poc_schema.*.name.regex' => 'El identificador solo puede tener minúsculas, números y guion bajo, y empezar por una letra (ej: url_afectada).',
            'poc_schema.*.name.distinct' => 'Hay dos campos con el mismo identificador.',
            'poc_schema.*.name.max' => 'El identificador puede tener como máximo 100 caracteres.',
            'poc_schema.*.label.required' => 'Escribe la etiqueta que verá el investigador.',
            'poc_schema.*.type.required' => 'Elige el tipo de campo.',
            'poc_schema.*.type.in' => 'Elige un tipo de campo válido.',
            'poc_schema.*.options.required_if' => 'Un campo de selección necesita al menos una opción.',
            'poc_schema.*.options.*.value.required' => 'Cada opción necesita un valor.',
            'poc_schema.*.options.*.label.required' => 'Cada opción necesita una etiqueta.',
        ];
    }
}
