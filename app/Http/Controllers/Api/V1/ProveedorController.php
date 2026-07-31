<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Proveedor\ActualizarMisDatosRequest;
use App\Http\Requests\Proveedor\ActualizarMisRubrosRequest;
use App\Http\Requests\Proveedor\SuspenderProveedorRequest;
use App\Models\Proveedor;
use App\Models\Rubro;
use App\Services\AuditoriaService;
use App\Services\EstadoService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ProveedorController extends Controller
{
    public function __construct(private AuditoriaService $auditoria) {}

    public function misDatos(Request $request): JsonResponse
    {
        return ApiResponse::success($this->formatearMisDatos($this->proveedorAutenticado($request)));
    }

    public function actualizarMisDatos(ActualizarMisDatosRequest $request): JsonResponse
    {
        $proveedor = $this->proveedorAutenticado($request);

        $valorAnterior = $proveedor->only(['PROV_CORREO', 'PROV_TELEFONO', 'PROV_PERSONA_CONTACTO']);

        $proveedor->update([
            'PROV_CORREO' => $request->input('correo'),
            'PROV_TELEFONO' => $request->input('telefono'),
            'PROV_PERSONA_CONTACTO' => $request->input('persona_contacto'),
        ]);

        $this->auditoria->registrar(
            'PROVEEDORES',
            'ACTUALIZAR',
            'PROVEEDOR',
            $proveedor->PROV_ID,
            $valorAnterior,
            $proveedor->only(['PROV_CORREO', 'PROV_TELEFONO', 'PROV_PERSONA_CONTACTO']),
        );

        return ApiResponse::success($this->formatearMisDatos($proveedor->fresh()), 'Datos actualizados.');
    }

    public function actualizarMisRubros(ActualizarMisRubrosRequest $request): JsonResponse
    {
        $proveedor = $this->proveedorAutenticado($request);

        $rubroIds = array_merge($request->input('rubros_servicios', []), $request->input('rubros_bienes', []));

        DB::transaction(function () use ($proveedor, $rubroIds) {
            $proveedor->rubros()->sync($rubroIds);
        });

        $this->auditoria->registrar('PROVEEDORES', 'ACTUALIZAR', 'PROVEEDOR', $proveedor->PROV_ID, null, ['rubros' => $rubroIds]);

        return ApiResponse::success($this->formatearMisDatos($proveedor->fresh()), 'Rubros actualizados.');
    }

    public function suspender(SuspenderProveedorRequest $request, EstadoService $estadoService): JsonResponse
    {
        $proveedor = $this->proveedorAutenticado($request);

        $estadoService->transicionar(
            modelo: $proveedor,
            columnaEstado: 'PROV_ESTADO_ID',
            contexto: 'PROVEEDOR',
            codigoDestino: 'SUSPENDIDO',
            transicionesPermitidas: ['ACTIVO' => ['SUSPENDIDO']],
            modulo: 'PROVEEDORES',
            tabla: 'PROVEEDOR',
        );

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return ApiResponse::success(null, 'Tu cuenta ha sido suspendida.');
    }

    private function proveedorAutenticado(Request $request): Proveedor
    {
        return $request->user()->proveedor()->firstOrFail();
    }

    private function formatearMisDatos(Proveedor $proveedor): array
    {
        $proveedor->loadMissing(['estado', 'rubros']);

        return [
            'proveedor_id' => $proveedor->PROV_ID,
            'numero_documento' => $proveedor->PROV_NUMERO_DOCUMENTO,
            'razon_social' => $proveedor->PROV_RAZON_SOCIAL,
            'tipo_persona' => $proveedor->PROV_TIPO_PERSONA,
            'correo' => $proveedor->PROV_CORREO,
            'telefono' => $proveedor->PROV_TELEFONO,
            'persona_contacto' => $proveedor->PROV_PERSONA_CONTACTO,
            'estado' => [
                'codigo' => $proveedor->estado->ESTA_CODIGO,
                'color' => $proveedor->estado->ESTA_COLOR,
            ],
            'rubros_servicios' => $proveedor->rubros->where('RUBR_TIPO', 'SERVICIO')->values()->map(fn (Rubro $r) => [
                'rubro_id' => $r->RUBR_ID,
                'nombre' => $r->RUBR_NOMBRE,
            ]),
            'rubros_bienes' => $proveedor->rubros->where('RUBR_TIPO', 'BIEN')->values()->map(fn (Rubro $r) => [
                'rubro_id' => $r->RUBR_ID,
                'nombre' => $r->RUBR_NOMBRE,
            ]),
        ];
    }
}
