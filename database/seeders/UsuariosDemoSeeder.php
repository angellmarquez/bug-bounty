<?php

namespace Database\Seeders;

use App\Enums\GravedadSancion;
use App\Models\Rol;
use App\Models\User;
use App\Services\Reputacion\ReputationService;
use Illuminate\Database\Seeder;

/**
 * Investigadores de demostración para el panel de usuarios: uno por rango, uno suspendido,
 * uno sancionado sin suspensión y una cuenta desactivada. Los puntos y las sanciones pasan
 * por ReputationService para que ledger, saldo, suspensión y auditoría cuadren.
 *
 * Contraseña de todas las cuentas: `investigador`.
 */
class UsuariosDemoSeeder extends Seeder
{
    public function run(ReputationService $reputacion): void
    {
        $rol = Rol::where('slug', 'investigador')->firstOrFail();

        $crear = function (string $email, string $nombre, int $puntos, bool $activo = true) use ($rol, $reputacion): User {
            $usuario = User::updateOrCreate(
                ['email' => $email],
                ['name' => $nombre, 'password' => 'investigador', 'email_verified_at' => now(), 'is_active' => $activo],
            );
            $usuario->roles()->sync([$rol->id]);

            $faltan = $puntos - $reputacion->saldo($usuario);
            if ($faltan !== 0) {
                $reputacion->asentar($usuario->id, $faltan, 'participacion', metadata: ['origen' => 'datos de demostracion']);
            }

            return $usuario;
        };

        // Uno por rango (umbrales en config/reputacion.php).
        $crear('plata@bugbounty.local', 'Investigadora Plata', 150);
        $crear('oro@bugbounty.local', 'Investigador Oro', 420);
        $crear('platino@bugbounty.local', 'Investigadora Platino', 820);
        $crear('diamante@bugbounty.local', 'Investigador Diamante', 1650);

        // Sanción media: resta puntos y suspende 7 días.
        $suspendido = $crear('suspendido@bugbounty.local', 'Investigador Suspendido', 260);
        if ($suspendido->suspensionActiva() === null) {
            $reputacion->aplicarSancion($suspendido, 'fabricacion_evidencia', GravedadSancion::Media, metadata: ['origen' => 'datos de demostracion']);
        }

        // Sanción leve: resta puntos pero no suspende; la cuenta sigue activa.
        $sancionado = $crear('sancionado@bugbounty.local', 'Investigadora Sancionada', 180);
        if (! $sancionado->sanciones()->exists()) {
            $reputacion->aplicarSancion($sancionado, 'reporte_duplicado', GravedadSancion::Leve, metadata: ['origen' => 'datos de demostracion']);
        }

        // Desactivada por el administrador: ABAC le deniega todo.
        $crear('desactivado@bugbounty.local', 'Investigador Desactivado', 40, activo: false);
    }
}
