<?php

namespace App\Services\Notificaciones;

use App\Enums\TipoEventoReporte;
use App\Models\Apelacion;
use App\Models\ClavePgpPlataforma;
use App\Models\Empresa;
use App\Models\EmpresaInvitacion;
use App\Models\EventoReporte;
use App\Models\Programa;
use App\Models\Reporte;
use App\Models\Sancion;
use App\Models\User;
use App\Notifications\AvisoPlataforma;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * Punto único donde se decide QUIÉN recibe QUÉ aviso dentro de la plataforma.
 *
 * Un aviso nunca debe romper la acción que lo origina: cualquier fallo se registra y se ignora.
 */
class Notificador
{
    private const ESTADOS = [
        'en_revision' => 'en revisión',
        'validado' => 'validado',
        'rechazado' => 'rechazado',
        'duplicado' => 'duplicado',
        'fuera_de_alcance' => 'fuera de alcance',
        'en_reparacion' => 'en reparación (la empresa lo está corrigiendo)',
        'cerrado' => 'cerrado como resuelto',
    ];

    // ------------------------------------------------------------------
    // Informes
    // ------------------------------------------------------------------

    public function desdeEventoDeInforme(EventoReporte $evento): void
    {
        $this->seguro(function () use ($evento): void {
            $reporte = $evento->reporte()->with(['programa.empresa', 'investigador'])->first();
            if ($reporte === null) {
                return;
            }

            $actor = $evento->actor_id === null ? null : User::query()->find($evento->actor_id);
            $ref = "{$reporte->numero_reporte} · {$reporte->titulo}";
            $url = "/reportes/{$reporte->id}";
            $programa = $reporte->programa;
            $propietarios = $this->propietarios($programa?->empresa);

            match ($evento->tipo) {
                TipoEventoReporte::Enviado => $this->enviar(
                    $this->moderadoresDe($programa)->merge($propietarios),
                    new AvisoPlataforma('informe', 'Nuevo informe recibido', "{$ref} en {$programa?->nombre}", $url),
                    $actor,
                ),
                TipoEventoReporte::CambioDeEstado => $this->cambioDeEstado($evento, $reporte, $actor, $ref, $url, $propietarios),
                TipoEventoReporte::MarcadoDuplicado => $this->enviar(
                    [$reporte->investigador],
                    new AvisoPlataforma('informe', 'Tu informe fue marcado como duplicado', $ref, $url),
                    $actor,
                ),
                TipoEventoReporte::Asignacion => $this->enviar(
                    [User::query()->find($evento->datos['asignado_a'] ?? 0)],
                    new AvisoPlataforma('informe', 'Se te asignó un informe', $ref, $url),
                    $actor,
                ),
                TipoEventoReporte::Comentario => $this->comentario($reporte, $actor, $ref, $url, $propietarios),
                default => null,
            };
        });
    }

    /** @param  Collection<int, User>  $propietarios */
    private function cambioDeEstado(EventoReporte $evento, Reporte $reporte, ?User $actor, string $ref, string $url, Collection $propietarios): void
    {
        $nuevo = (string) ($evento->datos['estado_nuevo'] ?? '');
        $estado = self::ESTADOS[$nuevo] ?? str_replace('_', ' ', $nuevo);

        $this->enviar(
            [$reporte->investigador],
            new AvisoPlataforma('informe', "Tu informe está {$estado}", $ref, $url),
            $actor,
        );

        // Al validarse, la empresa dueña del programa tiene que corregir la vulnerabilidad.
        if ($nuevo === 'validado') {
            $this->enviar(
                $propietarios,
                new AvisoPlataforma('informe', 'Informe validado: hay que corregirlo', $ref, $url),
                $actor,
            );
        }
    }

    /** @param  Collection<int, User>  $propietarios */
    private function comentario(Reporte $reporte, ?User $actor, string $ref, string $url, Collection $propietarios): void
    {
        // Un borrador solo lo ve su autor.
        if ($reporte->estado->value === 'borrador') {
            return;
        }

        $participantes = collect([$reporte->investigador, $reporte->asignado_a ? User::query()->find($reporte->asignado_a) : null])
            ->merge($propietarios);

        $quien = $actor?->name ?? 'Alguien';

        $this->enviar($participantes, new AvisoPlataforma('informe', 'Nuevo comentario en un informe', "{$quien} comentó en {$ref}", $url), $actor);
    }

    // ------------------------------------------------------------------
    // Sanciones, apelaciones y reputación
    // ------------------------------------------------------------------

    public function sancionAplicada(Sancion $sancion): void
    {
        $this->seguro(function () use ($sancion): void {
            $detalle = "Sanción {$sancion->gravedad->value}: {$sancion->motivo} ({$sancion->puntos} puntos).";

            if ($sancion->suspension_hasta !== null) {
                $detalle .= ' Suspendido hasta el '.$sancion->suspension_hasta->format('d/m/Y').'.';
            }

            if ($sancion->plazo_apelacion !== null) {
                $detalle .= ' Puedes apelar hasta el '.$sancion->plazo_apelacion->format('d/m/Y').'.';
            }

            $this->enviar([User::query()->find($sancion->usuario_id)], new AvisoPlataforma('sancion', 'Se te aplicó una sanción', $detalle, '/reputacion/sanciones'));
        });
    }

    public function sancionRevocada(Sancion $sancion): void
    {
        $this->seguro(fn () => $this->enviar(
            [User::query()->find($sancion->usuario_id)],
            new AvisoPlataforma('sancion', 'Se revocó tu sanción', 'Se te devolvieron '.abs($sancion->puntos).' puntos y se levantó la suspensión.', '/reputacion/sanciones'),
        ));
    }

    public function apelacionPresentada(Apelacion $apelacion): void
    {
        $this->seguro(function () use ($apelacion): void {
            $apelacion->loadMissing(['sancion', 'usuario']);

            $resolutores = User::query()
                ->whereHas('roles', fn ($q) => $q->whereIn('slug', ['moderador', 'administrador']))
                ->whereKeyNot($apelacion->usuario_id)
                ->when($apelacion->sancion->aplicada_por, function ($q, $sanciono) {
                    // Quien aplicó la sanción no la resuelve, salvo un administrador.
                    $q->where(fn ($q) => $q->whereKeyNot($sanciono)->orWhereHas('roles', fn ($r) => $r->where('slug', 'administrador')));
                })
                ->get();

            $this->enviar(
                $resolutores,
                new AvisoPlataforma('apelacion', 'Nueva apelación pendiente', "{$apelacion->usuario->name} apeló una sanción ({$apelacion->sancion->motivo}).", "/moderacion/apelaciones/{$apelacion->id}"),
            );
        });
    }

    public function apelacionResuelta(Apelacion $apelacion, bool $aprobada, ?User $resolutor): void
    {
        $this->seguro(function () use ($apelacion, $aprobada, $resolutor): void {
            $apelacion->loadMissing(['sancion', 'usuario']);
            $resultado = $aprobada ? 'aprobada' : 'rechazada';

            $this->enviar(
                [$apelacion->usuario],
                new AvisoPlataforma(
                    'apelacion',
                    "Tu apelación fue {$resultado}",
                    $aprobada ? 'La sanción se revocó y se te devolvieron los puntos.' : 'La sanción sigue vigente. Cada sanción se apela una sola vez.',
                    "/reputacion/apelaciones/{$apelacion->id}",
                ),
            );

            // Quien aplicó la sanción se entera de cómo terminó la apelación.
            $sanciono = $apelacion->sancion->aplicada_por ? User::query()->find($apelacion->sancion->aplicada_por) : null;
            $this->enviar(
                [$sanciono],
                new AvisoPlataforma('apelacion', "Apelación {$resultado}", "La apelación de {$apelacion->usuario->name} contra la sanción que aplicaste fue {$resultado}.", "/moderacion/apelaciones/{$apelacion->id}"),
                $resolutor,
            );
        });
    }

    /** @param  array{nombre: string, minimo: int}  $antes @param  array{nombre: string, minimo: int}  $despues */
    public function rangoCambiado(int $usuarioId, array $antes, array $despues): void
    {
        if ($antes['minimo'] === $despues['minimo']) {
            return;
        }

        $sube = $despues['minimo'] > $antes['minimo'];

        $this->seguro(fn () => $this->enviar(
            [User::query()->find($usuarioId)],
            new AvisoPlataforma('reputacion', $sube ? "Subiste al rango {$despues['nombre']}" : "Bajaste al rango {$despues['nombre']}", $sube ? 'Ya puedes acceder a programas de un nivel más alto.' : 'Puede que ya no tengas acceso a algunos programas.', '/reputacion'),
        ));
    }

    // ------------------------------------------------------------------
    // Empresas y moderación
    // ------------------------------------------------------------------

    public function empresaPendiente(Empresa $empresa): void
    {
        $this->seguro(fn () => $this->enviar(
            $this->administradores(),
            new AvisoPlataforma('empresa', 'Empresa pendiente de aprobación', "{$empresa->razon_social} solicitó acceso a la plataforma.", '/admin/empresas'),
        ));
    }

    public function empresaDecidida(Empresa $empresa, string $decision): void
    {
        $this->seguro(function () use ($empresa, $decision): void {
            [$titulo, $mensaje] = match ($decision) {
                'aprobada' => ['Tu empresa fue aprobada', 'Ya puedes publicar programas y recibir informes.'],
                'rechazada' => ['Tu empresa fue rechazada', $empresa->motivo_estado ?: 'Revisa los datos y vuelve a solicitarlo.'],
                'suspendida' => ['Tu empresa fue suspendida', $empresa->motivo_estado ?: 'Contacta con el administrador de la plataforma.'],
                default => ['Estado de tu empresa actualizado', ''],
            };

            $this->enviar($this->propietarios($empresa), new AvisoPlataforma('empresa', $titulo, $mensaje, '/empresa'));
        });
    }

    public function moderadorRol(User $usuario, bool $asignado): void
    {
        $this->seguro(fn () => $this->enviar(
            [$usuario],
            new AvisoPlataforma('moderacion', $asignado ? 'Ahora eres moderador' : 'Ya no eres moderador', $asignado ? 'Cuando te asignen programas verás sus informes en Moderación.' : 'Dejaste de tener acceso a la moderación de programas.', '/moderacion'),
        ));
    }

    public function moderadorPrograma(User $usuario, Programa $programa, bool $asignado): void
    {
        $this->seguro(fn () => $this->enviar(
            [$usuario],
            new AvisoPlataforma('moderacion', $asignado ? 'Se te asignó un programa' : 'Te retiraron de un programa', $asignado ? "Ahora moderas {$programa->nombre}." : "Ya no moderas {$programa->nombre}.", '/moderacion'),
        ));
    }

    // ------------------------------------------------------------------
    // Cifrado de la plataforma
    // ------------------------------------------------------------------

    public function claveGenerada(ClavePgpPlataforma $clave, string $origen): void
    {
        $this->seguro(function () use ($clave, $origen): void {
            $huella = trim(chunk_split(substr($clave->huella, 0, 16), 4, ' '));
            $como = $origen === 'automatico' ? 'al recibir un informe sin clave' : 'durante la instalación';

            $this->enviar(
                $this->administradores(),
                new AvisoPlataforma('sistema', 'Se creó la clave de cifrado', "El sistema generó una clave PGP nueva {$como} (huella {$huella}…). Los informes nuevos se cifran con ella.", '/admin/pgp'),
            );
        });
    }

    // ------------------------------------------------------------------
    // Invitaciones a una empresa
    // ------------------------------------------------------------------

    public function invitacionRecibida(EmpresaInvitacion $invitacion): void
    {
        $this->seguro(function () use ($invitacion): void {
            $invitacion->loadMissing(['empresa', 'invitadoPor', 'usuario']);
            $nombre = $invitacion->empresa->nombre_comercial ?? $invitacion->empresa->razon_social;

            $this->enviar(
                [$invitacion->usuario],
                new AvisoPlataforma(
                    'invitacion',
                    'Te invitaron a formar parte de una empresa',
                    "{$invitacion->invitadoPor->name} te invita a publicar programas de {$nombre}. Míralo y decide si aceptas.",
                    '/invitaciones',
                ),
            );
        });
    }

    public function invitacionRespondida(EmpresaInvitacion $invitacion, bool $aceptada): void
    {
        $this->seguro(function () use ($invitacion, $aceptada): void {
            $invitacion->loadMissing(['empresa', 'usuario']);
            $resultado = $aceptada ? 'aceptó' : 'rechazó';

            $this->enviar(
                $this->propietarios($invitacion->empresa),
                new AvisoPlataforma('invitacion', "{$invitacion->usuario->name} {$resultado} tu invitación", $aceptada ? 'Ya puede publicar programas de tu empresa.' : 'La invitación quedó cerrada.', '/empresa'),
            );
        });
    }

    public function invitacionCancelada(EmpresaInvitacion $invitacion): void
    {
        $this->seguro(function () use ($invitacion): void {
            $invitacion->loadMissing(['empresa', 'usuario']);
            $nombre = $invitacion->empresa->nombre_comercial ?? $invitacion->empresa->razon_social;

            $this->enviar([$invitacion->usuario], new AvisoPlataforma('invitacion', 'Se canceló una invitación', "{$nombre} retiró su invitación.", '/invitaciones'));
        });
    }

    public function miembroRetirado(User $miembro, Empresa $empresa): void
    {
        $this->seguro(function () use ($miembro, $empresa): void {
            $nombre = $empresa->nombre_comercial ?? $empresa->razon_social;

            $this->enviar([$miembro], new AvisoPlataforma('invitacion', 'Ya no formas parte de una empresa', "{$nombre} te retiró. Ya puedes reportar a sus programas.", '/dashboard'));
        });
    }

    // ------------------------------------------------------------------
    // Ayudas
    // ------------------------------------------------------------------

    /** @return Collection<int, User> */
    private function moderadoresDe(?Programa $programa): Collection
    {
        if ($programa === null) {
            return collect();
        }

        return User::query()
            ->whereHas('roles', fn ($q) => $q->where('slug', 'moderador'))
            ->whereHas('programasModerados', fn ($q) => $q->whereKey($programa->id))
            ->get();
    }

    /** @return Collection<int, User> */
    private function propietarios(?Empresa $empresa): Collection
    {
        if ($empresa === null) {
            return collect();
        }

        return $empresa->usuarios()
            ->wherePivot('rol_interno', 'propietario')
            ->wherePivot('estado', 'activo')
            ->get();
    }

    /** @return Collection<int, User> */
    private function administradores(): Collection
    {
        return User::query()->whereHas('roles', fn ($q) => $q->where('slug', 'administrador'))->get();
    }

    /**
     * @param  iterable<int, User|null>|Collection<int, User|null>  $destinatarios
     */
    private function enviar(iterable $destinatarios, AvisoPlataforma $aviso, ?User $excepto = null): void
    {
        $usuarios = collect($destinatarios)
            ->filter()
            ->unique('id')
            ->reject(fn (User $usuario) => $excepto !== null && $usuario->id === $excepto->id)
            ->values();

        if ($usuarios->isNotEmpty()) {
            Notification::send($usuarios, $aviso);
        }
    }

    private function seguro(callable $accion): void
    {
        try {
            $accion();
        } catch (Throwable $e) {
            report($e);
        }
    }
}
