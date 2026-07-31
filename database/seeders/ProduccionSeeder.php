<?php

namespace Database\Seeders;

use App\Models\Configuracion;
use App\Models\Estado;
use App\Models\Modulo;
use App\Models\Rubro;
use App\Models\TipoComprobante;
use App\Models\TipoDocumento;
use App\Models\Uit;
use App\Models\UnidadMedida;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Datos imprescindibles para operar (02C Parte II). Corre también en producción.
 */
class ProduccionSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedConfiguracion();
        $this->seedUit();
        $this->seedTipoDocumento();
        $this->seedTipoComprobante();
        $this->seedUnidadMedida();
        $this->seedRubro();
        $this->seedEstado();
        $this->seedModulo();
        $this->seedRolesYPermisos();
        $this->seedAdmin();
    }

    private function seedConfiguracion(): void
    {
        $filas = [
            ['CONF_CLAVE' => 'INSTITUCION_NOMBRE', 'CONF_VALOR' => 'Municipalidad Distrital de La Perla', 'CONF_TIPO' => 'TEXTO', 'CONF_DESCRIPCION' => 'Nombre de la entidad titular de la plataforma.'],
            ['CONF_CLAVE' => 'UMBRAL_UIT', 'CONF_VALOR' => '8', 'CONF_TIPO' => 'ENTERO', 'CONF_DESCRIPCION' => 'Umbral de contratación en UIT.'],
            ['CONF_CLAVE' => 'DIAS_URGENCIA', 'CONF_VALOR' => '2', 'CONF_TIPO' => 'ENTERO', 'CONF_DESCRIPCION' => 'Días restantes para marcar una convocatoria como urgente.'],
            ['CONF_CLAVE' => 'CORREO_REMITENTE', 'CONF_VALOR' => 'no-reply@munilaperla.gob.pe', 'CONF_TIPO' => 'TEXTO', 'CONF_DESCRIPCION' => 'Correo remitente de las notificaciones institucionales.'],
            ['CONF_CLAVE' => 'PLANTILLA_DJ_DEFECTO', 'CONF_VALOR' => '', 'CONF_TIPO' => 'TEXTO', 'CONF_DESCRIPCION' => 'Referencia a la plantilla de declaración jurada por defecto.'],
        ];

        foreach ($filas as $fila) {
            Configuracion::updateOrCreate(['CONF_CLAVE' => $fila['CONF_CLAVE']], $fila);
        }
    }

    private function seedUit(): void
    {
        Uit::updateOrCreate(
            ['UITA_ANIO' => now()->year],
            ['UITA_VALOR' => 5350.00, 'UITA_VIGENTE' => true],
        );
    }

    private function seedTipoDocumento(): void
    {
        $filas = [
            ['TIDO_CODIGO' => 'RUC', 'TIDO_NOMBRE' => 'Registro Único de Contribuyentes', 'TIDO_LONGITUD' => 11],
            ['TIDO_CODIGO' => 'DNI', 'TIDO_NOMBRE' => 'Documento Nacional de Identidad', 'TIDO_LONGITUD' => 8],
            ['TIDO_CODIGO' => 'CE', 'TIDO_NOMBRE' => 'Carné de Extranjería', 'TIDO_LONGITUD' => 9],
        ];

        foreach ($filas as $fila) {
            TipoDocumento::updateOrCreate(['TIDO_CODIGO' => $fila['TIDO_CODIGO']], $fila);
        }
    }

    private function seedTipoComprobante(): void
    {
        $filas = [
            ['TICO_CODIGO' => 'FACTURA', 'TICO_NOMBRE' => 'Factura', 'TICO_INCLUYE_IGV' => true],
            ['TICO_CODIGO' => 'RECIBO_HONORARIOS', 'TICO_NOMBRE' => 'Recibo por Honorarios', 'TICO_INCLUYE_IGV' => false],
        ];

        foreach ($filas as $fila) {
            TipoComprobante::updateOrCreate(['TICO_CODIGO' => $fila['TICO_CODIGO']], $fila);
        }
    }

    private function seedUnidadMedida(): void
    {
        $filas = [
            ['UNME_CODIGO' => 'UNIDAD', 'UNME_NOMBRE' => 'Unidad'],
            ['UNME_CODIGO' => 'SERVICIO', 'UNME_NOMBRE' => 'Servicio'],
            ['UNME_CODIGO' => 'PAR', 'UNME_NOMBRE' => 'Par'],
            ['UNME_CODIGO' => 'CAJA', 'UNME_NOMBRE' => 'Caja'],
            ['UNME_CODIGO' => 'MILLAR', 'UNME_NOMBRE' => 'Millar'],
            ['UNME_CODIGO' => 'KIT', 'UNME_NOMBRE' => 'Kit'],
            ['UNME_CODIGO' => 'GLOBAL', 'UNME_NOMBRE' => 'Global'],
        ];

        foreach ($filas as $fila) {
            UnidadMedida::updateOrCreate(['UNME_CODIGO' => $fila['UNME_CODIGO']], $fila);
        }
    }

    private function seedRubro(): void
    {
        $filas = [
            ['RUBR_TIPO' => 'BIEN', 'RUBR_CODIGO' => 'B-01', 'RUBR_NOMBRE' => 'Útiles de oficina'],
            ['RUBR_TIPO' => 'BIEN', 'RUBR_CODIGO' => 'B-02', 'RUBR_NOMBRE' => 'Equipos de cómputo'],
            ['RUBR_TIPO' => 'BIEN', 'RUBR_CODIGO' => 'B-03', 'RUBR_NOMBRE' => 'Mobiliario'],
            ['RUBR_TIPO' => 'SERVICIO', 'RUBR_CODIGO' => 'S-01', 'RUBR_NOMBRE' => 'Mantenimiento'],
            ['RUBR_TIPO' => 'SERVICIO', 'RUBR_CODIGO' => 'S-02', 'RUBR_NOMBRE' => 'Consultoría'],
            ['RUBR_TIPO' => 'SERVICIO', 'RUBR_CODIGO' => 'S-03', 'RUBR_NOMBRE' => 'Limpieza'],
        ];

        foreach ($filas as $fila) {
            Rubro::updateOrCreate(['RUBR_TIPO' => $fila['RUBR_TIPO'], 'RUBR_CODIGO' => $fila['RUBR_CODIGO']], $fila);
        }
    }

    private function seedEstado(): void
    {
        $filas = [
            ['ESTA_CONTEXTO' => 'CONVOCATORIA', 'ESTA_CODIGO' => 'BORRADOR', 'ESTA_NOMBRE' => 'Borrador', 'ESTA_COLOR' => 'grey', 'ESTA_ORDEN' => 1],
            ['ESTA_CONTEXTO' => 'CONVOCATORIA', 'ESTA_CODIGO' => 'PUBLICADA', 'ESTA_NOMBRE' => 'Convocatoria Vigente', 'ESTA_COLOR' => 'success', 'ESTA_ORDEN' => 2],
            ['ESTA_CONTEXTO' => 'CONVOCATORIA', 'ESTA_CODIGO' => 'EN_EVALUACION', 'ESTA_NOMBRE' => 'Convocatoria en Evaluación', 'ESTA_COLOR' => 'info', 'ESTA_ORDEN' => 3],
            ['ESTA_CONTEXTO' => 'CONVOCATORIA', 'ESTA_CODIGO' => 'ADJUDICADA', 'ESTA_NOMBRE' => 'Convocatoria Adjudicada', 'ESTA_COLOR' => 'primary', 'ESTA_ORDEN' => 4],
            ['ESTA_CONTEXTO' => 'CONVOCATORIA', 'ESTA_CODIGO' => 'DESIERTA', 'ESTA_NOMBRE' => 'Convocatoria Desierta', 'ESTA_COLOR' => 'warning', 'ESTA_ORDEN' => 5],
            ['ESTA_CONTEXTO' => 'CONVOCATORIA', 'ESTA_CODIGO' => 'CANCELADA', 'ESTA_NOMBRE' => 'Convocatoria Cancelada', 'ESTA_COLOR' => 'error', 'ESTA_ORDEN' => 6],
            ['ESTA_CONTEXTO' => 'OFERTA', 'ESTA_CODIGO' => 'ENVIADA', 'ESTA_NOMBRE' => 'Enviada', 'ESTA_COLOR' => 'info', 'ESTA_ORDEN' => 1],
            ['ESTA_CONTEXTO' => 'OFERTA', 'ESTA_CODIGO' => 'EN_EVALUACION', 'ESTA_NOMBRE' => 'En Evaluación', 'ESTA_COLOR' => 'grey', 'ESTA_ORDEN' => 2],
            ['ESTA_CONTEXTO' => 'OFERTA', 'ESTA_CODIGO' => 'APROBADA', 'ESTA_NOMBRE' => 'Aprobada', 'ESTA_COLOR' => 'success', 'ESTA_ORDEN' => 3],
            ['ESTA_CONTEXTO' => 'OFERTA', 'ESTA_CODIGO' => 'DESAPROBADA', 'ESTA_NOMBRE' => 'Desaprobada', 'ESTA_COLOR' => 'error', 'ESTA_ORDEN' => 4],
            ['ESTA_CONTEXTO' => 'OFERTA', 'ESTA_CODIGO' => 'ADJUDICADA', 'ESTA_NOMBRE' => 'Adjudicada', 'ESTA_COLOR' => 'primary', 'ESTA_ORDEN' => 5],
            ['ESTA_CONTEXTO' => 'OFERTA', 'ESTA_CODIGO' => 'NO_ADJUDICADA', 'ESTA_NOMBRE' => 'No Adjudicada', 'ESTA_COLOR' => 'grey', 'ESTA_ORDEN' => 6],
            ['ESTA_CONTEXTO' => 'ENTIDAD', 'ESTA_CODIGO' => 'SOLICITADA', 'ESTA_NOMBRE' => 'Solicitud Pendiente', 'ESTA_COLOR' => 'warning', 'ESTA_ORDEN' => 1],
            ['ESTA_CONTEXTO' => 'ENTIDAD', 'ESTA_CODIGO' => 'APROBADA', 'ESTA_NOMBRE' => 'Aprobada', 'ESTA_COLOR' => 'success', 'ESTA_ORDEN' => 2],
            ['ESTA_CONTEXTO' => 'ENTIDAD', 'ESTA_CODIGO' => 'OBSERVADA', 'ESTA_NOMBRE' => 'Observada', 'ESTA_COLOR' => 'info', 'ESTA_ORDEN' => 3],
            ['ESTA_CONTEXTO' => 'ENTIDAD', 'ESTA_CODIGO' => 'RECHAZADA', 'ESTA_NOMBRE' => 'Rechazada', 'ESTA_COLOR' => 'error', 'ESTA_ORDEN' => 4],
            ['ESTA_CONTEXTO' => 'ENTIDAD', 'ESTA_CODIGO' => 'SUSPENDIDA', 'ESTA_NOMBRE' => 'Suspendida', 'ESTA_COLOR' => 'grey', 'ESTA_ORDEN' => 5],
            ['ESTA_CONTEXTO' => 'PROVEEDOR', 'ESTA_CODIGO' => 'ACTIVO', 'ESTA_NOMBRE' => 'Activo', 'ESTA_COLOR' => 'success', 'ESTA_ORDEN' => 1],
            ['ESTA_CONTEXTO' => 'PROVEEDOR', 'ESTA_CODIGO' => 'SUSPENDIDO', 'ESTA_NOMBRE' => 'Suspendido', 'ESTA_COLOR' => 'error', 'ESTA_ORDEN' => 2],
        ];

        foreach ($filas as $fila) {
            Estado::updateOrCreate(
                ['ESTA_CONTEXTO' => $fila['ESTA_CONTEXTO'], 'ESTA_CODIGO' => $fila['ESTA_CODIGO']],
                $fila,
            );
        }
    }

    private function seedModulo(): void
    {
        $filas = [
            ['MODU_CODIGO' => 'ADMINISTRACION', 'MODU_NOMBRE' => 'Administración', 'MODU_ICONO' => 'mdi-cog', 'MODU_ORDEN' => 1],
            ['MODU_CODIGO' => 'ENTIDADES', 'MODU_NOMBRE' => 'Entidades', 'MODU_ICONO' => 'mdi-domain', 'MODU_ORDEN' => 2],
            ['MODU_CODIGO' => 'PROVEEDORES', 'MODU_NOMBRE' => 'Proveedores', 'MODU_ICONO' => 'mdi-truck', 'MODU_ORDEN' => 3],
            ['MODU_CODIGO' => 'REQUERIMIENTOS', 'MODU_NOMBRE' => 'Requerimientos', 'MODU_ICONO' => 'mdi-clipboard-list', 'MODU_ORDEN' => 4],
            ['MODU_CODIGO' => 'CONVOCATORIAS', 'MODU_NOMBRE' => 'Convocatorias', 'MODU_ICONO' => 'mdi-bullhorn', 'MODU_ORDEN' => 5],
            ['MODU_CODIGO' => 'OFERTAS', 'MODU_NOMBRE' => 'Ofertas', 'MODU_ICONO' => 'mdi-file-document', 'MODU_ORDEN' => 6],
            ['MODU_CODIGO' => 'EVALUACION', 'MODU_NOMBRE' => 'Evaluación', 'MODU_ICONO' => 'mdi-clipboard-check', 'MODU_ORDEN' => 7],
            ['MODU_CODIGO' => 'ADJUDICACION', 'MODU_NOMBRE' => 'Adjudicación', 'MODU_ICONO' => 'mdi-gavel', 'MODU_ORDEN' => 8],
            ['MODU_CODIGO' => 'REPORTES', 'MODU_NOMBRE' => 'Reportes', 'MODU_ICONO' => 'mdi-chart-bar', 'MODU_ORDEN' => 9],
            ['MODU_CODIGO' => 'DASHBOARD', 'MODU_NOMBRE' => 'Dashboard', 'MODU_ICONO' => 'mdi-view-dashboard', 'MODU_ORDEN' => 10],
            ['MODU_CODIGO' => 'AUDITORIA', 'MODU_NOMBRE' => 'Auditoría', 'MODU_ICONO' => 'mdi-history', 'MODU_ORDEN' => 11],
            ['MODU_CODIGO' => 'DATOS_ABIERTOS', 'MODU_NOMBRE' => 'Datos Abiertos', 'MODU_ICONO' => 'mdi-database', 'MODU_ORDEN' => 12],
            ['MODU_CODIGO' => 'NOTIFICACIONES', 'MODU_NOMBRE' => 'Notificaciones', 'MODU_ICONO' => 'mdi-bell', 'MODU_ORDEN' => 13],
        ];

        foreach ($filas as $fila) {
            Modulo::updateOrCreate(['MODU_CODIGO' => $fila['MODU_CODIGO']], $fila);
        }
    }

    private function seedRolesYPermisos(): void
    {
        $permisos = [
            'proveedores.ver_propio', 'proveedores.editar_propio', 'proveedores.suspender_propio',
            'requerimientos.ver', 'requerimientos.crear', 'requerimientos.editar', 'requerimientos.eliminar',
            'convocatorias.ver', 'convocatorias.crear', 'convocatorias.editar', 'convocatorias.publicar', 'convocatorias.cancelar',
            'ofertas.ver', 'ofertas.crear', 'ofertas.retirar',
            'entidades.ver', 'entidades.aprobar', 'entidades.gestionar',
            'evaluacion.ver', 'evaluacion.evaluar',
            'adjudicacion.ver', 'adjudicacion.adjudicar', 'adjudicacion.declarar_desierta',
            'dashboard.ver',
            'reportes.ver', 'reportes.exportar',
            'auditoria.ver',
            'administracion.usuarios', 'administracion.proveedores', 'administracion.roles', 'administracion.configuracion', 'administracion.catalogos',
        ];

        foreach ($permisos as $permiso) {
            Permission::findOrCreate($permiso, 'web');
        }

        $administrador = Role::findOrCreate('ADMINISTRADOR', 'web');
        $administrador->syncPermissions($permisos);

        $auditor = Role::findOrCreate('AUDITOR', 'web');
        $auditor->syncPermissions([
            'entidades.ver', 'evaluacion.ver', 'adjudicacion.ver', 'dashboard.ver',
            'reportes.ver', 'reportes.exportar', 'auditoria.ver',
            'convocatorias.ver', 'requerimientos.ver',
        ]);

        $entidad = Role::findOrCreate('ENTIDAD', 'web');
        $entidad->syncPermissions([
            'requerimientos.ver', 'requerimientos.crear', 'requerimientos.editar', 'requerimientos.eliminar',
            'convocatorias.ver', 'convocatorias.crear', 'convocatorias.editar', 'convocatorias.publicar', 'convocatorias.cancelar',
            'evaluacion.ver', 'evaluacion.evaluar',
            'adjudicacion.ver', 'adjudicacion.adjudicar', 'adjudicacion.declarar_desierta',
            'dashboard.ver', 'reportes.ver', 'reportes.exportar',
        ]);

        $proveedor = Role::findOrCreate('PROVEEDOR', 'web');
        $proveedor->syncPermissions([
            'proveedores.ver_propio', 'proveedores.editar_propio', 'proveedores.suspender_propio',
            'ofertas.ver', 'ofertas.crear', 'ofertas.retirar',
        ]);
    }

    private function seedAdmin(): void
    {
        $admin = User::updateOrCreate(
            ['email' => 'admin@munilaperla.gob.pe'],
            ['name' => 'Administrador de Plataforma', 'password' => Hash::make('Password123')],
        );

        if (! $admin->hasRole('ADMINISTRADOR')) {
            $admin->assignRole('ADMINISTRADOR');
        }
    }
}
