<?php

namespace App\Services;

use App\Models\Convocatoria;
use App\Models\Estado;
use App\Models\Oferta;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Cierre de la convocatoria desde EN_EVALUACION (02B M08): adjudicar (estricto,
 * todas evaluadas, menor precio por defecto) o declarar desierta. Genera y
 * persiste el PDF de resultados (ARCHIVO contexto RESULTADO_PDF) y notifica.
 */
class AdjudicacionService
{
    public function __construct(
        private EstadoService $estadoService,
        private AuditoriaService $auditoria,
        private ResultadoService $resultadoService,
        private ArchivoService $archivoService,
        private NotificacionService $notificacion,
        private NumeracionService $numeracion,
    ) {}

    public function adjudicar(Convocatoria $convocatoria, ?int $ofertaId): array
    {
        $codigoConvocatoria = Estado::where('ESTA_ID', $convocatoria->CONV_ESTADO_ID)->value('ESTA_CODIGO');

        if ($codigoConvocatoria !== 'EN_EVALUACION') {
            throw new HttpException(409, 'Solo se puede adjudicar una convocatoria en evaluación.');
        }

        $ofertas = Oferta::select(['OFER_ID', 'OFER_ESTADO_ID', 'OFER_TOTAL_GENERAL', 'OFER_PROVEEDOR_ID'])
            ->where('OFER_CONVOCATORIA_ID', $convocatoria->CONV_ID)
            ->get();

        $estadosPorId = Estado::where('ESTA_CONTEXTO', 'OFERTA')->pluck('ESTA_CODIGO', 'ESTA_ID');
        $codigoDe = fn (Oferta $o) => $estadosPorId[$o->OFER_ESTADO_ID] ?? null;

        $pendientes = $ofertas->filter(fn (Oferta $o) => $codigoDe($o) === 'EN_EVALUACION');

        if ($pendientes->isNotEmpty()) {
            throw new HttpException(409, 'Faltan ofertas por evaluar: '.$pendientes->pluck('OFER_ID')->implode(', '));
        }

        $aprobadas = $ofertas->filter(fn (Oferta $o) => $codigoDe($o) === 'APROBADA');

        if ($aprobadas->isEmpty()) {
            throw new HttpException(409, 'No hay ofertas aprobadas para adjudicar; considere declarar desierta la convocatoria.');
        }

        if ($ofertaId) {
            $ofertaGanadora = $aprobadas->firstWhere('OFER_ID', $ofertaId);

            if (! $ofertaGanadora) {
                throw new HttpException(422, 'La oferta indicada no existe o no está aprobada en ambas evaluaciones.');
            }
        } else {
            $ofertaGanadora = $aprobadas->sortBy('OFER_TOTAL_GENERAL')->first();
        }

        DB::transaction(function () use ($convocatoria, $ofertas, $ofertaGanadora) {
            $convocatoria->CONV_OFERTA_ADJUDICADA_ID = $ofertaGanadora->OFER_ID;
            $convocatoria->CONV_FECHA_RESULTADO = now();
            $convocatoria->save();

            $this->estadoService->transicionar(
                modelo: $convocatoria,
                columnaEstado: 'CONV_ESTADO_ID',
                contexto: 'CONVOCATORIA',
                codigoDestino: 'ADJUDICADA',
                transicionesPermitidas: ['EN_EVALUACION' => ['ADJUDICADA']],
                modulo: 'ADJUDICACION',
                tabla: 'CONVOCATORIA',
                accion: 'ADJUDICAR',
                datosAdicionales: ['oferta_adjudicada_id' => $ofertaGanadora->OFER_ID],
            );

            foreach ($ofertas as $oferta) {
                $codigoDestinoOferta = $oferta->OFER_ID === $ofertaGanadora->OFER_ID ? 'ADJUDICADA' : 'NO_ADJUDICADA';

                $this->estadoService->transicionar(
                    modelo: $oferta,
                    columnaEstado: 'OFER_ESTADO_ID',
                    contexto: 'OFERTA',
                    codigoDestino: $codigoDestinoOferta,
                    transicionesPermitidas: [
                        'APROBADA' => ['ADJUDICADA', 'NO_ADJUDICADA'],
                        'DESAPROBADA' => ['NO_ADJUDICADA'],
                    ],
                    modulo: 'ADJUDICACION',
                    tabla: 'OFERTA',
                    accion: 'ADJUDICAR',
                );
            }

            $this->generarYGuardarResultadoPdf($convocatoria);
        });

        $this->notificarResultados($convocatoria->CONV_ID, $ofertaGanadora->OFER_ID);

        return [
            'convocatoria_id' => $convocatoria->CONV_ID,
            'oferta_adjudicada_id' => $ofertaGanadora->OFER_ID,
            'estado' => 'ADJUDICADA',
        ];
    }

    public function desierta(Convocatoria $convocatoria, string $motivo): Convocatoria
    {
        $codigoConvocatoria = Estado::where('ESTA_ID', $convocatoria->CONV_ESTADO_ID)->value('ESTA_CODIGO');

        if ($codigoConvocatoria !== 'EN_EVALUACION') {
            throw new HttpException(409, 'Solo se puede declarar desierta una convocatoria en evaluación.');
        }

        $existeAprobada = Oferta::where('OFER_CONVOCATORIA_ID', $convocatoria->CONV_ID)
            ->whereHas('estado', fn ($q) => $q->where('ESTA_CODIGO', 'APROBADA'))
            ->exists();

        if ($existeAprobada) {
            throw new HttpException(409, 'Existen ofertas aprobadas; no se puede declarar desierta, debe adjudicar.');
        }

        DB::transaction(function () use ($convocatoria, $motivo) {
            $convocatoria->CONV_MOTIVO_CIERRE = $motivo;
            $convocatoria->CONV_FECHA_RESULTADO = now();
            $convocatoria->save();

            $this->estadoService->transicionar(
                modelo: $convocatoria,
                columnaEstado: 'CONV_ESTADO_ID',
                contexto: 'CONVOCATORIA',
                codigoDestino: 'DESIERTA',
                transicionesPermitidas: ['EN_EVALUACION' => ['DESIERTA']],
                modulo: 'ADJUDICACION',
                tabla: 'CONVOCATORIA',
                accion: 'DESIERTA',
                datosAdicionales: ['motivo' => $motivo],
            );

            $ofertas = Oferta::select(['OFER_ID', 'OFER_ESTADO_ID'])
                ->where('OFER_CONVOCATORIA_ID', $convocatoria->CONV_ID)
                ->get();

            $estadosPorId = Estado::where('ESTA_CONTEXTO', 'OFERTA')->pluck('ESTA_CODIGO', 'ESTA_ID');

            foreach ($ofertas as $oferta) {
                $codigoActual = $estadosPorId[$oferta->OFER_ESTADO_ID] ?? null;

                if ($codigoActual === 'NO_ADJUDICADA') {
                    continue;
                }

                $this->estadoService->transicionar(
                    modelo: $oferta,
                    columnaEstado: 'OFER_ESTADO_ID',
                    contexto: 'OFERTA',
                    codigoDestino: 'NO_ADJUDICADA',
                    transicionesPermitidas: [
                        'ENVIADA' => ['NO_ADJUDICADA'],
                        'EN_EVALUACION' => ['NO_ADJUDICADA'],
                        'APROBADA' => ['NO_ADJUDICADA'],
                        'DESAPROBADA' => ['NO_ADJUDICADA'],
                    ],
                    modulo: 'ADJUDICACION',
                    tabla: 'OFERTA',
                    accion: 'DESIERTA',
                );
            }

            $this->generarYGuardarResultadoPdf($convocatoria);
        });

        $this->notificarResultados($convocatoria->CONV_ID, null);

        return $convocatoria->fresh();
    }

    private function generarYGuardarResultadoPdf(Convocatoria $convocatoria): void
    {
        // El N.° de "Indagación de Mercado" se genera una sola vez por
        // convocatoria (correlativo por entidad, 05C hardening pedido
        // explícitamente por el usuario) y queda fijo para siempre.
        if (! $convocatoria->CONV_NUMERO_INDAGACION) {
            $convocatoria->CONV_NUMERO_INDAGACION = $this->numeracion->generarIndagacionMercado($convocatoria->CONV_ENTIDAD_ID);
            $convocatoria->save();
        }

        $pdf = $this->resultadoService->generarPdf($convocatoria);
        $contenido = $pdf->output();

        $this->archivoService->guardarContenido(
            $contenido,
            'RESULTADO_PDF',
            $convocatoria->CONV_ID,
            "resultados-{$convocatoria->CONV_NUMERO}.pdf",
            'application/pdf',
        );
    }

    private function notificarResultados(int $convocatoriaId, ?int $ofertaGanadoraId): void
    {
        $ofertas = Oferta::select(['OFER_ID', 'OFER_PROVEEDOR_ID', 'OFER_ESTADO_ID'])
            ->where('OFER_CONVOCATORIA_ID', $convocatoriaId)
            ->with(['proveedor' => fn ($q) => $q->select(['PROV_ID', 'PROV_USER_ID', 'PROV_CORREO'])])
            ->get();

        foreach ($ofertas as $oferta) {
            if (! $oferta->proveedor) {
                continue;
            }

            $esGanadora = $oferta->OFER_ID === $ofertaGanadoraId;
            $titulo = $esGanadora ? 'Su oferta fue adjudicada' : 'Resultado de su postulación';
            $mensaje = $esGanadora
                ? 'Felicitaciones, su oferta resultó adjudicada.'
                : 'La convocatoria a la que postuló ya tiene resultado. Revise el detalle en la plataforma.';

            if ($oferta->proveedor->PROV_USER_ID) {
                $this->notificacion->crearInApp(
                    $oferta->proveedor->PROV_USER_ID,
                    'ADJUDICACION',
                    $titulo,
                    $mensaje,
                    'CONVOCATORIA',
                    $convocatoriaId,
                );
            }

            if ($oferta->proveedor->PROV_CORREO) {
                $this->notificacion->enviarCorreo($oferta->proveedor->PROV_CORREO, $titulo, $mensaje);
            }
        }
    }
}
