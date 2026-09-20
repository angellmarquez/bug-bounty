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

Al crear o editar un programa, la empresa puede marcarlo como público y definir una
reputación mínima. Los investigadores solo verán programas públicos activos cuando
su puntuación de reputación sea igual o superior a ese mínimo. La misma regla se
aplica al crear un reporte, incluso si se intenta acceder directamente mediante
una URL.

## Flujo de moderación

El rol `moderador` puede revisar todos los reportes que no estén en borrador. Desde el detalle de un reporte puede validar o rechazarlo.

Al rechazar, la opción **Marcar como reporte falso y aplicar sanción** permite seleccionar gravedad `leve`, `media` o `grave`. La sanción:

- descuenta puntos mediante el ledger de reputación;
- puede suspender al investigador según la gravedad;
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
