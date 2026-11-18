<?php

namespace Tests\Unit;

use App\Support\PdfMinimo;
use PHPUnit\Framework\TestCase;

class PdfMinimoTest extends TestCase
{
    public function test_genera_un_pdf_con_xref_correcto(): void
    {
        $pdf = PdfMinimo::desdeTexto('Términos de referencia (ejemplo)');

        $this->assertStringStartsWith('%PDF-1.4', $pdf);
        $this->assertStringEndsWith("%%EOF\n", $pdf);
        preg_match('/startxref\n(\d+)\n/', $pdf, $m);
        $this->assertSame('xref', substr($pdf, (int) $m[1], 4));
    }

    public function test_asegurar_solo_toca_pdfs_de_texto(): void
    {
        $this->assertStringStartsWith('%PDF', PdfMinimo::asegurar('a.pdf', 'texto'));
        $this->assertSame('%PDF-real', PdfMinimo::asegurar('a.pdf', '%PDF-real'));
        $this->assertSame('texto', PdfMinimo::asegurar('a.docx', 'texto'));
    }
}
