<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cabeceras HTTP de endurecimiento en todas las respuestas web:
 *  - la página no se puede incrustar en otro sitio (clickjacking),
 *  - el navegador no "adivina" tipos de archivo (MIME sniffing),
 *  - no se filtra la URL completa (con ids de informes) a otros dominios,
 *  - sin acceso a cámara, micrófono ni ubicación,
 *  - formularios y <base> solo hacia este mismo sitio, sin plugins (<object>),
 *  - en HTTPS, el navegador recuerda usar siempre HTTPS (HSTS).
 */
class CabecerasDeSeguridad
{
    public function handle(Request $request, Closure $next): Response
    {
        $respuesta = $next($request);

        $cabeceras = [
            'X-Frame-Options' => 'DENY',
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=(), usb=()',
            'Cross-Origin-Opener-Policy' => 'same-origin',
            // Política mínima que no rompe Vite ni Inertia: no restringe scripts, pero sí
            // quién puede enmarcar la página, a dónde se envían formularios y el uso de plugins.
            'Content-Security-Policy' => "frame-ancestors 'none'; base-uri 'self'; form-action 'self'; object-src 'none'",
        ];

        if ($request->isSecure()) {
            $cabeceras['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains';
        }

        foreach ($cabeceras as $nombre => $valor) {
            if (! $respuesta->headers->has($nombre)) {
                $respuesta->headers->set($nombre, $valor);
            }
        }

        // No anunciar la tecnología ni la versión del servidor. PHP añade esta cabecera por su
        // cuenta (expose_php), fuera de la respuesta de Laravel: hay que quitarla en los dos sitios.
        $respuesta->headers->remove('X-Powered-By');
        if (! headers_sent()) {
            header_remove('X-Powered-By');
        }

        return $respuesta;
    }
}
