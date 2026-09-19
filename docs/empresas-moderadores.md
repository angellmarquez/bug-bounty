# Empresas y moderadores

## Accesos locales

Después de ejecutar `php artisan db:seed`, las cuentas demo son:

| Cuenta            | Correo                      | Contraseña  | Rol             |
| ----------------- | --------------------------- | ----------- | --------------- |
| Administrador     | `admin@bugbounty.local`     | `admin`     | `administrador` |
| Moderador         | `moderador@bugbounty.local` | `moderador` | `moderador`     |
| Empresa aprobada  | `empresa@bugbounty.local`   | `empresa`   | `empresa`       |
| Empresa pendiente | `pendiente@bugbounty.local` | `pendiente` | `empresa`       |

Estas credenciales son únicamente para desarrollo local. Deben cambiarse o eliminarse antes de producción.

## Rutas principales

- `/empresa/login`: acceso empresarial.
- `/empresa/registro`: solicitud de alta empresarial.
- `/empresa`: estado, programas y miembros de la empresa.
- `/admin/empresas`: aprobación, rechazo, suspensión y reactivación.
- `/admin/moderadores`: asignación de moderadores y alcance por programa.
- `/admin/usuarios`: roles globales.

## Flujo de empresa

1. La empresa se registra y queda en estado `pendiente`.
2. El administrador revisa la solicitud en `/admin/empresas`.
3. Al aprobarla, el propietario puede crear programas y gestionar miembros.
4. Los miembros aceptan invitaciones desde el enlace recibido.
5. Una empresa suspendida conserva sus datos, pero pierde acceso operativo hasta ser reactivada.

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
