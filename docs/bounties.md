# Recompensas (bounties) en USDC

Las empresas pagan las recompensas en **USDC** desde **su propia wallet**, directo a la wallet del
investigador. La plataforma **nunca custodia fondos ni claves**: arma la transferencia, la empresa
la firma en su wallet (MetaMask) y después la plataforma **verifica el pago en la blockchain**.

## Cómo funciona

```
sin_bounty ──asignar──▶ asignado ──pagar──▶ verificando ──▶ pagado
                           ▲                    │
                           └──── fallido ◀──────┘   (se registra otra transacción)
```

1. **Asignar.** La empresa dueña del programa fija el monto en un informe validado, en reparación o
   cerrado. Debe estar dentro del rango del programa (`recompensa_min` / `recompensa_max`). El
   investigador recibe un aviso.
2. **Pagar.** Desde el informe, la empresa pulsa **«Pagar con mi wallet»**:
    - la plataforma conecta MetaMask, la cambia a la red de pagos (la añade si no la conoce) y
      revisa que haya saldo de USDC;
    - arma `transfer(wallet del investigador, monto)` sobre el contrato USDC oficial;
    - la empresa firma, y el hash de la transacción se registra solo.

    Si pagó desde otra wallet o desde un exchange, puede registrar el hash a mano.

3. **Verificar.** El servidor lee el recibo por JSON-RPC y solo da el pago por bueno si:
    - la transacción no falló ni fue revertida;
    - hay un evento `Transfer` **del contrato USDC oficial** de la red;
    - el destino es **la wallet del investigador**, fijada al registrar el pago (si después la
      cambia, no altera la verificación);
    - el monto es **al menos** el asignado (USDC tiene 6 decimales);
    - la transacción tiene las **confirmaciones** exigidas (3 en Amoy, 30 en Polygon).

    Mientras falten confirmaciones queda en _verificando_. La página vuelve a consultar sola cada
    6 s y el comando `bounties:verificar-pendientes` lo hace cada minuto. Si la transacción no
    aparece en 30 minutos, pasa a _fallido_.

**Garantías:**

- Si el proveedor RPC falla o no responde, el pago **nunca** se confirma: queda en _verificando_.
- Un hash no puede pagar dos informes.
- Un bounty pagado ya no se puede sobrescribir ni cambiar de monto.
- La wallet solo la ven quien paga y el propio investigador, **nunca el moderador**, porque
  rompería el triaje ciego.
- Todo queda en la línea de tiempo del informe (evento «Recompensa») y en auditoría.

## Redes

| `BOUNTY_RED`                 | Red          | chain_id | USDC                                         | Uso                                                         |
| ---------------------------- | ------------ | -------- | -------------------------------------------- | ----------------------------------------------------------- |
| `polygon_amoy` (por defecto) | Polygon Amoy | 80002    | `0x41E94Eb019C0762f9Bfcf9Fb1E58725BfB0e7582` | Pruebas: el USDC no tiene valor                             |
| `polygon`                    | Polygon PoS  | 137      | `0x3c499c542cEF5E3811e1192ce70d8cC03d5c3359` | Dinero real: bloqueada salvo `BOUNTY_PERMITIR_MAINNET=true` |

## Probarlo en local (Amoy)

### 1. Certificados HTTPS en PHP (WAMP)

El PHP de WAMP no trae los certificados raíz y el servidor no puede consultar la blockchain.
Aparece en `storage/logs/laravel.log` como `cURL error 60: SSL certificate problem`, y el pago se
queda en _verificando_. Para arreglarlo:

1. Descarga `cacert.pem` desde <https://curl.se/ca/cacert.pem>, por ejemplo en `C:\wamp64\cacert.pem`.
2. En el `php.ini` del PHP que usas (`php --ini` lo muestra), pon:
    ```ini
    curl.cainfo = "C:\wamp64\cacert.pem"
    openssl.cafile = "C:\wamp64\cacert.pem"
    ```
3. Reinicia WAMP y la terminal.

### 2. Base de datos

La rama añade migraciones. **`php artisan migrate` se aplica a la base del `.env`**: si apunta a
Supabase, las columnas se crean allí. Para probar sin tocar Supabase, cambia temporalmente el
`.env` a `DB_CONNECTION=sqlite` y ejecuta `php artisan migrate:fresh --seed`.

### 3. Wallets de prueba (MetaMask)

Necesitas **dos cuentas** de MetaMask: una para la empresa (paga) y otra para el investigador (cobra).

1. Con la cuenta de la **empresa**, consigue:
    - **POL** para el gas: <https://faucet.polygon.technology> (red _Amoy_);
    - **USDC de prueba**: <https://faucet.circle.com> (red _Polygon PoS Amoy_).
2. Copia la dirección de la cuenta del **investigador**.

No hace falta añadir Amoy a MetaMask a mano: la plataforma lo hace al pagar.

### 4. Recorrido

1. **Admin** (_Administración → Empresas_): activa el **Plan Profesional** de la empresa si quieres
   probar programas privados o de élite. Para las recompensas no hace falta.
2. **Empresa**: edita un programa y activa **Recompensas** (con rango y tabla opcionales).
3. **Investigador** (_Configuración → Perfil_): pega la dirección de su wallet y guarda.
4. **Empresa**: abre un informe **validado** de ese programa. En la tarjeta **Recompensa**:
    - **Asignar recompensa**, por ejemplo 1 USDC;
    - **Pagar 1.00 USDC con mi wallet** → MetaMask pide cambiar a Amoy y confirmar la transferencia.
5. La tarjeta pasa a **Verificando** y, tras unos segundos, a **Pagado**, con el enlace a
   Polygonscan. El investigador recibe el aviso «Recibiste el pago de tu recompensa».

**Para probar los rechazos**, registra a mano (_«¿Pagaste desde otra wallet…?»_):

- el hash de una transferencia de **menos** USDC que el bounty → «Monto insuficiente»;
- el hash de una transferencia **a otra wallet** → «no transfiere USDC … a la wallet del investigador»;
- el mismo hash en **otro informe** → «Esta transacción ya se usó para pagar otro informe».

Para verificar pagos en segundo plano, deja corriendo `php artisan schedule:work`. `composer dev`
no lo arranca.

## Antes de usar dinero real (mainnet)

Esta funcionalidad está lista para **testnet**. Para producción con dinero real, falta:

- [ ] **Legal**: términos de servicio que dejen claro que la plataforma no custodia fondos, y
      consulta con un abogado del país donde opere la plataforma.
- [ ] **Listas de sanciones**: revisar las wallets de los investigadores antes de pagar.
- [ ] **Impuestos**: informar a los investigadores de que declaran lo que cobran.
- [ ] **RPC propio** (Alchemy o Infura) en `BOUNTY_RPC_URL_POLYGON`: el público puede saturarse.
- [ ] Probar el flujo completo en Amoy, cambiar a `BOUNTY_RED=polygon` y activar
      `BOUNTY_PERMITIR_MAINNET=true`.
- [ ] Mantener el scheduler (`schedule:run` en cron) activo en el servidor.
