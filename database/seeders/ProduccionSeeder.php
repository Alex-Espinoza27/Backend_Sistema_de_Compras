<?php

namespace Database\Seeders;

use App\Models\Configuracion;
use App\Models\Entidad;
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
        $this->seedEntidad();
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
            ['CONF_CLAVE' => 'CORREO_REMITENTE_NOMBRE', 'CONF_VALOR' => 'Compras La Perla', 'CONF_TIPO' => 'TEXTO', 'CONF_DESCRIPCION' => 'Nombre remitente que ven los destinatarios de las notificaciones.'],
            ['CONF_CLAVE' => 'PLANTILLA_DJ_DEFECTO', 'CONF_VALOR' => '', 'CONF_TIPO' => 'TEXTO', 'CONF_DESCRIPCION' => 'Referencia a la plantilla de declaración jurada por defecto.'],
            // Color de marca (05C hardening, pedido explícitamente por el
            // usuario): hex aplicado en caliente (sin recompilar) al
            // encabezado y a los botones/chips "primary" de toda la
            // plataforma (pública y autenticada) vía `App.vue` + `AppBar.vue`.
            // Vacío = se mantiene el azul/navy estático de vuetify.ts.
            ['CONF_CLAVE' => 'COLOR_PRIMARIO', 'CONF_VALOR' => '', 'CONF_TIPO' => 'COLOR', 'CONF_DESCRIPCION' => 'Color de marca (hex, ej. #0090D8) aplicado al encabezado y a los botones primarios de toda la plataforma. Vacío = usar el color por defecto del sistema.'],
            // Entidad principal del portal público (05C hardening, pedido
            // explícitamente por el usuario): si hay más de una entidad
            // APROBADA, `/` (PortadaView.vue) deja de mostrar directo el
            // diseño de convocatorias y cae al buscador genérico de
            // entidades — para evitarlo con La Perla, se identifica la
            // entidad "de casa" por su RUC (dato estable, no por nombre) y
            // `PublicoController::tema()` resuelve el ID real para el
            // frontend. Se guarda el RUC, no el ENTI_ID, porque el ID es un
            // autoincremental que puede variar entre entornos/reseeds; el RUC
            // de la municipalidad no cambia.
            ['CONF_CLAVE' => 'ENTIDAD_PRINCIPAL_RUC', 'CONF_VALOR' => '20100000001', 'CONF_TIPO' => 'TEXTO', 'CONF_DESCRIPCION' => 'RUC de la entidad que el portal público ("/") muestra directamente, aunque haya más de una entidad aprobada. Vacío = mostrar el buscador de entidades cuando haya más de una.'],
            // Credenciales SMTP (05C hardening, pedido explícitamente por el
            // usuario): si SMTP_HOST queda vacío, sigue rigiendo lo que haya
            // en .env (ej. Mailpit en desarrollo) — ver AppServiceProvider.
            ['CONF_CLAVE' => 'SMTP_HOST', 'CONF_VALOR' => '', 'CONF_TIPO' => 'TEXTO', 'CONF_DESCRIPCION' => 'Servidor SMTP para el envío real de correos (vacío = usar el de .env).'],
            ['CONF_CLAVE' => 'SMTP_PUERTO', 'CONF_VALOR' => '587', 'CONF_TIPO' => 'ENTERO', 'CONF_DESCRIPCION' => 'Puerto del servidor SMTP.'],
            ['CONF_CLAVE' => 'SMTP_USUARIO', 'CONF_VALOR' => '', 'CONF_TIPO' => 'TEXTO', 'CONF_DESCRIPCION' => 'Usuario/cuenta para autenticarse contra el servidor SMTP.'],
            ['CONF_CLAVE' => 'SMTP_PASSWORD', 'CONF_VALOR' => '', 'CONF_TIPO' => 'SECRETO', 'CONF_DESCRIPCION' => 'Contraseña SMTP (se guarda cifrada, nunca se muestra en texto plano).'],
            ['CONF_CLAVE' => 'SMTP_CIFRADO', 'CONF_VALOR' => 'tls', 'CONF_TIPO' => 'TEXTO', 'CONF_DESCRIPCION' => 'Cifrado del servidor SMTP: tls, ssl, o vacío para ninguno.'],
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
            // Tipo de identidad para usuarios de plataforma (05C hardening,
            // pedido explícitamente por el usuario): "PS" se interpreta como
            // Pasaporte; renombrar TIDO_CODIGO aquí si se quiso decir otra cosa.
            ['TIDO_CODIGO' => 'PASAPORTE', 'TIDO_NOMBRE' => 'Pasaporte', 'TIDO_LONGITUD' => 12],
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

    /**
     * La plataforma hoy aloja una sola entidad (05C hardening, pedido
     * explícitamente por el usuario): sin esto, un servidor nuevo arrancaba
     * sin ninguna entidad aprobada, y el portal público nunca podía resolver
     * "la única entidad" (`PortadaView.vue` cae al listado genérico en vez
     * de mostrar el diseño de entidad única). Mismos datos que ya usa
     * `DemoSeeder` para la entidad "aprobada", para no tener dos fuentes de
     * verdad distintas del mismo registro institucional.
     */
    private function seedEntidad(): void
    {
        Entidad::updateOrCreate(
            ['ENTI_NUMERO_DOCUMENTO' => '20100000001'],
            [
                'ENTI_TIPO_DOCUMENTO_ID' => TipoDocumento::where('TIDO_CODIGO', 'RUC')->value('TIDO_ID'),
                'ENTI_RAZON_SOCIAL' => 'Municipalidad Distrital de La Perla',
                'ENTI_SIGLA' => 'MDLP',
                'ENTI_CORREO' => 'contacto@munilaperla.gob.pe',
                'ENTI_TELEFONO' => '014567890',
                'ENTI_DIRECCION' => 'Av. Guardia Chalaca 100, La Perla, Callao',
                'ENTI_PERSONA_CONTACTO' => 'Jefe de Abastecimiento',
                'ENTI_ESTADO_ID' => Estado::where('ESTA_CONTEXTO', 'ENTIDAD')->where('ESTA_CODIGO', 'APROBADA')->value('ESTA_ID'),
                'ENTI_FECHA_SOLICITUD' => now()->subDays(60),
                'ENTI_FECHA_RESOLUCION' => now()->subDays(59),
            ],
        );
    }

    private function seedModulo(): void
    {
        // Orden pedido por el usuario (Fase 12 frontend): Dashboard primero,
        // luego el flujo troncal ("core"), luego gestión de actores,
        // luego transversales de solo lectura, y Administración al final
        // (agrupa mantenimiento de catálogos + usuarios + roles y permisos).
        $filas = [
            ['MODU_CODIGO' => 'DASHBOARD', 'MODU_NOMBRE' => 'Dashboard', 'MODU_ICONO' => 'mdi-view-dashboard', 'MODU_ORDEN' => 1],
            ['MODU_CODIGO' => 'REQUERIMIENTOS', 'MODU_NOMBRE' => 'Requerimientos', 'MODU_ICONO' => 'mdi-clipboard-list', 'MODU_ORDEN' => 2],
            ['MODU_CODIGO' => 'AREAS', 'MODU_NOMBRE' => 'Áreas', 'MODU_ICONO' => 'mdi-office-building-outline', 'MODU_ORDEN' => 3],
            ['MODU_CODIGO' => 'CONVOCATORIAS', 'MODU_NOMBRE' => 'Convocatorias', 'MODU_ICONO' => 'mdi-bullhorn', 'MODU_ORDEN' => 4],
            // M06/M07/M08 del TDR (00_CONTEXTO_TDR.md §4), separados de vuelta
            // en sus propios módulos de nivel superior (05C hardening —
            // estaban fusionados en un solo "Convocatorias" desde la Fase 11).
            ['MODU_CODIGO' => 'PRESENTACION_OFERTAS', 'MODU_NOMBRE' => 'Presentación de Ofertas', 'MODU_ICONO' => 'mdi-file-document-multiple-outline', 'MODU_ORDEN' => 5],
            ['MODU_CODIGO' => 'OFERTAS', 'MODU_NOMBRE' => 'Ver Convocatorias', 'MODU_ICONO' => 'mdi-file-document', 'MODU_ORDEN' => 6],
            ['MODU_CODIGO' => 'EVALUACION', 'MODU_NOMBRE' => 'Evaluación', 'MODU_ICONO' => 'mdi-clipboard-check', 'MODU_ORDEN' => 7],
            ['MODU_CODIGO' => 'ADJUDICACION', 'MODU_NOMBRE' => 'Adjudicación', 'MODU_ICONO' => 'mdi-gavel', 'MODU_ORDEN' => 8],
            ['MODU_CODIGO' => 'ENTIDADES', 'MODU_NOMBRE' => 'Entidades', 'MODU_ICONO' => 'mdi-domain', 'MODU_ORDEN' => 9],
            // GESTION_PROVEEDORES (05C hardening) es distinto del código
            // legado `PROVEEDORES` (permisos proveedores.*_propio del propio
            // proveedor, siempre excluido del menú — ver AuthController::
            // construirMe) para no chocar con ese prefijo de permiso.
            ['MODU_CODIGO' => 'GESTION_PROVEEDORES', 'MODU_NOMBRE' => 'Proveedores', 'MODU_ICONO' => 'mdi-truck', 'MODU_ORDEN' => 10],
            ['MODU_CODIGO' => 'PROVEEDORES', 'MODU_NOMBRE' => 'Proveedores', 'MODU_ICONO' => 'mdi-truck', 'MODU_ORDEN' => 11],
            ['MODU_CODIGO' => 'REPORTES', 'MODU_NOMBRE' => 'Reportes', 'MODU_ICONO' => 'mdi-chart-bar', 'MODU_ORDEN' => 12],
            ['MODU_CODIGO' => 'AUDITORIA', 'MODU_NOMBRE' => 'Auditoría', 'MODU_ICONO' => 'mdi-history', 'MODU_ORDEN' => 13],
            ['MODU_CODIGO' => 'DATOS_ABIERTOS', 'MODU_NOMBRE' => 'Datos Abiertos', 'MODU_ICONO' => 'mdi-database', 'MODU_ORDEN' => 14],
            ['MODU_CODIGO' => 'NOTIFICACIONES', 'MODU_NOMBRE' => 'Notificaciones', 'MODU_ICONO' => 'mdi-bell', 'MODU_ORDEN' => 15],
            ['MODU_CODIGO' => 'GESTION_USUARIOS', 'MODU_NOMBRE' => 'Usuarios', 'MODU_ICONO' => 'mdi-account-multiple-outline', 'MODU_ORDEN' => 16],
            ['MODU_CODIGO' => 'ROLES_PERMISOS', 'MODU_NOMBRE' => 'Roles y Permisos', 'MODU_ICONO' => 'mdi-shield-account-outline', 'MODU_ORDEN' => 17],
            // Antes "Administración": tras separar Proveedores/Usuarios/Roles
            // en sus propios módulos (05C hardening), solo queda
            // Catálogos+Configuración/UIT — el nombre pasa a "Configuración".
            ['MODU_CODIGO' => 'ADMINISTRACION', 'MODU_NOMBRE' => 'Configuración', 'MODU_ICONO' => 'mdi-cog', 'MODU_ORDEN' => 18],
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
            'areas.ver', 'areas.gestionar',
            'convocatorias.ver', 'convocatorias.crear', 'convocatorias.editar', 'convocatorias.publicar', 'convocatorias.cancelar', 'convocatorias.ampliar',
            'presentacion_ofertas.ver',
            'ofertas.ver', 'ofertas.crear', 'ofertas.retirar',
            'entidades.ver', 'entidades.aprobar', 'entidades.gestionar',
            'evaluacion.ver', 'evaluacion.evaluar',
            'adjudicacion.ver', 'adjudicacion.adjudicar', 'adjudicacion.declarar_desierta',
            'dashboard.ver',
            'reportes.ver', 'reportes.exportar',
            'auditoria.ver',
            'gestion_proveedores.ver', 'gestion_proveedores.gestionar',
            'roles_permisos.ver', 'roles_permisos.gestionar',
            'gestion_usuarios.ver', 'gestion_usuarios.gestionar',
            'administracion.configuracion', 'administracion.catalogos',
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
            'convocatorias.ver', 'requerimientos.ver', 'presentacion_ofertas.ver',
        ]);

        $entidad = Role::findOrCreate('ENTIDAD', 'web');
        $entidad->syncPermissions([
            'requerimientos.ver', 'requerimientos.crear', 'requerimientos.editar', 'requerimientos.eliminar',
            'areas.ver', 'areas.gestionar',
            'convocatorias.ver', 'convocatorias.crear', 'convocatorias.editar', 'convocatorias.publicar', 'convocatorias.cancelar', 'convocatorias.ampliar',
            'presentacion_ofertas.ver',
            'evaluacion.ver', 'evaluacion.evaluar',
            'adjudicacion.ver', 'adjudicacion.adjudicar', 'adjudicacion.declarar_desierta',
            'dashboard.ver', 'reportes.ver', 'reportes.exportar',
        ]);

        $proveedor = Role::findOrCreate('PROVEEDOR', 'web');
        $proveedor->syncPermissions([
            'proveedores.ver_propio', 'proveedores.editar_propio', 'proveedores.suspender_propio',
            'ofertas.ver', 'ofertas.crear', 'ofertas.retirar',
            'dashboard.ver',
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
