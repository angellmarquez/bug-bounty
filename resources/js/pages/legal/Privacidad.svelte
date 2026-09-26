<script lang="ts">
    import DocumentoLegal from '@/components/publico/DocumentoLegal.svelte';
    import type { DatosLegales, Seccion } from '@/lib/legal';

    let { legal }: { legal: DatosLegales } = $props();

    const secciones = $derived<Seccion[]>([
        {
            id: 'responsable',
            titulo: 'Responsable del tratamiento',
            parrafos: [`${legal.operador} es responsable de los datos personales tratados en Huella. Puedes contactar con nosotros en ${legal.contacto}.`],
        },
        {
            id: 'datos',
            titulo: 'Qué datos tratamos',
            lista: [
                'Datos de cuenta: nombre, correo electrónico, contraseña (guardada con hash, nunca en claro) y, si la activas, la configuración de verificación en dos pasos.',
                'Datos de empresa: razón social, identificador fiscal, datos de contacto y responsable.',
                'Contenido que envías: informes, pruebas de concepto, fotos de evidencia, comentarios y apelaciones. Se guardan cifrados con PGP.',
                'Actividad en la plataforma: reputación, sanciones y registros de auditoría (acción, fecha, dirección IP y navegador).',
                'Preferencias: el tema visual que eliges.',
            ],
        },
        {
            id: 'finalidades',
            titulo: 'Para qué los usamos',
            lista: [
                'Prestar el servicio: gestionar tu cuenta, tus informes y la comunicación con las empresas.',
                'Seguridad: prevenir abusos, detectar informes fraudulentos y auditar quién accede a información confidencial.',
                'Cumplir obligaciones legales y atender tus solicitudes.',
            ],
            parrafos: ['La base legal es la ejecución de los Términos que aceptas, nuestro interés legítimo en mantener la plataforma segura y, cuando corresponda, el cumplimiento de obligaciones legales.'],
        },
        {
            id: 'quien',
            titulo: 'Quién puede ver tu información',
            lista: [
                'La moderación asignada ve el contenido de tus informes, pero no tu identidad (triaje ciego). Las fotos se le muestran sin su nombre original.',
                'La empresa destinataria solo ve los informes una vez validados.',
                'El administrador de la plataforma accede a lo imprescindible para resolver apelaciones y mantener el servicio.',
                'El Salón de la Fama muestra públicamente tu nombre, rango y puntos si figuras entre los investigadores destacados.',
                'No vendemos tus datos ni los compartimos con terceros con fines publicitarios.',
            ],
        },
        {
            id: 'seguridad',
            titulo: 'Cómo los protegemos',
            parrafos: [
                'El contenido sensible se cifra con PGP antes de guardarse; las fotos se limpian de metadatos (ubicación GPS, modelo del dispositivo) y se cifran. Los permisos se evalúan con reglas de acceso por atributos y cada descifrado queda registrado.',
            ],
        },
        {
            id: 'conservacion',
            titulo: 'Cuánto tiempo los conservamos',
            parrafos: [
                'Mientras tu cuenta esté activa. Si la eliminas, borramos tus datos personales salvo aquellos que la ley nos obligue a conservar y los registros de auditoría necesarios para demostrar la integridad de sanciones y apelaciones, que se conservarán el tiempo mínimo exigido.',
            ],
        },
        {
            id: 'derechos',
            titulo: 'Tus derechos',
            parrafos: [`Puedes acceder a tus datos, rectificarlos, suprimirlos, oponerte a su tratamiento, limitarlo y solicitar su portabilidad escribiendo a ${legal.contacto}. También puedes reclamar ante la autoridad de protección de datos competente.`],
        },
        {
            id: 'cookies',
            titulo: 'Cookies',
            parrafos: ['Usamos solo cookies técnicas necesarias: la sesión, la protección contra falsificación de peticiones (CSRF), el tema visual elegido y el estado del menú lateral. No usamos cookies publicitarias ni de seguimiento de terceros.'],
        },
        {
            id: 'cambios',
            titulo: 'Cambios en esta política',
            parrafos: ['Publicaremos cualquier cambio con su fecha de versión y te lo avisaremos en la plataforma.'],
        },
    ]);
</script>

<DocumentoLegal
    titulo="Política de Privacidad"
    resumen="Qué datos personales tratamos, para qué, quién puede verlos y cómo los protegemos. Sin letra pequeña."
    version={legal.version}
    {secciones}
/>
