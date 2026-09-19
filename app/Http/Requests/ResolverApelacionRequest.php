<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ResolverApelacionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'aprobada' => ['required', 'boolean'],
            'nota' => ['required', 'string', 'max:2000'],
        ];
    }
}
