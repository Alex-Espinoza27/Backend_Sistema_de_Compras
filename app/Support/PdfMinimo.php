<?php

namespace App\Support;

/**
 * PDF de una página con una línea de texto, para los archivos de ejemplo que
 * generan los datos de demostración y de estrés (solo desarrollo). Antes se
 * guardaba el texto plano con extensión .pdf y el visor fallaba al abrirlo.
 */
final class PdfMinimo
{
    public static function desdeTexto(string $texto): string
    {
        $texto = iconv('UTF-8', 'Windows-1252//TRANSLIT', $texto) ?: $texto;
        $texto = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $texto);
        $flujo = "BT /F1 12 Tf 50 780 Td ($texto) Tj ET";
        $objetos = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
            '<< /Length '.strlen($flujo)." >>\nstream\n$flujo\nendstream",
        ];

        $pdf = "%PDF-1.4\n";
        $posiciones = [];
        foreach ($objetos as $i => $objeto) {
            $posiciones[] = strlen($pdf);
            $pdf .= ($i + 1)." 0 obj\n$objeto\nendobj\n";
        }
        $inicioXref = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objetos) + 1)."\n0000000000 65535 f \n";
        foreach ($posiciones as $posicion) {
            $pdf .= sprintf("%010d 00000 n \n", $posicion);
        }

        return $pdf.'trailer << /Size '.(count($objetos) + 1)." /Root 1 0 R >>\nstartxref\n$inicioXref\n%%EOF\n";
    }

    /** Si el contenido de un .pdf no es un PDF real, lo convierte. */
    public static function asegurar(string $nombreArchivo, string $contenido): string
    {
        return str_ends_with($nombreArchivo, '.pdf') && ! str_starts_with($contenido, '%PDF')
            ? self::desdeTexto($contenido)
            : $contenido;
    }
}
