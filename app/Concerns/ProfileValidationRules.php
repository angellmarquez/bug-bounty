<?php

namespace App\Concerns;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

trait ProfileValidationRules
{
    /**
     * Un nombre de persona: solo letras (con tildes y ñ), separadas por un espacio, un
     * apóstrofo o un guion ("María José", "O'Connor", "Ana-Lucía"). Sin números ni símbolos:
     * no son parte de un nombre y así tampoco entra texto con aspecto de código o de SQL.
     */
    public const PATRON_NOMBRE = "/^\\pL+(?:[ '\\-]\\pL+)*$/u";

    /**
     * Razón social o nombre comercial: letras, números, espacios y la puntuación habitual
     * de un nombre de empresa ("Acme S.A.", "B&B Seguridad", "Tech-3, C.A.").
     */
    public const PATRON_NOMBRE_EMPRESA = "/^[\\pL\\pN][\\pL\\pN .,&'\\-()]*$/u";

    /**
     * Get the validation rules used to validate user profiles.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function profileRules(?int $userId = null): array
    {
        return [
            'name' => $this->nameRules(),
            'email' => $this->emailRules($userId),
        ];
    }

    /**
     * Get the validation rules used to validate user names.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function nameRules(): array
    {
        return ['required', 'string', 'min:2', 'max:100', 'regex:'.self::PATRON_NOMBRE];
    }

    /**
     * Get the validation rules used to validate user emails.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function emailRules(?int $userId = null): array
    {
        return [
            'required',
            'string',
            // rfc + strict: rechaza direcciones con comillas, comentarios o caracteres de control.
            'email:rfc,strict',
            'max:255',
            $userId === null
                ? Rule::unique(User::class)
                : Rule::unique(User::class)->ignore($userId),
        ];
    }

    /**
     * Mensajes en español para las reglas de formato.
     *
     * @return array<string, string>
     */
    protected function profileMessages(): array
    {
        return [
            'name.regex' => 'El nombre solo puede tener letras y espacios (también tildes, ñ, apóstrofo o guion). Sin números ni símbolos.',
            'name.min' => 'El nombre debe tener al menos 2 letras.',
            'email.email' => 'Escribe un correo electrónico válido.',
        ];
    }
}
