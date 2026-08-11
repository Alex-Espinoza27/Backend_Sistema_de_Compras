<?php

namespace App\Services;

use App\Models\Archivo;
use App\Models\Configuracion;
use App\Models\Convocatoria;
use App\Models\Estado;
use App\Models\Oferta;
use App\Models\Requerimiento;
use App\Models\RequerimientoItem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Reglas duras de dominio para Convocatorias (02B M05): creación en BORRADOR,
 * edición solo en BORRADOR, publicación (NumeracionService + EstadoService)
 * y cancelación (EstadoService, arrastra ofertas a NO_ADJUDICADA).
 */
class ConvocatoriaService
{
    public function __construct(
        private EstadoService $estadoService,
        private NumeracionService $numeracionService,
        private AuditoriaService $auditoria,
        private ArchivoService $archivoService,
    ) {}

    public function crear(array $datos, int $entidadId, ?UploadedFile $archivoPlantillaDj = null): Convocatoria
    {
        $requerimiento = Requerimiento::select(['REQU_ID', 'REQU_ENTIDAD_ID'])->findOrFail($datos['requerimiento_id']);

        if ($requerimiento->REQU_ENTIDAD_ID !== $entidadId) {
            throw new HttpException(403, 'El requerimiento no pertenece a su entidad.');
        }

        $tieneConvocatoria = Convocatoria::where('CONV_REQUERIMIENTO_ID', $requerimiento->REQU_ID)->exists();

        if ($tieneConvocatoria) {
            throw new HttpException(409, 'Este requerimiento ya tiene una convocatoria.');
        }

        $estadoBorradorId = Estado::where('ESTA_CONTEXTO', 'CONVOCATORIA')->where('ESTA_CODIGO', 'BORRADOR')->value('ESTA_ID');

        return DB::transaction(function () use ($datos, $requerimiento, $entidadId, $estadoBorradorId, $archivoPlantillaDj) {
            $convocatoria = Convocatoria::create([
                'CONV_REQUERIMIENTO_ID' => $requerimiento->REQU_ID,
                'CONV_ENTIDAD_ID' => $entidadId,
                'CONV_NOMBRE_SERVICIO' => $datos['nombre_servicio'],
                'CONV_FECHA_INICIO' => $this->aLimaUtc($datos['fecha_inicio']),
                'CONV_FECHA_FIN' => $this->aLimaUtc($datos['fecha_fin']),
                'CONV_ESTADO_ID' => $estadoBorradorId,
            ]);

            if ($archivoPlantillaDj) {
                $this->archivoService->subir($archivoPlantillaDj, 'CONVOCATORIA_PLANTILLA_DJ', $convocatoria->CONV_ID);
            }

            $this->auditoria->registrar('CONVOCATORIAS', 'CREAR', 'CONVOCATORIA', $convocatoria->CONV_ID);

            return $convocatoria;
        });
    }

    public function actualizar(Convocatoria $convocatoria, array $datos, ?UploadedFile $archivoPlantillaDj = null): Convocatoria
    {
        $this->asegurarBorrador($convocatoria);

        $valorAnterior = $convocatoria->only(['CONV_NOMBRE_SERVICIO', 'CONV_FECHA_INICIO', 'CONV_FECHA_FIN']);

        return DB::transaction(function () use ($convocatoria, $datos, $archivoPlantillaDj, $valorAnterior) {
            $convocatoria->update([
                'CONV_NOMBRE_SERVICIO' => $datos['nombre_servicio'],
                'CONV_FECHA_INICIO' => $this->aLimaUtc($datos['fecha_inicio']),
                'CONV_FECHA_FIN' => $this->aLimaUtc($datos['fecha_fin']),
            ]);

            // Solo se reemplaza la plantilla DJ si llega una nueva; si no, la
            // existente queda intacta (05C hardening — antes exigía un botón aparte).
            if ($archivoPlantillaDj) {
                $anterior = Archivo::where('ARCH_CONTEXTO', 'CONVOCATORIA_PLANTILLA_DJ')
                    ->where('ARCH_REFERENCIA_ID', $convocatoria->CONV_ID)
                    ->first();
                if ($anterior) {
                    $this->archivoService->eliminar($anterior);
                }
                $this->archivoService->subir($archivoPlantillaDj, 'CONVOCATORIA_PLANTILLA_DJ', $convocatoria->CONV_ID);
            }

            $this->auditoria->registrar(
                'CONVOCATORIAS',
                'ACTUALIZAR',
                'CONVOCATORIA',
                $convocatoria->CONV_ID,
                $valorAnterior,
                $convocatoria->only(['CONV_NOMBRE_SERVICIO', 'CONV_FECHA_INICIO', 'CONV_FECHA_FIN']),
            );

            return $convocatoria;
        });
    }

    public function publicar(Convocatoria $convocatoria): array
    {
        $this->asegurarBorrador($convocatoria);

        $totalItems = RequerimientoItem::where('REIT_REQUERIMIENTO_ID', $convocatoria->CONV_REQUERIMIENTO_ID)->count();

        if ($totalItems === 0) {
            throw new HttpException(409, 'El requerimiento no tiene ítems; no se puede publicar.');
        }

        if ($convocatoria->CONV_FECHA_FIN->lte(now())) {
            throw new HttpException(409, 'La fecha de fin debe ser futura para publicar.');
        }

        if ($convocatoria->CONV_FECHA_FIN->lte($convocatoria->CONV_FECHA_INICIO)) {
            throw new HttpException(409, 'La fecha de fin debe ser posterior a la fecha de inicio.');
        }

        $tienePlantillaPropia = Archivo::where('ARCH_CONTEXTO', 'CONVOCATORIA_PLANTILLA_DJ')
            ->where('ARCH_REFERENCIA_ID', $convocatoria->CONV_ID)
            ->exists();

        $plantillaDefecto = Configuracion::where('CONF_CLAVE', 'PLANTILLA_DJ_DEFECTO')->value('CONF_VALOR');

        if (! $tienePlantillaPropia && empty($plantillaDefecto)) {
            throw new HttpException(409, 'No existe plantilla de declaración jurada (propia ni por defecto); no se puede publicar.');
        }

        return DB::transaction(function () use ($convocatoria) {
            $numero = $this->numeracionService->generar($convocatoria->CONV_ENTIDAD_ID);

            $convocatoria->CONV_NUMERO = $numero;
            $convocatoria->CONV_FECHA_PUBLICACION = now();
            $convocatoria->save();

            $estado = $this->estadoService->transicionar(
                modelo: $convocatoria,
                columnaEstado: 'CONV_ESTADO_ID',
                contexto: 'CONVOCATORIA',
                codigoDestino: 'PUBLICADA',
                transicionesPermitidas: ['BORRADOR' => ['PUBLICADA']],
                modulo: 'CONVOCATORIAS',
                tabla: 'CONVOCATORIA',
                accion: 'PUBLICAR',
                datosAdicionales: ['numero' => $numero],
            );

            return [
                'convocatoria_id' => $convocatoria->CONV_ID,
                'numero' => $numero,
                'estado' => $estado->ESTA_CODIGO,
            ];
        });
    }

    public function cancelar(Convocatoria $convocatoria, string $motivo): Convocatoria
    {
        $estadoCodigo = Estado::where('ESTA_ID', $convocatoria->CONV_ESTADO_ID)->value('ESTA_CODIGO');

        if (! in_array($estadoCodigo, ['BORRADOR', 'PUBLICADA', 'EN_EVALUACION'], true)) {
            throw new HttpException(409, 'Esta convocatoria no se puede cancelar desde su estado actual.');
        }

        return DB::transaction(function () use ($convocatoria, $motivo) {
            $convocatoria->CONV_MOTIVO_CIERRE = $motivo;
            $convocatoria->CONV_FECHA_RESULTADO = now();
            $convocatoria->save();

            $this->estadoService->transicionar(
                modelo: $convocatoria,
                columnaEstado: 'CONV_ESTADO_ID',
                contexto: 'CONVOCATORIA',
                codigoDestino: 'CANCELADA',
                transicionesPermitidas: [
                    'BORRADOR' => ['CANCELADA'],
                    'PUBLICADA' => ['CANCELADA'],
                    'EN_EVALUACION' => ['CANCELADA'],
                ],
                modulo: 'CONVOCATORIAS',
                tabla: 'CONVOCATORIA',
                accion: 'CANCELAR',
                datosAdicionales: ['motivo' => $motivo],
            );

            $this->cerrarOfertasPendientes($convocatoria);

            return $convocatoria->fresh();
        });
    }

    private function cerrarOfertasPendientes(Convocatoria $convocatoria): void
    {
        $ofertas = Oferta::select(['OFER_ID', 'OFER_ESTADO_ID'])
            ->where('OFER_CONVOCATORIA_ID', $convocatoria->CONV_ID)
            ->get();

        $estadosCerrables = ['ENVIADA', 'EN_EVALUACION', 'APROBADA', 'DESAPROBADA'];

        foreach ($ofertas as $oferta) {
            $codigoOferta = Estado::where('ESTA_ID', $oferta->OFER_ESTADO_ID)->value('ESTA_CODIGO');

            if (! in_array($codigoOferta, $estadosCerrables, true)) {
                continue;
            }

            $this->estadoService->transicionar(
                modelo: $oferta,
                columnaEstado: 'OFER_ESTADO_ID',
                contexto: 'OFERTA',
                codigoDestino: 'NO_ADJUDICADA',
                transicionesPermitidas: array_fill_keys($estadosCerrables, ['NO_ADJUDICADA']),
                modulo: 'OFERTAS',
                tabla: 'OFERTA',
                accion: 'CANCELAR',
            );
        }
    }

    /**
     * PUBLICADA → EN_EVALUACION al vencer CONV_FECHA_FIN (00 §5, 01 §6: "la
     * transición se orquesta desde la aplicación... las funciones de BD son
     * apoyo, no la autoridad del flujo"). Invocado por el comando programado
     * `convocatorias:vencer`, nunca por un endpoint. Arrastra también las
     * ofertas ENVIADA de cada convocatoria a EN_EVALUACION.
     */
    public function vencerVigencia(): int
    {
        $convocatoriasVencidas = Convocatoria::select(['CONV_ID', 'CONV_ESTADO_ID'])
            ->whereHas('estado', fn ($q) => $q->where('ESTA_CODIGO', 'PUBLICADA'))
            ->where('CONV_FECHA_FIN', '<', now())
            ->get();

        foreach ($convocatoriasVencidas as $convocatoria) {
            DB::transaction(function () use ($convocatoria) {
                $this->estadoService->transicionar(
                    modelo: $convocatoria,
                    columnaEstado: 'CONV_ESTADO_ID',
                    contexto: 'CONVOCATORIA',
                    codigoDestino: 'EN_EVALUACION',
                    transicionesPermitidas: ['PUBLICADA' => ['EN_EVALUACION']],
                    modulo: 'CONVOCATORIAS',
                    tabla: 'CONVOCATORIA',
                );

                $convocatoria->CONV_FECHA_APERTURA = now();
                $convocatoria->save();

                $ofertasEnviadas = Oferta::select(['OFER_ID', 'OFER_ESTADO_ID'])
                    ->where('OFER_CONVOCATORIA_ID', $convocatoria->CONV_ID)
                    ->whereHas('estado', fn ($q) => $q->where('ESTA_CODIGO', 'ENVIADA'))
                    ->get();

                foreach ($ofertasEnviadas as $oferta) {
                    $this->estadoService->transicionar(
                        modelo: $oferta,
                        columnaEstado: 'OFER_ESTADO_ID',
                        contexto: 'OFERTA',
                        codigoDestino: 'EN_EVALUACION',
                        transicionesPermitidas: ['ENVIADA' => ['EN_EVALUACION']],
                        modulo: 'OFERTAS',
                        tabla: 'OFERTA',
                    );
                }
            });
        }

        return $convocatoriasVencidas->count();
    }

    private function asegurarBorrador(Convocatoria $convocatoria): void
    {
        $estadoCodigo = Estado::where('ESTA_ID', $convocatoria->CONV_ESTADO_ID)->value('ESTA_CODIGO');

        if ($estadoCodigo !== 'BORRADOR') {
            throw new HttpException(409, 'Solo se puede operar sobre una convocatoria en borrador.');
        }
    }

    /**
     * `fecha_inicio`/`fecha_fin` llegan como fecha+hora (`YYYY-MM-DDTHH:mm`,
     * 05C hardening — antes solo fecha, forzada a inicio/fin de día) desde el
     * formulario. La app corre en UTC (config('app.timezone')) pero Perú es
     * UTC-5 fijo (sin horario de verano): se interpreta la hora recibida como
     * America/Lima y se convierte a UTC para almacenar, sin recortar/forzar
     * ninguna parte del día.
     */
    private function aLimaUtc(string $fechaHora): Carbon
    {
        return Carbon::parse($fechaHora, 'America/Lima')->utc();
    }
}
