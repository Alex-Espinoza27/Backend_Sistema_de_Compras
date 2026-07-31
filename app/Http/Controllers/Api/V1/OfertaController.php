<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Oferta\OfertaIndexRequest;
use App\Http\Requests\Oferta\OfertaStoreRequest;
use App\Models\Archivo;
use App\Models\Configuracion;
use App\Models\Convocatoria;
use App\Models\Estado;
use App\Models\Oferta;
use App\Models\RequerimientoItem;
use App\Models\TipoComprobante;
use App\Services\ConstanciaService;
use App\Services\OfertaService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class OfertaController extends Controller
{
    public function __construct(
        private OfertaService $ofertaService,
        private ConstanciaService $constanciaService,
    ) {}

    public function postulacion(Request $request, int $id): JsonResponse
    {
        $proveedor = $request->user()->proveedor()->select(['PROV_ID', 'PROV_ESTADO_ID'])->firstOrFail();

        $convocatoria = Convocatoria::select(['CONV_ID', 'CONV_REQUERIMIENTO_ID', 'CONV_NUMERO', 'CONV_NOMBRE_SERVICIO', 'CONV_FECHA_FIN', 'CONV_ESTADO_ID', 'CONV_FECHA_INICIO'])
            ->findOrFail($id);

        $this->ofertaService->validarConvocatoriaVigente($convocatoria);
        $this->ofertaService->validarProveedorActivo($proveedor);
        $this->ofertaService->validarSinOfertaPrevia($convocatoria, $proveedor);

        $plantillaDj = $this->resolverPlantillaDj($convocatoria);

        $items = RequerimientoItem::select(['REIT_ID', 'REIT_NUMERO', 'REIT_DESCRIPCION', 'REIT_UNIDAD_MEDIDA_ID', 'REIT_CANTIDAD'])
            ->where('REIT_REQUERIMIENTO_ID', $convocatoria->CONV_REQUERIMIENTO_ID)
            ->with(['unidadMedida' => fn ($q) => $q->select(['UNME_ID', 'UNME_CODIGO'])])
            ->orderBy('REIT_NUMERO')
            ->get()
            ->map(fn (RequerimientoItem $item) => [
                'requerimiento_item_id' => $item->REIT_ID,
                'numero' => $item->REIT_NUMERO,
                'descripcion' => $item->REIT_DESCRIPCION,
                'unidad_medida' => $item->unidadMedida->UNME_CODIGO,
                'cantidad' => $item->REIT_CANTIDAD,
            ]);

        $tiposComprobante = TipoComprobante::select(['TICO_ID', 'TICO_CODIGO', 'TICO_NOMBRE'])
            ->get()
            ->map(fn (TipoComprobante $t) => ['tipo_comprobante_id' => $t->TICO_ID, 'codigo' => $t->TICO_CODIGO, 'nombre' => $t->TICO_NOMBRE]);

        return ApiResponse::success([
            'convocatoria' => [
                'convocatoria_id' => $convocatoria->CONV_ID,
                'numero' => $convocatoria->CONV_NUMERO,
                'nombre_servicio' => $convocatoria->CONV_NOMBRE_SERVICIO,
                'fecha_fin' => $convocatoria->CONV_FECHA_FIN,
            ],
            'plantilla_dj' => $plantillaDj,
            'items' => $items,
            'tipos_comprobante' => $tiposComprobante,
            'opciones_validez' => [15, 30],
        ]);
    }

    public function store(OfertaStoreRequest $request): JsonResponse
    {
        $proveedor = $request->user()->proveedor()->select(['PROV_ID', 'PROV_ESTADO_ID'])->firstOrFail();

        $resultado = $this->ofertaService->crear(
            $request->validated(),
            [
                'declaracion_jurada' => $request->file('declaracion_jurada'),
                'sustentos' => $request->file('sustentos'),
            ],
            $proveedor,
        );

        return ApiResponse::success($resultado, 'Postulación enviada.', 201);
    }

    public function index(OfertaIndexRequest $request): JsonResponse
    {
        $proveedor = $request->user()->proveedor()->select(['PROV_ID'])->firstOrFail();

        $columnaOrden = [
            'fecha_envio' => 'OFER_FECHA_ENVIO',
            'total_general' => 'OFER_TOTAL_GENERAL',
            'estado' => 'OFER_ESTADO_ID',
        ][$request->input('ordenar_por', 'fecha_envio')] ?? 'OFER_FECHA_ENVIO';

        $query = Oferta::query()
            ->select(['OFER_ID', 'OFER_CONVOCATORIA_ID', 'OFER_TOTAL_GENERAL', 'OFER_ESTADO_ID', 'OFER_FECHA_ENVIO'])
            ->where('OFER_PROVEEDOR_ID', $proveedor->PROV_ID)
            ->when($request->filled('estado'), function ($q) use ($request) {
                $estadoId = Estado::where('ESTA_CONTEXTO', 'OFERTA')->where('ESTA_CODIGO', $request->input('estado'))->value('ESTA_ID');
                $q->where('OFER_ESTADO_ID', $estadoId);
            })
            ->when($request->filled('entidad_id'), fn ($q) => $q->whereHas(
                'convocatoria',
                fn ($sub) => $sub->where('CONV_ENTIDAD_ID', $request->integer('entidad_id')),
            ))
            ->when($request->filled('buscar'), function ($q) use ($request) {
                $buscar = $request->string('buscar');
                $q->whereHas('convocatoria', function ($sub) use ($buscar) {
                    $sub->where(fn ($s) => $s->where('CONV_NOMBRE_SERVICIO', 'ilike', "%{$buscar}%")->orWhere('CONV_NUMERO', 'ilike', "%{$buscar}%"));
                });
            })
            ->with([
                'convocatoria' => fn ($q) => $q->select(['CONV_ID', 'CONV_NUMERO', 'CONV_NOMBRE_SERVICIO', 'CONV_ENTIDAD_ID', 'CONV_OFERTA_ADJUDICADA_ID'])
                    ->with(['entidad' => fn ($qq) => $qq->select(['ENTI_ID', 'ENTI_SIGLA'])]),
                'estado' => fn ($q) => $q->select(['ESTA_ID', 'ESTA_CODIGO', 'ESTA_NOMBRE', 'ESTA_COLOR']),
            ])
            ->orderBy($columnaOrden, $request->input('direccion', 'desc'));

        $paginador = $query->paginate(
            $request->integer('por_pagina', 15),
            page: $request->integer('pagina', 1),
        );

        $paginador->getCollection()->transform(fn (Oferta $o) => [
            'oferta_id' => $o->OFER_ID,
            'convocatoria' => ['numero' => $o->convocatoria->CONV_NUMERO, 'nombre_servicio' => $o->convocatoria->CONV_NOMBRE_SERVICIO],
            'entidad' => ['sigla' => $o->convocatoria->entidad->ENTI_SIGLA],
            'total_general' => $o->OFER_TOTAL_GENERAL,
            'estado' => ['codigo' => $o->estado->ESTA_CODIGO, 'nombre' => $o->estado->ESTA_NOMBRE, 'color' => $o->estado->ESTA_COLOR],
            'fecha_envio' => $o->OFER_FECHA_ENVIO,
            'es_ganadora' => $o->convocatoria->CONV_OFERTA_ADJUDICADA_ID === $o->OFER_ID,
        ]);

        return ApiResponse::paginado($paginador);
    }

    public function resumen(Request $request): JsonResponse
    {
        $proveedor = $request->user()->proveedor()->select(['PROV_ID'])->firstOrFail();

        $items = DB::table('OFERTA')
            ->join('CONVOCATORIA', 'CONVOCATORIA.CONV_ID', '=', 'OFERTA.OFER_CONVOCATORIA_ID')
            ->join('ENTIDAD', 'ENTIDAD.ENTI_ID', '=', 'CONVOCATORIA.CONV_ENTIDAD_ID')
            ->where('OFERTA.OFER_PROVEEDOR_ID', $proveedor->PROV_ID)
            ->whereNull('OFERTA.OFER_ELIMINADO_EN')
            ->select('ENTIDAD.ENTI_ID', 'ENTIDAD.ENTI_SIGLA')
            ->selectRaw('count(*) as total')
            ->groupBy('ENTIDAD.ENTI_ID', 'ENTIDAD.ENTI_SIGLA')
            ->orderBy('ENTIDAD.ENTI_SIGLA')
            ->get()
            ->map(fn ($fila) => ['entidad_id' => $fila->ENTI_ID, 'sigla' => $fila->ENTI_SIGLA, 'total_ofertas' => (int) $fila->total]);

        return ApiResponse::success($items);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $proveedor = $request->user()->proveedor()->select(['PROV_ID'])->firstOrFail();

        $oferta = Oferta::query()
            ->select([
                'OFER_ID', 'OFER_CONVOCATORIA_ID', 'OFER_PROVEEDOR_ID', 'OFER_ESTADO_ID', 'OFER_TIPO_COMPROBANTE_ID',
                'OFER_TOTAL_GENERAL', 'OFER_VALIDEZ_DIAS', 'OFER_PLAZO_EJECUCION_SEGUN_TDR', 'OFER_PLAZO_EJECUCION_DIAS',
                'OFER_FORMA_PAGO_SEGUN_TDR', 'OFER_FORMA_PAGO_DIAS', 'OFER_PLAZO_GARANTIA', 'OFER_CONTACTO_NOMBRE',
                'OFER_CONTACTO_TELEFONO', 'OFER_CONTACTO_CORREO', 'OFER_CONSTANCIA_CODIGO', 'OFER_FECHA_ENVIO',
                'OFER_EVAL_TECNICA', 'OFER_EVAL_TECNICA_MOTIVO', 'OFER_EVAL_LOGISTICA', 'OFER_EVAL_LOGISTICA_MOTIVO',
            ])
            ->where('OFER_PROVEEDOR_ID', $proveedor->PROV_ID)
            ->with([
                'convocatoria' => fn ($q) => $q->select(['CONV_ID', 'CONV_NUMERO', 'CONV_NOMBRE_SERVICIO', 'CONV_ESTADO_ID']),
                'estado' => fn ($q) => $q->select(['ESTA_ID', 'ESTA_CODIGO', 'ESTA_NOMBRE', 'ESTA_COLOR']),
                'tipoComprobante' => fn ($q) => $q->select(['TICO_ID', 'TICO_CODIGO', 'TICO_NOMBRE']),
                'items' => fn ($q) => $q->select(['OFIT_ID', 'OFIT_OFERTA_ID', 'OFIT_REQUERIMIENTO_ITEM_ID', 'OFIT_MARCA_MODELO', 'OFIT_PRECIO_UNITARIO', 'OFIT_PRECIO_TOTAL']),
                'items.requerimientoItem' => fn ($q) => $q->select(['REIT_ID', 'REIT_NUMERO', 'REIT_DESCRIPCION', 'REIT_UNIDAD_MEDIDA_ID'])
                    ->with(['unidadMedida' => fn ($qq) => $qq->select(['UNME_ID', 'UNME_CODIGO', 'UNME_NOMBRE'])]),
            ])
            ->findOrFail($id);

        $archivos = Archivo::select(['ARCH_ID', 'ARCH_CONTEXTO', 'ARCH_NOMBRE_ORIGINAL'])
            ->where('ARCH_REFERENCIA_ID', $oferta->OFER_ID)
            ->whereIn('ARCH_CONTEXTO', ['OFERTA_DJ', 'OFERTA_SUSTENTO'])
            ->get();

        $estadoConvocatoria = Estado::where('ESTA_ID', $oferta->convocatoria->CONV_ESTADO_ID)->value('ESTA_CODIGO');
        $resuelta = in_array($estadoConvocatoria, ['ADJUDICADA', 'DESIERTA', 'CANCELADA'], true);

        return ApiResponse::success([
            'oferta_id' => $oferta->OFER_ID,
            'convocatoria' => [
                'convocatoria_id' => $oferta->convocatoria->CONV_ID,
                'numero' => $oferta->convocatoria->CONV_NUMERO,
                'nombre_servicio' => $oferta->convocatoria->CONV_NOMBRE_SERVICIO,
            ],
            'estado' => ['codigo' => $oferta->estado->ESTA_CODIGO, 'nombre' => $oferta->estado->ESTA_NOMBRE, 'color' => $oferta->estado->ESTA_COLOR],
            'tipo_comprobante' => ['codigo' => $oferta->tipoComprobante->TICO_CODIGO, 'nombre' => $oferta->tipoComprobante->TICO_NOMBRE],
            'total_general' => $oferta->OFER_TOTAL_GENERAL,
            'validez_dias' => $oferta->OFER_VALIDEZ_DIAS,
            'plazo_ejecucion_segun_tdr' => $oferta->OFER_PLAZO_EJECUCION_SEGUN_TDR,
            'plazo_ejecucion_dias' => $oferta->OFER_PLAZO_EJECUCION_DIAS,
            'forma_pago_segun_tdr' => $oferta->OFER_FORMA_PAGO_SEGUN_TDR,
            'forma_pago_dias' => $oferta->OFER_FORMA_PAGO_DIAS,
            'plazo_garantia' => $oferta->OFER_PLAZO_GARANTIA,
            'contacto_nombre' => $oferta->OFER_CONTACTO_NOMBRE,
            'contacto_telefono' => $oferta->OFER_CONTACTO_TELEFONO,
            'contacto_correo' => $oferta->OFER_CONTACTO_CORREO,
            'constancia_codigo' => $oferta->OFER_CONSTANCIA_CODIGO,
            'fecha_envio' => $oferta->OFER_FECHA_ENVIO,
            'items' => $oferta->items->map(fn ($item) => [
                'numero' => $item->requerimientoItem->REIT_NUMERO,
                'descripcion' => $item->requerimientoItem->REIT_DESCRIPCION,
                'unidad_medida' => ['codigo' => $item->requerimientoItem->unidadMedida->UNME_CODIGO, 'nombre' => $item->requerimientoItem->unidadMedida->UNME_NOMBRE],
                'marca_modelo' => $item->OFIT_MARCA_MODELO,
                'precio_unitario' => $item->OFIT_PRECIO_UNITARIO,
                'precio_total' => $item->OFIT_PRECIO_TOTAL,
            ]),
            'archivos' => [
                'declaracion_jurada' => $this->formatearArchivo($archivos->firstWhere('ARCH_CONTEXTO', 'OFERTA_DJ')),
                'sustentos' => $archivos->where('ARCH_CONTEXTO', 'OFERTA_SUSTENTO')->map(fn ($a) => $this->formatearArchivo($a))->values(),
                'constancia_url' => "/api/v1/ofertas/{$oferta->OFER_ID}/constancia.pdf",
            ],
            'resultado_evaluacion' => $resuelta ? [
                'eval_tecnica' => $oferta->OFER_EVAL_TECNICA,
                'eval_tecnica_motivo' => $oferta->OFER_EVAL_TECNICA_MOTIVO,
                'eval_logistica' => $oferta->OFER_EVAL_LOGISTICA,
                'eval_logistica_motivo' => $oferta->OFER_EVAL_LOGISTICA_MOTIVO,
            ] : null,
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $proveedor = $request->user()->proveedor()->select(['PROV_ID'])->firstOrFail();

        $oferta = Oferta::select(['OFER_ID', 'OFER_CONVOCATORIA_ID', 'OFER_PROVEEDOR_ID'])
            ->where('OFER_PROVEEDOR_ID', $proveedor->PROV_ID)
            ->findOrFail($id);

        $this->ofertaService->retirar($oferta);

        return ApiResponse::success(null, 'Postulación retirada.');
    }

    public function constanciaPdf(Request $request, int $id): Response
    {
        $proveedor = $request->user()->proveedor()->select(['PROV_ID'])->firstOrFail();

        $oferta = Oferta::select(['OFER_ID', 'OFER_PROVEEDOR_ID', 'OFER_CONVOCATORIA_ID', 'OFER_CONSTANCIA_CODIGO', 'OFER_TOTAL_GENERAL', 'OFER_FECHA_ENVIO'])
            ->where('OFER_PROVEEDOR_ID', $proveedor->PROV_ID)
            ->findOrFail($id);

        if (! $oferta->OFER_CONSTANCIA_CODIGO) {
            throw new HttpException(409, 'Esta oferta aún no tiene constancia generada.');
        }

        return $this->constanciaService->generarPdf($oferta)->stream("constancia-{$oferta->OFER_CONSTANCIA_CODIGO}.pdf");
    }

    private function formatearArchivo(?Archivo $archivo): ?array
    {
        return $archivo ? ['archivo_id' => $archivo->ARCH_ID, 'nombre' => $archivo->ARCH_NOMBRE_ORIGINAL] : null;
    }

    private function resolverPlantillaDj(Convocatoria $convocatoria): ?array
    {
        $plantilla = Archivo::select(['ARCH_ID'])
            ->where('ARCH_CONTEXTO', 'CONVOCATORIA_PLANTILLA_DJ')
            ->where('ARCH_REFERENCIA_ID', $convocatoria->CONV_ID)
            ->first();

        if (! $plantilla) {
            $defecto = Configuracion::where('CONF_CLAVE', 'PLANTILLA_DJ_DEFECTO')->value('CONF_VALOR');
            $plantilla = ($defecto && ctype_digit($defecto))
                ? Archivo::select(['ARCH_ID'])->find((int) $defecto)
                : null;
        }

        return $plantilla ? ['archivo_id' => $plantilla->ARCH_ID, 'descarga_url' => "/api/v1/archivos/{$plantilla->ARCH_ID}/descargar"] : null;
    }
}
