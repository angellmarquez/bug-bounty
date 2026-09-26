<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\Rol;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        // El correo se guarda siempre en minúsculas: "Ana@X.com" y "ana@x.com" son la misma cuenta.
        if (isset($input['email'])) {
            $input['email'] = mb_strtolower(trim($input['email']));
        }

        Validator::make($input, [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
            'terminos' => ['accepted'],
        ], [
            ...$this->profileMessages(),
            'terminos.accepted' => 'Para crear la cuenta debes aceptar los Términos de Servicio y la Política de Privacidad.',
        ])->validate();

        return DB::transaction(function () use ($input) {
            $user = User::create([
                'name' => $input['name'],
                'email' => $input['email'],
                'password' => $input['password'],
                'terminos_aceptados_en' => now(),
                'terminos_version' => config('legal.version'),
            ]);

            $rolInvestigador = Rol::firstOrCreate(
                ['slug' => 'investigador'],
                [
                    'nombre' => 'Investigador',
                    'descripcion' => 'Rol por defecto para nuevos registros. Presenta reportes y gestiona su perfil PGP.',
                ],
            );

            $user->roles()->attach($rolInvestigador);

            return $user;
        });
    }
}
