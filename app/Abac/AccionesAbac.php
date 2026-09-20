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

    public const ReportePagar = 'reportes.pagar';

    public const ReporteCerrar = 'reportes.cerrar';

    public const ReporteEliminar = 'reportes.eliminar';

    public const ProgramaVer = 'programas.ver';

    public const ProgramaCrear = 'programas.crear';

    public const ProgramaGestionar = 'programas.gestionar';

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
}
