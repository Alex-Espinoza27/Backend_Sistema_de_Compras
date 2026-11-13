<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Entidad\AltaDirectaEntidadRequest;
use App\Http\Requests\Entidad\AprobarEntidadRequest;
use App\Http\Requests\Entidad\EntidadIndexRequest;
use App\Http\Requests\Entidad\MotivoEntidadRequest;
use App\Http\Requests\Entidad\UsuarioEntidadStoreRequest;
use App\Http\Requests\Entidad\UsuarioEntidadUpdateRequest;
use App\Models\Auditoria;
use App\Models\Entidad;
use App\Models\Estado;
use App\Models\User;
use App\Services\EntidadService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class EntidadController extends Controller
{
    public function __construct(private EntidadService $entidadService) {}

    public function index(EntidadIndexRequest $request): JsonResponse
    {
        $columnaOrden = [
            'razon_social' => 'ENTI_RAZON_SOCIAL',
            'fecha_solicitud' => 'ENTI_FECHA_SOLICITUD',
        ][$request->input('ordenar_por', 'razon_social')] ?? 'ENTI_RAZON_SOCIAL';

        $query = Entidad::query()
            ->select(['ENTI_ID', 'ENTI_NUMERO_DOCUMENTO', 'ENTI_RAZON_SOCIAL', 'ENTI_SIGLA', 'ENTI_CORREO', 'ENTI_TELEFONO', 'ENTI_ESTADO_ID', 'ENTI_FECHA_SOLICITUD'])
            ->withCount('convocatorias as convocatorias_total')
            ->when($request->filled('buscar'), function ($q) use ($request) {
                $buscar = $request->string('buscar');
                $q->where(function ($sub) use ($buscar) {
                    $sub->where('ENTI_RAZON_SOCIAL', 'ilike', "%{$buscar}%")
                        ->orWhere('ENTI_NUMERO_DOCUMENTO', 'ilike', "%{$buscar}%")
                        ->orWhere('ENTI_SIGLA', 'ilike', "%{$buscar}%");
                });
            })
            ->when($request->filled('estado'), function ($q) use ($request) {
                $estadoId = Estado::where('ESTA_CONTEXTO', 'ENTIDAD')->where('ESTA_CODIGO', $request->input('estado'))->value('ESTA_ID');
                $q->where('ENTI_ESTADO_ID', $estadoId);
            })
            ->with(['estado' => fn ($q) => $q->select(['ESTA_ID', 'ESTA_CODIGO', 'ESTA_NOMBRE', 'ESTA_COLOR'])])
            ->orderBy($columnaOrden, $request->input('direccion', 'asc'));

        $paginador = $query->paginate(
            $request->integer('por_pagina', 15),
            page: $request->integer('pagina', 1),
        );

        $paginador->getCollection()->transform(fn (Entidad $e) => [
            'entidad_id' => $e->ENTI_ID,
            'numero_documento' => $e->ENTI_NUMERO_DOCUMENTO,
            'razon_social' => $e->ENTI_RAZON_SOCIAL,
            'sigla' => $e->ENTI_SIGLA,
            'correo' => $e->ENTI_CORREO,
            'telefono' => $e->ENTI_TELEFONO,
            'estado' => ['codigo' => $e->estado->ESTA_CODIGO, 'nombre' => $e->estado->ESTA_NOMBRE, 'color' => $e->estado->ESTA_COLOR],
            // 05C hardening (pedido explícitamente por el usuario): sin el
            // ->format(), salía el ISO crudo de Eloquent ("…T17:09:50.000000Z").
            'fecha_solicitud' => $e->ENTI_FECHA_SOLICITUD?->format('Y-m-d H:i'),
            'convocatorias_total' => $e->convocatorias_total,
        ]);

        return ApiResponse::paginado($paginador);
    }

    public function show(int $id): JsonResponse
    {
        $entidad = Entidad::query()
            ->select([
                'ENTI_ID', 'ENTI_NUMERO_DOCUMENTO', 'ENTI_RAZON_SOCIAL', 'ENTI_SIGLA', 'ENTI_CORREO', 'ENTI_TELEFONO',
                'ENTI_DIRECCION', 'ENTI_PERSONA_CONTACTO', 'ENTI_ESTADO_ID', 'ENTI_MOTIVO_OBSERVACION',
                'ENTI_FECHA_SOLICITUD', 'ENTI_FECHA_RESOLUCION', 'ENTI_RESUELTA_POR',
            ])
            ->with([
                'estado' => fn ($q) => $q->select(['ESTA_ID', 'ESTA_CODIGO', 'ESTA_NOMBRE', 'ESTA_COLOR']),
                'usuarios' => fn ($q) => $q->select(['id', 'name', 'email', 'entidad_id']),
            ])
            ->findOrFail($id);

        $resueltaPor = $entidad->ENTI_RESUELTA_POR
            ? User::select(['id', 'name'])->find($entidad->ENTI_RESUELTA_POR)
            : null;

        $historico = Auditoria::select(['AUDI_ID', 'AUDI_USER_ID', 'AUDI_ACCION', 'AUDI_VALOR_NUEVO', 'AUDI_FECHA'])
            ->where('AUDI_MODULO', 'ENTIDADES')
            ->where('AUDI_TABLA', 'ENTIDAD')
            ->where('AUDI_REGISTRO_ID', $entidad->ENTI_ID)
            ->with(['user' => fn ($q) => $q->select(['id', 'name'])])
            ->orderByDesc('AUDI_FECHA')
            ->get()
            ->map(fn (Auditoria $a) => [
                'accion' => $a->AUDI_ACCION,
                'usuario' => $a->user?->name,
                'detalle' => $a->AUDI_VALOR_NUEVO,
                'fecha' => $a->AUDI_FECHA,
            ]);

        return ApiResponse::success([
            'entidad_id' => $entidad->ENTI_ID,
            'numero_documento' => $entidad->ENTI_NUMERO_DOCUMENTO,
            'razon_social' => $entidad->ENTI_RAZON_SOCIAL,
            'sigla' => $entidad->ENTI_SIGLA,
            'correo' => $entidad->ENTI_CORREO,
            'telefono' => $entidad->ENTI_TELEFONO,
            'direccion' => $entidad->ENTI_DIRECCION,
            'persona_contacto' => $entidad->ENTI_PERSONA_CONTACTO,
            'estado' => ['codigo' => $entidad->estado->ESTA_CODIGO, 'nombre' => $entidad->estado->ESTA_NOMBRE, 'color' => $entidad->estado->ESTA_COLOR],
            'motivo_observacion' => $entidad->ENTI_MOTIVO_OBSERVACION,
            'fecha_solicitud' => $entidad->ENTI_FECHA_SOLICITUD,
            'fecha_resolucion' => $entidad->ENTI_FECHA_RESOLUCION,
            'resuelta_por' => $resueltaPor ? ['id' => $resueltaPor->id, 'nombre' => $resueltaPor->name] : null,
            'usuarios' => $entidad->usuarios->map(fn (User $u) => ['id' => $u->id, 'nombre' => $u->name, 'correo' => $u->email]),
            'historico' => $historico,
        ]);
    }

    public function store(AltaDirectaEntidadRequest $request): JsonResponse
    {
        $entidad = $this->entidadService->altaDirecta($request->validated());

        return ApiResponse::success(['entidad_id' => $entidad->ENTI_ID], 'Entidad registrada y aprobada.', 201);
    }

    public function aprobar(AprobarEntidadRequest $request, int $id): JsonResponse
    {
        $entidad = Entidad::select(['ENTI_ID', 'ENTI_ESTADO_ID'])->findOrFail($id);

        $this->entidadService->aprobar($entidad, $request->input('usuario_inicial'));

        return ApiResponse::success(null, 'Entidad aprobada.');
    }

    public function observar(MotivoEntidadRequest $request, int $id): JsonResponse
    {
        $entidad = Entidad::select(['ENTI_ID', 'ENTI_ESTADO_ID', 'ENTI_CORREO'])->findOrFail($id);

        $this->entidadService->observar($entidad, $request->input('motivo'));

        return ApiResponse::success(null, 'Entidad observada.');
    }

    public function rechazar(MotivoEntidadRequest $request, int $id): JsonResponse
    {
        $entidad = Entidad::select(['ENTI_ID', 'ENTI_ESTADO_ID', 'ENTI_CORREO'])->findOrFail($id);

        $this->entidadService->rechazar($entidad, $request->input('motivo'));

        return ApiResponse::success(null, 'Entidad rechazada.');
    }

    public function suspender(MotivoEntidadRequest $request, int $id): JsonResponse
    {
        $entidad = Entidad::select(['ENTI_ID', 'ENTI_ESTADO_ID', 'ENTI_CORREO'])->findOrFail($id);

        $this->entidadService->suspender($entidad, $request->input('motivo'));

        return ApiResponse::success(null, 'Entidad suspendida.');
    }

    public function reactivar(int $id): JsonResponse
    {
        $entidad = Entidad::select(['ENTI_ID', 'ENTI_ESTADO_ID'])->findOrFail($id);

        $this->entidadService->reactivar($entidad);

        return ApiResponse::success(null, 'Entidad reactivada.');
    }

    public function usuarios(int $id): JsonResponse
    {
        Entidad::select(['ENTI_ID'])->findOrFail($id);

        $usuarios = User::select(['id', 'name', 'email'])
            ->where('entidad_id', $id)
            ->orderBy('name')
            ->get()
            ->map(fn (User $u) => ['id' => $u->id, 'nombre' => $u->name, 'correo' => $u->email]);

        return ApiResponse::success($usuarios);
    }

    public function storeUsuario(UsuarioEntidadStoreRequest $request, int $id): JsonResponse
    {
        $entidad = Entidad::select(['ENTI_ID'])->findOrFail($id);

        $usuario = $this->entidadService->crearUsuario($entidad, $request->validated());

        return ApiResponse::success(['id' => $usuario->id], 'Usuario creado.', 201);
    }

    public function updateUsuario(UsuarioEntidadUpdateRequest $request, int $id, int $userId): JsonResponse
    {
        Entidad::select(['ENTI_ID'])->findOrFail($id);

        $usuario = User::select(['id', 'name', 'email'])->where('entidad_id', $id)->findOrFail($userId);

        $this->entidadService->actualizarUsuario($usuario, $request->validated());

        return ApiResponse::success(null, 'Usuario actualizado.');
    }

    public function destroyUsuario(int $id, int $userId): JsonResponse
    {
        Entidad::select(['ENTI_ID'])->findOrFail($id);

        $usuario = User::select(['id'])->where('entidad_id', $id)->findOrFail($userId);

        $this->entidadService->eliminarUsuario($usuario);

        return ApiResponse::success(null, 'Usuario eliminado.');
    }
}
