<?php

namespace App\Services;

use App\Models\Estado;
use Illuminate\Database\Eloquent\Model;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Única autoridad para transiciones de *_ESTADO_ID (02A §7.2). Cada módulo
 * declara su propio mapa de transiciones permitidas (código actual → códigos
 * destino válidos) al invocar transicionar(); el servicio valida, aplica y
 * audita el cambio. Nadie más escribe una columna *_ESTADO_ID.
 */
class EstadoService
{
    public function __construct(private AuditoriaService $auditoria) {}

    public function transicionar(
        Model $modelo,
        string $columnaEstado,
        string $contexto,
        string $codigoDestino,
        array $transicionesPermitidas,
        string $modulo,
        string $tabla,
        string $accion = 'ACTUALIZAR',
        array $datosAdicionales = [],
    ): Estado {
        $estadoActual = Estado::findOrFail($modelo->{$columnaEstado});

        $permitidos = $transicionesPermitidas[$estadoActual->ESTA_CODIGO] ?? [];

        if (! in_array($codigoDestino, $permitidos, true)) {
            throw new HttpException(409, "No se puede pasar de {$estadoActual->ESTA_CODIGO} a {$codigoDestino}.");
        }

        $estadoDestino = Estado::where('ESTA_CONTEXTO', $contexto)
            ->where('ESTA_CODIGO', $codigoDestino)
            ->firstOrFail();

        $valorAnterior = [$columnaEstado => $estadoActual->ESTA_CODIGO];

        $modelo->{$columnaEstado} = $estadoDestino->ESTA_ID;
        $modelo->save();

        $this->auditoria->registrar(
            modulo: $modulo,
            accion: $accion,
            tabla: $tabla,
            registroId: $modelo->getKey(),
            valorAnterior: $valorAnterior,
            valorNuevo: array_merge([$columnaEstado => $estadoDestino->ESTA_CODIGO], $datosAdicionales),
        );

        return $estadoDestino;
    }
}
