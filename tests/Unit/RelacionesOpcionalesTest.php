<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * El comprobante de pago es opcional al postular (OFER_TIPO_COMPROBANTE_ID
 * admite nulo). Leerlo como `->tipoComprobante->X` revienta con
 * "Attempt to read property on null" en cuanto existe una oferta sin él: ya
 * pasó dos veces, en endpoints distintos. Esta prueba impide que vuelva a
 * entrar un acceso sin `?->` en controladores, servicios, exportaciones o PDFs.
 */
class RelacionesOpcionalesTest extends TestCase
{
    public function test_el_comprobante_siempre_se_lee_con_acceso_seguro(): void
    {
        $raiz = dirname(__DIR__, 2);
        $inseguros = [];

        foreach (['app', 'resources/views'] as $carpeta) {
            $archivos = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($raiz.'/'.$carpeta));
            foreach ($archivos as $archivo) {
                if (! $archivo->isFile() || $archivo->getExtension() !== 'php') {
                    continue;
                }
                foreach (file($archivo->getPathname()) as $n => $linea) {
                    if (preg_match('/->tipoComprobante->/', $linea)) {
                        $inseguros[] = str_replace($raiz.DIRECTORY_SEPARATOR, '', $archivo->getPathname()).':'.($n + 1);
                    }
                }
            }
        }

        $this->assertSame([], $inseguros, 'Use `?->` al leer tipoComprobante: la oferta puede no tener comprobante.');
    }
}
