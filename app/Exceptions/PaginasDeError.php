<?php

namespace App\Exceptions;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Convierte los errores HTTP en una página de la aplicación (con su diseño, su menú y una salida
 * clara) en lugar de la pantalla blanca por defecto de Laravel.
 *
 * - Las peticiones JSON no cambian.
 * - Con la depuración activada, un error 500 sigue mostrando el detalle (para quien desarrolla).
 * - Nunca se muestra el mensaje de una excepción interna ni las reglas de permisos: solo los
 *   mensajes que el propio código escribe con `abort()`.
 */
class PaginasDeError
{
    /** Estados que se muestran con la página de error de la aplicación. */
    private const ESTADOS = [403, 404, 405, 409, 410, 422, 429, 500, 503];

    /** Estados en los que el mensaje lo escribió el propio código con `abort()` (y es para el usuario). */
    private const CON_MENSAJE = [403, 409, 410, 422];

    /** Los mensajes por defecto de Laravel, en inglés, no se enseñan. */
    private const MENSAJES_GENERICOS = ['', 'Forbidden', 'This action is unauthorized.', 'Unauthorized'];

    public function __invoke(Response $respuesta, Throwable $error, Request $peticion): Response
    {
        if ($peticion->expectsJson() || $peticion->is('api/*')) {
            return $respuesta;
        }

        $estado = $respuesta->getStatusCode();

        // Una sesión caducada (419) no necesita una página: se vuelve atrás con un aviso.
        if ($estado === 419) {
            return back()->with('error', 'La página expiró. Vuelve a intentarlo.');
        }

        if (! in_array($estado, self::ESTADOS, true)) {
            return $respuesta;
        }

        // Un 422 «real» (validación) ya redirige; aquí solo llegan los abort(422).
        if ($estado === 422 && ! $error instanceof HttpExceptionInterface) {
            return $respuesta;
        }

        if ($estado === 500 && config('app.debug')) {
            return $respuesta;
        }

        return Inertia::render('Error', [
            'status' => $estado,
            'mensaje' => $this->mensajePara($estado, $error),
            'reintentar_en' => $estado === 429 ? $this->segundosDeEspera($respuesta) : null,
        ])->toResponse($peticion)->setStatusCode($estado);
    }

    private function mensajePara(int $estado, Throwable $error): ?string
    {
        // Solo un abort() escrito por nosotros: un rechazo del sistema de permisos (con los
        // nombres de sus reglas) o un error interno nunca llegan a la pantalla.
        if (! in_array($estado, self::CON_MENSAJE, true) || get_class($error) !== HttpException::class) {
            return null;
        }

        $mensaje = trim($error->getMessage());

        return in_array($mensaje, self::MENSAJES_GENERICOS, true) ? null : $mensaje;
    }

    private function segundosDeEspera(Response $respuesta): ?int
    {
        $espera = $respuesta->headers->get('Retry-After');

        return is_numeric($espera) ? max(1, (int) $espera) : null;
    }
}
