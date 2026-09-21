# Empresas y moderadores

## Accesos locales

Después de ejecutar `php artisan db:seed`, las cuentas demo son:

| Cuenta            | Correo                         | Contraseña     | Rol             |
| ----------------- | ------------------------------ | -------------- | --------------- |
| Administrador     | `admin@bugbounty.local`        | `admin`        | `administrador` |
| Moderador         | `moderador@bugbounty.local`    | `moderador`    | `moderador`     |
| Empresa aprobada  | `empresa@bugbounty.local`      | `empresa`      | `empresa`       |
| Empresa pendiente | `pendiente@bugbounty.local`    | `pendiente`    | `empresa`       |
| Investigador demo | `investigador@bugbounty.local` | `investigador` | `investigador`  |

Estas credenciales son únicamente para desarrollo local. Deben cambiarse o eliminarse antes de producción.

## Rutas principales

- `/empresa/login`: acceso empresarial.
- `/empresa/registro`: solicitud de alta empresarial.
- `/empresa`: estado, programas, miembros y reportes recibidos de la empresa.
- `/admin/empresas`: aprobación, rechazo, suspensión y reactivación.
- `/admin/moderadores`: asignación de moderadores y alcance por programa.
- `/admin/usuarios`: roles globales.

## Flujo de empresa

1. La empresa se registra y queda en estado `pendiente`.
2. El administrador revisa la solicitud en `/admin/empresas`.
3. Al aprobarla, el propietario puede crear programas y gestionar miembros.
4. Los miembros aceptan invitaciones desde el enlace recibido.
5. Una empresa suspendida conserva sus datos, pero pierde acceso operativo hasta ser reactivada.

El panel empresarial solo muestra reportes no borrador asociados a los programas propios.
El detalle permite consultar la prueba de concepto enviada por el investigador, pero no
expone las notas internas de moderación ni reportes de otras empresas.

Al crear o editar un programa, la empresa puede marcarlo como público y elegir su
**nivel de acceso** (bajo, medio o alto). Cada nivel exige un rango de reputación
(por defecto bajo = Bronce, medio = Plata, alto = Oro; ver "Reputación y rangos").
Los investigadores solo ven los programas públicos activos de los niveles a los que su
rango les da acceso. La misma regla se aplica al crear un reporte, incluso si se intenta
abrir el programa mediante una URL.

## Sin pagos: la recompensa es la reputación

La plataforma no gestiona dinero (es software libre y las empresas no pagan a través de ella).
El ciclo de un informe es:

`enviado → en revisión → validado → en reparación → cerrado (resuelto)`

- El moderador decide si es válido, duplicado o no válido; al validar, el investigador suma puntos.
- La empresa marca el informe **en reparación** y lo **cierra como resuelto** cuando la
  vulnerabilidad queda corregida; al cerrarlo, el investigador suma más puntos.
- Los puntos de cada evento se configuran en `config/reputacion.php` (`puntos.reporte_validado`,
  `puntos.reporte_resuelto`).

## Reputación y rangos

El saldo de reputación se traduce en un rango, como en un programa de niveles:

| Rango    | Desde |
| -------- | ----- |
| Bronce   | 0 pts |
| Plata    | 100   |
| Oro      | 300   |
| Platino  | 700   |
| Diamante | 1500  |

Los umbrales y la equivalencia nivel → rango están en `config/reputacion.php`
(`rangos` y `acceso`). El rango se calcula a partir del ledger, nunca se guarda; una sanción
puede bajar de rango. El panel "Mi estado" (Dashboard) y el menú de usuario muestran los roles,
el rango, la suspensión vigente, los programas que modera y el estado de la empresa.

## Flujo de moderación

El rol `moderador` revisa **solo los programas que un administrador le asigna** (`/admin/moderadores`);
nunca ve borradores. Desde el detalle de un informe puede iniciar la revisión, validarlo, rechazarlo o marcarlo como duplicado.

Un moderador puede ser también investigador (conserva ambas etiquetas), pero por conflicto de interés:

- no puede enviar informes a los programas que modera (vería las vulnerabilidades de los demás);
- no se le puede asignar un programa en el que ya presentó informes;
- nadie revisa, valida ni cierra su propio informe (tampoco un administrador).

Al rechazar, la opción **Marcar como reporte falso y aplicar sanción** permite seleccionar gravedad `leve`, `media` o `grave`. La sanción:

- descuenta puntos mediante el ledger de reputación;
- puede suspender al investigador según la gravedad: mientras dure la suspensión no puede enviar informes nuevos (sí puede entrar, ver su reputación y apelar);
- crea un evento en la línea de tiempo;
- queda registrada en auditoría;
- puede ser apelada por el investigador.

Un rechazo normal, por ejemplo por estar fuera de alcance, no crea una sanción.

## Migraciones y pruebas

```powershell
php artisan migrate
php artisan db:seed
php artisan test --filter=Empresas
npm run types:check
```
