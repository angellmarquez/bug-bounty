import Award from '@lucide/svelte/icons/award';
import Bell from '@lucide/svelte/icons/bell';
import Building2 from '@lucide/svelte/icons/building-2';
import Bug from '@lucide/svelte/icons/bug';
import ClipboardCheck from '@lucide/svelte/icons/clipboard-check';
import Mail from '@lucide/svelte/icons/mail';
import MessageSquare from '@lucide/svelte/icons/message-square';
import ShieldAlert from '@lucide/svelte/icons/shield-alert';
import type { Component } from 'svelte';

/** Ícono y color de cada tipo de aviso. */
const TIPOS: Record<string, { icono: Component; clase: string }> = {
    informe: { icono: Bug as unknown as Component, clase: 'text-sky-600 bg-sky-500/10' },
    sancion: { icono: ShieldAlert as unknown as Component, clase: 'text-red-600 bg-red-500/10' },
    apelacion: { icono: MessageSquare as unknown as Component, clase: 'text-amber-600 bg-amber-500/10' },
    empresa: { icono: Building2 as unknown as Component, clase: 'text-violet-600 bg-violet-500/10' },
    moderacion: { icono: ClipboardCheck as unknown as Component, clase: 'text-emerald-600 bg-emerald-500/10' },
    reputacion: { icono: Award as unknown as Component, clase: 'text-yellow-600 bg-yellow-500/10' },
    invitacion: { icono: Mail as unknown as Component, clase: 'text-indigo-600 bg-indigo-500/10' },
};

export function estiloDeAviso(tipo: string): { icono: Component; clase: string } {
    return (
        TIPOS[tipo] ?? {
            icono: Bell as unknown as Component,
            clase: 'text-muted-foreground bg-muted',
        }
    );
}

/** «hace 5 min», «hace 3 h», «hace 2 días»… */
export function haceTiempo(iso: string | null): string {
    if (!iso) return '';

    const segundos = Math.max(0, Math.floor((Date.now() - new Date(iso).getTime()) / 1000));

    if (segundos < 60) return 'ahora mismo';
    const minutos = Math.floor(segundos / 60);
    if (minutos < 60) return `hace ${minutos} min`;
    const horas = Math.floor(minutos / 60);
    if (horas < 24) return `hace ${horas} h`;
    const dias = Math.floor(horas / 24);
    if (dias < 30) return `hace ${dias} ${dias === 1 ? 'día' : 'días'}`;

    return new Intl.DateTimeFormat('es-ES', { day: '2-digit', month: 'short', year: 'numeric' }).format(new Date(iso));
}
