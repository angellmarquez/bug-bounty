<?php

namespace App\Http\Controllers;

use App\Models\Empresa;
use App\Models\Rol;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Laravel\Fortify\Features;

class EmpresaAuthController extends Controller
{
    public function login(): InertiaResponse
    {
        return Inertia::render('auth/EmpresaLogin', [
            'canResetPassword' => Features::enabled(Features::resetPasswords()),
        ]);
    }

    public function create(): InertiaResponse
    {
        return Inertia::render('auth/EmpresaRegister', [
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'razon_social' => ['required', 'string', 'max:255'],
            'nombre_comercial' => ['nullable', 'string', 'max:255'],
            'identificador_fiscal' => ['required', 'string', 'max:100', 'unique:empresas,identificador_fiscal'],
            'empresa_email' => ['required', 'email', 'max:255'],
            'telefono' => ['nullable', 'string', 'max:50'],
            'sitio_web' => ['nullable', 'url', 'max:255'],
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
