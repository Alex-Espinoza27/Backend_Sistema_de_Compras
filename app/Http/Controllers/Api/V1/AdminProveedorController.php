<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminProveedorIndexRequest;
use App\Http\Requests\Admin\AdminProveedorStoreRequest;
use App\Http\Requests\Admin\AdminProveedorUpdateRequest;
use App\Models\Estado;
use App\Models\Proveedor;
use App\Models\Rubro;
use App\Services\AdministracionService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class AdminProveedorController extends Controller
{
    public function __construct(private AdministracionService $administracionService) {}

    /** Listado de solo lectura para uso administrativo (I2: encontrar el ID a
     * reactivar) y como combo del filtro `proveedor_id` en Reportes (J2) —
     * no existía ningún endpoint que listara proveedores fuera del registro
     * público. Mismo patrón que `EntidadController::index` (Fase 10). */
    public function index(AdminProveedorIndexRequest $request): JsonResponse
    {
        $columnaOrden = [
            'razon_social' => 'PROV_RAZON_SOCIAL',
        ][$request->input('ordenar_por', 'razon_social')] ?? 'PROV_RAZON_SOCIAL';

        $query = Proveedor::query()
            ->select(['PROV_ID', 'PROV_NUMERO_DOCUMENTO', 'PROV_RAZON_SOCIAL', 'PROV_CORREO', 'PROV_ESTADO_ID'])
            ->when($request->filled('buscar'), function ($q) use ($request) {
                $buscar = $request->string('buscar');
                $q->where(function ($sub) use ($buscar) {
                    $sub->where('PROV_RAZON_SOCIAL', 'ilike', "%{$buscar}%")
                        ->orWhere('PROV_NUMERO_DOCUMENTO', 'ilike', "%{$buscar}%");
                });
            })
            ->when($request->filled('estado'), function ($q) use ($request) {
                $estadoId = Estado::where('ESTA_CONTEXTO', 'PROVEEDOR')->where('ESTA_CODIGO', $request->input('estado'))->value('ESTA_ID');
                $q->where('PROV_ESTADO_ID', $estadoId);
            })
            ->with(['estado' => fn ($q) => $q->select(['ESTA_ID', 'ESTA_CODIGO', 'ESTA_NOMBRE', 'ESTA_COLOR'])])
            ->orderBy($columnaOrden, $request->input('direccion', 'asc'));

        $paginador = $query->paginate(
            $request->integer('por_pagina', 15),
            page: $request->integer('pagina', 1),
        );

        $paginador->getCollection()->transform(fn (Proveedor $p) => [
            'proveedor_id' => $p->PROV_ID,
            'numero_documento' => $p->PROV_NUMERO_DOCUMENTO,
            'razon_social' => $p->PROV_RAZON_SOCIAL,
            'correo' => $p->PROV_CORREO,
            'estado' => ['codigo' => $p->estado->ESTA_CODIGO, 'nombre' => $p->estado->ESTA_NOMBRE, 'color' => $p->estado->ESTA_COLOR],
        ]);

        return ApiResponse::paginado($paginador);
    }

    public function reactivar(int $id): JsonResponse
    {
        $proveedor = Proveedor::select(['PROV_ID', 'PROV_ESTADO_ID'])->findOrFail($id);

        $this->administracionService->reactivarProveedor($proveedor);

        return ApiResponse::success(null, 'Proveedor reactivado.');
    }

    /** Suspensión iniciada por un administrador (05C hardening) — distinta
     * de la auto-suspensión de `ProveedorController::suspender`. */
    public function suspender(int $id): JsonResponse
    {
        $proveedor = Proveedor::select(['PROV_ID', 'PROV_ESTADO_ID'])->findOrFail($id);

        $this->administracionService->suspenderProveedor($proveedor);

        return ApiResponse::success(null, 'Proveedor suspendido.');
    }

    /** Detalle completo para el formulario de edición (05C hardening) —
     * mismo shape que `ProveedorController::formatearMisDatos()` más el
     * código/nombre del tipo de documento y el origen del dato. */
    public function show(int $id): JsonResponse
    {
        $proveedor = Proveedor::query()
            ->with([
                'tipoDocumento' => fn ($q) => $q->select(['TIDO_ID', 'TIDO_CODIGO', 'TIDO_NOMBRE']),
                'estado' => fn ($q) => $q->select(['ESTA_ID', 'ESTA_CODIGO', 'ESTA_NOMBRE', 'ESTA_COLOR']),
                'rubros',
            ])
            ->findOrFail($id);

        return ApiResponse::success([
            'proveedor_id' => $proveedor->PROV_ID,
            'numero_documento' => $proveedor->PROV_NUMERO_DOCUMENTO,
            'tipo_documento' => $proveedor->tipoDocumento->TIDO_CODIGO,
            'razon_social' => $proveedor->PROV_RAZON_SOCIAL,
            'tipo_persona' => $proveedor->PROV_TIPO_PERSONA,
            'correo' => $proveedor->PROV_CORREO,
            'telefono' => $proveedor->PROV_TELEFONO,
            'persona_contacto' => $proveedor->PROV_PERSONA_CONTACTO,
            'estado' => ['codigo' => $proveedor->estado->ESTA_CODIGO, 'nombre' => $proveedor->estado->ESTA_NOMBRE, 'color' => $proveedor->estado->ESTA_COLOR],
            'origen_dato' => $proveedor->PROV_ORIGEN_DATO,
            'rubros_servicios' => $proveedor->rubros->where('RUBR_TIPO', 'SERVICIO')->values()->map(fn (Rubro $r) => ['rubro_id' => $r->RUBR_ID, 'nombre' => $r->RUBR_NOMBRE]),
            'rubros_bienes' => $proveedor->rubros->where('RUBR_TIPO', 'BIEN')->values()->map(fn (Rubro $r) => ['rubro_id' => $r->RUBR_ID, 'nombre' => $r->RUBR_NOMBRE]),
        ]);
    }

    public function store(AdminProveedorStoreRequest $request): JsonResponse
    {
        $proveedor = $this->administracionService->crearProveedor($request->validated());

        return ApiResponse::success(['proveedor_id' => $proveedor->PROV_ID], 'Proveedor creado.', 201);
    }

    public function update(AdminProveedorUpdateRequest $request, int $id): JsonResponse
    {
        $proveedor = Proveedor::findOrFail($id);

        $this->administracionService->actualizarProveedor($proveedor, $request->validated());

        return ApiResponse::success(null, 'Proveedor actualizado.');
    }
}
