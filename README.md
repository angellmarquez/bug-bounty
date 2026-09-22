# bug-bounty

Plataforma de Divulgación Coordinada de Vulnerabilidades (bug bounty) en español, construida sobre **Laravel 13 + Inertia 3 + Svelte 5**, con un motor de reglas **ABAC** propio y **cifrado PGP interno** para el contenido sensible.

## Requisitos para probar el programa

| Requisito | Para qué |
|---|---|
| **PHP 8.4** + extensiones estándar de Laravel | Backend |
| **Composer** | Dependencias PHP |
| **Node 22** + npm | Assets del frontend (Svelte + Vite) |
| Base de datos **PostgreSQL** (proyecto de Supabase) o **SQLite** local | Persistencia |
| **[Gpg4win](https://www.gpg4win.org/)** (opcional) | Cifrado PGP *real*. Sin esto, la app funciona igual con un driver de respaldo (`fallback`) que simula el cifrado, sin confidencialidad real — ver [Cifrado PGP](#cifrado-pgp-cómo-funciona-y-qué-cifra) |

### Poner el proyecto en marcha

```bash
composer install
cp .env.example .env
php artisan key:generate
```

Configurá la base de datos en `.env` (por defecto `sqlite`; para Supabase ver el bloque comentado en `.env.example`, que incluye la variable `DB_POOLED` necesaria si usás el *connection pooler* de Supabase).

```bash
php artisan migrate --seed   # crea las tablas y los usuarios/programas demo
npm install
npm run build                 # o `npm run dev` para hot-reload
php artisan serve
```

O todo junto en desarrollo: `composer dev` (levanta `artisan serve` + `queue:listen` + `npm run dev` en paralelo).

Con eso la app queda en `http://localhost:8000`.

### Usuarios demo disponibles

Sembrados por `database/seeders/EmpresaDemoSeeder.php`. Todos con el email como usuario y estas contraseñas:

| Rol | Email | Password | Para qué sirve |
|---|---|---|---|
| **Administrador** | `admin@bugbounty.local` | `admin` | Gestiona usuarios, aprueba/rechaza empresas, ve auditoría, sanciones, config de reputación y estado de PGP. **No** puede crear/editar/publicar programas ni ver reportes (eso es exclusivo de la empresa dueña) |
| **Moderador** | `moderador@bugbounty.local` | `moderador` | Triaja reportes de los programas que se le asignaron (`/moderacion`): asigna, valida, rechaza, marca duplicados |
| **Investigador** | `investigador@bugbounty.local` | `investigador` | Ve programas públicos, crea y envía reportes, tiene su propio ledger de reputación (`/reputacion`) |
| **Empresa (propietario)** | `empresa@bugbounty.local` | `empresa` | Dueño de "Empresa Demo Seguridad S.A.": crea/publica/edita sus programas, ve los reportes recibidos, invita investigadores como publicadores |
| **Empresa (pendiente de aprobación)** | `pendiente@bugbounty.local` | `pendiente` | Para probar el flujo de una empresa que todavía no fue aprobada por un admin |

También existe `test@example.com` / `password` (usuario genérico de `DatabaseSeeder`, rol `investigador` por defecto).

### Comandos de verificación

```bash
composer test          # Pint + PHPStan + Pest (backend)
npm run check           # lint del frontend
npm run types:check     # svelte-check
php artisan abac:audit --usuario=<id> --accion=<accion> [--programa=<id>|--reporte=<id>]   # probar un permiso puntual
```

### Qué probar por rol (flujo sugerido)

- **Investigador**: `/programas` → entrar a uno → "Reportar un bug" (el programa debe quedar fijo, no editable) → completar el wizard (Detalles → CVSS → PoC → Revisión) → enviar. Ver `/reportes` y `/reputacion`.
- **Empresa**: `/empresa` → "Crear programa" (con objetivos y, opcionalmente, "Qué bugs buscas") → publicarlo (cambiar de borrador a activo) → ver los reportes recibidos.
- **Moderador**: `/moderacion` → entrar a un programa con reportes → asignarse uno → validar/rechazar/marcar duplicado.
- **Admin**: `/admin/usuarios`, `/admin/empresas` (aprobar/rechazar), `/admin/sanciones`, `/admin/config/reputacion`, `/admin/pgp`.

---

## Cifrado PGP: cómo funciona y qué cifra

### El driver

El cifrado lo maneja `App\Services\Pgp\PgpService`, que delega en uno de dos *drivers* (`App\Services\Pgp\Contracts\PgpDriver`):

- **`gpg`** (`GpgBinaryDriver`): invoca el binario real de GnuPG (Gpg4win en Windows) para cifrar/descifrar de verdad.
- **`fallback`** (`FallbackPgpDriver`): simula el formato de un mensaje PGP (`-----BEGIN FAKE PGP MESSAGE-----...`) sin cifrado real. Se usa automáticamente si no hay `gpg` disponible, y **está prohibido en producción** — solo sirve para desarrollo local y para que los tests corran rápido sin depender de un binario externo.

`PGP_DRIVER` en `.env` controla cuál se usa: `auto` (por defecto) detecta si `gpg` está disponible y usa `fallback` si no; se puede forzar con `gpg` o `fallback`.

### La clave de la plataforma

La plataforma genera y gestiona **su propia** clave PGP (no la de cada usuario): un solo par de claves cuya pública se usa para cifrar todo el contenido sensible, y cuya privada (guardada cifrada en la tabla `claves_pgp_plataforma`) es la única capaz de descifrarlo. Se crea sola la primera vez que hace falta (o con `php artisan pgp:setup`); `php artisan pgp:check` verifica que el driver, la clave y el ciclo cifrar/descifrar funcionen.

### Qué se cifra exactamente en la base de datos

| Tabla | Columna | ¿Se cifra? | Motivo |
|---|---|---|---|
| `reportes` | `descripcion` | ✅ | Detalle de la vulnerabilidad: lo más sensible del sistema |
| `reportes` | `poc` | ✅ | Prueba de concepto (pasos, URLs afectadas, evidencia) |
| `reportes` | `titulo`, `categoria`, `estado`, `vector_cvss`, `puntuacion_cvss`, `severidad` | ❌ | Necesarios en claro para listar, filtrar y ordenar reportes sin descifrar cada fila |
| `programas` | `descripcion` | ✅ | Puede describir debilidades conocidas de la empresa |
| `programas` | `bugs_buscados` | ✅ | Lo que la empresa sospecha que está mal — la pista más directa para un atacante si se filtra la base |
| `objetivos_programa` | `valor` | ✅ | El dominio/IP/API exacto en alcance: la superficie de ataque real |
| `objetivos_programa` | `descripcion` | ✅ | Contexto adicional de ese objetivo |
| `programas` | `nombre`, `slug`, `estado`, `es_publico`, `nivel_acceso`, `poc_schema` | ❌ | Necesarios en claro para listar/buscar programas públicos sin descifrar cada fila |
| `objetivos_programa` | `tipo` | ❌ | Solo una categoría (web/api/móvil/otro), no revela nada por sí sola |

**Importante sobre el listado público de programas** (`/programas`): las cards **no muestran la descripción** (antes sí, se sacó a propósito) — solo nombre, empresa, badges de tipo de objetivo y nivel de acceso. Así la página lista 15 programas por página sin tener que descifrar nada; el contenido cifrado (descripción, qué bugs buscan, objetivos) se descifra **una sola vez**, recién al entrar al detalle de un programa puntual (`/programas/{id}`).

### Quién puede ver el contenido descifrado

El descifrado ocurre en el backend (nunca se manda el texto cifrado al frontend) y depende de permisos ABAC ya existentes:

- El **investigador** dueño del reporte y el **admin** siempre ven el contenido.
- El **moderador** solo ve reportes (y el alcance del programa: `bugs_buscados`/`objetivos`) de los programas que modera.
- La **empresa** propietaria ve los reportes recibidos en sus programas y sus propios `bugs_buscados`/objetivos al editar.
- Nadie más — ni siquiera con acceso directo a la base de datos — puede leer estos campos sin la clave privada de la plataforma.

### Cómo verificarlo vos mismo

```bash
php artisan pgp:check          # confirma que el driver, la clave y el ciclo cifrar/descifrar andan
php artisan tinker
>>> $r = App\Models\Reporte::find(1);
>>> $r->getRawOriginal('descripcion');   // debería verse "-----BEGIN PGP MESSAGE-----..." (o FAKE con el driver de respaldo)
>>> app(App\Services\Pgp\PgpService::class)->descifrarReporte($r->getRawOriginal('descripcion'), $r->poc)['descripcion'];  // el texto real
```

Lo mismo aplica a `App\Models\Programa` con `descifrarPrograma()` y a `App\Models\ObjetivoPrograma` con `descifrarObjetivo()`.

### Nota sobre Windows y `gpg-agent`

GnuPG en Windows depende de un proceso `gpg-agent` que gestiona la clave privada; en algunos entornos (por ejemplo dentro de ciertas sesiones automatizadas o cuando conviven dos instalaciones de GnuPG en el mismo PATH, como la de Git y la de Gpg4win) ese agente puede fallar al crear su socket. Si ves un error `PgpException: gpg falló... no se puede crear el socket`, probá:

1. Poner `PGP_BINARY` en `.env` apuntando a la ruta completa del `gpg.exe` real, por ejemplo `PGP_BINARY="C:/Program Files/GnuPG/bin/gpg.exe"` (ver comentario en `.env.example`), para no depender de qué `gpg` resuelva el PATH.
2. Cerrar cualquier `gpg-agent.exe` que esté corriendo (`Get-Process gpg-agent | Stop-Process -Force` en PowerShell) y reintentar — el próximo `gpg` lo vuelve a levantar solo.
3. Si persiste, correr `php artisan pgp:check` desde una terminal interactiva normal (no automatizada) para descartar restricciones del entorno.
