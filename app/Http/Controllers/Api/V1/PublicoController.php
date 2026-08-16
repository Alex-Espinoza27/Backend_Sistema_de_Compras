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
use App\Models\Oferta;
use App\Models\Rubro;
use App\Services\Captcha\CaptchaService;
use App\Services\EntidadService;
use App\Services\ResultadoService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

    /** Catálogo público de rubros para el paso 3 del registro de proveedor (B2). */
    public function rubros(): JsonResponse
    {
        $items = Rubro::select(['RUBR_ID', 'RUBR_TIPO', 'RUBR_CODIGO', 'RUBR_NOMBRE'])
            ->orderBy('RUBR_TIPO')->orderBy('RUBR_CODIGO')->get()
            ->map(fn (Rubro $r) => ['rubro_id' => $r->RUBR_ID, 'tipo' => $r->RUBR_TIPO, 'codigo' => $r->RUBR_CODIGO, 'nombre' => $r->RUBR_NOMBRE]);

        return ApiResponse::success(['items' => $items])
            ->header('Cache-Control', 'public, max-age=300');
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

        // "Mis postulaciones" como módulo separado (05C hardening, pedido
        // explícitamente por el usuario): reutiliza este mismo listado con
        // los mismos 3 tabs de estado, filtrado a las convocatorias donde el
        // proveedor autenticado ya tiene una oferta.
        $proveedorId = $request->user()?->proveedor?->PROV_ID;
        $mias = filter_var($request->input('mias'), FILTER_VALIDATE_BOOLEAN);

        if ($mias && ! $proveedorId) {
            throw new HttpException(403, 'Debe iniciar sesión como proveedor para ver sus postulaciones.');
        }

        $query = Convocatoria::query()
            ->select([
                'CONV_ID', 'CONV_REQUERIMIENTO_ID', 'CONV_ENTIDAD_ID', 'CONV_NUMERO',
                'CONV_NOMBRE_SERVICIO', 'CONV_FECHA_INICIO', 'CONV_FECHA_FIN', 'CONV_FECHA_PUBLICACION', 'CONV_ESTADO_ID',
                'CONV_MOTIVO_CIERRE', 'CONV_OFERTA_ADJUDICADA_ID', 'CONV_NUMERO_INDAGACION',
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
            ->when($request->filled('tipo'), function ($sub) use ($request) {
                $sub->whereHas('requerimiento', fn ($q) => $q->where('REQU_TIPO', $request->input('tipo')));
            })
            ->when($mias, function ($sub) use ($proveedorId) {
                $sub->whereHas('ofertas', fn ($q) => $q->where('OFER_PROVEEDOR_ID', $proveedorId));
            })
            ->with([
                'entidad' => fn ($sub) => $sub->select(['ENTI_ID', 'ENTI_SIGLA']),
                'estado' => fn ($sub) => $sub->select(['ESTA_ID', 'ESTA_CODIGO', 'ESTA_NOMBRE', 'ESTA_COLOR']),
                // `withTrashed()`: una convocatoria concluida (ADJUDICADA/
                // DESIERTA/CANCELADA) es un registro histórico — si su
                // requerimiento se borró después (soft delete), debe seguir
                // mostrando su tipo, no romper con "property on null".
                'requerimiento' => fn ($sub) => $sub->select(['REQU_ID', 'REQU_TIPO'])->withTrashed(),
            ])
            // Solo en CONCLUIDAS hace falta el proveedor ganador para armar
            // `resultado_resumen` (05C hardening, pedido explícitamente por
            // el usuario) — no se carga en VIGENTES/EN_EVALUACION, donde
            // nunca hay oferta adjudicada.
            ->when($estadoGrupo === 'CONCLUIDAS', fn ($sub) => $sub->with([
                'ofertaAdjudicada' => fn ($sub2) => $sub2->select(['OFER_ID', 'OFER_PROVEEDOR_ID', 'OFER_TOTAL_GENERAL'])
                    ->with(['proveedor' => fn ($sub3) => $sub3->select(['PROV_ID', 'PROV_RAZON_SOCIAL'])]),
            ]))
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

        // "Ya postulado" / "Mis postulaciones" (05C hardening, pedido
        // explícitamente por el usuario): si quien pide el listado es un
        // proveedor autenticado, se resuelve aquí mismo su propia oferta por
        // cada convocatoria de esta página — evita que se muestre "Postular"
        // en una convocatoria donde ya postuló (antes solo se bloqueaba al
        // enviar el formulario) y, en modo `mias`, da el `oferta_id` para
        // enlazar directo al detalle de esa postulación.
        $misOfertasPorConvocatoria = collect();
        if ($proveedorId) {
            $misOfertasPorConvocatoria = Oferta::whereIn('OFER_CONVOCATORIA_ID', collect($paginador->items())->pluck('CONV_ID'))
                ->where('OFER_PROVEEDOR_ID', $proveedorId)
                ->pluck('OFER_ID', 'OFER_CONVOCATORIA_ID');
        }

        $paginador->getCollection()->transform(function (Convocatoria $conv) use ($estadoGrupo, $diasUrgencia, $archivosTdr, $misOfertasPorConvocatoria, $mias) {
            $archivoTdr = $archivosTdr->get($conv->CONV_REQUERIMIENTO_ID);
            $miOfertaId = $misOfertasPorConvocatoria->get($conv->CONV_ID);

            $accion = $mias
                ? 'VER_POSTULACION'
                : match ($estadoGrupo) {
                    'VIGENTES' => $miOfertaId ? 'YA_POSTULADO' : 'POSTULAR',
                    'EN_EVALUACION' => 'NINGUNA',
                    // DESIERTA solo tiene documento descargable cuando hubo
                    // ofertas evaluadas y ninguna aprobó (05C hardening,
                    // pedido explícitamente por el usuario) — si fue "no se
                    // presentaron cotizaciones", nunca se generó
                    // CONV_NUMERO_INDAGACION ni el PDF. CANCELADA nunca corrió
                    // evaluación, se queda solo con MOTIVO.
                    'CONCLUIDAS' => $conv->estado->ESTA_CODIGO === 'ADJUDICADA' || ($conv->estado->ESTA_CODIGO === 'DESIERTA' && $conv->CONV_NUMERO_INDAGACION)
                        ? 'RESULTADOS'
                        : 'MOTIVO',
                };

            $item = [
                'convocatoria_id' => $conv->CONV_ID,
                'numero' => $conv->CONV_NUMERO,
                'nombre_servicio' => $conv->CONV_NOMBRE_SERVICIO,
                'tipo' => $conv->requerimiento?->REQU_TIPO,
                'entidad' => [
                    'entidad_id' => $conv->entidad->ENTI_ID,
                    'sigla' => $conv->entidad->ENTI_SIGLA,
                ],
                'fecha_publicacion' => $conv->CONV_FECHA_PUBLICACION,
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

            // Motivo/resultado visibles directamente en la lista, sin abrir
            // el detalle (05C hardening, pedido explícitamente por el
            // usuario — antes solo se veían al abrir el modal "Ver motivo").
            if ($estadoGrupo === 'CONCLUIDAS') {
                if (in_array($conv->estado->ESTA_CODIGO, ['DESIERTA', 'CANCELADA'], true)) {
                    $item['motivo_cierre'] = $conv->CONV_MOTIVO_CIERRE;
                } elseif ($conv->estado->ESTA_CODIGO === 'ADJUDICADA' && $conv->ofertaAdjudicada) {
                    $item['resultado_resumen'] = sprintf(
                        'Convocatoria adjudicada a %s por S/ %s.',
                        $conv->ofertaAdjudicada->proveedor->PROV_RAZON_SOCIAL,
                        number_format((float) $conv->ofertaAdjudicada->OFER_TOTAL_GENERAL, 2),
                    );
                }
            }

            if ($mias) {
                $item['oferta_id'] = $miOfertaId;
            }

            if ($estadoGrupo === 'VIGENTES') {
                $item['urgencia_dias'] = (int) $conv->urgencia_dias;
                $item['es_urgente'] = (int) $conv->urgencia_dias <= $diasUrgencia;
            }

            return $item;
        });

        // "private": el accion ('POSTULAR' vs 'YA_POSTULADO') ahora puede
        // variar según el proveedor autenticado que pida el listado, así que
        // ya no es seguro cachearlo como respuesta pública/compartida.
        return ApiResponse::paginado($paginador)
            ->header('Cache-Control', 'private, max-age=30');
    }

    public function convocatoria(Request $request, int $id): JsonResponse
    {
        $borradorId = Estado::where('ESTA_CONTEXTO', 'CONVOCATORIA')->where('ESTA_CODIGO', 'BORRADOR')->value('ESTA_ID');

        $conv = Convocatoria::query()
            ->select([
                'CONV_ID', 'CONV_REQUERIMIENTO_ID', 'CONV_ENTIDAD_ID', 'CONV_NUMERO',
                'CONV_NOMBRE_SERVICIO', 'CONV_FECHA_INICIO', 'CONV_FECHA_FIN', 'CONV_FECHA_PUBLICACION',
                'CONV_ESTADO_ID', 'CONV_MOTIVO_CIERRE', 'CONV_OFERTA_ADJUDICADA_ID', 'CONV_NUMERO_INDAGACION',
            ])
            ->where('CONV_ESTADO_ID', '!=', $borradorId)
            ->with([
                'entidad' => fn ($sub) => $sub->select(['ENTI_ID', 'ENTI_RAZON_SOCIAL', 'ENTI_SIGLA']),
                'estado' => fn ($sub) => $sub->select(['ESTA_ID', 'ESTA_CODIGO', 'ESTA_NOMBRE', 'ESTA_COLOR']),
                'ofertaAdjudicada' => fn ($sub) => $sub->select(['OFER_ID', 'OFER_PROVEEDOR_ID', 'OFER_TOTAL_GENERAL'])
                    ->with(['proveedor' => fn ($sub2) => $sub2->select(['PROV_ID', 'PROV_RAZON_SOCIAL'])]),
                // `withTrashed()`: una convocatoria concluida (ADJUDICADA/
                // DESIERTA/CANCELADA) es un registro histórico — si su
                // requerimiento se borró después (soft delete), debe seguir
                // mostrando su tipo, no romper con "property on null".
                'requerimiento' => fn ($sub) => $sub->select(['REQU_ID', 'REQU_TIPO'])->withTrashed(),
                'requerimiento.items' => fn ($sub) => $sub->select([
                    'REIT_ID', 'REIT_REQUERIMIENTO_ID', 'REIT_NUMERO', 'REIT_DESCRIPCION',
                    'REIT_UNIDAD_MEDIDA_ID', 'REIT_CANTIDAD', 'REIT_PRESENTACION',
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

        // Mismo criterio de `accion` que el listado (`convocatorias()`),
        // incluido "ya postulado" para el proveedor autenticado (05C
        // hardening) — antes esta pantalla no sabía nada del proveedor y el
        // frontend adivinaba `accion` solo a partir del estado.
        $proveedorId = $request->user()?->proveedor?->PROV_ID;
        $yaPostulo = $proveedorId && Oferta::where('OFER_CONVOCATORIA_ID', $conv->CONV_ID)
            ->where('OFER_PROVEEDOR_ID', $proveedorId)
            ->exists();

        $accion = match (true) {
            $conv->estado->ESTA_CODIGO === 'PUBLICADA' => $yaPostulo ? 'YA_POSTULADO' : 'POSTULAR',
            $conv->estado->ESTA_CODIGO === 'ADJUDICADA' => 'RESULTADOS',
            // DESIERTA solo tiene documento cuando hubo ofertas evaluadas y
            // ninguna aprobó (05C hardening, pedido explícitamente por el
            // usuario) — si fue "no se presentaron cotizaciones", no hay PDF.
            $conv->estado->ESTA_CODIGO === 'DESIERTA' && $conv->CONV_NUMERO_INDAGACION => 'RESULTADOS',
            in_array($conv->estado->ESTA_CODIGO, ['DESIERTA', 'CANCELADA'], true) => 'MOTIVO',
            default => 'NINGUNA',
        };

        return ApiResponse::success([
            'convocatoria_id' => $conv->CONV_ID,
            'numero' => $conv->CONV_NUMERO,
            'nombre_servicio' => $conv->CONV_NOMBRE_SERVICIO,
            'tipo' => $conv->requerimiento?->REQU_TIPO,
            'entidad' => [
                'entidad_id' => $conv->entidad->ENTI_ID,
                'razon_social' => $conv->entidad->ENTI_RAZON_SOCIAL,
                'sigla' => $conv->entidad->ENTI_SIGLA,
            ],
            'fecha_publicacion' => $conv->CONV_FECHA_PUBLICACION,
            'fecha_inicio' => $conv->CONV_FECHA_INICIO,
            'fecha_fin' => $conv->CONV_FECHA_FIN,
            'estado' => [
                'codigo' => $conv->estado->ESTA_CODIGO,
                'nombre' => $conv->estado->ESTA_NOMBRE,
                'color' => $conv->estado->ESTA_COLOR,
            ],
            'accion' => $accion,
            'motivo_cierre' => $conv->CONV_MOTIVO_CIERRE,
            'resultado_resumen' => $conv->estado->ESTA_CODIGO === 'ADJUDICADA' && $conv->ofertaAdjudicada
                ? sprintf(
                    'Convocatoria adjudicada a %s por S/ %s.',
                    $conv->ofertaAdjudicada->proveedor->PROV_RAZON_SOCIAL,
                    number_format((float) $conv->ofertaAdjudicada->OFER_TOTAL_GENERAL, 2),
                )
                : null,
            'items' => ($conv->requerimiento?->items ?? collect())->map(fn ($item) => [
                'numero' => $item->REIT_NUMERO,
                'descripcion' => $item->REIT_DESCRIPCION,
                'unidad_medida' => [
                    'codigo' => $item->unidadMedida->UNME_CODIGO,
                    'nombre' => $item->unidadMedida->UNME_NOMBRE,
                ],
                'cantidad' => $item->REIT_CANTIDAD,
                'presentacion' => $item->REIT_PRESENTACION,
            ]),
            'archivos' => [
                'tdr' => $archivoTdr ? ['archivo_id' => $archivoTdr->ARCH_ID, 'nombre' => $archivoTdr->ARCH_NOMBRE_ORIGINAL] : null,
                'plantilla_dj' => $plantillaDj ? ['archivo_id' => $plantillaDj->ARCH_ID, 'nombre' => $plantillaDj->ARCH_NOMBRE_ORIGINAL] : null,
            ],
        ])->header('Cache-Control', 'private, max-age=60');
    }

    /**
     * TDR/EETT de una convocatoria ya publicada (05C hardening, pedido
     * explícitamente por el usuario): cualquier interesado debe poder
     * descargarlo sin autenticarse para decidir si postula, igual que ya
     * podía ver el resto del detalle público. Antes solo existía
     * `ArchivoController::descargar`, que exige sesión + `requerimientos.ver`.
     */
    public function tdr(int $id): Response
    {
        $borradorId = Estado::where('ESTA_CONTEXTO', 'CONVOCATORIA')->where('ESTA_CODIGO', 'BORRADOR')->value('ESTA_ID');

        $conv = Convocatoria::select(['CONV_ID', 'CONV_REQUERIMIENTO_ID', 'CONV_ESTADO_ID'])
            ->where('CONV_ESTADO_ID', '!=', $borradorId)
            ->findOrFail($id);

        $archivoTdr = Archivo::select(['ARCH_ID', 'ARCH_NOMBRE_ORIGINAL', 'ARCH_RUTA', 'ARCH_MIME'])
            ->where('ARCH_CONTEXTO', 'REQUERIMIENTO_TDR')
            ->where('ARCH_REFERENCIA_ID', $conv->CONV_REQUERIMIENTO_ID)
            ->first();

        if (! $archivoTdr || ! Storage::exists($archivoTdr->ARCH_RUTA)) {
            throw new HttpException(404, 'Esta convocatoria no tiene un TDR/EETT disponible.');
        }

        return Storage::download($archivoTdr->ARCH_RUTA, $archivoTdr->ARCH_NOMBRE_ORIGINAL, [
            'Content-Type' => $archivoTdr->ARCH_MIME,
        ]);
    }

    public function resultadosPdf(int $id): Response
    {
        // ADJUDICADA o DESIERTA (05C hardening, pedido explícitamente por el
        // usuario): ambos estados generan y persisten el mismo documento
        // "Indagación de Mercado" (AdjudicacionService::generarYGuardarResultadoPdf,
        // llamado tanto por adjudicar() como por desierta()) — antes solo
        // ADJUDICADA podía descargarlo, aunque el archivo de DESIERTA ya
        // existía guardado.
        $estadosConDocumento = Estado::where('ESTA_CONTEXTO', 'CONVOCATORIA')
            ->whereIn('ESTA_CODIGO', ['ADJUDICADA', 'DESIERTA'])
            ->pluck('ESTA_ID');

        $conv = Convocatoria::select(['CONV_ID', 'CONV_NUMERO', 'CONV_NOMBRE_SERVICIO', 'CONV_ENTIDAD_ID', 'CONV_ESTADO_ID', 'CONV_FECHA_RESULTADO', 'CONV_NUMERO_INDAGACION'])
            ->with(['entidad' => fn ($sub) => $sub->select(['ENTI_ID', 'ENTI_RAZON_SOCIAL'])])
            ->findOrFail($id);

        // DESIERTA sin ofertas ("no se presentaron cotizaciones", 05C
        // hardening pedido explícitamente por el usuario) nunca generó
        // número de indagación ni PDF — nunca se debe armar uno "al vuelo"
        // para este caso, aunque el estado sí esté en la lista de arriba.
        if (! $estadosConDocumento->contains($conv->CONV_ESTADO_ID) || ! $conv->CONV_NUMERO_INDAGACION) {
            throw new HttpException(409, 'Esta convocatoria aún no tiene un documento de resultados disponible.');
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
