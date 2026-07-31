<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ConfiguracionUpdateRequest;
use App\Http\Requests\Admin\UitStoreRequest;
use App\Http\Requests\Admin\UitUpdateRequest;
use App\Models\Configuracion;
use App\Models\Uit;
use App\Services\AdministracionService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class AdminConfiguracionController extends Controller
{
    public function __construct(private AdministracionService $administracionService) {}

    public function index(): JsonResponse
    {
        $items = Configuracion::select(['CONF_CLAVE', 'CONF_VALOR', 'CONF_TIPO', 'CONF_DESCRIPCION'])
            ->orderBy('CONF_CLAVE')
            ->get()
            ->map(fn (Configuracion $c) => [
                'clave' => $c->CONF_CLAVE,
                'valor' => $c->CONF_VALOR,
                'tipo' => $c->CONF_TIPO,
                'descripcion' => $c->CONF_DESCRIPCION,
            ]);

        return ApiResponse::success($items);
    }

    public function update(ConfiguracionUpdateRequest $request): JsonResponse
    {
        $this->administracionService->actualizarConfiguracion($request->input('configuraciones'));

        return ApiResponse::success(null, 'Configuración actualizada.');
    }

    public function uitIndex(): JsonResponse
    {
        $items = Uit::select(['UITA_ID', 'UITA_ANIO', 'UITA_VALOR', 'UITA_VIGENTE'])
            ->orderByDesc('UITA_ANIO')
            ->get()
            ->map(fn (Uit $u) => ['uit_id' => $u->UITA_ID, 'anio' => $u->UITA_ANIO, 'valor' => $u->UITA_VALOR, 'vigente' => $u->UITA_VIGENTE]);

        return ApiResponse::success($items);
    }

    public function uitStore(UitStoreRequest $request): JsonResponse
    {
        $uit = $this->administracionService->crearUit($request->validated());

        return ApiResponse::success(['uit_id' => $uit->UITA_ID], 'Valor de UIT creado.', 201);
    }

    public function uitUpdate(UitUpdateRequest $request, int $id): JsonResponse
    {
        $uit = Uit::select(['UITA_ID', 'UITA_ANIO', 'UITA_VALOR', 'UITA_VIGENTE'])->findOrFail($id);

        $this->administracionService->actualizarUit($uit, $request->validated());

        return ApiResponse::success(null, 'Valor de UIT actualizado.');
    }
}
