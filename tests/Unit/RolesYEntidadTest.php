<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\AdministracionService;
use App\Support\CatalogoPermisos;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Roles personalizados y entidad: quién queda limitado a los datos de su
 * entidad, qué entidad se guarda al crear el usuario y qué permisos se pueden
 * dar o quitar desde Roles y Permisos.
 */
class RolesYEntidadTest extends TestCase
{
    public static function casosLimite(): array
    {
        return [
            'ENTIDAD con entidad' => [['ENTIDAD'], 5, true],
            'ENTIDAD sin entidad (dato roto): limitado, no ve nada' => [['ENTIDAD'], null, true],
            'rol personalizado con entidad' => [['SUPERVISOR_LOGISTICA'], 5, true],
            'rol personalizado sin entidad: ve todas' => [['SUPERVISOR_LOGISTICA'], null, false],
            'ADMINISTRADOR aunque tenga entidad' => [['ADMINISTRADOR'], 5, false],
            'AUDITOR aunque tenga entidad' => [['AUDITOR'], 5, false],
            'PROVEEDOR' => [['PROVEEDOR'], null, false],
        ];
    }

    #[DataProvider('casosLimite')]
    public function test_quien_queda_limitado_a_su_entidad(array $roles, ?int $entidadId, bool $esperado): void
    {
        $this->assertSame($esperado, User::limitaAEntidad($roles, $entidadId));
    }

    public function test_entidad_que_se_guarda_segun_el_rol(): void
    {
        $this->assertSame(3, AdministracionService::entidadParaRol('ENTIDAD', 3));
        $this->assertSame(3, AdministracionService::entidadParaRol('SUPERVISOR_LOGISTICA', '3'));
        $this->assertNull(AdministracionService::entidadParaRol('SUPERVISOR_LOGISTICA', null));
        $this->assertNull(AdministracionService::entidadParaRol('ADMINISTRADOR', 3));
        $this->assertNull(AdministracionService::entidadParaRol('AUDITOR', 3));
    }

    public function test_un_rol_personalizado_no_recibe_permisos_ocultos_ni_de_proveedor(): void
    {
        $finales = CatalogoPermisos::combinar(
            'SUPERVISOR_LOGISTICA',
            ['convocatorias.ver', 'entidades.ver', 'ofertas.crear', 'proveedores.ver_propio'],
            [],
            ['ENTIDADES'],
        );

        $this->assertSame(['convocatorias.ver'], $finales);
    }

    public function test_guardar_un_rol_conserva_lo_que_la_pantalla_no_ofrece(): void
    {
        // AUDITOR tenía entidades.ver (módulo oculto): la pantalla ya no lo
        // muestra ni lo envía, y no debe perderse al guardar.
        $finales = CatalogoPermisos::combinar(
            'AUDITOR',
            ['reportes.ver'],
            ['entidades.ver', 'reportes.ver', 'auditoria.ver'],
            ['ENTIDADES'],
        );

        sort($finales);
        $this->assertSame(['entidades.ver', 'reportes.ver'], $finales);
    }

    public function test_proveedor_tiene_permisos_fijos(): void
    {
        $this->assertFalse(CatalogoPermisos::esEditable('ofertas.crear', 'PROVEEDOR', ['ENTIDADES']));
        $this->assertFalse(CatalogoPermisos::esEditable('requerimientos.ver', 'PROVEEDOR', ['ENTIDADES']));
        $this->assertFalse(CatalogoPermisos::esEditable('ofertas.crear', 'SUPERVISOR_LOGISTICA', ['ENTIDADES']));
        $this->assertFalse(CatalogoPermisos::esEditable('entidades.aprobar', 'ADMINISTRADOR', ['ENTIDADES']));
        $this->assertTrue(CatalogoPermisos::esEditable('requerimientos.ver', 'SUPERVISOR_LOGISTICA', ['ENTIDADES']));

        // Guardar PROVEEDOR no cambia nada: ni agrega lo enviado ni quita lo que tenía.
        $finales = CatalogoPermisos::combinar(
            'PROVEEDOR',
            ['requerimientos.ver'],
            ['ofertas.crear', 'ofertas.ver', 'proveedores.ver_propio'],
            ['ENTIDADES'],
        );
        sort($finales);
        $this->assertSame(['ofertas.crear', 'ofertas.ver', 'proveedores.ver_propio'], $finales);
    }
}
