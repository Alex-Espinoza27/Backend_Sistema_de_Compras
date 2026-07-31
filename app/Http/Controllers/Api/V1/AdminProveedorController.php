<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Proveedor;
use App\Services\AdministracionService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class AdminProveedorController extends Controller
{
    public function __construct(private AdministracionService $administracionService) {}

    public function reactivar(int $id): JsonResponse
    {
        $proveedor = Proveedor::select(['PROV_ID', 'PROV_ESTADO_ID'])->findOrFail($id);

        $this->administracionService->reactivarProveedor($proveedor);

        return ApiResponse::success(null, 'Proveedor reactivado.');
    }
}
