<?php

namespace App\Services;

use App\Models\Archivo;
use App\Models\Convocatoria;
use App\Models\Requerimiento;
use App\Models\RequerimientoItem;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Reglas duras de dominio para Requerimientos (02B M04): la entidad debe estar
 * APROBADA para crear; edición/borrado bloqueados si ya existe una
 * CONVOCATORIA en cualquier estado distinto de BORRADOR. REQU_NUMERO se
 * genera solo (NumeracionService), nunca lo digita el usuario.
 */
class RequerimientoService
{
    public function __construct(
        private AuditoriaService $auditoria,
        private NumeracionService $numeracionService,
        private ArchivoService $archivoService,
    ) {}

    public function crear(array $datos, User $usuario, ?UploadedFile $archivoTdr = null): Requerimiento
    {
        // 05C hardening: quien no tiene rol ENTIDAD (ADMINISTRADOR o un rol
        // custom "staff interno") crea el requerimiento en nombre de la
        // entidad que elija explícitamente (`entidad_id`, validado en
        // `RequerimientoStoreRequest`). Se verifica el ROL, no solo si
        // `entidad_id` está vacío en la fila del usuario.
        $entidadId = $usuario->hasRole('ENTIDAD') ? $usuario->entidad_id : ($datos['entidad_id'] ?? null);

        if (! $entidadId) {
            throw new HttpException(403, 'Debe pertenecer a una entidad para crear requerimientos.');
        }

        $estadoEntidad = DB::table('ENTIDAD')->where('ENTI_ID', $entidadId)->value('ENTI_ESTADO_ID');
        $estadoAprobadaId = DB::table('ESTADO')->where('ESTA_CONTEXTO', 'ENTIDAD')->where('ESTA_CODIGO', 'APROBADA')->value('ESTA_ID');

        if ($estadoEntidad !== $estadoAprobadaId) {
            throw new HttpException(403, 'Su entidad no está aprobada; no puede crear requerimientos.');
        }

        return DB::transaction(function () use ($datos, $entidadId, $archivoTdr) {
            $numero = $this->numeracionService->generarRequerimiento($entidadId);

            $requerimiento = Requerimiento::create([
                'REQU_ENTIDAD_ID' => $entidadId,
                'REQU_NUMERO' => $numero,
                'REQU_OBJETO' => $datos['objeto'],
                'REQU_DESCRIPCION' => $datos['descripcion'],
                'REQU_TIPO' => $datos['tipo'],
                'REQU_AREA_ID' => $datos['area_id'],
            ]);

            $requerimiento->rubros()->sync($datos['rubro_ids'] ?? []);
            $this->guardarItems($requerimiento, $datos['items']);

            if ($archivoTdr) {
                $this->archivoService->subir($archivoTdr, 'REQUERIMIENTO_TDR', $requerimiento->REQU_ID);
            }

            $this->auditoria->registrar('REQUERIMIENTOS', 'CREAR', 'REQUERIMIENTO', $requerimiento->REQU_ID);

            return $requerimiento;
        });
    }

    public function actualizar(Requerimiento $requerimiento, array $datos, ?UploadedFile $archivoTdr = null): Requerimiento
    {
        $this->asegurarSinConvocatoriaPublicada($requerimiento, 'editar');

        $valorAnterior = $requerimiento->only(['REQU_NUMERO', 'REQU_OBJETO', 'REQU_DESCRIPCION', 'REQU_TIPO', 'REQU_AREA_ID']);

        return DB::transaction(function () use ($requerimiento, $datos, $archivoTdr, $valorAnterior) {
            $requerimiento->update([
                'REQU_OBJETO' => $datos['objeto'],
                'REQU_DESCRIPCION' => $datos['descripcion'],
                'REQU_TIPO' => $datos['tipo'],
                'REQU_AREA_ID' => $datos['area_id'],
            ]);

            $requerimiento->rubros()->sync($datos['rubro_ids'] ?? []);

            RequerimientoItem::where('REIT_REQUERIMIENTO_ID', $requerimiento->REQU_ID)->delete();
            $this->guardarItems($requerimiento, $datos['items']);

            // Solo se reemplaza el TDR si llega uno nuevo; si no, el existente
            // queda intacto (05C hardening — antes exigía un flujo aparte).
            if ($archivoTdr) {
                $anterior = Archivo::where('ARCH_CONTEXTO', 'REQUERIMIENTO_TDR')
                    ->where('ARCH_REFERENCIA_ID', $requerimiento->REQU_ID)
                    ->first();
                if ($anterior) {
                    $this->archivoService->eliminar($anterior);
                }
                $this->archivoService->subir($archivoTdr, 'REQUERIMIENTO_TDR', $requerimiento->REQU_ID);
            }

            $this->auditoria->registrar(
                'REQUERIMIENTOS',
                'ACTUALIZAR',
                'REQUERIMIENTO',
                $requerimiento->REQU_ID,
                $valorAnterior,
                $requerimiento->only(['REQU_NUMERO', 'REQU_OBJETO', 'REQU_DESCRIPCION', 'REQU_TIPO', 'REQU_AREA_ID']),
            );

            return $requerimiento;
        });
    }

    /** Desactivar en vez de eliminar (05C hardening, pedido explícitamente por
     * el usuario, mismo patrón que Usuarios/Proveedores/Catálogos/Roles): no
     * se borra ningún registro, el requerimiento queda listado con su estado
     * en "Inactivo". A diferencia del `eliminar()` que reemplaza, esto no
     * toca la convocatoria asociada (es una fila independiente) — por eso no
     * reutiliza `asegurarSinConvocatoriaPublicada`, no hay nada que proteger. */
    public function desactivar(Requerimiento $requerimiento): void
    {
        $requerimiento->update(['REQU_ACTIVO' => false]);

        $this->auditoria->registrar('REQUERIMIENTOS', 'DESACTIVAR', 'REQUERIMIENTO', $requerimiento->REQU_ID);
    }

    public function activar(Requerimiento $requerimiento): void
    {
        $requerimiento->update(['REQU_ACTIVO' => true]);

        $this->auditoria->registrar('REQUERIMIENTOS', 'ACTIVAR', 'REQUERIMIENTO', $requerimiento->REQU_ID);
    }

    private function guardarItems(Requerimiento $requerimiento, array $items): void
    {
        foreach ($items as $item) {
            RequerimientoItem::create([
                'REIT_REQUERIMIENTO_ID' => $requerimiento->REQU_ID,
                'REIT_NUMERO' => $item['numero'],
                'REIT_DESCRIPCION' => $item['descripcion'],
                'REIT_UNIDAD_MEDIDA_ID' => $item['unidad_medida_id'],
                'REIT_CANTIDAD' => $item['cantidad'],
                'REIT_PRESENTACION' => $item['presentacion'] ?? null,
            ]);
        }
    }

    private function asegurarSinConvocatoriaPublicada(Requerimiento $requerimiento, string $accion): void
    {
        $tieneConvocatoriaPublicada = Convocatoria::where('CONV_REQUERIMIENTO_ID', $requerimiento->REQU_ID)
            ->whereHas('estado', fn ($q) => $q->where('ESTA_CODIGO', '!=', 'BORRADOR'))
            ->exists();

        if ($tieneConvocatoriaPublicada) {
            throw new HttpException(409, "Este requerimiento tiene una convocatoria en un estado distinto de borrador; no se puede {$accion}.");
        }
    }
}
