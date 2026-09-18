<?php

namespace App\Services\Pgp\Exceptions;

/**
 * No se pudo descifrar un mensaje (clave ausente, contraseña incorrecta o datos corruptos).
 */
class PgpDecryptionFailedException extends PgpException {}
