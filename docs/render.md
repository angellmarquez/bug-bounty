# Despliegue en Render

`main` es la rama que se publica en Render; `develop` es la de trabajo en local. Cada push a
`main` despliega solo (Auto-Deploy).

## 1. Crear el servicio

- **New → Web Service**, repositorio `bug-bounty`, rama **`main`**.
- **Runtime:** Docker (usa el `Dockerfile` de la raíz).
- **Región:** Virginia (US East), la misma que el proyecto de Supabase (`us-east-1`): la app
  consulta la base en cada petición y conviene que estén cerca. Es también la región con menos
  latencia hacia Venezuela.

El contenedor, al arrancar (`docker/entrypoint.sh`):

1. exige `APP_KEY` y `PGP_STORAGE_KEY`;
2. ejecuta las migraciones;
3. reconstruye el keyring de GnuPG desde la base (`pgp:restore`) y crea la clave de custodia si
   falta;
4. lanza el scheduler en segundo plano (verificación de pagos, programas vencidos, SLA de
   apelaciones, vencimiento de planes);
5. sirve la app en el puerto `$PORT`.

## 2. Variables de entorno

Se cargan en **Environment → Add from .env**:

```env
APP_NAME=Huella
APP_ENV=production
APP_DEBUG=false
APP_URL=https://<servicio>.onrender.com
APP_KEY=<php artisan key:generate --show>
APP_LOCALE=es
LOG_CHANNEL=stderr
LOG_LEVEL=error

DB_CONNECTION=pgsql
DB_HOST=<host de Supabase>
DB_PORT=<5432 o 6543>
DB_DATABASE=postgres
DB_USERNAME=<postgres.xxxxx>
DB_PASSWORD=<contraseña de Supabase>
DB_SSLMODE=require
DB_POOLED=true

PGP_DRIVER=gpg
PGP_BINARY=gpg
PGP_ALGORITHM=ed25519
PGP_KEY_PASSWORD=<contraseña larga>
PGP_STORAGE_KEY=<base64:... de 32 bytes>

SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true
CACHE_STORE=database
QUEUE_CONNECTION=sync

ADJUNTOS_DISK=s3
AWS_ACCESS_KEY_ID=<access key de Supabase Storage>
AWS_SECRET_ACCESS_KEY=<secret de Supabase Storage>
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=evidencias
AWS_ENDPOINT=https://<project-ref>.supabase.co/storage/v1/s3
AWS_USE_PATH_STYLE_ENDPOINT=true

BOUNTY_RED=polygon_amoy
BOUNTY_PERMITIR_MAINNET=false
MAIL_ENABLED=false
MAIL_MAILER=log
```

Generar los secretos en local:

```bash
php artisan key:generate --show                                                  # APP_KEY
php artisan tinker --execute="echo 'base64:'.base64_encode(random_bytes(32));"   # PGP_STORAGE_KEY
```

> **`APP_KEY` y `PGP_STORAGE_KEY` no se cambian nunca** una vez que la base tiene datos: protegen
> las claves privadas PGP. Si cambian o se pierden, los informes cifrados quedan ilegibles para
> siempre. Guárdalos también en un gestor de contraseñas. Si producción reutiliza una base que ya
> tiene datos, hay que poner los mismos valores con los que se crearon.
>
> `PGP_KEY_PASSWORD` es obligatoria en producción: sin ella la app no crea claves.

## 3. Fotos de evidencia en Supabase Storage

El disco de un Web Service de Render es temporal: se borra en cada deploy o reinicio. Por eso,
en producción, las fotos van a **Supabase Storage** (compatible con S3):

1. En Supabase: **Storage → New bucket** → `evidencias`, **privado**.
2. **Storage → Settings → S3 Connection** → crear un access key. Copiar el endpoint, la región,
   el access key y el secret a las variables `AWS_*` de arriba.
3. `ADJUNTOS_DISK=s3`.

Para **desactivar el envío de fotos** (por ejemplo, mientras se revisa el bucket):
`ADJUNTOS_HABILITADOS=false`. La interfaz oculta el selector, el servidor rechaza cualquier foto
y las ya subidas se siguen viendo. Si una subida al bucket falla, el log registra el motivo que
devolvió Supabase.

Las fotos ya se suben **cifradas con PGP** y sin EXIF/GPS: aunque alguien entre al bucket solo
ve bloques cifrados. La app nunca genera URLs públicas; las sirve ella misma tras comprobar
permisos. Cada foto recuerda en qué disco se guardó, así que las antiguas siguen leyéndose.

## 4. Imagen Docker

- GnuPG real (en producción el driver de respaldo está prohibido).
- Extensiones `gd` (JPEG, PNG, WebP) y `exif` para procesar las fotos.
- `upload_max_filesize=6M`, `post_max_size=55M`, `memory_limit=256M`.

Los planes pequeños de Render tienen 512 MB de RAM: mantener `ADJUNTOS_MAX_PIXELES` (16 MP) y
`ADJUNTOS_MAX_KB` (5 MB).

## 5. Comprobar el despliegue

En **Logs** de Render debe verse el arranque sin errores. Luego, en **Shell**:

```bash
php artisan pgp:check          # driver gpg, clave y ciclo cifrar/descifrar
php artisan schedule:list      # tareas programadas
```
