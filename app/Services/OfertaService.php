<?php

namespace App\Services;

use App\Models\Convocatoria;
use App\Models\Estado;
use App\Models\Oferta;
use App\Models\OfertaItem;
use App\Models\Proveedor;
use DateTimeInterface;
use Illuminate\Support\Carbon;
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
        private NumeracionService $numeracionService,
        private CorreoPlataformaService $correos,
    ) {}

    /**
     * Valida con la hora del SERVIDOR (nunca la del navegador) que la
     * convocatoria esté publicada y dentro de su plazo. No depende de que la
     * tarea programada `convocatorias:vencer` ya haya corrido: si la fecha
     * fin pasó, se rechaza aunque el estado siga en PUBLICADA.
     */
    public function validarConvocatoriaVigente(Convocatoria $convocatoria): void
    {
        $codigo = Estado::where('ESTA_ID', $convocatoria->CONV_ESTADO_ID)->value('ESTA_CODIGO');
        $ahora = now();

        if ($codigo !== 'PUBLICADA') {
            throw new HttpException(409, 'La convocatoria no está publicada y vigente.');
        }
        if ($convocatoria->CONV_FECHA_INICIO->gt($ahora)) {
            throw new HttpException(409, 'La convocatoria aún no inicia; podrá postular desde el '.$this->fechaHora($convocatoria->CONV_FECHA_INICIO).'.');
        }
        if ($convocatoria->CONV_FECHA_FIN->lt($ahora)) {
            throw new HttpException(409, 'El plazo para postular venció el '.$this->fechaHora($convocatoria->CONV_FECHA_FIN).'.');
        }
    }

    private function fechaHora(DateTimeInterface $fecha): string
    {
        return Carbon::instance($fecha)->timezone(config('app.timezone'))->format('d/m/Y H:i');
    }

    /**
     * Relee la convocatoria con bloqueo de fila (FOR UPDATE) dentro de la
     * transacción. Así una postulación o un retiro no se cruzan con el
     * cierre automático (`ConvocatoriaService::vencerVigencia`) ni con una
     * ampliación de plazo: el que llega segundo espera y ve el estado final.
     */
    private function convocatoriaBloqueada(int $convocatoriaId): Convocatoria
    {
        return Convocatoria::select(['CONV_ID', 'CONV_ESTADO_ID', 'CONV_FECHA_INICIO', 'CONV_FECHA_FIN'])
            ->lockForUpdate()
            ->findOrFail($convocatoriaId);
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

        $resultado = DB::transaction(function () use ($datos, $archivos, $proveedor, $convocatoria, $estadoEnviadaId, $segunTdrEjecucion, $segunTdrPago) {
            // Segunda validación, ya con la fila bloqueada: la primera (antes
            // de la transacción) evita trabajo inútil, esta es la que manda
            // si el plazo venció mientras se procesaba el envío.
            $this->validarConvocatoriaVigente($this->convocatoriaBloqueada($convocatoria->CONV_ID));

            $oferta = Oferta::create([
                'OFER_CONVOCATORIA_ID' => $convocatoria->CONV_ID,
                'OFER_PROVEEDOR_ID' => $proveedor->PROV_ID,
                'OFER_ESTADO_ID' => $estadoEnviadaId,
                'OFER_TIPO_COMPROBANTE_ID' => $datos['tipo_comprobante_id'] ?? null,
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
            $this->archivoService->subir($archivos['sustento'], 'OFERTA_SUSTENTO', $oferta->OFER_ID);

            $oferta->OFER_CONSTANCIA_CODIGO = $this->numeracionService->generarOferta($proveedor->PROV_ID);
            $oferta->save();

            $this->auditoria->registrar('OFERTAS', 'CREAR', 'OFERTA', $oferta->OFER_ID);

            return [
                'oferta_id' => $oferta->OFER_ID,
                'constancia_codigo' => $oferta->OFER_CONSTANCIA_CODIGO,
                'total_general' => (string) $oferta->OFER_TOTAL_GENERAL,
                'constancia_url' => "/api/v1/ofertas/{$oferta->OFER_ID}/constancia.pdf",
            ];
        });

        $this->notificarPostulacion($proveedor->PROV_ID, $convocatoria->CONV_ID, $resultado['constancia_codigo']);

        return $resultado;
    }

    /** Correo de constancia al proveedor. Va después del commit y nunca hace
     * fallar una postulación ya registrada. */
    private function notificarPostulacion(int $proveedorId, int $convocatoriaId, string $constancia): void
    {
        try {
            $proveedor = Proveedor::select(['PROV_ID', 'PROV_CORREO', 'PROV_RAZON_SOCIAL'])->find($proveedorId);
            $convocatoria = Convocatoria::select(['CONV_ID', 'CONV_NUMERO', 'CONV_NOMBRE_SERVICIO', 'CONV_FECHA_INICIO', 'CONV_FECHA_FIN'])->find($convocatoriaId);

            if (! $proveedor?->PROV_CORREO || ! $convocatoria) {
                return;
            }

            $this->correos->postulacionRegistrada(
                $proveedor->PROV_CORREO,
                $proveedor->PROV_RAZON_SOCIAL,
                $convocatoria->CONV_NUMERO,
                $convocatoria->CONV_NOMBRE_SERVICIO,
                $convocatoria->CONV_FECHA_INICIO,
                $convocatoria->CONV_FECHA_FIN,
                $constancia,
            );
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public function retirar(Oferta $oferta): void
    {
        DB::transaction(function () use ($oferta) {
            $convocatoria = $this->convocatoriaBloqueada($oferta->OFER_CONVOCATORIA_ID);
            $codigoConvocatoria = Estado::where('ESTA_ID', $convocatoria->CONV_ESTADO_ID)->value('ESTA_CODIGO');

            // Estado Y fecha del servidor: entre el vencimiento y la próxima
            // corrida de `convocatorias:vencer` el estado sigue en PUBLICADA,
            // pero el plazo ya cerró y la postulación queda firme.
            if ($codigoConvocatoria !== 'PUBLICADA' || $convocatoria->CONV_FECHA_FIN->lt(now())) {
                throw new HttpException(409, 'La convocatoria ya no está vigente; la postulación quedó firme.');
            }

            // 05C hardening (pedido explícitamente por el usuario): ahora se
            // puede evaluar mientras la convocatoria sigue PUBLICADA, así que ya
            // no basta con mirar el estado de la convocatoria — una oferta que
            // ya fue calificada no se puede retirar aunque el plazo siga abierto.
            $codigoOferta = Estado::where('ESTA_ID', $oferta->OFER_ESTADO_ID)->value('ESTA_CODIGO');
            if ($codigoOferta !== 'ENVIADA') {
                throw new HttpException(409, 'Esta oferta ya fue evaluada; no se puede retirar.');
            }

            $oferta->delete();

            $this->auditoria->registrar('OFERTAS', 'ELIMINAR', 'OFERTA', $oferta->OFER_ID);
        });
    }
}
