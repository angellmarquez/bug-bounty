# Imagen para Render (Web Service, entorno Docker). Render no tiene runtime
# nativo de PHP, así que el despliegue es siempre vía Dockerfile.
#
# Dos etapas:
#  1) builder: instala deps de PHP y Node, y compila los assets. El build de
#     Vite necesita PHP porque el plugin wayfinder genera resources/js/routes
#     llamando a "php artisan" (ver vite.config.ts / AGENTS.md).
#  2) runtime: imagen final, solo con lo necesario para servir la app +
#     el binario de gpg para cifrado PGP real.

FROM php:8.3-cli-bookworm AS builder

RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip curl ca-certificates \
        libpq-dev libzip-dev libonig-dev libxml2-dev \
    && docker-php-ext-install pdo pdo_pgsql pgsql mbstring xml bcmath zip \
    && curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
    && apt-get install -y --no-install-recommends nodejs \
    && curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app
COPY . .

RUN composer install --no-dev --optimize-autoloader --no-interaction --no-progress

# .env "de mentira" solo para que artisan pueda arrancar durante el build
# (wayfinder necesita leer las rutas; requiere vendor/autoload.php, por eso
# va después de composer install). No lleva secretos y se borra al final; en
# runtime, Render inyecta las variables reales como entorno del contenedor.
RUN cp .env.example .env \
    && php artisan key:generate --ansi

RUN npm ci \
    && npm run build

RUN rm -f .env


FROM php:8.3-cli-bookworm AS runtime

RUN apt-get update && apt-get install -y --no-install-recommends \
        libpq-dev libzip-dev libonig-dev libxml2-dev gnupg2 ca-certificates \
    && docker-php-ext-install pdo pdo_pgsql pgsql mbstring xml bcmath zip \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app
COPY --from=builder /app /app

RUN mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views \
        storage/logs storage/app/pgp bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 10000
ENTRYPOINT ["entrypoint.sh"]
