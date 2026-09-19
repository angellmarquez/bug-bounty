<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegistrarClavePgpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'clave_publica' => ['required', 'string', 'min:10'],
            'es_principal' => ['sometimes', 'boolean'],
        ];
    }
}
