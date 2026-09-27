<?php

namespace App\Abac;

/**
 * Acciones del vocabulario ABAC.
 *
 * Se expresan en notación `recurso.accion` y se usan tanto en
 * `config/abac.php` como en `Gate::authorize('abac', [..])`.
 */
final class AccionesAbac
{
    public const ReporteCrear = 'reportes.crear';

    public const ReporteVer = 'reportes.ver';

    public const ReporteVerNotasInternas = 'reportes.ver_notas_internas';

    public const ReporteEditar = 'reportes.editar';

    public const ReporteEnviar = 'reportes.enviar';

    public const ReporteAsignar = 'reportes.asignar';

    /** Tomar un informe para revisarlo (pasa a en revisión y queda asignado a quien lo toma). */
    public const ReporteRevisar = 'reportes.revisar';

    public const ReporteValidar = 'reportes.validar';

    public const ReporteRechazar = 'reportes.rechazar';

    public const ReporteMarcarDuplicado = 'reportes.marcar_duplicado';

    public const ReporteMarcarEnReparacion = 'reportes.marcar_en_reparacion';

    public const ReporteCerrar = 'reportes.cerrar';

    public const ReporteEliminar = 'reportes.eliminar';

    public const ReporteDecryptPoc = 'reportes.decrypt_poc';

    public const ReporteAsignarBounty = 'reportes.asignar_bounty';

    public const ReportePagarBounty = 'reportes.pagar_bounty';

    public const ProgramaInvitarHacker = 'programas.invitar_hacker';

    public const ProgramaVer = 'programas.ver';

    public const ProgramaCrear = 'programas.crear';

    public const ProgramaGestionar = 'programas.gestionar';

    public const ProgramaEditar = 'programas.editar';

    public const ProgramaCambiarEstado = 'programas.cambiar_estado';

    public const ProgramaEliminar = 'programas.eliminar';

    public const ApelacionCrear = 'apelaciones.crear';

    public const ApelacionResolver = 'apelaciones.resolver';

    public const EmpresaVer = 'empresas.ver';

    public const EmpresaAprobar = 'empresas.aprobar';

    public const EmpresaRechazar = 'empresas.rechazar';

    public const EmpresaSuspender = 'empresas.suspender';

    public const EmpresaReactivar = 'empresas.reactivar';

    /** Activar o quitar el Plan Profesional de una empresa (solo el administrador). */
    public const EmpresaCambiarPlan = 'empresas.cambiar_plan';

    /** La empresa paga (o renueva) su propio Plan Profesional en USDC. */
    public const EmpresaPagarPlan = 'empresas.pagar_plan';

    /** Ver y cambiar la configuración del plan (wallet de tesorería, precio): solo el administrador. */
    public const ConfigSuscripcionVer = 'config_suscripcion.ver';

    public const ConfigSuscripcionActualizar = 'config_suscripcion.actualizar';

    /** Ver los ingresos recibidos por planes: solo el administrador. */
    public const IngresosVer = 'ingresos.ver';

    public const EmpresaGestionarMiembros = 'empresas.gestionar_miembros';

    public const ModeradorAsignar = 'moderadores.asignar';

    public const ModeradorRevocar = 'moderadores.revocar';

    public const ModeracionVer = 'moderacion.ver';

    public const ReputacionVer = 'reputacion.ver';

    public const UsuarioVer = 'usuarios.ver';

    public const SancionVer = 'sanciones.ver';

    public const SancionRevocar = 'sanciones.revocar';

    public const AuditoriaVer = 'auditoria.ver';

    public const ConfigReputacionVer = 'config_reputacion.ver';

    public const ConfigReputacionActualizar = 'config_reputacion.actualizar';

    public const ClavePgpPlataformaVer = 'claves_pgp_plataforma.ver';

    /** Ver (y emitir, si aún no existe) el certificado de divulgación de un informe cerrado. */
    public const CertificadoVer = 'certificados.ver';

    /** Evaluar peticiones arbitrarias contra la política en el simulador visual. */
    public const AbacSimular = 'abac.simular';
}
