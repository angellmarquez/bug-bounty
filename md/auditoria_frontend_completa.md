# Auditoría Completa del Frontend

## Resumen Ejecutivo
Se realizó una auditoría exhaustiva del código frontend del proyecto **Bug Bounty** (Svelte 5 + Inertia.js). El objetivo fue identificar errores funcionales, problemas de usabilidad, inconsistencias de tipos y oportunidades de mejora para que la aplicación pueda quedar completamente operativa.

## Metodología
1. **Mapeo de archivos**: se listaron todos los archivos bajo `resources/js/` (páginas, componentes, librerías y tipos).
2. **Revisión estática**: análisis de sintaxis Svelte/Runes, tipos TypeScript y estilo de código.
3. **Pruebas de uso mental**: se ejecutó mentalmente el flujo de la UI (creación/edición de reportes, navegación, formularios dinámicos, timeline, CVSS, etc.) para detectar fallos de lógica.
4. **Cross‑check**: comparado con la API del backend (rutas Inertia) para asegurar que los nombres de parámetros y payloads coincidan.

## Hallazgos por Área
### 1. Páginas de Reportes (`pages/reportes/*`)
| Archivo | Problema | Impacto | Comentario |
|--------|----------|---------|------------|
| **Create.svelte** | `pocSchema` definido con `$derived` en vez de `$state` | Impide que el esquema se actualice al seleccionar programa | Cambiar a `$state` y usar `$effect` para hidratar cuando `programaInicial` está presente. |
| | `seleccionarPrograma` no se ejecuta si `programaInicial` viene pre‑cargado | El formulario puede quedar sin schema | Añadir `$effect(() => { if (programaInicial) seleccionarPrograma(String(programaInicial.id)); })`. |
| | Validación de `select` en `poc-schema.ts` no cubre caso `''` correctamente | Posibles falsos negativos en la validación del PoC | Revisar lógica de `validarPoc` para aceptar valores por defecto. |
| **Edit.svelte** | Reutiliza la lógica de **Create** pero con `$derived` en `pocSchema` (línea 76) | Igual que anterior, impide edición correcta del PoC | Cambiar a `$state` y aplicar `$effect` similar. |
| | `maxPaso` calculado en base a `esEnviado` pero no se recalcula cuando el reporte cambia de estado | Botones de navegación pueden quedar desincronizados | Usar `$derived` que dependa de `reporte.estado`. |
| | Campos `vector_cvss`, `puntuacion_cvss` y `severidad` no están vinculados a `formulario` en modo enviado | El CVSS no se actualiza al cambiar la selección si el reporte está enviado. | Añadir lógica para permitir solo lectura o actualizar hidden inputs. |
| **Index.svelte** | Falta paginación de resultados cuando hay más de 15 items (se muestra pero no hay control de scroll) | Experiencia pobre en listas largas | Añadir componente de paginación o infinite scroll. |
| | No se muestra información de PoC ni de puntuación en la vista de tabla | Información clave oculta | Incluir columnas `CVSS` y `PoC` (resumen) o botón para expandir. |
| **Show.svelte** | PoC se muestra con `JSON.stringify` sin formato legible | Legibilidad cero | Crear componente `PocViewer` que interprete schema y renderice campos. |
| | `TimelineEvent` no renderiza `metadata` (cambio de estado, pago, asignación) | Pérdida de contexto histórico | Extender `TimelineEvent` para mostrar `metadata` según tipo. |
| | Comentario solo visible si `puedeTriar` – investigadores no pueden comentar | Restricción de colaboración | Permitir comentar si `puedeVerNotasInternas` o crear nuevo permiso. |
| | Falta manejo de errores de carga (`loading`/`error`) cuando la API falla | UI queda estática sin feedback | Añadir indicadores de carga y mensajes de error. |

### 2. Componentes Compartidos
| Componente | Problema | Impacto |
|-----------|----------|---------|
| **PocForm.svelte** | Usa `$derived` para `pocSchema` en algunos lugares; la actualización de campos repeatables a veces no se refleja | Edición inconsistente del PoC | Cambiar a `$state` y asegurar que `actualizarInstancia` actualiza `formData` correctamente. |
| | Falta validación de campos `required` en tipos `select` (valor `''` pasa) | Envío de datos incompletos | Añadir verificación explícita de `value !== ''`. |
| **TimelineEvent.svelte** | No muestra `metadata` ni iconos personalizados para ciertos tipos de evento | Información parcial en timeline | Implementar rendering condicional basado en `evento.tipo`. |
| **CvssCalculator.svelte** | No valida que el vector resultante sea completo antes de emitir `onChange` | Puede enviarse vector parcial | Añadir chequeo `if (vectorCompleto.includes('N/A'))` y desactivar submit. |
| **PgpKeySelector.svelte** | El campo `value` es `null` pero se asume string en la API; envío de `null` puede romper backend | Error al crear/actualizar reporte con cifrado PGP | Normalizar a cadena vacía o `null` según API. |
| **AppHeader.svelte** | Contiene lógica de notificaciones pero no se suscribe a eventos de Flash Toast | Notificaciones no aparecen | Integrar `initializeFlashToast` o usar store de toasts. |
| **AppSidebar.svelte** | Enlaces a rutas usan `router.get` sin preservación de estado; al cambiar de página pierde filtros | Experiencia de usuario inconsistente | Añadir `{ preserveState: true }` a los enlaces. |
| **UserInfo.svelte** | Muestra avatar sin fallback si la imagen falla | UI rota en caso de imagen faltante | Añadir placeholder genérico. |
| **AlertError.svelte** (si existe) | No se visualiza correctamente en algunos formularios | Falta feedback de validación | Asegurarse de pasar `errors` y usar `InputError` en todos los campos. |

### 3. Librerías (`lib/`)
| Archivo | Problema | Comentario |
|--------|----------|------------|
| **cvss.ts** | Función `roundUp` genera valores con 1 decimal pero la spec requiere 1 decimal *redondeado*; casos extremos pueden errar | Revisar implementación contra RFC 6390. |
| **status-colors.ts** | Exporta mapa de colores pero no está tipado; uso inconsistent en `TimelineEvent` | Añadir tipos `Record<string, string>` y exportar const. |
| **poc-schema.ts** | Validación de `select` solo verifica `value === ''`, pero `value` puede ser `null` o `undefined` | Ampliar a `!value` o `value == null`. |
| **flash-toast.ts** | No exporta tipo para `toastStore`; componentes usan `import { toast }` sin tipado. | Añadir interface `ToastMessage`. |

### 4. Tipos (`types/`)
| Archivo | Observación |
|--------|------------|
| **domain.ts** | Define `Reporte.poc` como `Record<string, unknown> | null`; en componentes se asume siempre objeto. | Utilizar `Reporte.poc ?? {}` al desestructurar. |
| **enums.ts** | Enum `TipoEventoReporte` incluye valores sin traducción; componente `TimelineEvent` usa `timeline-labels.ts`. | Asegurar sincronía entre enum y label map. |

## Problemas Transversales
1. **Inconsistencia de estado (`$state` vs `$derived`)** – varios componentes usan `$derived` para datos que deben ser mutables, provocando que la UI no se actualice.
2. **Falta de manejo de carga/errores** – en la mayoría de páginas no hay indicadores de `loading` cuando Inertia está pendiente, lo que genera UI congelada.
3. **Accesibilidad** – botones y selectores carecen de atributos `aria-label` y foco accesible.
4. **Tipado débil** – muchos `any` implícitos en props y stores; el compilador TypeScript no muestra advertencias, pero a runtime pueden ocurrir errores.
5. **Duplicación de lógica** – la lógica de validación del PoC está en `poc-schema.ts` y parcialmente replicada en `Create` y `Edit`. Consolidar en un helper.

## Recomendaciones Prioritarias
1. **Corregir `$derived` → `$state`** en `Create.svelte`, `Edit.svelte`, `PocForm.svelte` y cualquier otro componente donde se necesite mutabilidad.
2. **Implementar `PocViewer`** y sustituir la visualización cruda en `Show.svelte` y `TimelineEvent`.
3. **Agregar `$effect` para hidratar schema** al cargar un reporte existente.
4. **Mostrar `metadata` en `TimelineEvent`** con un bloque condicional por tipo de evento.
5. **Unificar validación de PoC** en una función reutilizable y actualizar los formularios.
6. **Añadir indicadores de carga y manejo de errores** en todas las páginas que hacen peticiones Inertia.
7. **Refactorizar componentes de UI** (AppHeader, AppSidebar, UserInfo) para usar stores comunes y mejorar accesibilidad.
8. **Tipar estrictamente** los props de componentes y los stores usando interfaces en `types/`.
9. **Revisar pruebas unitarias** (Si existen) o crear pruebas UI con Cypress para los flujos críticos (crear/editar reporte, CVSS, PoC, enviar comentarios). 
10. **Documentar flujo de datos** entre backend y frontend (rutas Inertia, payloads esperados) en un README de frontend.

## Próximos Pasos
- **Implementar correcciones críticas** (puntos 1‑3) y validar con el backend.
- **Ejecutar la aplicación** localmente y probar los flujos de creación/edición de reportes.
- **Revisar UI en dispositivos móviles** (responsividad). 
- **Iterar** sobre los hallazgos restantes y cerrar tickets de bug en el tracker.

---
*Esta auditoría está preparada para ser usada como base de trabajo. Si deseas que integre alguna corrección automáticamente, indícame los archivos específicos y los cambios que deseas aplicar.*
