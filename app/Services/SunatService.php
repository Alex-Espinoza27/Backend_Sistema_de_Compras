<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Consulta de RUC contra SUNAT con caché y degradación a manual (02A §7.5).
 * Regla dura: SUNAT nunca puede bloquear el registro. Si no hay credenciales
 * configuradas o la consulta falla, se intenta la caché local; si tampoco hay
 * dato, se habilita la captura manual (origen = MANUAL).
 */
class SunatService
{
    private const TTL_CACHE_SEGUNDOS = 60 * 60 * 24 * 30;

    public function consultar(string $numeroDocumento): array
    {
        $apiUrl = config('services.sunat.api_url');
        $token = config('services.sunat.token');

        if ($apiUrl && $token) {
            try {
                $respuesta = Http::withToken($token)
                    ->timeout(5)
                    ->get(rtrim($apiUrl, '/').'/'.$numeroDocumento);

                if ($respuesta->successful()) {
                    $datos = [
                        'razon_social' => (string) ($respuesta->json('razon_social') ?? $respuesta->json('nombre') ?? ''),
                        'tipo_persona' => $this->inferirTipoPersona($respuesta->json('tipo'), $numeroDocumento),
                        'estado_sunat' => (string) ($respuesta->json('estado') ?? 'ACTIVO'),
                        'origen' => 'SUNAT',
                    ];

                    Cache::put($this->claveCache($numeroDocumento), $datos, self::TTL_CACHE_SEGUNDOS);

                    return $datos;
                }
            } catch (Throwable $e) {
                Log::warning('SunatService: fallo al consultar SUNAT, se degrada a caché/manual.', [
                    'numero_documento' => $numeroDocumento,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($cacheado = Cache::get($this->claveCache($numeroDocumento))) {
            return $cacheado;
        }

        return [
            'razon_social' => '',
            'tipo_persona' => $this->inferirTipoPersona(null, $numeroDocumento),
            'estado_sunat' => null,
            'origen' => 'MANUAL',
        ];
    }

    private function inferirTipoPersona(?string $tipoSunat, string $numeroDocumento): string
    {
        if ($tipoSunat) {
            return str_contains(strtoupper($tipoSunat), 'NATURAL') ? 'NATURAL' : 'JURIDICA';
        }

        return strlen($numeroDocumento) === 8 ? 'NATURAL' : 'JURIDICA';
    }

    private function claveCache(string $numeroDocumento): string
    {
        return "sunat:ruc:{$numeroDocumento}";
    }
}
