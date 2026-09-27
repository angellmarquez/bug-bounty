import type { RedBounty } from '@/types/domain';

/**
 * Pago de un bounty en USDC desde la wallet del navegador (MetaMask u otra compatible con
 * EIP-1193). La plataforma solo arma la transferencia: la firma la empresa y el dinero va
 * directo de su wallet a la del investigador. Después el servidor la verifica on-chain.
 */

type ProveedorEip1193 = {
    request: (args: { method: string; params?: unknown[] }) => Promise<unknown>;
};

type ErrorWallet = { code?: number; message?: string };

// Selectores ERC-20: transfer(address,uint256) y balanceOf(address).
const SELECTOR_TRANSFER = '0xa9059cbb';
const SELECTOR_BALANCE_OF = '0x70a08231';

export class ErrorPagoWallet extends Error {}

export function proveedor(): ProveedorEip1193 | null {
    const eth = (window as unknown as { ethereum?: ProveedorEip1193 }).ethereum;

    return eth && typeof eth.request === 'function' ? eth : null;
}

function palabra(hexSinPrefijo: string): string {
    return hexSinPrefijo.toLowerCase().padStart(64, '0');
}

/** Datos de la llamada `transfer(destino, unidades)` del contrato USDC. */
export function codificarTransfer(destino: string, unidades: string): string {
    if (!/^0x[0-9a-fA-F]{40}$/.test(destino)) {
        throw new ErrorPagoWallet(
            'La wallet del investigador no es una dirección válida.',
        );
    }

    return (
        SELECTOR_TRANSFER +
        palabra(destino.slice(2)) +
        palabra(BigInt(unidades).toString(16))
    );
}

function mensajeDe(error: unknown): string {
    const e = error as ErrorWallet;

    if (e?.code === 4001) return 'Cancelaste la operación en la wallet.';
    if (e?.code === -32002)
        return 'La wallet ya tiene una solicitud abierta: revisa la extensión.';

    return e?.message ?? 'La wallet devolvió un error desconocido.';
}

async function llamar<T>(
    eth: ProveedorEip1193,
    method: string,
    params: unknown[] = [],
): Promise<T> {
    try {
        return (await eth.request({ method, params })) as T;
    } catch (error) {
        throw new ErrorPagoWallet(mensajeDe(error));
    }
}

export async function conectar(eth: ProveedorEip1193): Promise<string> {
    const cuentas = await llamar<string[]>(eth, 'eth_requestAccounts');

    if (!cuentas?.[0])
        throw new ErrorPagoWallet('La wallet no compartió ninguna cuenta.');

    return cuentas[0];
}

/** Cambia la wallet a la red de pagos; si no la conoce, la añade (Amoy suele faltar). */
export async function asegurarRed(
    eth: ProveedorEip1193,
    red: RedBounty,
): Promise<void> {
    const chainId = '0x' + red.chain_id.toString(16);
    const actual = await llamar<string>(eth, 'eth_chainId');

    if (actual?.toLowerCase() === chainId) return;

    try {
        await eth.request({
            method: 'wallet_switchEthereumChain',
            params: [{ chainId }],
        });
    } catch (error) {
        if ((error as ErrorWallet)?.code !== 4902)
            throw new ErrorPagoWallet(mensajeDe(error));

        await llamar(eth, 'wallet_addEthereumChain', [
            {
                chainId,
                chainName: red.nombre,
                nativeCurrency: red.moneda_nativa,
                rpcUrls: [red.rpc_url],
                blockExplorerUrls: [red.explorer_url],
            },
        ]);
    }
    const final = await llamar<string>(eth, 'eth_chainId');
    if (final?.toLowerCase() !== chainId) {
        throw new ErrorPagoWallet(
            `La wallet sigue en otra red. Cámbiala a ${red.nombre} en MetaMask y vuelve a intentarlo.`,
        );
    }
}

export async function saldoUsdc(
    eth: ProveedorEip1193,
    red: RedBounty,
    cuenta: string,
): Promise<bigint> {
    const resultado = await llamar<string>(eth, 'eth_call', [
        { to: red.usdc, data: SELECTOR_BALANCE_OF + palabra(cuenta.slice(2)) },
        'latest',
    ]);

    return BigInt(resultado && resultado !== '0x' ? resultado : '0x0');
}

/** Pide a la wallet que firme y envíe la transferencia. Devuelve el hash de la transacción. */
export async function transferirUsdc(
    eth: ProveedorEip1193,
    red: RedBounty,
    cuenta: string,
    destino: string,
    unidades: string,
): Promise<string> {
    const comision = await comisionSugerida(eth, red);

    return llamar<string>(eth, 'eth_sendTransaction', [
        {
            from: cuenta,
            to: red.usdc,
            data: codificarTransfer(destino, unidades),
            value: '0x0',
            ...comision,
        },
    ]);
}

const GWEI = 1_000_000_000n;

/**
 * Comisión EIP-1559 que acepta la red. Polygon exige una propina mínima (25 gwei) y MetaMask,
 * con un RPC que no la conoce, propone 1,5 gwei y la transacción se rechaza. Se toma la mayor
 * entre la que sugiere el nodo y la mínima de la red, y el tope cubre que la base se duplique.
 */
async function comisionSugerida(
    eth: ProveedorEip1193,
    red: RedBounty,
): Promise<
    | { maxPriorityFeePerGas: string; maxFeePerGas: string }
    | Record<string, never>
> {
    const minima = BigInt(red.propina_minima_gwei ?? 0) * GWEI;
    if (minima === 0n) return {};

    let propina = minima;
    try {
        const sugerida = BigInt(
            await llamar<string>(eth, 'eth_maxPriorityFeePerGas'),
        );
        if (sugerida > propina) propina = sugerida;
    } catch {
        // Algunos nodos no lo implementan: basta con la mínima de la red.
    }

    let base = 0n;
    try {
        const bloque = await llamar<{ baseFeePerGas?: string } | null>(
            eth,
            'eth_getBlockByNumber',
            ['latest', false],
        );
        base = BigInt(bloque?.baseFeePerGas ?? '0x0');
    } catch {
        base = 0n;
    }

    return {
        maxPriorityFeePerGas: '0x' + propina.toString(16),
        maxFeePerGas: '0x' + (base * 2n + propina).toString(16),
    };
}

/** 0x3532…8f1D */
export function direccionCorta(direccion: string): string {
    return `${direccion.slice(0, 6)}…${direccion.slice(-4)}`;
}

export function formatearUsdc(unidades: bigint): string {
    const entero = unidades / 1_000_000n;
    const decimales = (unidades % 1_000_000n)
        .toString()
        .padStart(6, '0')
        .slice(0, 2);

    return `${entero}.${decimales}`;
}
