#!/bin/sh
set -e

# APP_KEY debe venir fijo por variable de entorno de Render (nunca generarlo
# acá): si cambia entre despliegues, todo lo que use el cast "encrypted"
# (por ejemplo claves_pgp_plataforma.clave_privada) queda ilegible para siempre.
if [ -z "$APP_KEY" ]; then
    echo "ERROR: falta APP_KEY en las variables de entorno. No se arranca sin ella." >&2
    exit 1
fi

php artisan config:cache
php artisan view:cache
# No se cachean rutas: routes/settings.php define una con un Closure
# (.well-known/passkey-endpoints), y route:cache falla si encuentra una.

php artisan migrate --force

# Reimporta la clave de la plataforma (persistida en la base de datos) al
# keyring local: en disco efímero (Render sin disco persistente) el keyring
# de GnuPG se pierde en cada reinicio del contenedor, aunque la clave siga
# activa en claves_pgp_plataforma.
php artisan pgp:restore || true

# No falla el arranque si la clave PGP no se puede crear todavía (por ejemplo,
# el binario de gpg no está disponible): la plataforma la crea sola al recibir
# el primer reporte (ver PgpService::asegurarClave).
php artisan pgp:setup --tolerante || true

exec php artisan serve --host=0.0.0.0 --port="${PORT:-10000}"
