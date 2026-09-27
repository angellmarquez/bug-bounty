# Plan Profesional (pago en USDC)

Las empresas contratan el **Plan Profesional** pagando en **USDC** desde **su propia wallet**,
directamente a la **wallet de tesorería del proyecto**. La plataforma verifica el pago en la
blockchain con el mismo verificador que los bounties (ver `docs/bounties.md`) y activa el plan.

**Qué desbloquea:**

- programas **privados**, solo para investigadores invitados;
- programas de **élite**, que exigen rango Plata u Oro;
- programas **solo para investigadores verificados** (filtro anti-spam).

## Cómo funciona

1. La empresa entra en **Mi plan** y pulsa **«Pagar N USDC con mi wallet»**. MetaMask se cambia
   a la red de pagos y la empresa firma la transferencia a la tesorería. Si pagó desde otra
   wallet o desde un exchange, puede registrar el hash a mano.
2. El servidor lee el recibo y solo confirma si hay un `Transfer` del **USDC oficial** hacia la
   **tesorería**, por **al menos el precio**, con las confirmaciones exigidas.
3. Al confirmarse, el plan queda activo **N días**. Si la empresa renueva **antes de vencer**, los
   días **se suman** al vencimiento actual.
4. **Unos días antes de vencer** se avisa a la empresa una sola vez. **Al vencer**, vuelve al Plan
   Comunitario: sus programas **siguen funcionando**, pero no puede crear nuevos privados ni
   subir su nivel de exigencia hasta renovar.

**Garantías:**

- Un hash no se puede usar dos veces, ni para otro plan ni para un bounty.
- Solo puede haber un pago en verificación a la vez.
- Si la red no responde, el pago **nunca** se confirma.
- Todo queda en auditoría.

Tareas programadas (`php artisan schedule:work` en local, cron en el servidor):

| Comando                              | Frecuencia  | Qué hace                                              |
| ------------------------------------ | ----------- | ----------------------------------------------------- |
| `suscripciones:verificar-pendientes` | cada minuto | verifica los pagos en curso                           |
| `suscripciones:revisar-vencimientos` | cada hora   | avisa de los planes por vencer y degrada los vencidos |

## Configuración (panel del admin)

En **Administración → Config. Plan** se definen:

- la **wallet de tesorería**: solo su **dirección pública**;
- el **precio** en USDC, **5 por defecto**;
- la **duración** en días, **30 por defecto**.

Cambiar la tesorería pide la **contraseña del admin**, queda en **auditoría** con la dirección
anterior y avisa a **todos los administradores**. En **Ingresos** se ven los pagos recibidos,
los totales y el enlace a la tesorería en el explorador.

Si no hay tesorería configurada en el panel, se usa `PLAN_TESORERIA_WALLET` del `.env`. Si
tampoco existe, el pago del plan aparece como no disponible.

## Cómo proteger la tesorería

La plataforma **nunca** tiene la clave privada de la tesorería: solo su dirección. Aunque
alguien comprometiera el servidor, no podría mover esos fondos. Para guardarlos:

- **Wallet física** (Ledger, Trezor): la clave nunca sale del dispositivo.
- **Multifirma Safe** (safe.global), recomendada para un equipo: por ejemplo, 2 de 3 personas
  deben firmar para mover los fondos. Funciona en Polygon.
- **Nunca** pongas la frase de recuperación ni la clave privada en el servidor, en el `.env`,
  en GitHub ni en un chat.

**Para pruebas en testnet** basta una cuenta de MetaMask aparte, por ejemplo «Proyecto».
Pega su dirección en _Config. Plan_.

## Investigadores verificados

Un investigador es **verificado** si cumple todo esto:

- tiene al menos **3 informes confirmados por la empresa** (en reparación o cerrados). Uno
  validado solo por moderación no cuenta;
- no tiene una **suspensión** en curso;
- no tiene **sanciones vigentes de los últimos 30 días** (aplicadas o en apelación, no revocadas).

Las empresas con Plan Profesional pueden limitar un programa a investigadores verificados.
