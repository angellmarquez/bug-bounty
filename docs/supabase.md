# Conexión a Supabase (PostgreSQL)

Guía de la **Fase 0**: conectar la plataforma a PostgreSQL en Supabase con SSL y subir el esquema del dominio.

## Datos del proyecto

| Dato                 | Valor                                      |
| -------------------- | ------------------------------------------ |
| Proyecto (ref)       | `ifdbqkqjaceulgrmdlwz`                     |
| API URL              | `https://ifdbqkqjaceulgrmdlwz.supabase.co` |
| Host de BD (directo) | `db.ifdbqkqjaceulgrmdlwz.supabase.co`      |
| Puerto               | `5432`                                     |
| Base de datos        | `postgres`                                 |
| Usuario              | `postgres.ifdbqkqjaceulgrmdlwz`            |
| SSL                  | `sslmode=require`                          |

> La contraseña de la base de datos **no se versiona**. Se define en el dashboard de Supabase
> (`Project Settings → Database → Database password`) y va en `.env` local o en los secretos del
> entorno de despliegue. Rótala si se expone.

## Activar PostgreSQL en local

En `.env`, sustituye el bloque de SQLite por:

```dotenv
DB_CONNECTION=pgsql
DB_HOST=db.ifdbqkqjaceulgrmdlwz.supabase.co
DB_PORT=5432
DB_DATABASE=postgres
DB_USERNAME=postgres.ifdbqkqjaceulgrmdlwz
DB_PASSWORD=<tu-password>
DB_SSLMODE=require
```

`config/database.php` ya expone `sslmode` en la conexión `pgsql`, por lo que `DB_SSLMODE=require`
es suficiente para forzar TLS. Si usas el pooler (ver abajo), cambia `DB_HOST` y `DB_USERNAME`
por los del pooler que indique Supabase.

Luego limpia la caché de configuración:

```powershell
php artisan config:clear
php artisan migrate:status
```

`migrate:status` debe mostrar todas las migraciones como `Ran` (la tabla `migrations` ya está
sincronizada en Supabase). No ejecutes `php artisan migrate` esperando crear tablas: el esquema
ya está aplicado.

## Comportamiento de cada entorno

| Entorno                 | Driver                | Notas                                            |
| ----------------------- | --------------------- | ------------------------------------------------ |
| Local (dev)             | SQLite por defecto    | Sin cambios si no defines el bloque `pgsql`.     |
| Tests / CI              | SQLite `:memory:`     | Definido en `phpunit.xml`; **no** toca Supabase. |
| Producción / despliegue | PostgreSQL (Supabase) | Usa el bloque `pgsql`.                           |

## Esquema aplicado

Migraciones del kit y del dominio, todas aplicadas en Supabase y registradas en `public.migrations`
(batch 1; la fase 2 añade `claves_pgp_plataforma` como batch 2):

- Kit: `users`, `password_reset_tokens`, `sessions`, `cache`, `cache_locks`, `jobs`,
  `job_batches`, `failed_jobs`, `passkeys` y columnas 2FA en `users`.
- Dominio: `roles`, `rol_usuario`, `programas`, `objetivos_programa`, `reportes`,
  `eventos_reporte`, `claves_pgp`, `claves_pgp_plataforma`, `sanciones`, `apelaciones`,
  `ledger_reputacion`, `auditorias` y `users.reputation_score`.

Además se sembraron los roles base: `administrador`, `gestion`, `investigador`.

Si en el futuro se añaden migraciones de Laravel, el flujo normal es:

```powershell
php artisan migrate
```

contra el `pgsql` de Supabase (con las credenciales configuradas). Para reconstruir el esquema
desde cero (peligroso, borra datos) existe `php artisan migrate:fresh`, pero recuerda que
**no** debe usarse sobre datos reales.

## Row Level Security (RLS)

Supabase habilita RLS automáticamente en las tablas del esquema `public`. Este proyecto **no**
consume PostgREST/`anon key` desde el frontend: el acceso a datos es siempre a través de Laravel,
que conecta como el rol propietario `postgres.<ref>`. Un propietario de tabla omite RLS, por lo
que las políticas no son necesarias para el funcionamiento actual.

Consecuencia de seguridad: con RLS activo y sin políticas, los roles `anon` y `authenticated`
no pueden leer ni escribir ninguna tabla vía API REST. Eso es lo deseado mientras el frontend no
use el cliente de Supabase.

Si en el futuro se expone PostgREST, habrá que añadir políticas explícitas por tabla
(ver el aviso `rls_enabled_no_policy` de los advisors). El aviso sobre `public.rls_auto_enable()`
proviene de una función propia de la plataforma Supabase, no del proyecto.

## Pooler de conexiones

Para despliegues serverless/efímeros, Supabase ofrece poolers. Con Laravel:

- **Session pooler** (`...pooler.supabase.com:5432`): compatible con Laravel.
- **Transaction pooler** (`...pooler.supabase.com:6543`): puede romper _prepared statements_
  persistentes; úsalo con precaución o desactiva el modo de sentencias preparadas.

## Solución de problemas

- **`SSL required` / `sslmode`**: asegúrate de `DB_SSLMODE=require`.
- **`password authentication failed`**: revisa `DB_USERNAME` (incluye el ref: `postgres.<ref>`) y
  la contraseña; si dudas, rótala en el dashboard.
- **`could not translate host name`**: usa el host directo o el pooler según la red; algunos
  entornos IPv6 requieren el pooler.
- **Config cacheada**: `php artisan config:clear` tras cambiar `.env`.
