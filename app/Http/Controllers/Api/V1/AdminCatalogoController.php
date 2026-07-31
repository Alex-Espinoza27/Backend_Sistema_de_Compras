<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\EstadoStoreRequest;
use App\Http\Requests\Admin\EstadoUpdateRequest;
use App\Http\Requests\Admin\RubroStoreRequest;
use App\Http\Requests\Admin\RubroUpdateRequest;
use App\Http\Requests\Admin\TipoComprobanteStoreRequest;
use App\Http\Requests\Admin\TipoComprobanteUpdateRequest;
use App\Http\Requests\Admin\UnidadMedidaStoreRequest;
use App\Http\Requests\Admin\UnidadMedidaUpdateRequest;
use App\Models\Estado;
use App\Models\Rubro;
use App\Models\TipoComprobante;
use App\Models\UnidadMedida;
use App\Support\ApiResponse;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

class AdminCatalogoController extends Controller
{
    // --- Rubros ---

    public function rubrosIndex(): JsonResponse
    {
        $items = Rubro::select(['RUBR_ID', 'RUBR_TIPO', 'RUBR_CODIGO', 'RUBR_NOMBRE'])
            ->orderBy('RUBR_TIPO')->orderBy('RUBR_CODIGO')->get()
            ->map(fn (Rubro $r) => ['rubro_id' => $r->RUBR_ID, 'tipo' => $r->RUBR_TIPO, 'codigo' => $r->RUBR_CODIGO, 'nombre' => $r->RUBR_NOMBRE]);

        return ApiResponse::success($items);
    }

    public function rubrosStore(RubroStoreRequest $request): JsonResponse
    {
        $rubro = Rubro::create([
            'RUBR_TIPO' => $request->input('tipo'),
            'RUBR_CODIGO' => $request->input('codigo'),
            'RUBR_NOMBRE' => $request->input('nombre'),
        ]);

        return ApiResponse::success(['rubro_id' => $rubro->RUBR_ID], 'Rubro creado.', 201);
    }

    public function rubrosUpdate(RubroUpdateRequest $request, int $id): JsonResponse
    {
        $rubro = Rubro::findOrFail($id);
        $rubro->update([
            'RUBR_TIPO' => $request->input('tipo'),
            'RUBR_CODIGO' => $request->input('codigo'),
            'RUBR_NOMBRE' => $request->input('nombre'),
        ]);

        return ApiResponse::success(null, 'Rubro actualizado.');
    }

    public function rubrosDestroy(int $id): JsonResponse
    {
        $rubro = Rubro::findOrFail($id);
        $this->eliminarOConflicto(fn () => $rubro->delete());

        return ApiResponse::success(null, 'Rubro eliminado.');
    }

    // --- Unidades de medida ---

    public function unidadesMedidaIndex(): JsonResponse
    {
        $items = UnidadMedida::select(['UNME_ID', 'UNME_CODIGO', 'UNME_NOMBRE'])
            ->orderBy('UNME_CODIGO')->get()
            ->map(fn (UnidadMedida $u) => ['unidad_medida_id' => $u->UNME_ID, 'codigo' => $u->UNME_CODIGO, 'nombre' => $u->UNME_NOMBRE]);

        return ApiResponse::success($items);
    }

    public function unidadesMedidaStore(UnidadMedidaStoreRequest $request): JsonResponse
    {
        $unidad = UnidadMedida::create(['UNME_CODIGO' => $request->input('codigo'), 'UNME_NOMBRE' => $request->input('nombre')]);

        return ApiResponse::success(['unidad_medida_id' => $unidad->UNME_ID], 'Unidad de medida creada.', 201);
    }

    public function unidadesMedidaUpdate(UnidadMedidaUpdateRequest $request, int $id): JsonResponse
    {
        $unidad = UnidadMedida::findOrFail($id);
        $unidad->update(['UNME_CODIGO' => $request->input('codigo'), 'UNME_NOMBRE' => $request->input('nombre')]);

        return ApiResponse::success(null, 'Unidad de medida actualizada.');
    }

    public function unidadesMedidaDestroy(int $id): JsonResponse
    {
        $unidad = UnidadMedida::findOrFail($id);
        $this->eliminarOConflicto(fn () => $unidad->delete());

        return ApiResponse::success(null, 'Unidad de medida eliminada.');
    }

    // --- Tipos de comprobante ---

    public function tiposComprobanteIndex(): JsonResponse
    {
        $items = TipoComprobante::select(['TICO_ID', 'TICO_CODIGO', 'TICO_NOMBRE', 'TICO_INCLUYE_IGV'])
            ->orderBy('TICO_CODIGO')->get()
            ->map(fn (TipoComprobante $t) => ['tipo_comprobante_id' => $t->TICO_ID, 'codigo' => $t->TICO_CODIGO, 'nombre' => $t->TICO_NOMBRE, 'incluye_igv' => $t->TICO_INCLUYE_IGV]);

        return ApiResponse::success($items);
    }

    public function tiposComprobanteStore(TipoComprobanteStoreRequest $request): JsonResponse
    {
        $tipo = TipoComprobante::create([
            'TICO_CODIGO' => $request->input('codigo'),
            'TICO_NOMBRE' => $request->input('nombre'),
            'TICO_INCLUYE_IGV' => $request->boolean('incluye_igv'),
        ]);

        return ApiResponse::success(['tipo_comprobante_id' => $tipo->TICO_ID], 'Tipo de comprobante creado.', 201);
    }

    public function tiposComprobanteUpdate(TipoComprobanteUpdateRequest $request, int $id): JsonResponse
    {
        $tipo = TipoComprobante::findOrFail($id);
        $tipo->update([
            'TICO_CODIGO' => $request->input('codigo'),
            'TICO_NOMBRE' => $request->input('nombre'),
            'TICO_INCLUYE_IGV' => $request->boolean('incluye_igv'),
        ]);

        return ApiResponse::success(null, 'Tipo de comprobante actualizado.');
    }

    public function tiposComprobanteDestroy(int $id): JsonResponse
    {
        $tipo = TipoComprobante::findOrFail($id);
        $this->eliminarOConflicto(fn () => $tipo->delete());

        return ApiResponse::success(null, 'Tipo de comprobante eliminado.');
    }

    // --- Estados ---

    public function estadosIndex(): JsonResponse
    {
        $items = Estado::select(['ESTA_ID', 'ESTA_CONTEXTO', 'ESTA_CODIGO', 'ESTA_NOMBRE', 'ESTA_COLOR', 'ESTA_ORDEN'])
            ->orderBy('ESTA_CONTEXTO')->orderBy('ESTA_ORDEN')->get()
            ->map(fn (Estado $e) => [
                'estado_id' => $e->ESTA_ID, 'contexto' => $e->ESTA_CONTEXTO, 'codigo' => $e->ESTA_CODIGO,
                'nombre' => $e->ESTA_NOMBRE, 'color' => $e->ESTA_COLOR, 'orden' => $e->ESTA_ORDEN,
            ]);

        return ApiResponse::success($items);
    }

    public function estadosStore(EstadoStoreRequest $request): JsonResponse
    {
        $estado = Estado::create([
            'ESTA_CONTEXTO' => $request->input('contexto'),
            'ESTA_CODIGO' => $request->input('codigo'),
            'ESTA_NOMBRE' => $request->input('nombre'),
            'ESTA_COLOR' => $request->input('color'),
            'ESTA_ORDEN' => $request->input('orden'),
        ]);

        return ApiResponse::success(['estado_id' => $estado->ESTA_ID], 'Estado creado.', 201);
    }

    public function estadosUpdate(EstadoUpdateRequest $request, int $id): JsonResponse
    {
        $estado = Estado::findOrFail($id);
        $estado->update([
            'ESTA_CONTEXTO' => $request->input('contexto'),
            'ESTA_CODIGO' => $request->input('codigo'),
            'ESTA_NOMBRE' => $request->input('nombre'),
            'ESTA_COLOR' => $request->input('color'),
            'ESTA_ORDEN' => $request->input('orden'),
        ]);

        return ApiResponse::success(null, 'Estado actualizado.');
    }

    public function estadosDestroy(int $id): JsonResponse
    {
        $estado = Estado::findOrFail($id);
        $this->eliminarOConflicto(fn () => $estado->delete());

        return ApiResponse::success(null, 'Estado eliminado.');
    }

    private function eliminarOConflicto(\Closure $accion): void
    {
        try {
            $accion();
        } catch (QueryException $e) {
            if ($e->getCode() === '23503') {
                throw new HttpException(409, 'No se puede eliminar: está en uso por otros registros.');
            }

            throw $e;
        }
    }
}
