<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\EstadoStoreRequest;
use App\Http\Requests\Admin\EstadoUpdateRequest;
use App\Http\Requests\Admin\RubroStoreRequest;
use App\Http\Requests\Admin\RubroUpdateRequest;
use App\Http\Requests\Admin\TipoComprobanteStoreRequest;
use App\Http\Requests\Admin\TipoComprobanteUpdateRequest;
use App\Http\Requests\Admin\TipoDocumentoStoreRequest;
use App\Http\Requests\Admin\TipoDocumentoUpdateRequest;
use App\Http\Requests\Admin\UnidadMedidaStoreRequest;
use App\Http\Requests\Admin\UnidadMedidaUpdateRequest;
use App\Models\Estado;
use App\Models\Rubro;
use App\Models\TipoComprobante;
use App\Models\TipoDocumento;
use App\Models\UnidadMedida;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class AdminCatalogoController extends Controller
{
    // --- Rubros ---

    public function rubrosIndex(): JsonResponse
    {
        $items = Rubro::select(['RUBR_ID', 'RUBR_TIPO', 'RUBR_CODIGO', 'RUBR_NOMBRE', 'RUBR_ACTIVO'])
            ->orderBy('RUBR_TIPO')->orderBy('RUBR_CODIGO')->get()
            ->map(fn (Rubro $r) => ['rubro_id' => $r->RUBR_ID, 'tipo' => $r->RUBR_TIPO, 'codigo' => $r->RUBR_CODIGO, 'nombre' => $r->RUBR_NOMBRE, 'activo' => $r->RUBR_ACTIVO]);

        return ApiResponse::success($items);
    }

    public function rubrosStore(RubroStoreRequest $request): JsonResponse
    {
        $tipo = $request->input('tipo');
        $codigo = $request->input('codigo');

        if (empty($codigo)) {
            $prefijo = $tipo === 'BIEN' ? 'B-' : 'S-';
            $codigos = Rubro::where('RUBR_TIPO', $tipo)
                ->where('RUBR_CODIGO', 'LIKE', "{$prefijo}%")
                ->pluck('RUBR_CODIGO');

            $maxNum = 0;
            foreach ($codigos as $c) {
                if (preg_match('/^' . preg_quote($prefijo, '/') . '(\d+)$/', $c, $m)) {
                    $val = (int) $m[1];
                    if ($val > $maxNum) {
                        $maxNum = $val;
                    }
                }
            }
            $num = $maxNum + 1;
            $codigo = $prefijo . str_pad((string) $num, 2, '0', STR_PAD_LEFT);
            while (Rubro::where('RUBR_CODIGO', $codigo)->exists()) {
                $num++;
                $codigo = $prefijo . str_pad((string) $num, 2, '0', STR_PAD_LEFT);
            }
        }

        $rubro = Rubro::create([
            'RUBR_TIPO' => $tipo,
            'RUBR_CODIGO' => $codigo,
            'RUBR_NOMBRE' => $request->input('nombre'),
        ]);

        return ApiResponse::success(['rubro_id' => $rubro->RUBR_ID, 'codigo' => $codigo], 'Rubro creado.', 201);
    }

    public function rubrosUpdate(RubroUpdateRequest $request, int $id): JsonResponse
    {
        $rubro = Rubro::findOrFail($id);
        $rubro->update([
            'RUBR_TIPO' => $request->input('tipo'),
            'RUBR_CODIGO' => $request->input('codigo'),
            'RUBR_NOMBRE' => $request->input('nombre'),
            'RUBR_ACTIVO' => $request->boolean('activo'),
        ]);

        return ApiResponse::success(null, 'Rubro actualizado.');
    }

    // --- Unidades de medida ---

    public function unidadesMedidaIndex(): JsonResponse
    {
        $items = UnidadMedida::select(['UNME_ID', 'UNME_CODIGO', 'UNME_NOMBRE', 'UNME_ACTIVO'])
            ->orderBy('UNME_CODIGO')->get()
            ->map(fn (UnidadMedida $u) => ['unidad_medida_id' => $u->UNME_ID, 'codigo' => $u->UNME_CODIGO, 'nombre' => $u->UNME_NOMBRE, 'activo' => $u->UNME_ACTIVO]);

        return ApiResponse::success($items);
    }

    public function unidadesMedidaStore(UnidadMedidaStoreRequest $request): JsonResponse
    {
        $codigo = $request->input('codigo');
        $nombre = $request->input('nombre');

        if (empty($codigo)) {
            $base = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', Str::ascii($nombre)));
            $base = substr($base, 0, 16);
            if (empty($base)) {
                $base = 'UM';
            }
            $codigo = $base;
            $contador = 1;
            while (UnidadMedida::where('UNME_CODIGO', $codigo)->exists()) {
                $codigo = substr($base, 0, 15) . '_' . $contador;
                $contador++;
            }
        }

        $unidad = UnidadMedida::create(['UNME_CODIGO' => $codigo, 'UNME_NOMBRE' => $nombre]);

        return ApiResponse::success(['unidad_medida_id' => $unidad->UNME_ID, 'codigo' => $codigo], 'Unidad de medida creada.', 201);
    }

    public function unidadesMedidaUpdate(UnidadMedidaUpdateRequest $request, int $id): JsonResponse
    {
        $unidad = UnidadMedida::findOrFail($id);
        $unidad->update([
            'UNME_CODIGO' => $request->input('codigo'),
            'UNME_NOMBRE' => $request->input('nombre'),
            'UNME_ACTIVO' => $request->boolean('activo'),
        ]);

        return ApiResponse::success(null, 'Unidad de medida actualizada.');
    }

    // --- Tipos de comprobante ---

    public function tiposComprobanteIndex(): JsonResponse
    {
        $items = TipoComprobante::select(['TICO_ID', 'TICO_CODIGO', 'TICO_NOMBRE', 'TICO_INCLUYE_IGV', 'TICO_ACTIVO'])
            ->orderBy('TICO_CODIGO')->get()
            ->map(fn (TipoComprobante $t) => ['tipo_comprobante_id' => $t->TICO_ID, 'codigo' => $t->TICO_CODIGO, 'nombre' => $t->TICO_NOMBRE, 'incluye_igv' => $t->TICO_INCLUYE_IGV, 'activo' => $t->TICO_ACTIVO]);

        return ApiResponse::success($items);
    }

    public function tiposComprobanteStore(TipoComprobanteStoreRequest $request): JsonResponse
    {
        $codigo = $request->input('codigo');
        $nombre = $request->input('nombre');

        if (empty($codigo)) {
            $base = strtoupper(preg_replace('/[^A-Za-z0-9_]/', '', Str::snake(Str::ascii($nombre))));
            $base = substr($base, 0, 16);
            if (empty($base)) {
                $base = 'COMPROBANTE';
            }
            $codigo = $base;
            $contador = 1;
            while (TipoComprobante::where('TICO_CODIGO', $codigo)->exists()) {
                $codigo = substr($base, 0, 15) . '_' . $contador;
                $contador++;
            }
        }

        $tipo = TipoComprobante::create([
            'TICO_CODIGO' => $codigo,
            'TICO_NOMBRE' => $nombre,
            'TICO_INCLUYE_IGV' => $request->boolean('incluye_igv'),
        ]);

        return ApiResponse::success(['tipo_comprobante_id' => $tipo->TICO_ID, 'codigo' => $codigo], 'Tipo de comprobante creado.', 201);
    }

    public function tiposComprobanteUpdate(TipoComprobanteUpdateRequest $request, int $id): JsonResponse
    {
        $tipo = TipoComprobante::findOrFail($id);
        $tipo->update([
            'TICO_CODIGO' => $request->input('codigo'),
            'TICO_NOMBRE' => $request->input('nombre'),
            'TICO_INCLUYE_IGV' => $request->boolean('incluye_igv'),
            'TICO_ACTIVO' => $request->boolean('activo'),
        ]);

        return ApiResponse::success(null, 'Tipo de comprobante actualizado.');
    }

    // --- Estados ---

    public function estadosIndex(): JsonResponse
    {
        $items = Estado::select(['ESTA_ID', 'ESTA_CONTEXTO', 'ESTA_CODIGO', 'ESTA_NOMBRE', 'ESTA_COLOR', 'ESTA_ORDEN', 'ESTA_ACTIVO'])
            ->orderBy('ESTA_CONTEXTO')->orderBy('ESTA_ORDEN')->get()
            ->map(fn (Estado $e) => [
                'estado_id' => $e->ESTA_ID, 'contexto' => $e->ESTA_CONTEXTO, 'codigo' => $e->ESTA_CODIGO,
                'nombre' => $e->ESTA_NOMBRE, 'color' => $e->ESTA_COLOR, 'orden' => $e->ESTA_ORDEN, 'activo' => $e->ESTA_ACTIVO,
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
            'ESTA_ACTIVO' => $request->boolean('activo'),
        ]);

        return ApiResponse::success(null, 'Estado actualizado.');
    }

    // --- Tipos de documento ---

    public function tiposDocumentoIndex(): JsonResponse
    {
        $items = TipoDocumento::select(['TIDO_ID', 'TIDO_CODIGO', 'TIDO_NOMBRE', 'TIDO_LONGITUD', 'TIDO_ACTIVO'])
            ->orderBy('TIDO_CODIGO')->get()
            ->map(fn (TipoDocumento $t) => [
                'tipo_documento_id' => $t->TIDO_ID, 'codigo' => $t->TIDO_CODIGO,
                'nombre' => $t->TIDO_NOMBRE, 'longitud' => $t->TIDO_LONGITUD, 'activo' => $t->TIDO_ACTIVO,
            ]);

        return ApiResponse::success($items);
    }

    public function tiposDocumentoStore(TipoDocumentoStoreRequest $request): JsonResponse
    {
        $tipo = TipoDocumento::create([
            'TIDO_CODIGO' => strtoupper($request->input('codigo')),
            'TIDO_NOMBRE' => $request->input('nombre'),
            'TIDO_LONGITUD' => $request->input('longitud'),
        ]);

        return ApiResponse::success(['tipo_documento_id' => $tipo->TIDO_ID], 'Tipo de documento creado.', 201);
    }

    public function tiposDocumentoUpdate(TipoDocumentoUpdateRequest $request, int $id): JsonResponse
    {
        $tipo = TipoDocumento::findOrFail($id);
        $tipo->update([
            'TIDO_CODIGO' => strtoupper($request->input('codigo')),
            'TIDO_NOMBRE' => $request->input('nombre'),
            'TIDO_LONGITUD' => $request->input('longitud'),
            'TIDO_ACTIVO' => $request->boolean('activo'),
        ]);

        return ApiResponse::success(null, 'Tipo de documento actualizado.');
    }
}
