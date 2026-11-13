<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ConfiguracionUpdateRequest;
use App\Http\Requests\Admin\ProbarCorreoRequest;
use App\Http\Requests\Admin\UitStoreRequest;
use App\Http\Requests\Admin\UitUpdateRequest;
use App\Models\Configuracion;
use App\Models\Uit;
use App\Services\AdministracionService;
use App\Services\ConvocatoriaService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminConfiguracionController extends Controller
{
    public function __construct(
        private AdministracionService $administracionService,
        private ConvocatoriaService $convocatoriaService,
    ) {}

    public function index(): JsonResponse
    {
        $items = Configuracion::select(['CONF_CLAVE', 'CONF_VALOR', 'CONF_TIPO', 'CONF_DESCRIPCION'])
            ->orderBy('CONF_CLAVE')
            ->get()
            ->map(fn (Configuracion $c) => [
                'clave' => $c->CONF_CLAVE,
                // CONF_TIPO=SECRETO (credenciales SMTP, 05C hardening): nunca
                // se devuelve el valor cifrado — solo si ya hay uno guardado,
                // para que el frontend pueda mostrar un indicador sin
                // exponerlo. Dejarlo vacío al guardar significa "no cambiar".
                'valor' => $c->CONF_TIPO === 'SECRETO' ? ($c->CONF_VALOR ? '••••••••' : '') : $c->CONF_VALOR,
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

    /** Envía un correo de prueba con la configuración SMTP actual (05C
     * hardening, pedido explícitamente por el usuario): forma directa de
     * confirmar que las credenciales guardadas realmente funcionan. */
    public function probarCorreo(ProbarCorreoRequest $request): JsonResponse
    {
        $this->administracionService->enviarCorreoPrueba($request->string('destino'));

        return ApiResponse::success(null, 'Correo de prueba enviado.');
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

    /** 05C hardening (pedido explícitamente por el usuario): `PLANTILLA_DJ_DEFECTO`
     * era un campo de texto libre sin ninguna forma real de subir el archivo
     * — nadie podía adivinar que ahí había que escribir el ID numérico de un
     * `Archivo`. Ahora es un archivo real, igual que la plantilla propia de
     * cada convocatoria. */
    public function plantillaDjDefectoStore(Request $request): JsonResponse
    {
        $request->validate(['archivo' => ['required', 'file']]);

        $archivo = $this->convocatoriaService->definirPlantillaDjDefecto($request->file('archivo'));

        return ApiResponse::success(['archivo_id' => $archivo->ARCH_ID, 'nombre' => $archivo->ARCH_NOMBRE_ORIGINAL], 'Plantilla por defecto actualizada.');
    }

    public function plantillaDjDefectoDestroy(): JsonResponse
    {
        $this->convocatoriaService->quitarPlantillaDjDefecto();

        return ApiResponse::success(null, 'Plantilla por defecto eliminada.');
    }
}
