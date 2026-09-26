<?php

namespace App\Http\Controllers;

use App\Concerns\ProfileValidationRules;
use App\Models\Auditoria;
use App\Models\Empresa;
use App\Models\Rol;
use App\Models\User;
use App\Services\Notificaciones\Notificador;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class EmpresaAuthController extends Controller
{
    use ProfileValidationRules;

    public function login(): InertiaResponse
    {
        return Inertia::render('auth/EmpresaLogin');
    }

    public function create(): InertiaResponse
    {
        return Inertia::render('auth/EmpresaRegister', [
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $request->all();
        foreach (['email', 'empresa_email'] as $campo) {
            if (isset($datos[$campo]) && is_string($datos[$campo])) {
                $datos[$campo] = mb_strtolower(trim($datos[$campo]));
            }
        }

        // Cada campo acepta solo el formato que le corresponde: nada de números en el nombre
        // de una persona ni símbolos sueltos en un teléfono o un identificador fiscal.
        $validated = Validator::make($datos, [
            'name' => $this->nameRules(),
            'email' => ['required', 'string', 'email:rfc,strict', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'razon_social' => ['required', 'string', 'min:2', 'max:255', 'regex:'.self::PATRON_NOMBRE_EMPRESA],
            'nombre_comercial' => ['nullable', 'string', 'max:255', 'regex:'.self::PATRON_NOMBRE_EMPRESA],
            'identificador_fiscal' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9][A-Za-z0-9.\-\/ ]*$/', 'unique:empresas,identificador_fiscal'],
            'empresa_email' => ['required', 'email:rfc,strict', 'max:255'],
            'telefono' => ['nullable', 'string', 'max:30', 'regex:/^\+?[0-9 ()\-]{6,30}$/'],
            'sitio_web' => ['nullable', 'url:http,https', 'max:255'],
            'terminos' => ['accepted'],
        ], [
            ...$this->profileMessages(),
            'razon_social.regex' => 'La razón social solo puede tener letras, números, espacios y . , & \' - ( ).',
            'nombre_comercial.regex' => 'El nombre comercial solo puede tener letras, números, espacios y . , & \' - ( ).',
            'identificador_fiscal.regex' => 'El identificador fiscal solo puede tener letras, números, puntos, guiones y barras.',
            'telefono.regex' => 'El teléfono solo puede tener números, espacios, paréntesis, guiones y un + inicial.',
            'sitio_web.url' => 'El sitio web debe ser una dirección http:// o https:// válida.',
            'terminos.accepted' => 'Para registrar la empresa debes aceptar los Términos de Servicio, la Política de Privacidad y la Política de Divulgación.',
        ])->validate();

        $user = DB::transaction(function () use ($validated): User {
            $empresa = Empresa::create([
                'razon_social' => $validated['razon_social'],
                'nombre_comercial' => $validated['nombre_comercial'] ?? null,
                'identificador_fiscal' => $validated['identificador_fiscal'],
                'slug' => $this->slugDisponible($validated['razon_social']),
                'email' => $validated['empresa_email'],
                'telefono' => $validated['telefono'] ?? null,
                'sitio_web' => $validated['sitio_web'] ?? null,
            ]);

            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => $validated['password'],
                'terminos_aceptados_en' => now(),
                'terminos_version' => config('legal.version'),
            ]);

            $rolEmpresa = Rol::firstOrCreate(
                ['slug' => 'empresa'],
                [
                    'nombre' => 'Empresa',
                    'descripcion' => 'Gestiona sus programas y recibe reportes de vulnerabilidades.',
                ],
            );

            $user->roles()->attach($rolEmpresa);
            $empresa->usuarios()->attach($user, [
                'rol_interno' => 'propietario',
                'estado' => 'activo',
            ]);

            return $user;
        });

        Auth::login($user);
        $request->session()->regenerate();

        $empresa = $user->empresas()->firstOrFail();
        Auditoria::registrar('empresa.registrada', $empresa, ['razon_social' => $empresa->razon_social]);
        app(Notificador::class)->empresaPendiente($empresa);

        return redirect()->route('empresa.dashboard')
            ->with('success', 'Solicitud registrada. Tu empresa queda pendiente de aprobación.');
    }

    private function slugDisponible(string $razonSocial): string
    {
        $base = Str::slug($razonSocial);
        $slug = $base;
        $contador = 2;

        while (Empresa::withTrashed()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$contador}";
            $contador++;
        }

        return $slug;
    }
}
