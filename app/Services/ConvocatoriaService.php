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

    public function crear(array $datos, ?int $entidadId, ?UploadedFile $archivoPlantillaDj = null): Convocatoria
    {
        $requerimiento = Requerimiento::select(['REQU_ID', 'REQU_ENTIDAD_ID'])->findOrFail($datos['requerimiento_id']);

        if ($entidadId !== null && $requerimiento->REQU_ENTIDAD_ID !== $entidadId) {
            throw new HttpException(403, 'El requerimiento no pertenece a su entidad.');
        }

        $entidadIdEfectiva = $entidadId ?? $requerimiento->REQU_ENTIDAD_ID;

        $tieneConvocatoria = Convocatoria::where('CONV_REQUERIMIENTO_ID', $requerimiento->REQU_ID)->exists();

        if ($tieneConvocatoria) {
            throw new HttpException(409, 'Este requerimiento ya tiene una convocatoria.');
        }

        $estadoBorradorId = Estado::where('ESTA_CONTEXTO', 'CONVOCATORIA')->where('ESTA_CODIGO', 'BORRADOR')->value('ESTA_ID');

        return DB::transaction(function () use ($datos, $requerimiento, $entidadIdEfectiva, $estadoBorradorId, $archivoPlantillaDj) {
            $convocatoria = Convocatoria::create([
                'CONV_REQUERIMIENTO_ID' => $requerimiento->REQU_ID,
                'CONV_ENTIDAD_ID' => $entidadIdEfectiva,
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

        if (! $tienePlantillaPropia && ! $this->plantillaDjDefectoArchivo()) {
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
     * Amplía el plazo de postulación de una convocatoria ya publicada (05C
     * hardening, pedido explícitamente por el usuario). `CONV_FECHA_FIN`
     * sigue siendo la única fecha que lee el resto del sistema (vigencia en
     * `OfertaService`, filtro "Vigentes" público, urgencia, exports,
     * reportes, el job `convocatorias:vencer`) — se sobrescribe con la nueva
     * fecha y el valor anterior se guarda en `CONV_FECHA_FIN_ANTERIOR`, solo
     * para control interno (nunca se expone en el portal público).
     *
     * Solo se permite mientras sigue PUBLICADA: una vez que el job
     * `convocatorias:vencer` la pasa a EN_EVALUACION, "ampliar" implicaría
     * revertir ese cambio de estado, que es un caso distinto y no es lo que
     * se pidió acá.
     */
    public function ampliarFechaFin(Convocatoria $convocatoria, string $nuevaFechaFin): Convocatoria
    {
        $estadoCodigo = Estado::where('ESTA_ID', $convocatoria->CONV_ESTADO_ID)->value('ESTA_CODIGO');

        if ($estadoCodigo !== 'PUBLICADA') {
            throw new HttpException(409, 'Solo se puede ampliar el plazo de una convocatoria publicada.');
        }

        $nueva = $this->aLimaUtc($nuevaFechaFin);

        if ($nueva->lte($convocatoria->CONV_FECHA_FIN)) {
            throw new HttpException(409, 'La nueva fecha de fin debe ser posterior a la fecha de fin actual.');
        }

        return DB::transaction(function () use ($convocatoria, $nueva) {
            $anterior = $convocatoria->CONV_FECHA_FIN;

            $convocatoria->CONV_FECHA_FIN_ANTERIOR = $anterior;
            $convocatoria->CONV_FECHA_FIN = $nueva;
            $convocatoria->save();

            $this->auditoria->registrar(
                'CONVOCATORIAS',
                'AMPLIAR_FECHA_FIN',
                'CONVOCATORIA',
                $convocatoria->CONV_ID,
                ['fecha_fin' => $anterior],
                ['fecha_fin' => $nueva],
            );

            return $convocatoria->fresh();
        });
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

    /** `PLANTILLA_DJ_DEFECTO` guarda el `ARCH_ID` (como texto) de la
     * plantilla subida desde Configuración — no un texto libre. 05C
     * hardening (pedido explícitamente por el usuario): antes `publicar()`
     * solo comprobaba que el valor no estuviera vacío (cualquier texto
     * "pasaba"), mientras que la descarga real (`OfertaController`) sí
     * exigía que fuera un ID numérico de un `Archivo` existente — un valor
     * no numérico dejaba publicar pero nunca servía el archivo. Ahora ambos
     * usan este mismo método. */
    public function plantillaDjDefectoArchivo(): ?Archivo
    {
        $valor = Configuracion::where('CONF_CLAVE', 'PLANTILLA_DJ_DEFECTO')->value('CONF_VALOR');

        if (! $valor || ! ctype_digit($valor)) {
            return null;
        }

        return Archivo::select(['ARCH_ID', 'ARCH_NOMBRE_ORIGINAL', 'ARCH_RUTA'])->find((int) $valor);
    }

    public function definirPlantillaDjDefecto(UploadedFile $archivo): Archivo
    {
        $anterior = $this->plantillaDjDefectoArchivo();

        $nuevo = $this->archivoService->subir($archivo, 'PLANTILLA_DJ_DEFECTO', 0);

        Configuracion::updateOrCreate(['CONF_CLAVE' => 'PLANTILLA_DJ_DEFECTO'], ['CONF_VALOR' => (string) $nuevo->ARCH_ID]);

        if ($anterior) {
            $this->archivoService->eliminar($anterior);
        }

        return $nuevo;
    }

    public function quitarPlantillaDjDefecto(): void
    {
        $anterior = $this->plantillaDjDefectoArchivo();

        Configuracion::where('CONF_CLAVE', 'PLANTILLA_DJ_DEFECTO')->update(['CONF_VALOR' => '']);

        if ($anterior) {
            $this->archivoService->eliminar($anterior);
        }
    }
}
