<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

/**
 * Aviso dentro de la plataforma (la campana). Por ahora solo se guarda en la base de datos:
 * no se envía por correo.
 *
 * `tipo` agrupa el aviso (informe, sancion, apelacion, empresa, moderacion, reputacion, invitacion)
 * y `url` es una ruta interna a la que lleva al abrirlo.
 */
class AvisoPlataforma extends Notification
{
    public function __construct(
        public readonly string $tipo,
        public readonly string $titulo,
        public readonly string $mensaje,
        public readonly ?string $url = null,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'tipo' => $this->tipo,
            'titulo' => $this->titulo,
            'mensaje' => $this->mensaje,
            'url' => $this->url,
        ];
    }
}
