<script lang="ts">
    import DocumentoLegal from '@/components/publico/DocumentoLegal.svelte';
    import type { DatosLegales, Seccion } from '@/lib/legal';

    let { legal }: { legal: DatosLegales } = $props();

    const secciones = $derived<Seccion[]>([
        {
            id: 'principio',
            titulo: 'Qué es la divulgación coordinada',
            parrafos: [
                'La divulgación coordinada es un acuerdo entre quien descubre una vulnerabilidad y quien puede corregirla: primero se arregla y después, si procede, se hace pública. Así se protege a los usuarios finales mientras la solución no está lista.',
            ],
        },
        {
            id: 'proceso',
            titulo: 'Cómo funciona en Huella',
            lista: [
                'El investigador envía el informe a través del programa, con la evidencia que este pide. Nunca por otros canales.',
                'La moderación lo revisa a ciegas (sin conocer al autor) y lo valida, lo rechaza o pide más información.',
                'La empresa recibe los informes validados, los corrige y marca el informe como «en reparación» y después «cerrado».',
                'Todo el proceso queda en la línea de tiempo del informe, visible para el investigador.',
            ],
        },
        {
            id: 'plazos',
            titulo: 'Plazos',
            lista: [
                'La empresa debe dar respuesta a un informe validado en un plazo razonable, orientativamente 10 días hábiles.',
                'El plazo de corrección orientativo es de 90 días naturales desde la validación, ampliable de común acuerdo cuando la complejidad lo justifique.',
                'El investigador no hará público ningún detalle antes de que el informe esté cerrado y la empresa lo autorice, o de que venzan los plazos acordados sin respuesta, previo aviso a través de la plataforma.',
            ],
        },
        {
            id: 'puerto-seguro',
            titulo: 'Puerto seguro para investigadores',
            parrafos: [
                'Las empresas que publican un programa en Huella se comprometen a considerar autorizadas las pruebas realizadas de buena fe, dentro del alcance y conforme a los Términos de Servicio, y a no emprender acciones legales ni denunciar al investigador por ellas.',
                'Esta protección no cubre las conductas prohibidas: acceso o extracción de datos más allá de lo imprescindible, denegación de servicio, ingeniería social, extorsión o divulgación prematura.',
            ],
        },
        {
            id: 'fuera-alcance',
            titulo: 'Qué no se acepta en general',
            parrafos: ['Salvo que un programa diga lo contrario, no se consideran válidos:'],
            lista: [
                'Informes de herramientas automáticas sin una prueba de concepto verificable.',
                'Fallos que requieren acceso físico al dispositivo o una cuenta ya comprometida.',
                'Ausencia de cabeceras o configuraciones de buenas prácticas sin un impacto demostrable.',
                'Ataques de denegación de servicio o de fuerza bruta contra sistemas en producción.',
            ],
        },
        {
            id: 'contacto',
            titulo: 'Contacto',
            parrafos: [`Si una empresa no responde en los plazos o crees que se ha incumplido esta política, escribe a ${legal.contacto}.`],
        },
    ]);
</script>

<DocumentoLegal
    titulo="Política de Divulgación"
    resumen="Cómo se reporta, se corrige y, llegado el caso, se hace pública una vulnerabilidad sin poner en riesgo a nadie."
    version={legal.version}
    {secciones}
/>
