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

    public const ReporteValidar = 'reportes.validar';

    public const ReporteRechazar = 'reportes.rechazar';

    public const ReporteMarcarDuplicado = 'reportes.marcar_duplicado';

    public const ReporteMarcarEnReparacion = 'reportes.marcar_en_reparacion';

    public const ReporteCerrar = 'reportes.cerrar';

    public const ReporteEliminar = 'reportes.eliminar';

    public const ReporteDecryptPoc = 'reportes.decrypt_poc';

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

    public const EmpresaGestionarMiembros = 'empresas.gestionar_miembros';

    public const ModeradorAsignar = 'moderadores.asignar';

    public const ModeradorRevocar = 'moderadores.revocar';

    public const ModeracionVer = 'moderacion.ver';

    public const ReputacionVer = 'reputacion.ver';

    public const UsuarioVer = 'usuarios.ver';

    public const UsuarioActualizarRol = 'usuarios.actualizar_rol';

    public const SancionVer = 'sanciones.ver';

    public const SancionRevocar = 'sanciones.revocar';

    public const AuditoriaVer = 'auditoria.ver';

    public const ConfigReputacionVer = 'config_reputacion.ver';

    public const ConfigReputacionActualizar = 'config_reputacion.actualizar';

    public const ClavePgpPlataformaVer = 'claves_pgp_plataforma.ver';
}
