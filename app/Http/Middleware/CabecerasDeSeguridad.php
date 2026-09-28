<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cabeceras HTTP de endurecimiento en todas las respuestas web:
 *  - la página no se puede incrustar en otro sitio (clickjacking),
 *  - el navegador no "adivina" tipos de archivo (MIME sniffing),
 *  - no se filtra la URL completa (con ids de informes) a otros dominios,
 *  - sin acceso a cámara, micrófono ni ubicación,
 *  - formularios y <base> solo hacia este mismo sitio, sin plugins (<object>),
 *  - solo se ejecutan los scripts del propio sitio (CSP con nonce): un XSS no podría
 *    cargar ni ejecutar código ajeno,
 *  - en HTTPS, el navegador recuerda usar siempre HTTPS (HSTS).
 */
class CabecerasDeSeguridad
{
    public function handle(Request $request, Closure $next): Response
    {
        // Nonce de esta respuesta: Vite lo pone en sus <script> y la vista en el script del tema.
        $nonce = Vite::useCspNonce();

        $respuesta = $next($request);

        $cabeceras = [
            'X-Frame-Options' => 'DENY',
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=(), usb=()',
            'Cross-Origin-Opener-Policy' => 'same-origin',
            'Content-Security-Policy' => $this->politicaDeContenido($nonce, $request->isSecure()),
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

    /**
     * Todo sale del propio sitio. Scripts: solo los de Vite y el del tema, con el nonce (nada en
     * línea sin él, ni eval). Estilos en línea sí: Svelte anima con `style` y las fuentes se
     * declaran en un <style>; no ejecutan código. Imágenes `data:`/`blob:`: vista previa de las
     * fotos antes de subirlas. MetaMask no se ve afectado: la extensión inyecta su propio código.
     */
    private function politicaDeContenido(string $nonce, bool $https): string
    {
        $origenesVite = [];
        if (Vite::isRunningHot()) {
            // En desarrollo (npm run dev) los scripts y la recarga en caliente vienen del servidor de Vite.
            $vite = rtrim((string) file_get_contents(Vite::hotFile()));
            $origenesVite = [$vite, (string) preg_replace('#^http#', 'ws', $vite)];
        }

        $directivas = [
            'default-src' => ["'self'"],
            'script-src' => ["'self'", "'nonce-{$nonce}'", ...$origenesVite],
            'style-src' => ["'self'", "'unsafe-inline'", ...$origenesVite],
            'img-src' => ["'self'", 'data:', 'blob:'],
            'font-src' => ["'self'", 'data:'],
            'connect-src' => ["'self'", ...$origenesVite],
            'frame-ancestors' => ["'none'"],
            'base-uri' => ["'self'"],
            'form-action' => ["'self'"],
            'object-src' => ["'none'"],
        ];

        $politica = collect($directivas)
            ->map(fn (array $origenes, string $directiva): string => $directiva.' '.implode(' ', array_unique($origenes)))
            ->implode('; ');

        return $https ? $politica.'; upgrade-insecure-requests' : $politica;
    }
}
