# Despliegue en Render — pendientes

> Estado: **pendiente**. Hoy el proyecto corre solo en local. Esta lista reúne lo que hay
> que resolver antes de publicarlo en Render.

## 1. Fotos de evidencia (informes y apelaciones)

**Problema:** el disco de un servicio web de Render es temporal. Se borra en cada deploy,
reinicio o cambio de instancia. Si las fotos se guardan en el disco local
(`ADJUNTOS_DISK=local`, que es lo que se usa en desarrollo), se pierden y en la base
quedan registros de `adjuntos` apuntando a archivos inexistentes (la foto responde 404).

**Solución propuesta:** Supabase Storage (compatible con S3), en el mismo proyecto de la base.

1. Crear un bucket **privado** (p. ej. `evidencias`) en Supabase Storage.
2. Generar credenciales S3 en _Storage → Settings → S3 Connection_.
3. `composer require league/flysystem-aws-s3-v3 "^3.0"`.
4. Variables en Render:

    ```env
    ADJUNTOS_DISK=s3
    AWS_ACCESS_KEY_ID=<access key de Supabase>
    AWS_SECRET_ACCESS_KEY=<secret de Supabase>
    AWS_DEFAULT_REGION=<región del proyecto>
    AWS_BUCKET=evidencias
    AWS_ENDPOINT=https://<ref>.supabase.co/storage/v1/s3
    AWS_USE_PATH_STYLE_ENDPOINT=true
    ```

5. Comprobar que `config/filesystems.php` → disco `s3` lee `AWS_ENDPOINT` (ya lo hace).

Las fotos ya se guardan **cifradas con PGP**: aunque alguien acceda al bucket solo ve bloques
cifrados. El bucket debe seguir siendo privado; la app nunca genera URLs públicas.

Alternativa descartada: _Persistent Disk_ de Render (de pago, una sola instancia, sin
deploys sin caída).

## 2. PGP en producción

- Render no trae el binario `gpg` y en producción está prohibido el driver de respaldo.
  Hay que desplegar con **Docker** e instalar `gnupg` en la imagen.
- El llavero de `gpg` también vive en el disco temporal: al arrancar el contenedor hay que
  reconstruirlo desde la base (`PgpService::restaurarEnKeyring()` y
  `restaurarClavesDeEmpresaEnKeyring()`), por ejemplo en el script de arranque.

## 3. PHP en la imagen

- Extensiones: `gd` (con soporte JPEG, PNG y WebP) y `exif` para procesar las fotos;
  `fileinfo` para validar su tipo real.
- `php.ini`: `upload_max_filesize` y `post_max_size` por encima de `ADJUNTOS_MAX_KB`
  (5 MB por foto por defecto; `post_max_size` debe cubrir varias fotos juntas, p. ej. 55M).
- `memory_limit` ≥ 128M: GD descomprime la foto en memoria (límite `ADJUNTOS_MAX_PIXELES`).

## 4. Memoria del plan

Los planes pequeños de Render tienen 512 MB de RAM. Mantener `ADJUNTOS_MAX_PIXELES`
(16 MP) y `ADJUNTOS_MAX_KB` (5 MB) para que procesar fotos no agote la memoria.
