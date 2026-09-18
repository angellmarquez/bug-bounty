<?php

namespace App\Console\Commands;

use App\Abac\AbacEngine;
use App\Models\Apelacion;
use App\Models\ClavePgp;
use App\Models\Programa;
use App\Models\Reporte;
use App\Models\User;
use Illuminate\Console\Command;
use InvalidArgumentException;

/**
 * Audita la configuración ABAC y evalúa una acción concreta.
 *
 * Ejemplos:
 *   php artisan abac:audit                    -> lista todas las reglas
 *   php artisan abac:audit --usuario=1 --accion=reportes.ver --reporte=3
 */
class AbacAuditCommand extends Command
{
    protected $signature = 'abac:audit
        {--usuario= : ID del usuario a evaluar (omitir para invitado)}
        {--accion= : Acción a evaluar (p. ej. reportes.ver)}
        {--reporte= : ID del reporte como objeto}
        {--programa= : ID del programa como objeto}
        {--clave= : ID de clave PGP como objeto}
        {--apelacion= : ID de apelación como objeto}';

    protected $description = 'Audita la configuración ABAC y evalúa una acción concreta';

    /** @var array<string, class-string> */
    private const OBJETOS = [
        'reporte' => Reporte::class,
        'programa' => Programa::class,
        'clave' => ClavePgp::class,
        'apelacion' => Apelacion::class,
    ];

    public function handle(AbacEngine $engine): int
    {
        $accion = $this->option('accion');

        if ($accion === null) {
            return $this->listar();
        }

        return $this->evaluar((string) $accion, $engine);
    }

    private function listar(): int
    {
        $reglas = config('abac.reglas', []);

        if (! is_array($reglas) || $reglas === []) {
            $this->warn('No hay reglas configuradas en config/abac.php.');

            return self::FAILURE;
        }

        $this->line('=== Reglas ABAC ('.count($reglas).') ===');
        $this->line('deny_by_default: '.(config('abac.deny_by_default', true) ? 'activo (fail-closed)' : 'inactivo'));
        $this->newLine();

        foreach ($reglas as $regla) {
            if (! is_array($regla)) {
                continue;
            }

            $acciones = $regla['acciones'] ?? [];
            $id = (string) ($regla['id'] ?? '');
            $prioridad = (int) ($regla['prioridad'] ?? 100);
            $decision = (string) ($regla['decision'] ?? 'permitir');

            $this->line(sprintf('[%d] %s', $prioridad, $id));
            $this->line('    acciones: '.(is_array($acciones) ? implode(', ', array_map(strval(...), $acciones)) : ''));
            $this->line('    decisión: '.$decision);

            foreach (['sujeto', 'objeto', 'entorno'] as $grupo) {
                $condiciones = $regla[$grupo] ?? [];

                if (is_array($condiciones) && $condiciones !== []) {
                    $this->line('    '.$grupo.': '.json_encode($condiciones, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                }
            }

            $this->newLine();
        }

        $this->info('Usa --usuario, --accion y --reporte|--programa|--clave|--apelacion para evaluar un caso.');

        return self::SUCCESS;
    }

    private function evaluar(string $accion, AbacEngine $engine): int
    {
        $usuario = $this->resolverUsuario((string) ($this->option('usuario') ?? ''));
        $objeto = $this->resolverObjeto();

        $contexto = $engine->contexto($accion, $objeto, $usuario);
        $decision = $engine->evaluar($accion, $objeto, $usuario);

        $this->line('=== Evaluación ABAC ===');
        $this->line('Acción:   '.$accion);
        $this->line('Usuario:  '.($usuario === null ? '(invitado)' : sprintf('#%d %s', $usuario->id, $usuario->email)));
        $this->line('Objeto:   '.($objeto === null ? '(ninguno)' : sprintf('%s #%d', $objeto::class, $objeto->getKey())));
        $this->newLine();

        $this->mostrarAtributos('Sujeto', $contexto->sujeto);
        $this->mostrarAtributos('Objeto', $contexto->objetoAttrs);
        $this->mostrarAtributos('Entorno', $contexto->entorno);
        $this->newLine();

        foreach ($decision->detalle as $fila) {
            $grupos = $fila['grupos'] ?? [];

            if (is_array($grupos)) {
                $partes = [];

                foreach ($grupos as $grupo => $cumple) {
                    $partes[] = $grupo.': '.($cumple ? 'OK' : 'no');
                }

                $this->line(sprintf(
                    'Regla [%s]: %s (%s)',
                    (string) $fila['regla'],
                    ($fila['coincide'] ?? false) ? 'coincide' : 'no coincide',
                    implode(', ', $partes),
                ));
            }
        }

        $this->newLine();
        $this->line('Resultado: '.strtoupper($decision->decision->etiqueta()).' — '.$decision->motivo());

        return $decision->estaPermitida() ? self::SUCCESS : self::FAILURE;
    }

    private function resolverUsuario(string $id): ?User
    {
        if ($id === '') {
            return null;
        }

        /** @var User|null $usuario */
        $usuario = User::query()->find($id);

        if ($usuario === null) {
            throw new InvalidArgumentException("No existe el usuario [{$id}].");
        }

        return $usuario;
    }

    private function resolverObjeto(): mixed
    {
        $proporcionados = [];

        foreach (self::OBJETOS as $opcion => $clase) {
            $id = $this->option($opcion);

            if ($id !== null) {
                $proporcionados[$opcion] = (int) $id;
            }
        }

        if ($proporcionados === []) {
            return null;
        }

        if (count($proporcionados) > 1) {
            throw new InvalidArgumentException('Indica un único objeto: --reporte, --programa, --clave o --apelacion.');
        }

        $opcion = array_key_first($proporcionados);
        $id = $proporcionados[$opcion];
        $modelo = (self::OBJETOS[$opcion])::query()->find($id);

        if ($modelo === null) {
            throw new InvalidArgumentException("No existe el objeto [--{$opcion}={$id}].");
        }

        return $modelo;
    }

    /** @param array<string, mixed> $atributos */
    private function mostrarAtributos(string $titulo, array $atributos): void
    {
        $this->line($titulo.': '.json_encode($atributos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}
