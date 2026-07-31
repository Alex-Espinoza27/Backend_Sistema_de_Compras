<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Notificacion;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificacionController extends Controller
{
    public function contador(Request $request): JsonResponse
    {
        $noLeidas = Notificacion::where('NOTI_USER_ID', $request->user()->id)
            ->where('NOTI_LEIDA', false)
            ->count();

        return ApiResponse::success(['no_leidas' => $noLeidas]);
    }

    public function index(Request $request): JsonResponse
    {
        $items = Notificacion::query()
            ->select(['NOTI_ID', 'NOTI_TIPO', 'NOTI_TITULO', 'NOTI_MENSAJE', 'NOTI_LEIDA', 'NOTI_REFERENCIA_TIPO', 'NOTI_REFERENCIA_ID', 'NOTI_CREADO_EN'])
            ->where('NOTI_USER_ID', $request->user()->id)
            ->orderByDesc('NOTI_CREADO_EN')
            ->limit(50)
            ->get()
            ->map(fn (Notificacion $n) => [
                'notificacion_id' => $n->NOTI_ID,
                'tipo' => $n->NOTI_TIPO,
                'titulo' => $n->NOTI_TITULO,
                'mensaje' => $n->NOTI_MENSAJE,
                'leida' => $n->NOTI_LEIDA,
                'referencia_tipo' => $n->NOTI_REFERENCIA_TIPO,
                'referencia_id' => $n->NOTI_REFERENCIA_ID,
                'creado_en' => $n->NOTI_CREADO_EN,
            ]);

        return ApiResponse::success(['items' => $items]);
    }

    public function leer(Request $request, int $id): JsonResponse
    {
        $notificacion = Notificacion::select(['NOTI_ID', 'NOTI_USER_ID', 'NOTI_LEIDA'])
            ->where('NOTI_USER_ID', $request->user()->id)
            ->findOrFail($id);

        if (! $notificacion->NOTI_LEIDA) {
            $notificacion->NOTI_LEIDA = true;
            $notificacion->save();
        }

        return ApiResponse::success(['no_leidas' => $this->contarNoLeidas($request->user()->id)]);
    }

    public function leerTodas(Request $request): JsonResponse
    {
        Notificacion::where('NOTI_USER_ID', $request->user()->id)
            ->where('NOTI_LEIDA', false)
            ->update(['NOTI_LEIDA' => true]);

        return ApiResponse::success(['no_leidas' => 0]);
    }

    private function contarNoLeidas(int $userId): int
    {
        return Notificacion::where('NOTI_USER_ID', $userId)->where('NOTI_LEIDA', false)->count();
    }
}
