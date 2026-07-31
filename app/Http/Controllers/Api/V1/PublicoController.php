<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Entidad\SolicitudEntidadRequest;
use App\Http\Requests\Publico\ConvocatoriasRequest;
use App\Http\Requests\Publico\EntidadesRequest;
use App\Models\Archivo;
use App\Models\Configuracion;
use App\Models\Convocatoria;
use App\Models\Entidad;
use App\Models\Estado;
use App\Services\Captcha\CaptchaService;
use App\Services\EntidadService;
use App\Services\ResultadoService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

class PublicoController extends Controller
{
    public function __construct(
        private ResultadoService $resultadoService,
        private EntidadService $entidadService,
        private CaptchaService $captcha,
    ) {}

    public function solicitudEntidad(SolicitudEntidadRequest $request): JsonResponse
    {
        if (! $this->captcha->verificar($request->input('captcha'))) {
            return ApiResponse::error('El captcha no es válido.', null, 422);
        }

        $entidad = $this->entidadService->solicitar($request->validated());

        return ApiResponse::success(['entidad_id' => $entidad->ENTI_ID], 'Solicitud registrada. Un administrador la revisará pronto.', 201);
    }

    public function entidades(EntidadesRequest $request): JsonResponse
    {
        $estadoAprobadaId = Estado::where('ESTA_CONTEXTO', 'ENTIDAD')->where('ESTA_CODIGO', 'APROBADA')->value('ESTA_ID');
        $estadosConvocatoria = Estado::where('ESTA_CONTEXTO', 'CONVOCATORIA')->pluck('ESTA_ID', 'ESTA_CODIGO');
        $ahora = now();

        $entidades = Entidad::query()
            ->select(['ENTI_ID', 'ENTI_RAZON_SOCIAL', 'ENTI_SIGLA'])
            ->where('ENTI_ESTADO_ID', $estadoAprobadaId)
            ->when($request->filled('buscar'), function ($query) use ($request) {
                $buscar = $request->string('buscar');
                $query->where(function ($sub) use ($buscar) {
                    $sub->where('ENTI_RAZON_SOCIAL', 'ilike', "%{$buscar}%")
                        ->orWhere('ENTI_SIGLA', 'ilike', "%{$buscar}%");
                });
            })
            ->withCount([
                'convocatorias as convocatorias_total' => function ($query) use ($estadosConvocatoria) {
                    $query->where('CONV_ESTADO_ID', '!=', $estadosConvocatoria['BORRADOR']);
                },
                'convocatorias as convocatorias_vigentes' => function ($query) use ($estadosConvocatoria, $ahora) {
                    $query->where('CONV_ESTADO_ID', $estadosConvocatoria['PUBLICADA'])
                        ->where('CONV_FECHA_INICIO', '<=', $ahora)
                        ->where('CONV_FECHA_FIN', '>=', $ahora);
                },
            ])
            ->orderBy('ENTI_RAZON_SOCIAL')
            ->get();

        if ($request->boolean('solo_con_vigentes')) {
            $entidades = $entidades->filter(fn (Entidad $entidad) => $entidad->convocatorias_vigentes > 0)->values();
        }

        $items = $entidades->map(fn (Entidad $entidad) => [
            'entidad_id' => $entidad->ENTI_ID,
            'razon_social' => $entidad->ENTI_RAZON_SOCIAL,
            'sigla' => $entidad->ENTI_SIGLA,
            'convocatorias_vigentes' => $entidad->convocatorias_vigentes,
            'convocatorias_total' => $entidad->convocatorias_total,
        ])->values();

        return ApiResponse::success(['items' => $items])
            ->header('Cache-Control', 'public, max-age=60');
    }

    public function convocatorias(ConvocatoriasRequest $request): JsonResponse
    {
        $estados = Estado::where('ESTA_CONTEXTO', 'CONVOCATORIA')->pluck('ESTA_ID', 'ESTA_CODIGO');
        $estadoGrupo = $request->string('estado_grupo')->toString();

        $estadosDelGrupo = match ($estadoGrupo) {
            'VIGENTES' => [$estados['PUBLICADA']],
            'EN_EVALUACION' => [$estados['EN_EVALUACION']],
            'CONCLUIDAS' => [$estados['ADJUDICADA'], $estados['DESIERTA'], $estados['CANCELADA']],
        };

        $columnaOrden = [
            'fecha_fin' => 'CONV_FECHA_FIN',
            'fecha_inicio' => 'CONV_FECHA_INICIO',
            'numero' => 'CONV_NUMERO',
        ][$request->input('ordenar_por', 'fecha_fin')] ?? 'CONV_FECHA_FIN';

        $direccion = $request->input('direccion', 'asc');
        $ahora = now();

        $query = Convocatoria::query()
            ->select([
                'CONV_ID', 'CONV_REQUERIMIENTO_ID', 'CONV_ENTIDAD_ID', 'CONV_NUMERO',
                'CONV_NOMBRE_SERVICIO', 'CONV_FECHA_INICIO', 'CONV_FECHA_FIN', 'CONV_ESTADO_ID',
            ])
            ->selectRaw('fn_convocatoria_urgencia("CONV_ID") as urgencia_dias')
            ->whereIn('CONV_ESTADO_ID', $estadosDelGrupo)
            ->when($estadoGrupo === 'VIGENTES', function ($sub) use ($ahora) {
                $sub->where('CONV_FECHA_INICIO', '<=', $ahora)->where('CONV_FECHA_FIN', '>=', $ahora);
            })
            ->when($request->filled('entidad_id'), fn ($sub) => $sub->where('CONV_ENTIDAD_ID', $request->integer('entidad_id')))
            ->when($request->filled('buscar'), function ($sub) use ($request) {
                $buscar = $request->string('buscar');
                $sub->where(function ($sub2) use ($buscar) {
                    $sub2->where('CONV_NOMBRE_SERVICIO', 'ilike', "%{$buscar}%")
                        ->orWhere('CONV_NUMERO', 'ilike', "%{$buscar}%");
                });
            })
            ->with([
                'entidad' => fn ($sub) => $sub->select(['ENTI_ID', 'ENTI_SIGLA']),
                'estado' => fn ($sub) => $sub->select(['ESTA_ID', 'ESTA_CODIGO', 'ESTA_NOMBRE', 'ESTA_COLOR']),
            ])
            ->orderBy($columnaOrden, $direccion);

        $paginador = $query->paginate(
            $request->integer('por_pagina', 15),
            page: $request->integer('pagina', 1),
        );

        $diasUrgencia = (int) Configuracion::where('CONF_CLAVE', 'DIAS_URGENCIA')->value('CONF_VALOR');

        $requerimientoIds = collect($paginador->items())->pluck('CONV_REQUERIMIENTO_ID')->unique()->values();
        $archivosTdr = Archivo::query()
            ->select(['ARCH_ID', 'ARCH_REFERENCIA_ID', 'ARCH_NOMBRE_ORIGINAL'])
            ->where('ARCH_CONTEXTO', 'REQUERIMIENTO_TDR')
            ->whereIn('ARCH_REFERENCIA_ID', $requerimientoIds)
            ->get()
            ->keyBy('ARCH_REFERENCIA_ID');

        $paginador->getCollection()->transform(function (Convocatoria $conv) use ($estadoGrupo, $diasUrgencia, $archivosTdr) {
            $archivoTdr = $archivosTdr->get($conv->CONV_REQUERIMIENTO_ID);

            $accion = match ($estadoGrupo) {
                'VIGENTES' => 'POSTULAR',
                'EN_EVALUACION' => 'NINGUNA',
                'CONCLUIDAS' => $conv->estado->ESTA_CODIGO === 'ADJUDICADA' ? 'RESULTADOS' : 'MOTIVO',
            };

            $item = [
                'convocatoria_id' => $conv->CONV_ID,
                'numero' => $conv->CONV_NUMERO,
                'nombre_servicio' => $conv->CONV_NOMBRE_SERVICIO,
                'entidad' => [
                    'entidad_id' => $conv->entidad->ENTI_ID,
                    'sigla' => $conv->entidad->ENTI_SIGLA,
                ],
                'fecha_inicio' => $conv->CONV_FECHA_INICIO,
                'fecha_fin' => $conv->CONV_FECHA_FIN,
                'estado' => [
                    'codigo' => $conv->estado->ESTA_CODIGO,
                    'nombre' => $conv->estado->ESTA_NOMBRE,
                    'color' => $conv->estado->ESTA_COLOR,
                ],
                'archivo_tdr' => $archivoTdr
                    ? ['archivo_id' => $archivoTdr->ARCH_ID, 'nombre' => $archivoTdr->ARCH_NOMBRE_ORIGINAL]
                    : null,
                'accion' => $accion,
            ];

            if ($estadoGrupo === 'VIGENTES') {
                $item['urgencia_dias'] = (int) $conv->urgencia_dias;
                $item['es_urgente'] = (int) $conv->urgencia_dias <= $diasUrgencia;
            }

            return $item;
        });

        return ApiResponse::paginado($paginador)
            ->header('Cache-Control', 'public, max-age=30');
    }

    public function convocatoria(int $id): JsonResponse
    {
        $borradorId = Estado::where('ESTA_CONTEXTO', 'CONVOCATORIA')->where('ESTA_CODIGO', 'BORRADOR')->value('ESTA_ID');

        $conv = Convocatoria::query()
            ->select([
                'CONV_ID', 'CONV_REQUERIMIENTO_ID', 'CONV_ENTIDAD_ID', 'CONV_NUMERO',
                'CONV_NOMBRE_SERVICIO', 'CONV_FECHA_INICIO', 'CONV_FECHA_FIN',
                'CONV_ESTADO_ID', 'CONV_MOTIVO_CIERRE',
            ])
            ->where('CONV_ESTADO_ID', '!=', $borradorId)
            ->with([
                'entidad' => fn ($sub) => $sub->select(['ENTI_ID', 'ENTI_RAZON_SOCIAL', 'ENTI_SIGLA']),
                'estado' => fn ($sub) => $sub->select(['ESTA_ID', 'ESTA_CODIGO', 'ESTA_NOMBRE', 'ESTA_COLOR']),
                'requerimiento' => fn ($sub) => $sub->select(['REQU_ID']),
                'requerimiento.items' => fn ($sub) => $sub->select([
                    'REIT_ID', 'REIT_REQUERIMIENTO_ID', 'REIT_NUMERO', 'REIT_DESCRIPCION',
                    'REIT_UNIDAD_MEDIDA_ID', 'REIT_CANTIDAD',
                ])->orderBy('REIT_NUMERO'),
                'requerimiento.items.unidadMedida' => fn ($sub) => $sub->select(['UNME_ID', 'UNME_CODIGO', 'UNME_NOMBRE']),
            ])
            ->findOrFail($id);

        $archivoTdr = Archivo::select(['ARCH_ID', 'ARCH_NOMBRE_ORIGINAL'])
            ->where('ARCH_CONTEXTO', 'REQUERIMIENTO_TDR')
            ->where('ARCH_REFERENCIA_ID', $conv->CONV_REQUERIMIENTO_ID)
            ->first();

        $plantillaDj = Archivo::select(['ARCH_ID', 'ARCH_NOMBRE_ORIGINAL'])
            ->where('ARCH_CONTEXTO', 'CONVOCATORIA_PLANTILLA_DJ')
            ->where('ARCH_REFERENCIA_ID', $conv->CONV_ID)
            ->first();

        return ApiResponse::success([
            'convocatoria_id' => $conv->CONV_ID,
            'numero' => $conv->CONV_NUMERO,
            'nombre_servicio' => $conv->CONV_NOMBRE_SERVICIO,
            'entidad' => [
                'entidad_id' => $conv->entidad->ENTI_ID,
                'razon_social' => $conv->entidad->ENTI_RAZON_SOCIAL,
                'sigla' => $conv->entidad->ENTI_SIGLA,
            ],
            'fecha_inicio' => $conv->CONV_FECHA_INICIO,
            'fecha_fin' => $conv->CONV_FECHA_FIN,
            'estado' => [
                'codigo' => $conv->estado->ESTA_CODIGO,
                'nombre' => $conv->estado->ESTA_NOMBRE,
                'color' => $conv->estado->ESTA_COLOR,
            ],
            'motivo_cierre' => $conv->CONV_MOTIVO_CIERRE,
            'items' => $conv->requerimiento->items->map(fn ($item) => [
                'numero' => $item->REIT_NUMERO,
                'descripcion' => $item->REIT_DESCRIPCION,
                'unidad_medida' => [
                    'codigo' => $item->unidadMedida->UNME_CODIGO,
                    'nombre' => $item->unidadMedida->UNME_NOMBRE,
                ],
                'cantidad' => $item->REIT_CANTIDAD,
            ]),
            'archivos' => [
                'tdr' => $archivoTdr ? ['archivo_id' => $archivoTdr->ARCH_ID, 'nombre' => $archivoTdr->ARCH_NOMBRE_ORIGINAL] : null,
                'plantilla_dj' => $plantillaDj ? ['archivo_id' => $plantillaDj->ARCH_ID, 'nombre' => $plantillaDj->ARCH_NOMBRE_ORIGINAL] : null,
            ],
        ])->header('Cache-Control', 'public, max-age=60');
    }

    public function resultadosPdf(int $id): Response
    {
        $estadoAdjudicadaId = Estado::where('ESTA_CONTEXTO', 'CONVOCATORIA')->where('ESTA_CODIGO', 'ADJUDICADA')->value('ESTA_ID');

        $conv = Convocatoria::select(['CONV_ID', 'CONV_NUMERO', 'CONV_NOMBRE_SERVICIO', 'CONV_ENTIDAD_ID', 'CONV_ESTADO_ID', 'CONV_FECHA_RESULTADO'])
            ->with(['entidad' => fn ($sub) => $sub->select(['ENTI_ID', 'ENTI_RAZON_SOCIAL'])])
            ->findOrFail($id);

        if ($conv->CONV_ESTADO_ID !== $estadoAdjudicadaId) {
            throw new HttpException(409, 'Esta convocatoria aún no ha sido adjudicada.');
        }

        $archivoGuardado = Archivo::select(['ARCH_ID', 'ARCH_RUTA', 'ARCH_MIME'])
            ->where('ARCH_CONTEXTO', 'RESULTADO_PDF')
            ->where('ARCH_REFERENCIA_ID', $conv->CONV_ID)
            ->first();

        if ($archivoGuardado && Storage::exists($archivoGuardado->ARCH_RUTA)) {
            return Storage::download($archivoGuardado->ARCH_RUTA, "resultados-{$conv->CONV_NUMERO}.pdf", [
                'Content-Type' => $archivoGuardado->ARCH_MIME,
            ]);
        }

        return $this->resultadoService->generarPdf($conv)->stream("resultados-{$conv->CONV_NUMERO}.pdf");
    }
}
