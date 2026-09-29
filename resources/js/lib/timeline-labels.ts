import { TipoEventoReporte } from '@/types/enums';

const tipoEventoLabelMap: Record<TipoEventoReporte, string> = {
    creado: 'Reporte creado',
    enviado: 'Enviado para revisión',
    cambio_estado: 'Cambio de estado',
    comentario: 'Comentario',
    marcado_duplicado: 'Marcado como duplicado',
    sancion: 'Sanción aplicada',
    asignacion: 'Asignado a analista',
    bounty: 'Recompensa',
    cvss_ajustado: 'CVSS ajustado por moderación',
};

export function tipoEventoLabel(tipo: TipoEventoReporte): string {
    return tipoEventoLabelMap[tipo] ?? tipo;
}
