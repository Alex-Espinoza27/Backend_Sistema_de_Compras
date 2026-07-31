<?php

namespace App\Services;

use App\Models\Convocatoria;
use App\Models\Requerimiento;
use App\Models\RequerimientoItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Reglas duras de dominio para Requerimientos (02B M04): la entidad debe estar
 * APROBADA para crear; edición/borrado bloqueados si ya existe una
 * CONVOCATORIA en cualquier estado distinto de BORRADOR.
 */
class RequerimientoService
{
    public function __construct(private AuditoriaService $auditoria) {}

    public function crear(array $datos, User $usuario): Requerimiento
    {
        if (! $usuario->entidad_id) {
            throw new HttpException(403, 'Debe pertenecer a una entidad para crear requerimientos.');
        }

        $estadoEntidad = DB::table('ENTIDAD')->where('ENTI_ID', $usuario->entidad_id)->value('ENTI_ESTADO_ID');
        $estadoAprobadaId = DB::table('ESTADO')->where('ESTA_CONTEXTO', 'ENTIDAD')->where('ESTA_CODIGO', 'APROBADA')->value('ESTA_ID');

        if ($estadoEntidad !== $estadoAprobadaId) {
            throw new HttpException(403, 'Su entidad no está aprobada; no puede crear requerimientos.');
        }

        return DB::transaction(function () use ($datos, $usuario) {
            $requerimiento = Requerimiento::create([
                'REQU_ENTIDAD_ID' => $usuario->entidad_id,
                'REQU_RUBRO_ID' => $datos['rubro_id'] ?? null,
                'REQU_NUMERO' => $datos['numero'],
                'REQU_OBJETO' => $datos['objeto'],
                'REQU_DESCRIPCION' => $datos['descripcion'],
                'REQU_TIPO' => $datos['tipo'],
                'REQU_AREA_USUARIA' => $datos['area_usuaria'],
            ]);

            $this->guardarItems($requerimiento, $datos['items']);

            $this->auditoria->registrar('REQUERIMIENTOS', 'CREAR', 'REQUERIMIENTO', $requerimiento->REQU_ID);

            return $requerimiento;
        });
    }

    public function actualizar(Requerimiento $requerimiento, array $datos): Requerimiento
    {
        $this->asegurarSinConvocatoriaPublicada($requerimiento, 'editar');

        $valorAnterior = $requerimiento->only(['REQU_NUMERO', 'REQU_OBJETO', 'REQU_DESCRIPCION', 'REQU_TIPO', 'REQU_AREA_USUARIA', 'REQU_RUBRO_ID']);

        return DB::transaction(function () use ($requerimiento, $datos, $valorAnterior) {
            $requerimiento->update([
                'REQU_RUBRO_ID' => $datos['rubro_id'] ?? null,
                'REQU_NUMERO' => $datos['numero'],
                'REQU_OBJETO' => $datos['objeto'],
                'REQU_DESCRIPCION' => $datos['descripcion'],
                'REQU_TIPO' => $datos['tipo'],
                'REQU_AREA_USUARIA' => $datos['area_usuaria'],
            ]);

            RequerimientoItem::where('REIT_REQUERIMIENTO_ID', $requerimiento->REQU_ID)->delete();
            $this->guardarItems($requerimiento, $datos['items']);

            $this->auditoria->registrar(
                'REQUERIMIENTOS',
                'ACTUALIZAR',
                'REQUERIMIENTO',
                $requerimiento->REQU_ID,
                $valorAnterior,
                $requerimiento->only(['REQU_NUMERO', 'REQU_OBJETO', 'REQU_DESCRIPCION', 'REQU_TIPO', 'REQU_AREA_USUARIA', 'REQU_RUBRO_ID']),
            );

            return $requerimiento;
        });
    }

    public function eliminar(Requerimiento $requerimiento): void
    {
        $this->asegurarSinConvocatoriaPublicada($requerimiento, 'eliminar');

        $requerimiento->delete();

        $this->auditoria->registrar('REQUERIMIENTOS', 'ELIMINAR', 'REQUERIMIENTO', $requerimiento->REQU_ID);
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
