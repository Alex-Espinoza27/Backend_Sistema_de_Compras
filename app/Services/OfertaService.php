<?php

namespace App\Services;

use App\Models\Convocatoria;
use App\Models\Estado;
use App\Models\Oferta;
use App\Models\OfertaItem;
use App\Models\Proveedor;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Reglas duras de dominio para Ofertas (02B M06, 00 §6): envío atómico con
 * cobertura total de ítems, DJ + sustentos, constancia; retiro solo si la
 * convocatoria sigue vigente. Una oferta por (convocatoria, proveedor).
 */
class OfertaService
{
    public function __construct(
        private ArchivoService $archivoService,
        private AuditoriaService $auditoria,
    ) {}

    public function validarConvocatoriaVigente(Convocatoria $convocatoria): void
    {
        $codigo = Estado::where('ESTA_ID', $convocatoria->CONV_ESTADO_ID)->value('ESTA_CODIGO');
        $vigente = $codigo === 'PUBLICADA'
            && $convocatoria->CONV_FECHA_INICIO->lte(now())
            && $convocatoria->CONV_FECHA_FIN->gte(now());

        if (! $vigente) {
            throw new HttpException(409, 'La convocatoria no está publicada y vigente.');
        }
    }

    public function validarProveedorActivo(Proveedor $proveedor): void
    {
        $codigo = Estado::where('ESTA_ID', $proveedor->PROV_ESTADO_ID)->value('ESTA_CODIGO');

        if ($codigo !== 'ACTIVO') {
            throw new HttpException(423, 'Su cuenta de proveedor está suspendida.');
        }
    }

    public function validarSinOfertaPrevia(Convocatoria $convocatoria, Proveedor $proveedor): void
    {
        $existe = Oferta::where('OFER_CONVOCATORIA_ID', $convocatoria->CONV_ID)
            ->where('OFER_PROVEEDOR_ID', $proveedor->PROV_ID)
            ->exists();

        if ($existe) {
            throw new HttpException(409, 'Ya registró una postulación para esta convocatoria.');
        }
    }

    public function crear(array $datos, array $archivos, Proveedor $proveedor): array
    {
        $convocatoria = Convocatoria::select(['CONV_ID', 'CONV_ENTIDAD_ID', 'CONV_REQUERIMIENTO_ID', 'CONV_ESTADO_ID', 'CONV_FECHA_INICIO', 'CONV_FECHA_FIN'])
            ->findOrFail($datos['convocatoria_id']);

        $this->validarConvocatoriaVigente($convocatoria);
        $this->validarProveedorActivo($proveedor);
        $this->validarSinOfertaPrevia($convocatoria, $proveedor);

        $estadoEnviadaId = Estado::where('ESTA_CONTEXTO', 'OFERTA')->where('ESTA_CODIGO', 'ENVIADA')->value('ESTA_ID');

        $segunTdrEjecucion = filter_var($datos['plazo_ejecucion_segun_tdr'], FILTER_VALIDATE_BOOLEAN);
        $segunTdrPago = filter_var($datos['forma_pago_segun_tdr'], FILTER_VALIDATE_BOOLEAN);

        return DB::transaction(function () use ($datos, $archivos, $proveedor, $convocatoria, $estadoEnviadaId, $segunTdrEjecucion, $segunTdrPago) {
            $oferta = Oferta::create([
                'OFER_CONVOCATORIA_ID' => $convocatoria->CONV_ID,
                'OFER_PROVEEDOR_ID' => $proveedor->PROV_ID,
                'OFER_ESTADO_ID' => $estadoEnviadaId,
                'OFER_TIPO_COMPROBANTE_ID' => $datos['tipo_comprobante_id'],
                'OFER_TOTAL_GENERAL' => 0,
                'OFER_VALIDEZ_DIAS' => $datos['validez_dias'],
                'OFER_PLAZO_EJECUCION_SEGUN_TDR' => $segunTdrEjecucion,
                'OFER_PLAZO_EJECUCION_DIAS' => $segunTdrEjecucion ? null : $datos['plazo_ejecucion_dias'],
                'OFER_FORMA_PAGO_SEGUN_TDR' => $segunTdrPago,
                'OFER_FORMA_PAGO_DIAS' => $segunTdrPago ? null : $datos['forma_pago_dias'],
                'OFER_PLAZO_GARANTIA' => $datos['plazo_garantia'],
                'OFER_CONTACTO_NOMBRE' => $datos['contacto_nombre'],
                'OFER_CONTACTO_TELEFONO' => $datos['contacto_telefono'],
                'OFER_CONTACTO_CORREO' => $datos['contacto_correo'],
                'OFER_FECHA_ENVIO' => now(),
            ]);

            foreach ($datos['items'] as $item) {
                OfertaItem::create([
                    'OFIT_OFERTA_ID' => $oferta->OFER_ID,
                    'OFIT_REQUERIMIENTO_ITEM_ID' => $item['requerimiento_item_id'],
                    'OFIT_MARCA_MODELO' => $item['marca_modelo'],
                    'OFIT_PRECIO_UNITARIO' => $item['precio_unitario'],
                ]);
            }

            DB::statement('SELECT fn_oferta_recalcular_total(?)', [$oferta->OFER_ID]);
            $oferta->refresh();

            $this->archivoService->subir($archivos['declaracion_jurada'], 'OFERTA_DJ', $oferta->OFER_ID);

            /** @var UploadedFile $sustento */
            foreach ($archivos['sustentos'] as $sustento) {
                $this->archivoService->subir($sustento, 'OFERTA_SUSTENTO', $oferta->OFER_ID);
            }

            $oferta->OFER_CONSTANCIA_CODIGO = sprintf('CP-%06d-%d', $oferta->OFER_ID, now()->year);
            $oferta->save();

            $this->auditoria->registrar('OFERTAS', 'CREAR', 'OFERTA', $oferta->OFER_ID);

            return [
                'oferta_id' => $oferta->OFER_ID,
                'constancia_codigo' => $oferta->OFER_CONSTANCIA_CODIGO,
                'total_general' => (string) $oferta->OFER_TOTAL_GENERAL,
                'constancia_url' => "/api/v1/ofertas/{$oferta->OFER_ID}/constancia.pdf",
            ];
        });
    }

    public function retirar(Oferta $oferta): void
    {
        $convocatoria = Convocatoria::select(['CONV_ID', 'CONV_ESTADO_ID'])->findOrFail($oferta->OFER_CONVOCATORIA_ID);
        $codigoConvocatoria = Estado::where('ESTA_ID', $convocatoria->CONV_ESTADO_ID)->value('ESTA_CODIGO');

        if ($codigoConvocatoria !== 'PUBLICADA') {
            throw new HttpException(409, 'La convocatoria ya no está vigente; la postulación quedó firme.');
        }

        $oferta->delete();

        $this->auditoria->registrar('OFERTAS', 'ELIMINAR', 'OFERTA', $oferta->OFER_ID);
    }
}
