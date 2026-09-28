# bug-bounty

Plataforma de **Divulgación Coordinada de Vulnerabilidades** (bug bounty) en español.

Las empresas publican programas en los que invitan a encontrar fallos de seguridad en sus sistemas. Los investigadores (hackers éticos) buscan esos fallos y los reportan de forma privada. Un equipo de moderadores verifica cada informe antes de que llegue a la empresa, y la empresa corrige el problema y recompensa al investigador.

Todo el contenido sensible se guarda **cifrado con PGP**, cada permiso lo decide un motor de **control de acceso basado en atributos (ABAC)** y un sistema de **reputación** premia los hallazgos reales y castiga los informes falsos.

**Tecnologías:** Laravel 13 · Inertia 3 · Svelte 5 · PostgreSQL (Supabase) · GnuPG · Tailwind 4.

## Índice

1. [Roles](#1-roles)
2. [Cómo funciona la plataforma](#2-cómo-funciona-la-plataforma)
3. [Programas](#3-programas)
4. [El informe de vulnerabilidad](#4-el-informe-de-vulnerabilidad)
5. [La prueba de concepto (PoC)](#5-la-prueba-de-concepto-poc)
6. [Severidad con CVSS 3.1](#6-severidad-con-cvss-31)
7. [Moderación](#7-moderación)
8. [Cifrado PGP: cómo funciona y por qué es seguro](#8-cifrado-pgp-cómo-funciona-y-por-qué-es-seguro)
9. [Control de acceso (ABAC)](#9-control-de-acceso-abac)
10. [Reglas de acceso de la plataforma](#10-reglas-de-acceso-de-la-plataforma)
11. [Reputación, sanciones y apelaciones](#11-reputación-sanciones-y-apelaciones)
12. [Automatizaciones](#12-automatizaciones)
13. [Recompensas y planes en USDC](#13-recompensas-y-planes-en-usdc)
14. [Auditoría y trazabilidad](#14-auditoría-y-trazabilidad)
15. [Instalación y prueba](#15-instalación-y-prueba)

---

## 1. Roles

| Rol               | Qué hace                                                                                                    | Qué **no** puede hacer                                                                  |
| ----------------- | ----------------------------------------------------------------------------------------------------------- | --------------------------------------------------------------------------------------- |
| **Investigador**  | Busca vulnerabilidades, envía informes, gana reputación y recompensas, apela sanciones                      | Revisar informes ni crear programas                                                     |
| **Empresa**       | Crea y publica programas, recibe los informes ya verificados, los repara, los cierra y paga las recompensas | Reportar vulnerabilidades en ningún programa                                            |
| **Moderador**     | Revisa los informes de los programas que tiene asignados, sin saber quién los envió                         | Reportar, crear programas ni resolver apelaciones                                       |
| **Administrador** | Supervisa la plataforma: usuarios, empresas, sanciones, apelaciones, configuración y registros              | Participar en el trabajo diario: no crea, revisa ni cierra informes, ni edita programas |

**Solo la empresa crea y publica programas.** Un investigador nunca forma parte de una empresa: lo único que puede recibir de ella es una invitación a un programa privado, para reportar en él.

Cualquier persona que se registra entra como **investigador**. Las empresas se registran aparte y un administrador debe **aprobarlas** antes de que puedan publicar.

## 2. Cómo funciona la plataforma

```
Empresa crea un programa ─▶ lo publica ─▶ el sistema le asigna moderadores
                                               │
Investigador encuentra un fallo ─▶ escribe el informe ─▶ lo envía
                                               │
Moderador lo revisa a ciegas, por orden de llegada ─▶ valida o descarta
                                               │
Empresa recibe el informe validado ─▶ lo corrige ─▶ lo cierra como resuelto
                                               │
Investigador recibe reputación, un certificado firmado y, si corresponde, su recompensa
```

### Estados de un informe

| Estado                                       | Qué significa                                                                |
| -------------------------------------------- | ---------------------------------------------------------------------------- |
| **Borrador**                                 | Solo lo ve su autor. Puede guardarse a medias                                |
| **Enviado**                                  | Entró en la fila de revisión del programa                                    |
| **En revisión**                              | Un moderador lo tomó y queda a su cargo                                      |
| **Necesita información**                     | El moderador pidió aclaraciones; el investigador puede editarlo y reenviarlo |
| **Validado**                                 | La vulnerabilidad es real. Desde ahora la empresa puede verlo                |
| **En reparación**                            | La empresa confirmó el hallazgo y lo está corrigiendo                        |
| **Cerrado**                                  | El fallo está resuelto                                                       |
| **Rechazado / Duplicado / Fuera de alcance** | El informe se descartó                                                       |

Un informe solo puede pasar de un estado a otro por los caminos permitidos. Si dos personas intentan cambiarlo a la vez, la segunda recibe un aviso para recargar.

## 3. Programas

Un programa es la invitación de una empresa a probar la seguridad de sus sistemas. Define:

- **Objetivos:** qué se puede atacar (sitios web, APIs, aplicaciones móviles u otros) y con qué detalle.
- **Qué fallos busca la empresa** y cuáles quedan fuera.
- **Fechas de inicio y fin.** Un borrador puede guardarse sin fechas, pero **para publicarlo necesita las dos** y quedar abierto **al menos 3 días**, para que los investigadores tengan tiempo real de trabajar. Fuera de ese periodo no se aceptan informes, y al terminar el programa se pausa solo.
- **Nivel de acceso** (bajo, medio o alto): exige un rango mínimo de reputación al investigador.
- **Visibilidad:** público para todos, o privado solo para investigadores invitados.
- **Solo verificados:** limita el programa a investigadores con historial comprobado.
- **Formulario de prueba de concepto** propio (ver [sección 5](#5-la-prueba-de-concepto-poc)).
- **Rango de recompensas** en USDC, si el programa paga.

Un programa pasa por los estados **borrador**, **activo**, **en pausa** y **finalizado**. En el listado público solo aparecen el nombre, la empresa y el tipo de objetivos; la descripción y el alcance exacto se muestran únicamente al entrar al programa.

## 4. El informe de vulnerabilidad

### Cómo se escribe

El investigador completa un asistente de **cuatro pasos**:

1. **Detalles:** título, descripción del fallo y categoría. El programa queda fijado si se entra desde él.
2. **Severidad:** una calculadora CVSS que da la puntuación en vivo.
3. **Prueba de concepto y evidencia:** el formulario del programa y hasta 10 fotos.
4. **Revisión:** un resumen final para guardar como borrador o enviar.

Cada informe recibe un número único por año, por ejemplo **BB-2026-0001**.

### Qué ocurre al enviarlo

1. Se comprueba que el investigador **tenga permiso** para reportar en ese programa.
2. Se comprueba que no esté **enviando en masa** (ver [sección 12](#12-automatizaciones)).
3. Se valida que la **prueba de concepto esté completa** según el formulario del programa.
4. La **descripción y la prueba de concepto se cifran** antes de guardarse.
5. Las **fotos se limpian** de datos ocultos, se cifran y se guardan.
6. Queda registrado en la **línea de tiempo** del informe y en la **auditoría**, y se avisa a los interesados.

Si algo falla a mitad de camino, no se guarda nada: nunca queda un informe a medias.

### El informe que recibe la empresa

La empresa **solo ve el informe cuando moderación ya lo revisó** (validado o descartado). Nunca le llegan borradores ni informes sin verificar, así su equipo solo dedica tiempo a hallazgos confirmados.

En el informe la empresa encuentra:

- El número, el título, la categoría y el estado.
- La **severidad**, con su vector CVSS y su puntuación.
- La **descripción y la prueba de concepto descifradas** solo para ella. Cada vez que las abre queda registrado quién, cuándo y desde dónde.
- Las **fotos de evidencia**.
- La **línea de tiempo** completa: cuándo se envió, quién lo revisó, comentarios y cambios de estado.
- Las acciones disponibles: **confirmar y pasar a reparación**, **cerrar como resuelto** y **asignar y pagar la recompensa**.

### Línea de tiempo

Cada acción sobre un informe (envío, revisión, validación, comentario, sanción, pago) queda como un evento con fecha y autor. Todos los que pueden ver el informe ven su historia; para el moderador, las acciones del autor aparecen como "Investigador anónimo".

### Certificado de divulgación

Cuando un informe se cierra como resuelto, el investigador recibe un **certificado de divulgación responsable** sellado con una huella SHA-256 y **firmado con la clave PGP** de la plataforma. Cualquiera puede comprobar que es auténtico, sin cuenta, desde la página pública de verificación: basta con **escanear el código QR** impreso en el certificado. También se pueden descargar la firma y la clave pública para verificarlo por su cuenta con cualquier programa PGP.

## 5. La prueba de concepto (PoC)

La prueba de concepto es la evidencia de que la vulnerabilidad existe: los pasos para reproducirla, la dirección afectada, el código usado, etc. **Es obligatoria en todos los programas.**

### Un formulario distinto para cada programa

Cada empresa diseña **su propio formulario** de prueba de concepto con un editor visual al crear el programa, **sin programar nada**. Así una empresa con una API puede pedir el método y la respuesta, y otra con una aplicación web, la URL y los pasos.

Cada campo puede ser de tipo **texto corto, texto largo, lista de opciones, número, URL o código**, y puede marcarse como **obligatorio** o **repetible** (por ejemplo, para añadir varios pasos). Si una empresa no define campos, se pide uno genérico: "Evidencia y pasos para reproducir".

### Validación

- **Mientras se escribe,** el navegador avisa de lo que falta.
- **Al guardar,** el servidor vuelve a comprobarlo todo, porque la validación del navegador se puede saltar. Además, rechaza campos que el programa no pidió y **bloquea direcciones de redes internas** (como `localhost` o `192.168.x.x`), para que nadie pueda usar la plataforma para atacar sus propios servidores internos.
- **Un borrador** puede guardarse con la prueba incompleta; **al enviar**, se exige completa.

La prueba de concepto se guarda **cifrada** y solo la pueden leer el autor, el moderador que revisa ese informe y la empresa una vez validado.

## 6. Severidad con CVSS 3.1

La severidad de cada vulnerabilidad se mide con **CVSS versión 3.1** (Common Vulnerability Scoring System), el estándar internacional que usan los catálogos públicos de vulnerabilidades y las principales plataformas de bug bounty. Se usan las **métricas base**:

| Métrica                                       | Pregunta que responde                                              |
| --------------------------------------------- | ------------------------------------------------------------------ |
| Vector de ataque                              | ¿Desde dónde se ataca? (Internet, red local, acceso local, físico) |
| Complejidad                                   | ¿Es fácil o difícil de explotar?                                   |
| Privilegios requeridos                        | ¿Hace falta una cuenta o permisos?                                 |
| Interacción del usuario                       | ¿La víctima tiene que hacer algo?                                  |
| Alcance                                       | ¿El daño sale del componente vulnerable?                           |
| Confidencialidad, integridad y disponibilidad | ¿Cuánto daño causa en cada una?                                    |

La calculadora aplica la fórmula oficial de la especificación 3.1 y guarda el **vector** (por ejemplo `CVSS:3.1/AV:N/AC:L/PR:N/UI:N/S:U/C:H/I:H/A:H`), la **puntuación** y la **severidad**:

| Puntuación | Severidad |
| ---------- | --------- |
| 0.0        | Ninguna   |
| 0.1 – 3.9  | Baja      |
| 4.0 – 6.9  | Media     |
| 7.0 – 8.9  | Alta      |
| 9.0 – 10.0 | Crítica   |

La severidad se usa para ordenar y filtrar informes, calcular los **puntos de reputación**, detectar **informes inflados** y ayudar a encontrar **duplicados**.

## 7. Moderación

- **Asignación automática:** al publicar un programa, el sistema le asigna moderadores (ver [sección 12](#12-automatizaciones)).
- **Orden de llegada:** los informes se revisan en el orden en que se enviaron. La recompensa es para quien encontró el fallo primero, así que no se puede validar un informe mientras haya otro anterior pendiente en el mismo programa.
- **Una fila por programa:** el moderador solo puede abrir el siguiente informe de la fila y los que ya tiene a su cargo. Si dos moderadores intentan tomar el mismo a la vez, solo uno lo consigue.
- **Revisión a ciegas:** el moderador **no sabe quién envió el informe**. Solo ve su rango y su historial (cuántos informes envió, cuántos se aprobaron y cuántos se descartaron). Así se evitan favoritismos y represalias. Incluso los nombres de las fotos se ocultan.
- **Herramientas:** el moderador ve el alcance del programa para comprobar que el fallo está dentro, recibe sugerencias de posibles duplicados y puede pedir más información, validar, rechazar (con o sin sanción) o marcar como duplicado.

## 8. Cifrado PGP: cómo funciona y por qué es seguro

Un informe de vulnerabilidad es, en la práctica, un manual para atacar a una empresa. Si se filtrara la base de datos, esa información podría usarse contra ella antes de que se corrija. Por eso **ningún contenido sensible se guarda en texto claro**.

### Qué es PGP

PGP (en su versión estándar, **OpenPGP**) es un sistema de **cifrado de clave pública** usado desde hace décadas para proteger correos, archivos y actualizaciones de software. Cada clave tiene dos partes:

- una **clave pública**, que sirve para **cifrar**: cualquiera puede usarla, pero solo sirve para cerrar;
- una **clave privada**, la única capaz de **descifrar** lo que se cifró con su pública.

La plataforma usa **GnuPG**, la implementación libre y auditada de OpenPGP, en lugar de un cifrado propio. Inventar criptografía es uno de los errores más comunes en seguridad; usar un estándar probado evita ese riesgo.

### Cómo se aplica en la plataforma

**1. Las claves se crean solas.** La plataforma genera y administra todas las claves. Ni las empresas ni los investigadores tienen que crear, subir ni custodiar nada. Existen dos tipos:

| Clave                 | Cuántas hay                 | Para qué sirve                                                                                    |
| --------------------- | --------------------------- | ------------------------------------------------------------------------------------------------- |
| **Clave de custodia** | Una para toda la plataforma | Permite a moderación y administración leer los informes para revisarlos, y firma los certificados |
| **Clave de empresa**  | Una por cada empresa        | Permite a esa empresa, y solo a ella, leer los informes y datos de sus programas                  |

Cuando hace falta una clave que todavía no existe, se crea en ese momento. Un mecanismo de bloqueo impide que dos peticiones simultáneas creen dos claves distintas. Cada vez que se genera una clave, se avisa a los administradores y queda registrado.

**2. Cada contenido se cifra para dos destinatarios.** Al guardar un informe, se cifra **a la vez para la clave de la empresa dueña del programa y para la clave de custodia**. Así la empresa puede leer sus informes sin depender de la plataforma, y moderación puede revisarlos sin tener la clave de la empresa. Ninguna otra empresa puede leerlo, porque su clave no está entre los destinatarios.

**3. Qué se cifra.**

| Se cifra                                                   | Se deja legible, y por qué                                                                                   |
| ---------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------ |
| Descripción de la vulnerabilidad                           | Título, estado y severidad: hacen falta para listar y ordenar informes, y no explican cómo explotar el fallo |
| Prueba de concepto                                         | La categoría del informe                                                                                     |
| Descripción del programa y los fallos que busca la empresa | Nombre y estado del programa: necesarios para el listado público                                             |
| Los objetivos exactos (dominios, direcciones, APIs)        | El tipo de objetivo (web, API, móvil): solo una categoría                                                    |
| Las fotos de evidencia                                     | —                                                                                                            |

**4. El descifrado ocurre solo en el servidor, y solo con permiso.** El navegador nunca recibe contenido cifrado ni claves. Cuando alguien abre un informe, el servidor primero comprueba sus permisos y solo entonces descifra y le envía el texto legible. Cada descifrado queda registrado con quién lo pidió, cuándo y desde qué dirección.

**5. Las fotos se tratan igual.** Al subirlas se comprueba que sean imágenes reales, se **reconstruyen desde cero** para eliminar la ubicación GPS, los datos de la cámara y cualquier contenido oculto, se calcula su huella SHA-256 y se cifran. Al mostrarlas se descifran, se comprueba que la huella coincida (que nadie las alteró) y se entregan de forma que el navegador no pueda ejecutarlas.

### Por qué se considera seguro

- **Criptografía moderna y estándar.** Las claves usan **curvas elípticas**: Ed25519 para firmar y Curve25519 para cifrar, los mismos algoritmos que protegen SSH, Signal o WireGuard. OpenPGP combina estas claves con un **cifrado simétrico distinto para cada mensaje**, de modo que descubrir el contenido de un informe no ayuda a descifrar otro.
- **Robar la base de datos no basta.** En la base de datos el contenido está cifrado. Las claves privadas también están guardadas ahí, pero **cifradas a su vez con un secreto que no está en la base de datos**, sino en la configuración del servidor. Quien se lleve solo la base de datos se lleva información ilegible.
- **Secretos separados.** Ese secreto que protege las claves privadas es **distinto** de la clave general de la aplicación, que también protege sesiones y cookies. Si una se filtrara, la otra seguiría protegiendo las claves PGP.
- **Doble protección en producción.** En producción, las claves privadas deben tener además una **contraseña propia**: sin ella, la plataforma se niega a crear claves. Así, aunque alguien copiara los archivos de claves del servidor, no podría usarlas sin esa contraseña.
- **Separación por empresa.** Cada empresa tiene su propia clave. Un error de permisos no puede hacer que una empresa lea los informes de otra, porque criptográficamente no son destinatarios.
- **Permisos antes que claves.** Aunque el servidor pueda descifrar, solo lo hace después de que el motor de permisos autorice esa lectura concreta. Cifrado y control de acceso se refuerzan entre sí.
- **Nada sensible en registros.** Las claves privadas nunca se envían al navegador, nunca aparecen en los registros de auditoría y nunca se incluyen al convertir los datos para mostrarlos.
- **Vigencia limitada.** Las claves se generan con una vigencia de un año.
- **Sin atajos en producción.** Para desarrollo existe un modo de simulación que no cifra de verdad (sirve para trabajar sin GnuPG instalado). **La plataforma se niega a usarlo en producción.**
- **Firmas verificables.** Los certificados van firmados con la clave de la plataforma y cualquiera puede comprobar esa firma, lo que demuestra que no fueron falsificados.

### Límites que conviene conocer

- Es un **cifrado gestionado por la plataforma**, no de extremo a extremo: el servidor tiene las claves para poder mostrar el contenido a quien corresponde. Protege contra el robo de la base de datos, filtraciones, copias de seguridad expuestas y accesos indebidos entre empresas. No protege contra alguien que controle por completo el servidor en funcionamiento con todos sus secretos. Por eso los secretos se guardan separados y con contraseña.
- Si se pierde el secreto que protege las claves privadas, el contenido cifrado **no se puede recuperar**. Debe guardarse una copia fuera del servidor, en un gestor de contraseñas.
- Si el servidor pierde sus archivos de claves (por ejemplo, al reiniciarse en un servicio en la nube), la plataforma puede **reconstruirlos** a partir de la base de datos.

## 9. Control de acceso (ABAC)

### Qué es y por qué se eligió

En un sistema clásico por roles, los permisos son fijos: "el moderador puede validar informes". Pero en esta plataforma casi nada depende solo del rol: depende de **qué informe**, **de qué programa**, **en qué estado** y **en qué momento**.

Por eso se construyó un motor propio de **control de acceso basado en atributos (ABAC)**. Cada permiso se decide mirando:

| Qué mira        | Ejemplos                                                                                                                         |
| --------------- | -------------------------------------------------------------------------------------------------------------------------------- |
| **El usuario**  | Su rol, si está suspendido, si está verificado, su rango, qué programas modera, a qué empresa pertenece                          |
| **El recurso**  | Estado del informe, quién es el autor, a quién está asignado, nivel de acceso del programa, si el programa está dentro de fechas |
| **El contexto** | La fecha y hora actual, la empresa con la que está trabajando el usuario                                                         |

Así se pueden expresar reglas como: "el moderador puede validar un informe **solo si** es de un programa que modera, está asignado a él y no lo escribió él mismo".

### Cómo decide

Toda la política vive en un **único lugar**, con **44 reglas** que se pueden leer como frases. El motor sigue dos principios:

1. **Si alguna regla prohíbe, gana la prohibición**, aunque otra regla permita, e incluso contra los permisos del administrador.
2. **Si ninguna regla permite, se prohíbe.** Nada está permitido por omisión.

Con estos dos principios, un error de configuración tiende a **cerrar el acceso, no a abrirlo**.

Las reglas se evalúan por orden de importancia: primero los bloqueos globales (usuarios desactivados o suspendidos), después las prohibiciones por conflicto de interés, luego la supervisión del administrador y por último los permisos de cada rol.

### Cómo se aplica

- **Todas** las acciones del servidor preguntan al mismo motor antes de ejecutarse.
- La interfaz solo muestra los botones que el usuario puede usar, pero **el servidor vuelve a comprobarlo siempre**: manipular el navegador no sirve de nada.
- Se puede **auditar cualquier decisión**: una herramienta de línea de comandos y un simulador en el panel del administrador muestran, regla por regla, por qué un usuario pudo o no pudo hacer algo.

## 10. Reglas de acceso de la plataforma

### Bloqueos globales

- Un **usuario desactivado** no puede hacer nada.
- Un **usuario suspendido** no puede reportar, moderar ni administrar, pero **sí puede ver su reputación y apelar**. Sin esa salida, una sanción injusta sería definitiva.

### Administrador

- Tiene acceso de **supervisión** a toda la plataforma.
- **No** participa en el día a día de los informes: no los crea, no los revisa, no los valida, no los repara ni los cierra.
- **No** crea, edita, publica ni elimina programas: eso es decisión de la empresa, y el administrador no actúa en su nombre.
- Es el único que puede **reasignar** un informe a otro moderador y quien **resuelve las apelaciones**.

### Separación de funciones

- Un **moderador nunca reporta**, en ningún programa, para que no use su acceso privilegiado a favor propio.
- Una **empresa nunca reporta**: es la parte que paga.
- Un **investigador no crea programas** ni revisa informes.
- **Fuera de las fechas** de un programa no se pueden crear ni enviar informes.

### Investigador

- Puede reportar en programas **públicos y activos** de su nivel de acceso, y en programas **privados** donde fue invitado.
- **No** puede reportar en programas "solo verificados" si no está verificado.
- Ve, edita y envía **solo sus propios informes**, y solo mientras están en borrador, enviados o con información pedida. Solo puede eliminar borradores.
- Ve los programas públicos (activos o en pausa) de su nivel y los privados donde fue invitado.
- Ve **su propia reputación** y puede **apelar sus sanciones dentro del plazo**.

### Empresa

- Crea programas, y edita, publica, pausa y elimina **solo los de su empresa**.
- Invita investigadores a sus programas privados.
- Ve y descifra los informes de sus programas **solo cuando moderación ya los decidió**.
- Pasa a reparación y cierra los informes **validados** de sus programas.
- Asigna y paga las recompensas de sus informes, y paga **su propio** plan.

### Moderador

- Ve **solo los programas que modera**.
- Abre **solo el siguiente informe de la fila** y los que ya tiene a su cargo.
- Revisa, valida, rechaza o marca como duplicado **solo** los informes que tiene asignados, de sus programas y que no escribió él.
- No crea, edita ni publica programas.

### Conflicto de interés (nadie puede ser juez y parte)

Estas prohibiciones ganan siempre, incluso frente al administrador:

- **Nadie revisa su propio informe** ni lee sus notas internas de moderación.
- **Nadie resuelve su propia apelación.**
- **Un moderador no resuelve apelaciones.**
- La asignación automática **no elige** como moderador a quien pertenece a una empresa o ya reportó en ese programa.

### Certificados

- El investigador ve los certificados de **sus** informes cerrados.
- La empresa y los moderadores ven los certificados de **sus** programas.
- Cualquiera puede **verificar** un certificado desde la página pública.

## 11. Reputación, sanciones y apelaciones

### Un registro que no se puede reescribir

La reputación funciona como un **libro contable**: cada suma o resta de puntos es un movimiento nuevo y **ningún movimiento se modifica ni se borra**. Si una sanción se anula, no se borra: se añade otro movimiento que devuelve los puntos. Así siempre se puede reconstruir por qué alguien tiene el saldo que tiene.

### Puntos según la severidad

Los puntos se calculan **automáticamente según la severidad CVSS**, sin decisiones personales:

| Severidad | Al confirmarlo la empresa | Al cerrarlo como resuelto |
| --------- | ------------------------- | ------------------------- |
| Crítica   | 100                       | 200                       |
| Alta      | 50                        | 100                       |
| Media     | 25                        | 50                        |
| Baja      | 10                        | 20                        |
| Ninguna   | 5                         | 10                        |

Los puntos **no** se dan cuando el moderador valida, sino cuando la **empresa confirma** el hallazgo. Así nadie puede regalar puntos por su cuenta.

### Rangos

| Rango    | Puntos | Acceso                  |
| -------- | ------ | ----------------------- |
| Bronce   | 0      | Programas de nivel bajo |
| Plata    | 100    | Hasta nivel medio       |
| Oro      | 300    | Todos los niveles       |
| Platino  | 700    | Todos los niveles       |
| Diamante | 1500   | Todos los niveles       |

Los mejores investigadores aparecen en un **salón de la fama** público.

Un investigador es **verificado** si tiene al menos 3 informes confirmados por empresas, no está suspendido y no tiene sanciones en los últimos 30 días.

### Sanciones proporcionales

Si un informe es falso o fabricado, el moderador puede sancionar al rechazarlo:

| Gravedad | Puntos que resta | Suspensión |
| -------- | ---------------- | ---------- |
| Leve     | 25               | No         |
| Media    | 80               | 7 días     |
| Grave    | 250              | 30 días    |

**Reincidencia:** cada sanción vigente aumenta la siguiente un 50 %, hasta el triple. Por ejemplo, una sanción grave con dos sanciones previas vigentes resta 500 puntos.

### Detección de trampas

El sistema analiza tres comportamientos típicos de quien intenta inflar sus números:

- **Ráfaga:** muchos informes enviados en pocos minutos.
- **Duplicados:** más de la mitad de sus informes resultan duplicados.
- **Fabricación:** informes marcados como alta o crítica que se descartan y no traen una prueba de concepto válida.

El análisis evita sancionar dos veces por la misma conducta.

### Apelaciones

- El investigador tiene **7 días** para apelar una sanción, una sola vez, y puede adjuntar fotos como evidencia.
- La resuelve un **administrador**. Si la aprueba, la sanción se anula y los puntos vuelven. Si la rechaza, la suspensión continúa por los días que faltaban.
- **Cada paso de la apelación queda sellado**: quién actuó, con qué rol, cuándo y desde dónde, con una huella SHA-256 **encadenada** al paso anterior. Si alguien modificara o borrara un paso, la cadena dejaría de cuadrar y se detectaría.
- **Protección ante demoras:** si una apelación lleva más de 48 horas sin respuesta, se alerta al administrador; si pasan 5 días, **la suspensión se levanta de forma provisional** para no perjudicar al investigador por la demora del equipo.
- Si un informe sancionado termina siendo **validado**, la sanción se anula sola.

## 12. Automatizaciones

### Tareas que se ejecutan solas cada cierto tiempo

| Tarea                          | Cada cuánto | Qué hace                                                                                                       |
| ------------------------------ | ----------- | -------------------------------------------------------------------------------------------------------------- |
| Verificar pagos de recompensas | Cada minuto | Comprueba en la blockchain si el pago llegó; si la transacción no aparece en 30 minutos, lo marca como fallido |
| Verificar pagos de planes      | Cada minuto | Comprueba los pagos del Plan Profesional y lo activa                                                           |
| Pausar programas vencidos      | Cada hora   | Pausa los programas cuya fecha terminó, avisa a la empresa y libera a sus moderadores                          |
| Vigilar apelaciones            | Cada hora   | Alerta de apelaciones demoradas y levanta suspensiones de forma provisional                                    |
| Revisar vencimiento de planes  | Cada hora   | Avisa de planes por vencer y pasa los vencidos al plan gratuito                                                |

### Automatizaciones que responden a una acción

| Automatización                | Qué hace                                                                                                                                                                                                                                   |
| ----------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| **Asignación de moderadores** | Asigna dos moderadores a cada programa publicado, eligiendo a los que tienen menos carga (máximo cinco programas cada uno) y descartando a suspendidos y a quien tenga conflicto de interés. Si no hay nadie libre, avisa al administrador |
| **Límite de envíos**          | Frena en el momento a quien envía más de 5 informes en 15 minutos, más de 3 al mismo programa en una hora o el mismo título dos veces en un día, y le dice cuánto esperar                                                                  |
| **Sugerencia de duplicados**  | Compara cada informe con los anteriores del programa y sugiere al moderador los más parecidos                                                                                                                                              |
| **Orden de llegada**          | Impide que un informe se adelante a otro anterior del mismo programa                                                                                                                                                                       |
| **Línea de tiempo**           | Registra cada acción sobre el informe                                                                                                                                                                                                      |
| **Notificaciones**            | Avisa a cada parte de lo que le afecta: nuevos informes, cambios de estado, sanciones, apelaciones, pagos, invitaciones y cambios de rango                                                                                                 |
| **Anulación de sanciones**    | Si un informe sancionado se valida, la sanción se anula y los puntos vuelven                                                                                                                                                               |
| **Puntos y rangos**           | Suma los puntos según la severidad y avisa al subir de rango                                                                                                                                                                               |
| **Certificados**              | Emite y firma el certificado al cerrar un informe                                                                                                                                                                                          |
| **Claves de cifrado**         | Crea las claves la primera vez que se necesitan                                                                                                                                                                                            |
| **Limpieza de fotos**         | Elimina ubicación y metadatos y cifra cada imagen                                                                                                                                                                                          |
| **Auditoría**                 | Registra cada acción sensible                                                                                                                                                                                                              |

## 13. Recompensas y planes en USDC

Los pagos se hacen en **USDC** (una criptomoneda estable ligada al dólar) sobre la red Polygon. **La plataforma nunca guarda dinero ni claves de monederos**: la empresa firma el pago en su propio monedero y el servidor solo verifica en la blockchain que llegó.

- **Recompensas:** la empresa fija el monto dentro del rango del programa y paga directo al monedero del investigador. El pago solo se da por bueno si es de USDC oficial, llega al monedero correcto, cubre el monto y tiene las confirmaciones necesarias. Una misma transacción no puede usarse para pagar dos informes. El moderador nunca ve el monedero del investigador, para no romper la revisión a ciegas.
- **Plan Profesional:** la empresa paga una suscripción (5 USDC cada 30 días por defecto) que le permite crear programas privados, de élite y solo para investigadores verificados. Al vencer vuelve al plan gratuito sin perder sus programas.

## 14. Auditoría y trazabilidad

Cada acción sensible queda registrada con **quién, qué, cuándo y desde dónde**: creación y envío de informes, cambios de estado, cada lectura de contenido cifrado (y cada intento fallido), sanciones, apelaciones, pagos, creación de claves y cambios de configuración. El administrador puede consultarlo todo desde su panel.

## 15. Instalación y prueba

### Requisitos

- PHP 8.4 con la extensión GD, Composer, Node 22.
- PostgreSQL (Supabase) o SQLite para pruebas locales.
- GnuPG (Gpg4win en Windows) para el cifrado real. Sin él, en desarrollo se usa el modo de simulación.

### Puesta en marcha

```bash
composer install
cp .env.example .env
php artisan key:generate
# Definir en .env el secreto PGP_STORAGE_KEY y la base de datos
php artisan migrate --seed
npm install
npm run build
php artisan serve
php artisan schedule:work   # tareas automáticas, en otra terminal
```

### Usuarios de prueba

| Rol                             | Email                          | Contraseña     |
| ------------------------------- | ------------------------------ | -------------- |
| Administrador                   | `admin@bugbounty.local`        | `admin`        |
| Moderador                       | `moderador@bugbounty.local`    | `moderador`    |
| Investigador                    | `investigador@bugbounty.local` | `investigador` |
| Empresa                         | `empresa@bugbounty.local`      | `empresa`      |
| Empresa pendiente de aprobación | `pendiente@bugbounty.local`    | `pendiente`    |

También hay investigadores de cada rango (`plata@`, `oro@`, `platino@` y `diamante@bugbounty.local`) y casos especiales (`suspendido@`, `sancionado@` y `desactivado@bugbounty.local`), todos con la contraseña `investigador`.

### Qué probar

- **Investigador:** entrar a un programa, reportar un fallo con el asistente y seguir su estado y su reputación.
- **Moderador:** tomar el siguiente informe de la fila y validarlo, rechazarlo o marcarlo como duplicado. El autor aparece como anónimo.
- **Empresa:** crear un programa con su propio formulario de prueba de concepto, publicarlo, y confirmar y cerrar los informes validados.
- **Administrador:** revisar usuarios, empresas, sanciones, apelaciones, auditoría y el simulador de permisos.
- **Casos límite:** entrar como usuario suspendido (solo puede ver su reputación y apelar) o intentar que una empresa reporte en su propio programa (el sistema lo impide).

### Verificación

```bash
composer test          # pruebas y análisis del backend
npm run check          # revisión del frontend
npm run types:check    # tipos del frontend
php artisan pgp:check  # comprueba que el cifrado funciona de punta a punta
```
