<?php

namespace App\Support;

use App\Models\Modulo;

/**
 * Qué permisos se pueden asignar o quitar a un rol desde "Roles y Permisos".
 *
 * - Módulos ocultos (MODULO.MODU_VISIBLE = false, p. ej. Entidades): no se
 *   ofrecen a ningún rol. Los permisos que un rol ya tenía se conservan al
 *   guardar, para no romper a ADMINISTRADOR/AUDITOR.
 * - PROVEEDOR es un rol reservado con permisos fijos (los del seeder): desde
 *   la pantalla no se le da ni se le quita nada.
 * - Módulos exclusivos del proveedor (su ficha y sus ofertas): sus rutas exigen
 *   el rol PROVEEDOR (`role:PROVEEDOR`), así que en cualquier otro rol el
 *   permiso no sirve para nada: no se ofrecen a ningún rol.
 */
final class CatalogoPermisos
{
    public const MODULOS_SOLO_PROVEEDOR = ['PROVEEDORES', 'OFERTAS'];

    /** Roles cuyos permisos no se editan desde la pantalla. */
    public const ROLES_PERMISOS_FIJOS = ['PROVEEDOR'];

    public static function tienePermisosFijos(string $rol): bool
    {
        return in_array($rol, self::ROLES_PERMISOS_FIJOS, true);
    }

    /** `convocatorias.crear` → `CONVOCATORIAS` (mismo criterio que el menú). */
    public static function modulo(string $permiso): string
    {
        return strtoupper(explode('.', $permiso)[0]);
    }

    /** @return list<string> */
    public static function modulosOcultos(): array
    {
        return Modulo::where('MODU_VISIBLE', false)->pluck('MODU_CODIGO')->all();
    }

    public static function esSoloProveedor(string $permiso): bool
    {
        return in_array(self::modulo($permiso), self::MODULOS_SOLO_PROVEEDOR, true);
    }

    /**
     * Nombre amigable y descripción de cada permiso, para la pantalla "Roles y
     * Permisos" (antes mostraba el slug crudo, ej. "proveedores.ver_propio",
     * pedido explícitamente por el usuario que no se entendía).
     */
    private const ETIQUETAS = [
        'proveedores.ver_propio' => ['nombre' => 'Ver mi ficha de proveedor', 'descripcion' => 'Ver los datos de su propia ficha de proveedor.'],
        'proveedores.editar_propio' => ['nombre' => 'Editar mi ficha de proveedor', 'descripcion' => 'Editar los datos de su propia ficha de proveedor.'],
        'proveedores.suspender_propio' => ['nombre' => 'Suspender mi ficha de proveedor', 'descripcion' => 'Suspender voluntariamente su propia ficha de proveedor.'],

        'requerimientos.ver' => ['nombre' => 'Ver requerimientos', 'descripcion' => 'Ver la lista y el detalle de los requerimientos.'],
        'requerimientos.crear' => ['nombre' => 'Crear requerimientos', 'descripcion' => 'Registrar nuevos requerimientos.'],
        'requerimientos.editar' => ['nombre' => 'Editar requerimientos', 'descripcion' => 'Modificar requerimientos existentes.'],
        'requerimientos.eliminar' => ['nombre' => 'Eliminar requerimientos', 'descripcion' => 'Eliminar requerimientos.'],

        'areas.ver' => ['nombre' => 'Ver áreas', 'descripcion' => 'Ver la lista de áreas de la entidad.'],
        'areas.gestionar' => ['nombre' => 'Gestionar áreas', 'descripcion' => 'Crear, editar y desactivar áreas.'],

        'convocatorias.ver' => ['nombre' => 'Ver convocatorias', 'descripcion' => 'Ver la lista y el detalle de las convocatorias.'],
        'convocatorias.crear' => ['nombre' => 'Crear convocatorias', 'descripcion' => 'Registrar nuevas convocatorias.'],
        'convocatorias.editar' => ['nombre' => 'Editar convocatorias', 'descripcion' => 'Modificar convocatorias existentes.'],
        'convocatorias.publicar' => ['nombre' => 'Publicar convocatorias', 'descripcion' => 'Publicar una convocatoria para que los proveedores la vean.'],
        'convocatorias.cancelar' => ['nombre' => 'Cancelar convocatorias', 'descripcion' => 'Cancelar una convocatoria ya publicada.'],
        'convocatorias.ampliar' => ['nombre' => 'Ampliar plazos de convocatorias', 'descripcion' => 'Ampliar el plazo de presentación de una convocatoria.'],

        'presentacion_ofertas.ver' => ['nombre' => 'Ver presentación de ofertas', 'descripcion' => 'Ver las ofertas presentadas por los proveedores en una convocatoria.'],

        'ofertas.ver' => ['nombre' => 'Ver mis ofertas', 'descripcion' => 'Ver las ofertas que ha presentado como proveedor.'],
        'ofertas.crear' => ['nombre' => 'Presentar ofertas', 'descripcion' => 'Presentar una oferta a una convocatoria.'],
        'ofertas.retirar' => ['nombre' => 'Retirar ofertas', 'descripcion' => 'Retirar una oferta ya presentada.'],

        'entidades.ver' => ['nombre' => 'Ver entidades', 'descripcion' => 'Ver la lista de entidades registradas.'],
        'entidades.aprobar' => ['nombre' => 'Aprobar entidades', 'descripcion' => 'Aprobar el registro de una nueva entidad.'],
        'entidades.gestionar' => ['nombre' => 'Gestionar entidades', 'descripcion' => 'Editar y administrar entidades registradas.'],

        'evaluacion.ver' => ['nombre' => 'Ver evaluaciones', 'descripcion' => 'Ver las evaluaciones de las ofertas presentadas.'],
        'evaluacion.evaluar' => ['nombre' => 'Evaluar ofertas', 'descripcion' => 'Calificar las ofertas presentadas por los proveedores.'],

        'adjudicacion.ver' => ['nombre' => 'Ver adjudicaciones', 'descripcion' => 'Ver el estado de adjudicación de una convocatoria.'],
        'adjudicacion.adjudicar' => ['nombre' => 'Adjudicar convocatorias', 'descripcion' => 'Adjudicar una convocatoria al proveedor ganador.'],
        'adjudicacion.declarar_desierta' => ['nombre' => 'Declarar convocatorias desiertas', 'descripcion' => 'Declarar desierta una convocatoria sin adjudicar.'],

        'dashboard.ver' => ['nombre' => 'Ver dashboard', 'descripcion' => 'Ver el panel de indicadores generales.'],

        'reportes.ver' => ['nombre' => 'Ver reportes', 'descripcion' => 'Ver los reportes del sistema.'],
        'reportes.exportar' => ['nombre' => 'Exportar reportes', 'descripcion' => 'Descargar los reportes en archivo.'],

        'auditoria.ver' => ['nombre' => 'Ver auditoría', 'descripcion' => 'Ver el historial de acciones realizadas en el sistema.'],

        'gestion_proveedores.ver' => ['nombre' => 'Ver proveedores', 'descripcion' => 'Ver la lista y fichas de los proveedores registrados.'],
        'gestion_proveedores.gestionar' => ['nombre' => 'Gestionar proveedores', 'descripcion' => 'Editar, suspender o aprobar proveedores registrados.'],

        'roles_permisos.ver' => ['nombre' => 'Ver roles y permisos', 'descripcion' => 'Ver la lista de roles y los permisos de cada uno.'],
        'roles_permisos.gestionar' => ['nombre' => 'Gestionar roles y permisos', 'descripcion' => 'Crear roles y asignar o quitar sus permisos.'],

        'gestion_usuarios.ver' => ['nombre' => 'Ver usuarios', 'descripcion' => 'Ver la lista de usuarios del sistema.'],
        'gestion_usuarios.gestionar' => ['nombre' => 'Gestionar usuarios', 'descripcion' => 'Crear, editar y desactivar usuarios, y asignarles roles.'],

        'administracion.configuracion' => ['nombre' => 'Configuración general', 'descripcion' => 'Editar la configuración general del sistema (UIT, parámetros, etc.).'],
        'administracion.catalogos' => ['nombre' => 'Gestionar catálogos', 'descripcion' => 'Editar catálogos del sistema (rubros, unidades de medida, tipos de documento, etc.).'],
    ];

    /** @return array{nombre: string, descripcion: string} */
    public static function etiqueta(string $permiso): array
    {
        return self::ETIQUETAS[$permiso] ?? ['nombre' => $permiso, 'descripcion' => ''];
    }

    /** @param  list<string>  $modulosOcultos */
    public static function esEditable(string $permiso, string $rol, array $modulosOcultos): bool
    {
        if (self::tienePermisosFijos($rol) || in_array(self::modulo($permiso), $modulosOcultos, true)) {
            return false;
        }

        return ! self::esSoloProveedor($permiso);
    }

    /**
     * Permisos finales de un rol al guardar: los editables que se enviaron más
     * los NO editables que el rol ya tenía (no se pueden ni dar ni quitar desde
     * la pantalla).
     *
     * @param  list<string>  $enviados
     * @param  list<string>  $actuales
     * @param  list<string>  $modulosOcultos
     * @return list<string>
     */
    public static function combinar(string $rol, array $enviados, array $actuales, array $modulosOcultos): array
    {
        $editables = array_filter($enviados, fn (string $p) => self::esEditable($p, $rol, $modulosOcultos));
        $conservados = array_filter($actuales, fn (string $p) => ! self::esEditable($p, $rol, $modulosOcultos));

        return array_values(array_unique([...$editables, ...$conservados]));
    }
}
