<script lang="ts">
    import DocumentoLegal from '@/components/publico/DocumentoLegal.svelte';
    import type { DatosLegales, Seccion } from '@/lib/legal';

    let { legal }: { legal: DatosLegales } = $props();

    const secciones = $derived<Seccion[]>([
        {
            id: 'objeto',
            titulo: 'Objeto y aceptación',
            parrafos: [
                `Estos Términos regulan el uso de Huella, una plataforma de divulgación coordinada de vulnerabilidades operada por ${legal.operador} («el Operador»). Huella pone en contacto a investigadores de seguridad («Investigadores») con organizaciones que publican programas para recibir informes sobre fallos de seguridad («Empresas»).`,
                'Al crear una cuenta confirmas que has leído y aceptas estos Términos, la Política de Privacidad y la Política de Divulgación. Si actúas en nombre de una organización, declaras tener facultades para obligarla. Si no estás de acuerdo, no utilices la plataforma.',
            ],
        },
        {
            id: 'cuentas',
            titulo: 'Cuentas y requisitos',
            lista: [
                'Debes ser mayor de edad según la ley de tu país y facilitar datos veraces.',
                'Cada persona tiene una sola cuenta de investigador. Las cuentas son personales e intransferibles.',
                'Las Empresas se registran con su razón social e identificador fiscal y solo operan tras la verificación del Operador.',
                'Eres responsable de la confidencialidad de tu contraseña. Recomendamos activar la verificación en dos pasos.',
                'Los investigadores y las empresas usan accesos separados. Una cuenta de investigador no pertenece a ninguna empresa ni publica programas.',
            ],
        },
        {
            id: 'investigadores',
            titulo: 'Reglas para investigadores',
            parrafos: ['Al investigar un programa te comprometes a:'],
            lista: [
                'Limitarte al alcance publicado por la Empresa. Lo que está fuera del alcance no está autorizado.',
                'No acceder, modificar, borrar ni extraer datos de terceros más allá de lo mínimo imprescindible para demostrar el fallo.',
                'No realizar ataques de denegación de servicio, ingeniería social, phishing ni pruebas físicas, salvo que el programa lo autorice expresamente.',
                'No utilizar la vulnerabilidad en tu beneficio ni en el de terceros, y detener la prueba en cuanto obtengas una evidencia suficiente.',
                'Mantener la confidencialidad del hallazgo hasta que la Política de Divulgación permita hacerlo público.',
                'Enviar informes veraces. Fabricar hallazgos, duplicarlos de forma deliberada o inflar su gravedad está prohibido.',
            ],
        },
        {
            id: 'empresas',
            titulo: 'Obligaciones de las empresas',
            lista: [
                'Publicar solo sistemas sobre los que tengan autoridad para autorizar pruebas de seguridad.',
                'Describir el alcance, las exclusiones y la evidencia que esperan de forma clara y actualizada.',
                'Revisar los informes validados en un plazo razonable y mantener informado al investigador a través de la plataforma.',
                'No emprender acciones legales contra investigadores que actúen de buena fe y dentro de estas reglas (ver «Puerto seguro» en la Política de Divulgación).',
                'Tratar la información de los informes de forma confidencial y usarla solo para corregir las vulnerabilidades.',
            ],
        },
        {
            id: 'reputacion',
            titulo: 'Reputación, sanciones y apelaciones',
            parrafos: [
                'Los informes validados suman puntos de reputación y permiten acceder a programas de mayor nivel. Los informes descartados pueden restar puntos cuando concurran indicios de mala fe (por ejemplo, falsos positivos fabricados para inflar métricas).',
                'Las sanciones son proporcionales a su gravedad, pueden incluir la suspensión temporal de la cuenta y siempre se notifican con su motivo. Toda sanción puede apelarse dentro del plazo indicado; la apelación la resuelve una persona distinta de quien aplicó la sanción y cada paso queda registrado de forma verificable.',
            ],
        },
        {
            id: 'confidencialidad',
            titulo: 'Confidencialidad y cifrado',
            parrafos: [
                'El contenido de los informes, sus pruebas de concepto y sus fotos se almacena cifrado con PGP y solo se descifra para las personas autorizadas en cada momento. Cada descifrado queda registrado. El Operador no vende ni cede el contenido de los informes.',
            ],
        },
        {
            id: 'propiedad',
            titulo: 'Propiedad intelectual',
            parrafos: [
                'El investigador conserva la autoría de su informe y concede a la Empresa destinataria y al Operador una licencia no exclusiva para usarlo con el fin de verificar, corregir y documentar la vulnerabilidad. La marca, el software y el diseño de Huella pertenecen al Operador.',
            ],
        },
        {
            id: 'responsabilidad',
            titulo: 'Limitación de responsabilidad',
            parrafos: [
                'Huella es un intermediario técnico: no garantiza que un programa esté libre de fallos ni que una Empresa corrija una vulnerabilidad en un plazo concreto. En la medida en que la ley lo permita, el Operador no responde de los daños derivados del uso indebido de la plataforma por parte de sus usuarios.',
            ],
        },
        {
            id: 'baja',
            titulo: 'Suspensión y baja',
            parrafos: [
                'Puedes eliminar tu cuenta desde Configuración en cualquier momento. El Operador puede suspender o cerrar cuentas que incumplan estos Términos, siguiendo el procedimiento de sanciones y apelaciones cuando corresponda. Se conservarán los registros de auditoría que exija la ley.',
            ],
        },
        {
            id: 'cambios',
            titulo: 'Cambios en los Términos',
            parrafos: [
                'Si modificamos estos Términos publicaremos una nueva versión con su fecha y te lo avisaremos en la plataforma. Los cambios relevantes requerirán que los aceptes de nuevo para seguir usando tu cuenta.',
            ],
        },
        {
            id: 'ley',
            titulo: 'Ley aplicable y contacto',
            parrafos: [
                `Estos Términos se rigen por la legislación de ${legal.jurisdiccion}. Para cualquier consulta sobre ellos puedes escribir a ${legal.contacto}.`,
            ],
        },
    ]);
</script>

<DocumentoLegal
    titulo="Términos de Servicio"
    resumen="Las reglas del juego para investigadores y empresas: qué puedes hacer en Huella, qué no, y cómo se resuelven los desacuerdos."
    version={legal.version}
    {secciones}
/>
