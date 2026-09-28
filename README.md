# bug-bounty

Plataforma de **Divulgación Coordinada de Vulnerabilidades** (bug bounty) en español. Las empresas publican programas, los investigadores reportan vulnerabilidades, los moderadores las revisan y la empresa las repara. Todo el contenido sensible viaja y se guarda **cifrado con PGP**, y cada permiso lo decide un motor **ABAC** propio.

**Stack:** Laravel 13 + Inertia 3 + Svelte 5 (runes) · Fortify (login, passkeys, 2FA) · Tailwind 4 · PostgreSQL (Supabase) o SQLite · GnuPG · Pest + PHPStan + Pint.

## Índice

1. [Roles de la plataforma](#1-roles-de-la-plataforma)
2. [Cómo funciona: recorrido general](#2-cómo-funciona-recorrido-general)
3. [El informe de vulnerabilidad](#3-el-informe-de-vulnerabilidad)
4. [Prueba de concepto (PoC)](#4-prueba-de-concepto-poc)
5. [CVSS: cálculo de la severidad](#5-cvss-cálculo-de-la-severidad)
6. [Cifrado PGP](#6-cifrado-pgp)
7. [ABAC: el motor de permisos](#7-abac-el-motor-de-permisos)
8. [Catálogo completo de reglas ABAC](#8-catálogo-completo-de-reglas-abac)
9. [Reputación y penalizaciones](#9-reputación-y-penalizaciones)
10. [Automatizaciones](#10-automatizaciones)
11. [Pagos: bounties y Plan Profesional](#11-pagos-bounties-y-plan-profesional)
12. [Instalación y puesta en marcha](#12-instalación-y-puesta-en-marcha)
13. [Qué probar por rol](#13-qué-probar-por-rol)
14. [Verificación y tests](#14-verificación-y-tests)
15. [Despliegue y documentación adicional](#15-despliegue-y-documentación-adicional)

---

## 1. Roles de la plataforma

| Rol | Qué hace | Qué **no** puede hacer |
| --- | --- | --- |
| **Investigador** (hacker) | Busca vulnerabilidades en los programas, crea y envía informes, acumula reputación, apela sanciones | Revisar (triar) informes, crear programas |
| **Empresa** (propietario) | Crea y publica programas, recibe los informes ya revisados, los repara y cierra, paga bounties y su plan | Reportar vulnerabilidades (en ningún programa) |
| **Publicador** | Investigador invitado por una empresa para crear y editar sus programas | Ver los informes recibidos ni gestionar miembros |
| **Moderador** | Revisa los informes de los programas que tiene asignados, en orden de llegada y **sin saber quién los envió** | Reportar, crear o editar programas, resolver apelaciones |
| **Administrador** | Supervisa: usuarios, empresas, sanciones, apelaciones, auditoría, configuración, PGP. Asigna informes a moderadores | Crear, revisar, reparar o cerrar informes; crear, editar o publicar programas |

El registro público crea usuarios con rol `investigador`. Las empresas se registran aparte y quedan **pendientes de aprobación** por un administrador.

## 2. Cómo funciona: recorrido general

```
Empresa crea programa ─▶ lo publica (activo) ─▶ se le asignan moderadores automáticamente
                                                     │
Investigador ve el programa ─▶ crea informe (borrador) ─▶ lo envía
                                                     │
Moderador (a ciegas, por orden de llegada) ─▶ lo revisa ─▶ valida / rechaza / duplicado / pide info
                                                     │
Empresa recibe el informe validado ─▶ lo confirma (en reparación) ─▶ lo cierra como resuelto
                                                     │
Investigador recibe puntos de reputación, certificado de divulgación y, si aplica, bounty en USDC
```

### Ciclo de vida de un informe

| Estado | Significado | Quién lo mueve |
| --- | --- | --- |
| `borrador` | Solo lo ve su autor; puede guardarse incompleto | Investigador |
| `enviado` | Entró en la cola de moderación del programa | Investigador |
| `en_revision` | Un moderador lo tomó y queda asignado a él | Moderador |
| `needs_info` | El moderador pidió más información; el investigador puede editarlo y reenviarlo | Moderador |
| `validado` | La vulnerabilidad es real; ahora la empresa puede verlo | Moderador |
| `en_reparacion` | La empresa confirmó el hallazgo y lo está corrigiendo | Empresa |
| `cerrado` | Vulnerabilidad resuelta | Empresa |
| `rechazado` / `duplicado` / `fuera_de_alcance` | Informe descartado | Moderador |

Las transiciones permitidas están fijadas en `ReporteController::TRANSICIONES_VALIDAS`; cualquier otra devuelve un error de validación.

### Reglas de negocio importantes

- **Orden de llegada:** la recompensa es para quien encontró la vulnerabilidad primero. Un informe no se puede validar ni confirmar mientras haya uno anterior del mismo programa pendiente (`ColaDeValidacion`).
- **Triaje ciego:** el moderador no ve el nombre del autor ni su wallet, solo su rango y su historial (informes enviados, aprobados, descartados). Los nombres de las fotos también se anonimizan.
- **Cola de moderación:** el moderador solo puede abrir el siguiente informe de la cola (el enviado más antiguo sin revisor) y los que ya tomó. Tomar un informe es atómico: si dos moderadores pulsan a la vez, solo uno se lo queda.
- **La empresa solo ve informes ya decididos por moderación:** validados, en reparación, cerrados o descartados. Nunca borradores ni informes en revisión.
- **Programas con fechas:** fuera del periodo del programa no se aceptan informes, aunque su estado siga siendo "activo".
- **Programas:** estados `borrador`, `activo`, `en_pausa`, `finalizado`; tienen objetivos (web/API/móvil/otro), nivel de acceso (bajo/medio/alto, según el rango del investigador), pueden ser públicos o privados (por invitación) y solo para investigadores verificados.

## 3. El informe de vulnerabilidad

### Qué contiene

El investigador lo completa en un **wizard de 4 pasos** (`resources/js/pages/reportes/Create.svelte`):

1. **Detalles:** programa (fijo si se entra desde el programa), título, descripción y categoría.
2. **CVSS:** calculadora de severidad (ver [sección 5](#5-cvss-cálculo-de-la-severidad)).
3. **PoC y evidencia:** la prueba de concepto según el formulario del programa y hasta 10 fotos.
4. **Revisión:** resumen antes de guardar como borrador o **guardar y enviar**.

Cada informe recibe un número correlativo por año: `BB-2026-0001`.

### Qué pasa al enviarlo

1. Se comprueba el permiso ABAC `reportes.crear` / `reportes.enviar`.
2. Se aplica el **límite de envíos** (anti-spam, ver [sección 10](#10-automatizaciones)).
3. Se valida la **PoC contra el esquema del programa** (en el servidor, no solo en el navegador).
4. La **descripción y la PoC se cifran con PGP** antes de tocar la base de datos.
5. Las **fotos** se limpian (se quitan EXIF y GPS), se cifran y se guardan.
6. Se crea el evento en la **línea de tiempo** y un registro de **auditoría**.

### El informe que recibe la empresa

Cuando moderación valida el informe, la empresa dueña del programa lo ve en su panel (`/empresa` → informes recibidos) con:

- Número, título, categoría, estado y fecha de envío.
- **Vector CVSS, puntuación y severidad** (con color por severidad).
- **Descripción y PoC descifradas** en el servidor solo para ella (regla `empresa-descifrar-poc`); cada lectura de la PoC queda en auditoría (`reportes.poc_descifrado`, con usuario, IP y huella de la clave).
- **Fotos de evidencia**, descifradas al vuelo y entregadas con cabeceras que impiden ejecutarlas.
- **Línea de tiempo** completa: envío, revisión, validación, comentarios, sanciones y pagos.
- Acciones: **confirmar y marcar en reparación**, **cerrar como resuelto**, **asignar y pagar el bounty** en USDC.

La empresa **no** ve el informe mientras está en borrador, enviado o en revisión: así solo le llegan hallazgos ya verificados.

### Línea de tiempo

Cada acción crea un `EventoReporte` (creado, enviado, cambio de estado, asignación, comentario, duplicado, sanción, recompensa). La vista (`Timeline.svelte`) la muestra a todos los que pueden ver el informe; para el moderador, los eventos del autor aparecen como "Investigador anónimo".

### Certificado de divulgación

Al cerrar un informe como resuelto se emite un **certificado de divulgación responsable**, sellado con **SHA-256** y **firmado con la clave PGP** de la plataforma. Cualquiera puede verificarlo sin iniciar sesión en `/verificar/{codigo}` y descargar la firma (`firma.asc`) y la clave pública (`clave.asc`) para comprobarlo fuera de la plataforma.

## 4. Prueba de concepto (PoC)

La PoC demuestra que la vulnerabilidad es real. **Es obligatoria en todos los programas** para enviar un informe.

### Formulario dinámico por programa

Cada programa guarda su propio formulario de PoC en la columna JSON `programas.poc_schema`. La empresa lo diseña con el editor visual (`PocSchemaEditor.svelte`) al crear o editar el programa, sin tocar código ni volver a desplegar.

Cada campo del esquema tiene:

| Propiedad | Uso |
| --- | --- |
| `name`, `label` | Identificador y etiqueta visible |
| `type` | `text`, `textarea`, `select`, `number`, `url`, `code` |
| `required` | Si es obligatorio al enviar |
| `repeatable` | Si admite varios valores (por ejemplo, varios pasos o URLs) |
| `options` | Opciones de un `select` |
| `placeholder`, `help`, `defaultValue` | Ayudas para el investigador |

Si el programa no define campos, se usa uno genérico obligatorio: **"Evidencia y pasos para reproducir"** (`CAMPO_POC_POR_DEFECTO` en `resources/js/lib/poc-schema.ts`).

### Validación en dos capas

- **Navegador** (`validarPoc` en `lib/poc-schema.ts`): avisa de los errores mientras se escribe.
- **Servidor** (`App\Rules\PocCumpleSchema`): repite las mismas reglas porque la del navegador se puede saltar con una petición directa. Además:
  - rechaza **campos no declarados** en el esquema;
  - valida URLs y **bloquea direcciones internas** (`localhost`, `127.0.0.1`, `10.x`, `192.168.x`, `172.16–31.x`, `169.254.x`);
  - valida números y opciones de `select`.
- **Borrador vs. envío:** un borrador puede guardarse con la PoC incompleta; al enviar se exigen todos los campos obligatorios, sin importar cómo se guardó el borrador.

### Cómo se protege

La PoC se guarda **cifrada con PGP**. Solo la descifran quienes tienen el permiso `reportes.decrypt_poc`: el autor, el moderador que revisa ese informe y la empresa dueña una vez validado. Cada descifrado queda registrado en auditoría.

## 5. CVSS: cálculo de la severidad

La plataforma usa **CVSS v3.1** (Common Vulnerability Scoring System), métricas **base**.

- **Calculadora:** `resources/js/components/CvssCalculator.svelte`, con la lógica en `resources/js/lib/cvss.ts`. La puntuación se calcula en vivo al elegir cada métrica.
- **Vector guardado:** `CVSS:3.1/AV:N/AC:L/PR:N/UI:N/S:U/C:H/I:H/A:H`, en la columna `reportes.vector_cvss`, junto con `puntuacion_cvss` (0.0–10.0) y `severidad`.
- **Métricas:** Attack Vector (AV), Attack Complexity (AC), Privileges Required (PR), User Interaction (UI), Scope (S), Confidentiality (C), Integrity (I), Availability (A).
- **Fórmula oficial 3.1:** incluye los pesos de PR según el Scope (`PR:L` = 0.68 y `PR:H` = 0.50 si el Scope cambia) y la función de redondeo `Roundup` de la especificación.

| Puntuación | Severidad | Color |
| --- | --- | --- |
| 0.0 | Ninguna | gris |
| 0.1 – 3.9 | Baja | verde |
| 4.0 – 6.9 | Media | cian |
| 7.0 – 8.9 | Alta | naranja |
| 9.0 – 10.0 | Crítica | rojo |

**Para qué se usa la severidad:**

- Ordenar y filtrar informes sin descifrarlos (el vector, la puntuación y la severidad se guardan en claro).
- **Puntos de reputación** proporcionales al impacto (ver [sección 9](#9-reputación-y-penalizaciones)).
- **Detección de fabricación:** informes alta/crítica descartados y sin PoC válida.
- **Detector de duplicados:** el vector CVSS es una de las señales de parecido.

## 6. Cifrado PGP

### Drivers

`App\Services\Pgp\PgpService` delega en uno de dos drivers:

- **`gpg`** (`GpgBinaryDriver`): usa el binario real de GnuPG (Gpg4win en Windows).
- **`fallback`** (`FallbackPgpDriver`): simula el formato (`-----BEGIN FAKE PGP MESSAGE-----`) **sin confidencialidad real**. Solo para desarrollo y tests; **está prohibido en producción**.

`PGP_DRIVER` elige cuál: `auto` (por defecto: usa `gpg` si está disponible), `gpg` o `fallback`. Algoritmo por defecto: **ed25519** (también admite `rsa4096`).

### Modelo de claves

La plataforma gestiona todas las claves internamente: **ni las empresas ni los investigadores suben claves**.

| Clave | Cuántas | Para qué |
| --- | --- | --- |
| **Clave de custodia** de la plataforma | Una activa | Permite a moderación y administración leer el contenido sin depender de la clave de la empresa. También firma los certificados |
| **Clave de empresa** | Una activa por empresa (caduca al año) | Permite a esa empresa leer los informes y programas que le pertenecen |

Cada contenido se cifra **para dos destinatarios a la vez**: la clave de custodia y la clave de la empresa dueña del programa. Si el programa no tiene empresa, solo para la de custodia. Cada fila guarda la huella de la clave con que se cifró (`clave_huella`).

### Dónde se guardan las claves

| Lugar | Qué guarda |
| --- | --- |
| Tabla `claves_pgp_plataforma` | Clave de custodia: huella, id, identidad, algoritmo, clave pública y **clave privada cifrada** |
| Tabla `claves_pgp_empresa` | Una fila por clave de cada empresa, con el mismo formato |
| `storage/app/pgp/gpg` (`PGP_GNUPG_HOME`) | Keyring de GnuPG que usa el binario para cifrar y descifrar |
| `storage/app/pgp/fallback` | Almacén del driver de respaldo (solo desarrollo) |
| `.env` → `PGP_STORAGE_KEY` | Secreto AES-256 que cifra la columna `clave_privada` en ambas tablas |
| `.env` → `PGP_KEY_PASSWORD` | Contraseña de las claves en GnuPG; **obligatoria en producción** |

La columna `clave_privada` está oculta en la serialización de los modelos (`#[Hidden]`) y **nunca se escribe en la auditoría**.

### Por qué `PGP_STORAGE_KEY` y no `APP_KEY`

`APP_KEY` también protege sesiones, cookies y otras columnas cifradas. Si se filtrara por cualquier motivo, no debe llevarse también las claves privadas PGP. Por eso el cast `App\Casts\CifradoConClavePgp` usa un secreto separado. Para generarlo:

```bash
php artisan tinker --execute="echo 'base64:'.base64_encode(random_bytes(32));"
```

### Creación automática

La clave de custodia se crea sola la primera vez que hace falta (al instalar o con el primer informe), y la de cada empresa la primera vez que se cifra algo suyo. Un **candado** (`Cache::lock`) evita que dos peticiones simultáneas creen dos claves. En producción, sin `PGP_KEY_PASSWORD` no se crea ninguna clave.

### Qué se cifra en la base de datos

| Tabla | Columna | ¿Cifrada? | Motivo |
| --- | --- | --- | --- |
| `reportes` | `descripcion`, `poc` | ✅ | Detalle de la vulnerabilidad y cómo reproducirla |
| `reportes` | `titulo`, `categoria`, `estado`, `vector_cvss`, `puntuacion_cvss`, `severidad` | ❌ | Necesarios para listar, filtrar y ordenar sin descifrar |
| `programas` | `descripcion`, `bugs_buscados` | ✅ | Pueden revelar debilidades conocidas de la empresa |
| `programas` | `nombre`, `slug`, `estado`, `es_publico`, `nivel_acceso`, `poc_schema` | ❌ | Necesarios para el listado público |
| `objetivos_programa` | `valor`, `descripcion` | ✅ | El dominio, IP o API exacta: la superficie de ataque real |
| `objetivos_programa` | `tipo` | ❌ | Solo una categoría (web/API/móvil/otro) |
| `adjuntos` | archivo en disco | ✅ | Fotos de evidencia de informes y apelaciones |

El listado público de programas no muestra la descripción, así no descifra nada; el contenido cifrado se descifra una sola vez al entrar al detalle.

### Fotos de evidencia

`App\Services\Adjuntos\AdjuntoService`:

1. Comprueba que el archivo sea de verdad PNG, JPEG o WebP (máx. 5 MB y 10 fotos por informe o apelación).
2. **Re-codifica la imagen** con GD: desaparecen EXIF, GPS y cualquier dato oculto. Limita los píxeles para evitar "bombas de descompresión".
3. Calcula su **SHA-256**, la **cifra con PGP** y la guarda en `config('adjuntos.disco')`.
4. Al servirla: la descifra al vuelo, verifica la huella y la entrega con cabeceras que impiden ejecutarla, **solo tras pasar ABAC**.

### Quién puede ver el contenido descifrado

El descifrado ocurre siempre en el backend; el navegador nunca recibe texto cifrado.

- **Investigador:** sus propios informes.
- **Moderador:** el informe que está revisando y el siguiente de su cola, además del alcance de ese programa.
- **Empresa:** los informes de sus programas ya decididos por moderación.
- **Administrador:** por el bypass de supervisión.
- Nadie más, ni siquiera con acceso directo a la base de datos, sin las claves privadas.

### Comandos PGP

| Comando | Qué hace |
| --- | --- |
| `php artisan pgp:setup` | Genera la clave de custodia y la deja activa |
| `php artisan pgp:check` | Verifica el driver, la clave y un ciclo completo cifrar/descifrar |
| `php artisan pgp:restore` | Reimporta en el keyring la clave de custodia y las de cada empresa desde la base de datos (para servidores con disco temporal) |
| `php artisan pgp:migrar-almacenamiento` | Pasa `clave_privada` de `APP_KEY` al secreto `PGP_STORAGE_KEY` |
| `php artisan pgp:migrar-claves-por-empresa [--dry-run]` | Genera la clave de cada empresa y re-cifra informes, programas y objetivos a [empresa, custodia] |

### Respaldo y recuperación

- Si se **borra el keyring** (`storage/app/pgp/gpg`), `pgp:restore` lo reconstruye desde la base de datos.
- Si se **pierde `PGP_STORAGE_KEY`**, las claves privadas guardadas en la base ya no se pueden abrir y el contenido cifrado queda **irrecuperable**. Hay que guardar ese secreto fuera del servidor (gestor de contraseñas).
- Si se pierde `PGP_KEY_PASSWORD`, GnuPG no puede usar las claves: también hay que respaldarla.

### Verificarlo a mano

```bash
php artisan pgp:check
php artisan tinker
>>> $r = App\Models\Reporte::find(1);
>>> $r->getRawOriginal('descripcion');   // "-----BEGIN PGP MESSAGE-----..." (o FAKE con el driver de respaldo)
>>> app(App\Services\Pgp\PgpService::class)->descifrarReporte($r->getRawOriginal('descripcion'), $r->poc)['descripcion'];
```

### Windows y `gpg-agent`

- **Dos GnuPG en el PATH** (el de Git y el de Gpg4win no son compatibles): fija `PGP_BINARY="C:/Program Files/GnuPG/bin/gpg.exe"` en `.env`.
- **`gpg-agent` tarda en arrancar** buscando lectores de tarjetas: `GpgBinaryDriver` escribe solo un `gpg-agent.conf` con `disable-scdaemon`.
- Si aparece `no se puede crear el socket`: cierra el agente (`Get-Process gpg-agent | Stop-Process -Force`) y reintenta. Si cada petición tarda varios segundos, excluye del antivirus `C:\Program Files\GnuPG` y `storage/app/pgp/gpg`.

## 7. ABAC: el motor de permisos

**ABAC** (Attribute-Based Access Control) decide cada permiso según **atributos**, no según una lista fija de permisos por rol. Así se expresan reglas como "el moderador solo puede revisar informes de programas que modera, que tenga asignados y que no sean suyos", que un modelo por roles no puede expresar.

### Anatomía de una regla

Toda la política está en `config/abac.php`. Ejemplo real:

```php
[
    'id' => 'moderador-triaje-asignado',
    'prioridad' => 35,
    'acciones' => ['reportes.revisar', 'reportes.validar', 'reportes.rechazar', 'reportes.marcar_duplicado'],
    'sujeto' => ['roles' => ['contains' => 'moderador']],
    'objeto' => [
        'estado' => ['in' => ['enviado', 'en_revision', 'needs_info']],
        'asignado_a' => ['=' => '@sujeto.id'],
        'programa_id' => ['in' => '@sujeto.programas_moderados'],
        'investigador_id' => ['!=' => '@sujeto.id'],
    ],
    'entorno' => [],
    'decision' => 'permitir',
],
```

| Parte | Qué evalúa | Ejemplos |
| --- | --- | --- |
| **Sujeto** | Atributos del usuario | roles, `suspendido`, `is_active`, `es_verificado`, `niveles_acceso`, `programas_moderados`, `empresa_id` |
| **Objeto** | Atributos del recurso (admite relaciones como `programa.empresa_id`) | estado, dueño, asignado, nivel de acceso, `fuera_de_fechas`, `siguiente_en_cola` |
| **Entorno** | Contexto de la petición | fecha y hora (`@entorno.ahora`), empresa activa (`@entorno.empresa_id`) |
| **Decisión** | `permitir` o `denegar` | |

Operadores: `=`, `!=`, `in`, `contains`, `>=`, `is_not_null`. Las referencias `@sujeto.x` y `@entorno.x` comparan atributos entre sí.

### Principios de evaluación

`App\Abac\AbacEngine` evalúa todas las reglas que aplican a la acción, de menor a mayor `prioridad`:

1. **El `denegar` siempre gana** (*deny-overrides*), sin importar la prioridad ni el bypass del administrador.
2. **Denegar por defecto** (`deny_by_default`): si ninguna regla permite la acción, se deniega.

| Prioridad | Tipo de regla |
| --- | --- |
| 1 | Bloqueos globales (usuario inactivo o suspendido) |
| 5 | Separación de funciones y conflicto de interés (denegaciones) |
| 10 | Bypass de supervisión del administrador |
| 15 | Restricción de entorno (verificados en programas premium) |
| 20 | Investigador |
| 35 | Empresa y moderador |

### Cómo se aplica en el código

- Un único Gate: `Gate::authorize('abac', [AccionesAbac::ReporteVer, $reporte, $contexto])` en controladores y `FormRequest`.
- Middleware `abac` para rutas completas.
- Las acciones son constantes `recurso.accion` en `App\Abac\AccionesAbac`.
- El frontend recibe qué acciones están disponibles (`accionesDisponibles`) y solo muestra los botones permitidos; el servidor vuelve a comprobarlo siempre.

### Cómo auditar un permiso

```bash
php artisan abac:audit                                            # resumen de la política
php artisan abac:audit --usuario=3 --accion=reportes.crear --programa=1
php artisan abac:audit --usuario=5 --accion=reportes.validar --reporte=8
```

Muestra regla por regla si coincidió y por qué, y el resultado final (código de salida 0 si permite, 1 si deniega). El administrador tiene además un **simulador ABAC** en el panel (`/admin/abac/simulador`).

## 8. Catálogo completo de reglas ABAC

La política tiene **44 reglas**. Todas están en `config/abac.php`.

### 8.1 Bloqueos globales (prioridad 1)

| Regla | Decisión | Qué hace |
| --- | --- | --- |
| `denegar-todo-a-usuario-inactivo` | Denegar | Un usuario desactivado no puede hacer nada |
| `denegar-todo-a-usuario-suspendido-salvo-apelar` | Denegar | Una suspensión vigente bloquea informes, programas, moderación y administración, pero deja ver su reputación y **apelar** (si no, una sanción injusta sería definitiva) |

### 8.2 Administrador

| Regla | Prioridad | Decisión | Qué hace |
| --- | --- | --- | --- |
| `admin-bypass-total` | 10 | Permitir | Acceso de supervisión a todo lo que no esté denegado |
| `denegar-dia-a-dia-de-reportes-al-administrador` | 5 | Denegar | No crea, edita, envía, revisa, valida, rechaza, marca duplicados, repara ni cierra informes: supervisa y arbitra |
| `denegar-crear-editar-eliminar-o-publicar-programas-al-administrador` | 5 | Denegar | No actúa en nombre de la empresa |

### 8.3 Separación de funciones: quién no reporta (prioridad 5)

| Regla | Decisión | Qué hace |
| --- | --- | --- |
| `denegar-crear-reportes-a-moderador` | Denegar | Un moderador no reporta en ningún programa (evita que use su acceso privilegiado) |
| `denegar-crear-reportes-a-empresa` | Denegar | La empresa es la parte que paga: nunca reporta |
| `denegar-reportar-programa-fuera-de-fechas` | Denegar | No se crean informes antes del inicio ni después del fin del programa |
| `denegar-enviar-informe-a-programa-fuera-de-fechas` | Denegar | Tampoco se envían borradores fuera de fechas |

### 8.4 Investigador

| Regla | Prioridad | Decisión | Qué permite / impide |
| --- | --- | --- | --- |
| `denegar-no-verificado-en-programa-premium` | 15 | Denegar | Un investigador no verificado no reporta en programas "solo verificados" |
| `inv-crear-reporte-en-programa-publico-activo` | 20 | Permitir | Reportar en programas públicos activos de su nivel de acceso |
| `inv-crear-reporte-en-programa-privado-invitado` | 20 | Permitir | Reportar en programas privados donde fue invitado |
| `inv-ver-reporte-propio` | 20 | Permitir | Ver sus propios informes |
| `inv-descifrar-poc-propio` | 20 | Permitir | Descifrar la PoC de sus informes |
| `inv-editar-reporte-propio-en-colada` | 20 | Permitir | Editar sus informes en `borrador`, `enviado` o `needs_info` |
| `inv-enviar-reporte-borrador` | 20 | Permitir | Enviar sus informes en `borrador` o `needs_info` |
| `inv-eliminar-reporte-borrador` | 20 | Permitir | Eliminar solo sus borradores |
| `inv-ver-programa-publico` | 20 | Permitir | Ver programas públicos activos o en pausa de su nivel |
| `inv-ver-programa-privado-invitado` | 20 | Permitir | Ver programas privados donde fue invitado |
| `inv-ver-reputacion-propia` | 20 | Permitir | Ver su ledger de reputación |
| `inv-apelar-sancion-propia-en-plazo` | 20 | Permitir | Apelar sus sanciones vigentes, **solo dentro del plazo** (compara con `@entorno.ahora`) |
| `denegar-crear-programas-al-investigador` | 5 | Denegar | No crea programas |
| `denegar-triaje-a-investigador` | 5 | Denegar | No asigna, revisa, valida, rechaza, repara ni cierra informes |

### 8.5 Empresa (prioridad 35)

| Regla | Decisión | Qué permite |
| --- | --- | --- |
| `empresa-crear-programa` | Permitir | Crear programas |
| `empresa-ver-programa-propio` | Permitir | Ver los programas de su empresa activa (atributo de entorno) |
| `empresa-gestionar-programa-propio` | Permitir | Editar, publicar, pausar y eliminar sus programas |
| `empresa-invitar-hacker-a-programa` | Permitir | Invitar investigadores a sus programas privados |
| `empresa-ver-reportes-de-sus-programas` | Permitir | Ver informes de sus programas **solo si moderación ya los decidió** |
| `empresa-descifrar-poc` | Permitir | Descifrar la PoC de esos mismos informes |
| `empresa-reparar-y-cerrar-reportes-de-sus-programas` | Permitir | Marcar en reparación y cerrar informes validados |
| `empresa-pagar-su-plan` | Permitir | Pagar su propio Plan Profesional (nunca el de otra) |
| `empresa-gestionar-y-pagar-bounties` | Permitir | Asignar y pagar bounties de informes validados, en reparación o cerrados |

### 8.6 Moderador

| Regla | Prioridad | Decisión | Qué permite / impide |
| --- | --- | --- | --- |
| `moderador-ver-sus-programas` | 35 | Permitir | Ver los programas que modera |
| `moderador-ver-reportes-que-tomo` | 35 | Permitir | Ver, leer notas internas y descifrar los informes asignados a él |
| `moderador-ver-siguiente-de-la-cola` | 35 | Permitir | Abrir solo el siguiente informe de la cola |
| `moderador-ver-cola-de-moderacion` | 35 | Permitir | Entrar a `/moderacion` |
| `moderador-triaje-asignado` | 35 | Permitir | Revisar, validar, rechazar o marcar duplicado **solo** en informes asignados a él, de programas que modera y que no sean suyos |
| `denegar-crear-o-editar-programas-al-moderador` | 5 | Denegar | No crea, edita, publica ni elimina programas |

### 8.7 Conflicto de interés: nadie es juez y parte (prioridad 5)

Estas denegaciones ganan siempre, incluso contra el bypass del administrador.

| Regla | Qué impide |
| --- | --- |
| `denegar-triaje-de-informe-propio` | Nadie asigna, revisa, valida, rechaza, repara, cierra ni lee notas internas de su propio informe |
| `denegar-resolver-apelaciones-a-moderador` | Un moderador no resuelve apelaciones: las resuelve el administrador |
| `denegar-resolver-apelacion-propia` | Nadie resuelve una apelación que presentó él mismo |

A esto se suman las denegaciones de las secciones 8.3 y 8.4 (moderadores y empresas no reportan) y la auto-asignación de moderadores, que excluye a quien tiene relación con la empresa o ya reportó en el programa.

### 8.8 Certificados de divulgación

| Regla | Prioridad | Qué permite |
| --- | --- | --- |
| `inv-ver-certificado-propio` | 20 | El investigador ve el certificado de sus informes cerrados |
| `empresa-ver-certificados-de-sus-programas` | 35 | La empresa ve los certificados de sus programas |
| `moderador-ver-certificados-de-sus-programas` | 35 | El moderador ve los certificados de los programas que modera |

## 9. Reputación y penalizaciones

Configuración en `config/reputacion.php`; el administrador puede cambiarla desde `/admin/config/reputacion`.

### Ledger inmutable

La tabla `ledger_reputacion` es la **única fuente de verdad** del saldo. Cada suma o resta es un asiento nuevo; nunca se edita uno existente. Revocar una sanción no borra el asiento: crea otro que devuelve los puntos.

### Puntos por severidad

Los puntos se calculan automáticamente según la severidad CVSS, sin decisión humana:

| Severidad | Confirmado por la empresa | Resuelto (cerrado) |
| --- | --- | --- |
| Crítica | 100 | 200 |
| Alta | 50 | 100 |
| Media | 25 | 50 |
| Baja | 10 | 20 |
| Ninguna | 5 | 10 |

Los puntos **no** se dan cuando el moderador valida, sino cuando la **empresa confirma** el hallazgo.

### Rangos y nivel de acceso

| Rango | Puntos mínimos | Acceso a programas |
| --- | --- | --- |
| Bronce | 0 | Nivel bajo |
| Plata | 100 | Nivel medio |
| Oro | 300 | Nivel alto |
| Platino | 700 | Todos |
| Diamante | 1500 | Todos |

El rango se calcula siempre a partir del saldo; no se guarda. Hay un ranking público de investigadores en `/hall-of-fame`.

**Investigador verificado:** al menos 3 informes confirmados por la empresa, sin suspensión en curso y sin sanciones vigentes en los últimos 30 días.

### Sanciones proporcionales

| Gravedad | Penalización base | Suspensión |
| --- | --- | --- |
| Leve | −25 | Sin suspensión |
| Media | −80 | 7 días |
| Grave | −250 | 30 días |

**Reincidencia:** la penalización se multiplica por `1 + 0.5 × sanciones vigentes`, con un máximo de ×3. Ejemplo: una sanción grave con 2 sanciones previas vigentes resta 250 × 2 = **−500**.

El moderador puede sancionar al rechazar un informe falso o fabricado. La sanción queda en el ledger, en la línea de tiempo, en auditoría y se notifica al investigador.

### Detección de trampas (`CheatDetectionService`)

Detecta tres patrones de "inflar métricas":

| Patrón | Criterio (configurable) |
| --- | --- |
| **Ráfaga** | Más de 5 informes enviados en 15 minutos |
| **Duplicados** | Más del 50 % de los informes marcados como duplicados (con un mínimo de 3) |
| **Fabricación** | Informes de severidad alta o crítica descartados (rechazados, duplicados o fuera de alcance) sin PoC válida |

Se ejecuta con `php artisan reputacion:audit --analizar` (solo análisis, no sanciona). El servicio también puede aplicar sanciones proporcionales por cada detección, con una ventana de idempotencia de 24 h para no castigar dos veces la misma conducta.

### Apelaciones

- Plazo: **7 días** desde la sanción; solo una apelación por sanción.
- Se pueden adjuntar fotos de evidencia (cifradas como las de los informes).
- La resuelve un **administrador**. Si se aprueba, la sanción se revoca y se devuelven los puntos. Si se rechaza, la suspensión continúa con los días que faltaban.
- **Traza encadenada:** cada paso (presentada, aprobada, rechazada) guarda quién, con qué rol, cuándo, IP y una huella **SHA-256 encadenada** con el paso anterior. Si alguien modifica o borra un paso, la cadena deja de cuadrar y se detecta.

## 10. Automatizaciones

### Tareas programadas

Definidas en `routes/console.php`. En local se ejecutan con `php artisan schedule:work`; en el servidor, con un cron que llame a `php artisan schedule:run` cada minuto.

| Comando | Frecuencia | Qué hace |
| --- | --- | --- |
| `bounties:verificar-pendientes` | Cada minuto (sin solapar) | Verifica en la blockchain los pagos de bounties en curso; si una transacción no aparece en 30 min, la marca como fallida |
| `suscripciones:verificar-pendientes` | Cada minuto (sin solapar) | Verifica los pagos del Plan Profesional y lo activa |
| `programas:pausar-vencidos` | Cada hora | Pausa los programas cuya fecha de fin pasó, avisa a la empresa y reasigna moderadores liberados |
| `apelaciones:auditar-sla` | Cada hora | Alerta al administrador de apelaciones con más de 48 h sin resolver y, pasados 5 días, **levanta la suspensión de forma cautelar** para no perjudicar al usuario |
| `suscripciones:revisar-vencimientos` | Cada hora | Avisa de planes a punto de vencer y pasa los vencidos al Plan Comunitario |

### Automatizaciones que se disparan con una acción

| Automatización | Cuándo se dispara | Qué hace |
| --- | --- | --- |
| **Asignación automática de moderadores** (`AutoAsignadorModeradores`) | Al publicar un programa o liberarse un moderador | Asigna 2 moderadores por programa, eligiendo a los de menor carga (máx. 5 programas activos cada uno). Excluye suspendidos, miembros de empresas y quien ya reportó en ese programa. Si no hay nadie libre, avisa al administrador |
| **Límite de envíos** (`LimiteDeEnvios`) | Al enviar un informe | Bloquea en el momento: más de 5 envíos en 15 min, más de 3 al mismo programa en 60 min, o el mismo título repetido en 24 h. Indica cuánto hay que esperar |
| **Detector de duplicados** (`DetectorDuplicados`) | Al abrir un informe para revisarlo | Puntúa el parecido (0–100) con informes anteriores del programa por título, categoría y vector CVSS, y sugiere los más probables al moderador |
| **Cola por orden de llegada** (`ColaDeValidacion`) | Al validar, confirmar o cerrar | Impide adelantar un informe a otro anterior del mismo programa |
| **Línea de tiempo** | En cada cambio de estado, asignación, comentario, duplicado, sanción o pago | Crea un `EventoReporte` |
| **Notificaciones** (`EventoReporteObserver` + `Notificador`) | Al crearse cualquier evento del informe, y en sanciones, apelaciones, pagos, planes, invitaciones y cambios de rango | Avisa a cada parte interesada |
| **Revocación en cascada de sanciones** | Al validar o confirmar un informe que había sido sancionado | Revoca la sanción, devuelve los puntos y aprueba la apelación pendiente |
| **Puntos de reputación** | Cuando la empresa confirma o cierra un informe | Suma puntos según la severidad CVSS |
| **Cambio de rango** | Cuando el saldo cruza un umbral | Notifica al investigador su nuevo rango |
| **Certificado de divulgación** | Al cerrar un informe | Emite y firma el certificado, y avisa al investigador |
| **Clave PGP** | Con el primer contenido que se cifra | Crea la clave de custodia o la de la empresa, con candado |
| **Limpieza de fotos** | Al subir evidencia | Quita EXIF/GPS, calcula SHA-256 y cifra |
| **Auditoría** | En cada acción sensible | Registra usuario, acción, entidad, IP y detalle (`/admin/auditoria`) |

## 11. Pagos: bounties y Plan Profesional

Todos los pagos son en **USDC** sobre Polygon (Amoy en pruebas). La plataforma **nunca custodia fondos ni claves privadas de wallets**: la empresa firma la transferencia en su MetaMask y el servidor la verifica en la blockchain.

- **Bounties:** la empresa asigna un monto dentro del rango del programa y paga directo a la wallet del investigador. Estados: `sin_bounty → asignado → verificando → pagado` (o `fallido`). El servidor solo confirma si el `Transfer` es del contrato USDC oficial, a la wallet del investigador, por al menos el monto y con las confirmaciones exigidas. Un mismo hash no puede pagar dos informes. Detalles: [docs/bounties.md](docs/bounties.md).
- **Plan Profesional:** la empresa paga a la tesorería del proyecto (5 USDC / 30 días por defecto) y desbloquea programas privados, de élite y solo para verificados. Al vencer vuelve al Plan Comunitario sin perder sus programas. Detalles: [docs/plan-profesional.md](docs/plan-profesional.md).

## 12. Instalación y puesta en marcha

### Requisitos

| Requisito | Para qué |
| --- | --- |
| **PHP 8.4** + extensiones de Laravel y GD | Backend y limpieza de fotos |
| **Composer** | Dependencias PHP |
| **Node 22** + npm | Frontend (Svelte + Vite) |
| **PostgreSQL** (Supabase) o **SQLite** | Base de datos |
| **[Gpg4win](https://www.gpg4win.org/)** / GnuPG (opcional en local) | Cifrado PGP real; sin él se usa el driver de respaldo |

### Pasos

```bash
composer install
cp .env.example .env
php artisan key:generate
```

En `.env`, define `PGP_STORAGE_KEY` (ver [sección 6](#por-qué-pgp_storage_key-y-no-app_key)) y la base de datos: `sqlite` por defecto, o Supabase siguiendo el bloque comentado de `.env.example` y [docs/supabase.md](docs/supabase.md).

```bash
php artisan migrate --seed   # tablas + datos demo
npm install
npm run build                # o `npm run dev` para hot-reload
php artisan serve
php artisan schedule:work    # en otra terminal: tareas programadas
```

O todo junto: `composer dev` (servidor + cola + Vite). La app queda en `http://localhost:8000`.

### Usuarios demo

| Rol | Email | Contraseña |
| --- | --- | --- |
| Administrador | `admin@bugbounty.local` | `admin` |
| Moderador | `moderador@bugbounty.local` | `moderador` |
| Investigador | `investigador@bugbounty.local` | `investigador` |
| Empresa (propietario) | `empresa@bugbounty.local` | `empresa` |
| Empresa pendiente de aprobación | `pendiente@bugbounty.local` | `pendiente` |

`UsuariosDemoSeeder` añade investigadores de cada rango (`plata@`, `oro@`, `platino@`, `diamante@bugbounty.local`) y casos especiales (`suspendido@`, `sancionado@`, `desactivado@bugbounty.local`), todos con la contraseña `investigador`.

## 13. Qué probar por rol

- **Investigador:** `/programas` → entrar a uno → "Reportar un bug" → completar el wizard (Detalles → CVSS → PoC → Revisión) → enviar. Ver `/reportes`, `/reputacion` y los certificados.
- **Moderador:** `/moderacion` → tomar el siguiente informe de la cola → validar, rechazar (con o sin sanción), pedir información o marcar duplicado.
- **Empresa:** `/empresa` → crear un programa con objetivos y formulario de PoC → publicarlo → confirmar y cerrar informes validados → asignar y pagar el bounty.
- **Administrador:** `/admin/usuarios`, `/admin/empresas`, `/admin/sanciones`, `/moderacion/apelaciones`, `/admin/auditoria`, `/admin/config/reputacion`, `/admin/pgp` y el simulador ABAC (`/admin/abac/simulador`).
- **Casos límite:** entrar con `suspendido@bugbounty.local` (solo puede ver su reputación y apelar) o intentar que la empresa reporte en su propio programa (ABAC lo deniega).

## 14. Verificación y tests

```bash
composer test          # Pint + PHPStan + Pest (backend)
npm run check          # lint del frontend (vp lint)
npm run types:check    # svelte-check
npm run test:e2e       # pruebas de navegador con Playwright (requiere npm run build)
php artisan abac:audit --usuario=<id> --accion=<accion> [--programa=<id>|--reporte=<id>|--apelacion=<id>]
php artisan reputacion:audit [--usuario=<id>] [--analizar]
php artisan pgp:check
```

Los tests fuerzan `PGP_DRIVER=fallback` (`phpunit.xml`) para no depender de GnuPG. CI (`.github/workflows/tests.yml`) ejecuta `composer ci:check` con PHP 8.4 y Node 22.

## 15. Despliegue y documentación adicional

| Documento | Contenido |
| --- | --- |
| [docs/supabase.md](docs/supabase.md) | Conexión a PostgreSQL en Supabase, SSL, pooler y RLS |
| [docs/render.md](docs/render.md) | Pendientes para desplegar en Render |
| [docs/empresas-moderadores.md](docs/empresas-moderadores.md) | Flujos de empresa y moderación |
| [docs/bounties.md](docs/bounties.md) | Bounties en USDC |
| [docs/plan-profesional.md](docs/plan-profesional.md) | Plan Profesional y tesorería |

**Pendientes antes de producción:**

- Mover las fotos de evidencia a **Supabase Storage (S3)**: el disco de Render es temporal.
- Instalar **GnuPG** en la imagen Docker, definir `PGP_KEY_PASSWORD` y `PGP_STORAGE_KEY`, y ejecutar `pgp:restore` al arrancar el contenedor.
- Configurar el **cron** del scheduler.
