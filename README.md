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

GnuPG en Windows depende de un proceso `gpg-agent` que gestiona la clave privada. Dos problemas conocidos, ya mitigados por la app misma:

- **Dos instalaciones de GnuPG en el PATH** (la de Git y la de Gpg4win no son compatibles entre sí): `PGP_BINARY` en `.env` apunta a la ruta completa del `gpg.exe` real (`PGP_BINARY="C:/Program Files/GnuPG/bin/gpg.exe"`, ver `.env.example`) para no depender de cuál resuelva el PATH.
- **`gpg-agent` se cuelga varios segundos al arrancar** intentando detectar lectores de tarjeta inteligente (`scdaemon`), algo casi seguro que Windows no tiene. `GpgBinaryDriver` escribe automáticamente un `gpg-agent.conf` con `disable-scdaemon` en el homedir la primera vez que lo usa — no hace falta tocar nada a mano.

Con eso, el primer cifrado/descifrado después de que el agente arranca puede tardar varios segundos (arranque del proceso), pero no debería fallar. Si aun así ves `PgpException: gpg falló... no se puede crear el socket`:

1. Cerrar cualquier `gpg-agent.exe` que esté corriendo (`Get-Process gpg-agent | Stop-Process -Force` en PowerShell) y reintentar — el próximo `gpg` lo vuelve a levantar solo, ya con la config correcta.
2. Si el arranque del agente tarda mucho (varios segundos en cada request), agregá una exclusión del antivirus/Windows Defender para `C:\Program Files\GnuPG` y la carpeta `storage/app/pgp/gpg` del proyecto — el escaneo en tiempo real de un ejecutable "nuevo" en cada arranque de `gpg-agent.exe` es la causa más común de esa demora.
3. Si persiste, correr `php artisan pgp:check` desde una terminal interactiva normal (no automatizada) para descartar restricciones del entorno.

---

## ABAC: cómo funciona y qué reglas tiene

### El motor

`config/abac.php` es la política completa: una lista ordenada de reglas, cada una con **sujeto** (atributos del usuario: roles, si está suspendido, a qué programas modera, su empresa activa...), **objeto** (atributos del recurso: estado, dueño, nivel de acceso...), **entorno** (contexto: fecha/hora, la empresa activa del request) y una **decisión** (`permitir` o `denegar`).

`App\Abac\AbacEngine` evalúa todas las reglas que apliquen a la acción pedida, en orden de `prioridad` (menor primero). Dos principios simples pero estrictos:

1. **El `denegar` siempre gana**, sin importar la prioridad ni si el admin tiene bypass. Se usa para excepciones de seguridad que nunca deben poder saltarse (conflicto de interés, autoevaluación, suspensión).
2. **`deny_by_default`**: si ninguna regla permite explícitamente la acción, se deniega. No hay "permitir por omisión".

Cada acción del sistema tiene un nombre `recurso.accion` (ej. `reportes.crear`, `programas.cambiar_estado`) definido como constante en `App\Abac\AccionesAbac`, y los controladores llaman `Gate::authorize('abac', [Accion, $objeto, $contextoExtra])` antes de actuar.

### Resumen por rol

| Rol | Puede | No puede |
|---|---|---|
| **Administrador** | Todo, vía el bypass `admin-bypass-total` (prioridad 10, acción `*`) — **excepto** lo que un `denegar` le bloquea explícitamente | **Crear, editar ni publicar programas** (regla `denegar-crear-editar-o-publicar-programas-al-administrador`: eso es de la empresa dueña, el admin no actúa en su nombre). Tampoco ve reportes ajenos ni el ledger de reputación de un investigador (`reputacion.ver` es solo para investigadores) |
| **Investigador** | Crear reportes en programas públicos/activos dentro de su nivel de acceso; ver/editar/enviar/eliminar sus propios reportes en los estados correspondientes; ver su propio ledger de reputación (`reputacion.ver`); apelar sus propias sanciones vigentes y en plazo | Triajar reportes (asignar/validar/rechazar/cerrar) — denegado explícitamente a quien **solo** tiene el rol investigador; reportar en un programa que modera o que pertenece a su propia empresa; reportar si está suspendido |
| **Moderador** | Ver y triajar reportes de los programas que se le asignaron (`programas_moderados`), tomar uno sin asignar o el que ya tiene asignado; ver esos programas; resolver apelaciones (salvo las de sanciones que él mismo aplicó) | Editar/crear/publicar programas; reportar en un programa que él mismo modera (conflicto de interés); triajar su propio reporte (nadie es juez de sí mismo, ni el admin) |
| **Empresa (propietario)** | Crear, ver, editar, publicar y eliminar los programas de su empresa; ver los reportes recibidos (no los borradores); marcar en reparación/cerrar reportes validados; gestionar miembros de su empresa | Ver/gestionar programas de otra empresa; reportar a sus propios programas mientras sea miembro |
| **Publicador** (investigador invitado por una empresa) | Crear, ver, editar y publicar programas de la empresa que lo invitó | Ver los reportes recibidos ni gestionar miembros (eso es solo del propietario) |

### Reglas de "nadie puede" (conflicto de interés, siempre `denegar`)

Estas ganan pase lo que pase, prioridad 5, incluso contra el bypass del admin:

- Nadie triaja (asigna/valida/rechaza/cierra) su propio reporte.
- Un moderador no reporta ni edita/envía reportes en un programa que él mismo modera.
- Quien pertenece a una empresa no reporta ni edita/envía reportes a los programas de esa empresa.
- Quien aplicó una sanción no resuelve la apelación de esa sanción; nadie resuelve la apelación que presentó él mismo.
- Nadie reporta ni envía reportes mientras tiene una suspensión vigente.

### Cómo probar una regla puntual

```bash
php artisan abac:audit --usuario=<id> --accion=<accion> [--programa=<id> | --reporte=<id> | --apelacion=<id>]
```

Muestra, regla por regla, si coincidió o no (y por qué) y el resultado final. Es la forma más rápida de confirmar "¿por qué a este usuario le dio 403 acá?" sin tener que leer todo `config/abac.php`.
