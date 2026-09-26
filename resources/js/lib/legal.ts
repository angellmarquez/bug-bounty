/** Sección de un documento legal (Términos, Privacidad, Divulgación). */
export type Seccion = {
    id: string;
    titulo: string;
    parrafos?: string[];
    lista?: string[];
};

/** Datos del operador que llegan del servidor (config/legal.php). */
export type DatosLegales = {
    version: string;
    operador: string;
    jurisdiccion: string;
    contacto: string;
};
