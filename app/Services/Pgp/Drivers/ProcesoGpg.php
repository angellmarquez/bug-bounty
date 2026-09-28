<?php

namespace App\Services\Pgp\Drivers;

use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Ejecuta un proceso de gpg y recoge su salida.
 *
 * En Linux delega en Symfony Process. En Windows, Symfony Process tarda ~220 ms por proceso
 * (sondea archivos temporales con pausas) frente a ~30 ms de proc_open, y un informe lanza 6 o 7
 * procesos de gpg entre cifrar y descifrar: más de un segundo de espera por página. Ahí se usa
 * proc_open directamente con el mismo diseño que evita bloqueos: la salida va a archivos
 * temporales (el hijo nunca se queda esperando a que leamos una tubería llena), así que escribir
 * toda la entrada por la tubería y cerrarla es seguro.
 */
final class ProcesoGpg
{
    private string $input = '';

    private float $timeout = 60;

    private ?int $exitCode = null;

    private string $output = '';

    private string $error = '';

    /**
     * @param  list<string>  $comando
     * @param  array<string, string|false>  $entorno  `false` quita la variable (como en Symfony Process)
     */
    public function __construct(
        private readonly array $comando,
        private readonly ?string $directorio = null,
        private readonly array $entorno = [],
    ) {}

    public function setTimeout(float $segundos): self
    {
        $this->timeout = $segundos;

        return $this;
    }

    public function setInput(string $input): self
    {
        $this->input = $input;

        return $this;
    }

    public function run(): int
    {
        if (DIRECTORY_SEPARATOR !== '\\') {
            $proceso = new Process($this->comando, $this->directorio, $this->entorno);
            $proceso->setTimeout($this->timeout);
            $proceso->setInput($this->input);
            $proceso->run();

            $this->output = $proceso->getOutput();
            $this->error = $proceso->getErrorOutput();

            return $this->exitCode = $proceso->getExitCode() ?? -1;
        }

        return $this->exitCode = $this->ejecutarEnWindows();
    }

    public function getExitCode(): ?int
    {
        return $this->exitCode;
    }

    public function getOutput(): string
    {
        return $this->output;
    }

    public function getErrorOutput(): string
    {
        return $this->error;
    }

    private function ejecutarEnWindows(): int
    {
        $salida = (string) tempnam(sys_get_temp_dir(), 'gpo');
        $errores = (string) tempnam(sys_get_temp_dir(), 'gpe');

        try {
            $proceso = proc_open(
                $this->comando,
                [0 => ['pipe', 'r'], 1 => ['file', $salida, 'w'], 2 => ['file', $errores, 'w']],
                $tuberias,
                $this->directorio,
                // proc_open reemplaza el entorno entero: se pasan solo las variables que se heredan.
                array_filter($this->entorno, fn (string|false $valor): bool => $valor !== false),
            );

            if (! is_resource($proceso)) {
                throw new RuntimeException('No se pudo iniciar el proceso de GnuPG.');
            }

            // Si gpg termina antes de leerlo todo (p. ej. por un error), la tubería se cierra: no es un fallo aquí.
            @fwrite($tuberias[0], $this->input);
            fclose($tuberias[0]);

            $limite = microtime(true) + $this->timeout;
            $codigo = -1;

            while (true) {
                $estado = proc_get_status($proceso);

                if (! $estado['running']) {
                    $codigo = $estado['exitcode'];
                    break;
                }

                if (microtime(true) > $limite) {
                    proc_terminate($proceso);
                    proc_close($proceso);

                    throw new RuntimeException(sprintf('El proceso de GnuPG superó el tiempo límite de %s segundos.', $this->timeout));
                }

                usleep(2_000);
            }

            // proc_get_status solo informa el código la primera vez que ve el proceso terminado.
            $cierre = proc_close($proceso);
            $codigo = $codigo === -1 ? $cierre : $codigo;

            $this->output = (string) file_get_contents($salida);
            $this->error = (string) file_get_contents($errores);

            return $codigo;
        } finally {
            // La salida de un descifrado es texto en claro: no queda en disco.
            @unlink($salida);
            @unlink($errores);
        }
    }
}
