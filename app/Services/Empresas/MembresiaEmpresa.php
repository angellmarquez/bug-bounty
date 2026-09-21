<?php

namespace App\Services\Empresas;

use App\Enums\EstadoEmpresa;
use App\Models\Empresa;
use App\Models\EmpresaInvitacion;
use App\Models\User;
use App\Services\Notificaciones\Notificador;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Quién puede formar parte de una empresa y cómo entra y sale.
 *
 * Reglas:
 *  - Solo se invita a investigadores ya registrados, por su correo (único en la plataforma).
 *  - Un moderador no puede pertenecer a una empresa (conflicto de interés): ve los informes de otros.
 *  - Un usuario pertenece a una sola empresa.
 *  - Entra si acepta la invitación; nadie es añadido sin su consentimiento.
 *  - Un investigador que ya reportó a esa empresa SÍ puede entrar: sigue viendo el estado de sus
 *    informes, pero mientras sea miembro no puede reportar a sus programas (lo aplica ABAC).
 */
class MembresiaEmpresa
{
    public const PROPIETARIO = 'propietario';

    public const PUBLICADOR = 'publicador';

    public const VIGENCIA_DIAS = 7;

    public function __construct(private readonly Notificador $notificador) {}

    /** ¿Por qué esta persona no puede unirse a la empresa? `null` si puede. */
    public function motivoDeRechazo(User $invitado, Empresa $empresa, bool $propio = false): ?string
    {
        $roles = $invitado->roles()->pluck('slug')->all();

        if (in_array('moderador', $roles, true)) {
            return 'Un moderador no puede formar parte de una empresa: revisa los informes de otros investigadores y tendría un conflicto de interés.';
        }

        if (in_array('administrador', $roles, true)) {
            return 'Un administrador no puede formar parte de una empresa.';
        }

        if (! in_array('investigador', $roles, true)) {
            return 'Solo se puede invitar a investigadores.';
        }

        $actual = $invitado->empresas()->wherePivot('estado', 'activo')->first();

        if ($actual !== null) {
            if ($propio) {
                return $actual->is($empresa)
                    ? 'Ya formas parte de esta empresa.'
                    : 'Ya perteneces a otra empresa: solo se permite una por usuario.';
            }

            return $actual->is($empresa)
                ? 'Esa persona ya forma parte de tu empresa.'
                : 'Esa persona ya pertenece a otra empresa: solo se permite una por usuario.';
        }

        return null;
    }

    /**
     * Invita a un investigador registrado, por el correo con el que se registró.
     *
     * @throws InvalidArgumentException con un mensaje listo para mostrar
     */
    public function invitar(Empresa $empresa, User $propietario, string $correo): EmpresaInvitacion
    {
        if ($empresa->estado !== EstadoEmpresa::Aprobada) {
            throw new InvalidArgumentException('La empresa debe estar aprobada para invitar a alguien.');
        }

        $invitado = User::query()->whereRaw('lower(email) = ?', [mb_strtolower(trim($correo))])->first();

        if ($invitado === null) {
            throw new InvalidArgumentException('No hay ningún usuario registrado con ese correo. Pídele que se registre como investigador primero.');
        }

        if ($invitado->is($propietario)) {
            throw new InvalidArgumentException('Ya eres parte de tu empresa.');
        }

        $motivo = $this->motivoDeRechazo($invitado, $empresa);

        if ($motivo !== null) {
            throw new InvalidArgumentException($motivo);
        }

        $pendiente = $empresa->invitaciones()->where('usuario_id', $invitado->id)->where('estado', 'pendiente')->where('expira_en', '>', now())->exists();

        if ($pendiente) {
            throw new InvalidArgumentException('Ya hay una invitación pendiente para esa persona.');
        }

        $invitacion = $empresa->invitaciones()->create([
            'usuario_id' => $invitado->id,
            'email' => $invitado->email,
            'token' => Str::random(64),
            'rol_interno' => self::PUBLICADOR,
            'estado' => 'pendiente',
            'invitado_por' => $propietario->id,
            'expira_en' => now()->addDays(self::VIGENCIA_DIAS),
        ]);

        $this->notificador->invitacionRecibida($invitacion->load(['empresa', 'invitadoPor', 'usuario']));

        return $invitacion;
    }

    /**
     * El invitado acepta: entra a la empresa como publicador.
     *
     * @throws InvalidArgumentException con un mensaje listo para mostrar
     */
    public function aceptar(EmpresaInvitacion $invitacion, User $usuario): void
    {
        $this->comprobarDestinatario($invitacion, $usuario);

        // Las reglas se vuelven a comprobar: desde que se invitó pudo pasar a ser moderador
        // o unirse a otra empresa.
        $motivo = $this->motivoDeRechazo($usuario, $invitacion->empresa, propio: true);

        if ($motivo !== null) {
            throw new InvalidArgumentException($motivo);
        }

        if ($invitacion->empresa->estado !== EstadoEmpresa::Aprobada) {
            throw new InvalidArgumentException('La empresa ya no está activa en la plataforma.');
        }

        DB::transaction(function () use ($invitacion, $usuario): void {
            $invitacion->empresa->usuarios()->attach($usuario->id, [
                'rol_interno' => $invitacion->rol_interno,
                'estado' => 'activo',
                'invitado_en' => $invitacion->created_at,
                'aceptado_en' => now(),
            ]);

            $invitacion->update(['estado' => 'aceptada', 'aceptado_en' => now(), 'respondida_en' => now()]);

            // Las demás invitaciones pendientes que tuviera dejan de tener sentido: solo una empresa.
            EmpresaInvitacion::query()
                ->where('usuario_id', $usuario->id)
                ->where('estado', 'pendiente')
                ->update(['estado' => 'cancelada', 'respondida_en' => now()]);
        });

        $this->notificador->invitacionRespondida($invitacion, true);
    }

    public function rechazar(EmpresaInvitacion $invitacion, User $usuario): void
    {
        $this->comprobarDestinatario($invitacion, $usuario);

        $invitacion->update(['estado' => 'rechazada', 'respondida_en' => now()]);

        $this->notificador->invitacionRespondida($invitacion, false);
    }

    /** El propietario retira una invitación que aún no fue respondida. */
    public function cancelar(EmpresaInvitacion $invitacion): void
    {
        if ($invitacion->estado !== 'pendiente') {
            throw new InvalidArgumentException('Esa invitación ya fue respondida.');
        }

        $invitacion->update(['estado' => 'cancelada', 'respondida_en' => now()]);

        $this->notificador->invitacionCancelada($invitacion);
    }

    /** El propietario retira a un publicador de la empresa. */
    public function retirar(Empresa $empresa, User $miembro): void
    {
        $fila = $empresa->usuarios()->whereKey($miembro->id)->first();

        if ($fila === null) {
            throw new InvalidArgumentException('Esa persona no pertenece a la empresa.');
        }

        if ($fila->pivot->rol_interno === self::PROPIETARIO) {
            throw new InvalidArgumentException('No se puede retirar al propietario.');
        }

        $empresa->usuarios()->detach($miembro->id);

        $this->notificador->miembroRetirado($miembro, $empresa);
    }

    /** ¿Tiene ya una empresa activa? Un moderador no debería (se lo impide antes el flujo de invitación). */
    public function pertenece(User $usuario): bool
    {
        return $usuario->empresas()->wherePivot('estado', 'activo')->exists();
    }

    private function comprobarDestinatario(EmpresaInvitacion $invitacion, User $usuario): void
    {
        if ((int) $invitacion->usuario_id !== (int) $usuario->id) {
            throw new InvalidArgumentException('Esta invitación no es tuya.');
        }

        if ($invitacion->estado !== 'pendiente') {
            throw new InvalidArgumentException('Esta invitación ya fue respondida.');
        }

        if ($invitacion->expira_en->isPast()) {
            throw new InvalidArgumentException('La invitación expiró. Pide una nueva al propietario de la empresa.');
        }
    }
}
