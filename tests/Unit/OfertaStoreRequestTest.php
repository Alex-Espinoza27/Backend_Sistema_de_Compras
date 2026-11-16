<?php

namespace Tests\Unit;

use App\Http\Requests\Oferta\OfertaStoreRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * Los errores de la postulación los lee quien postula: tienen que nombrar el
 * campo y el ítem en español y decir qué corregir. Se validan solo las reglas
 * que no consultan la base de datos.
 */
class OfertaStoreRequestTest extends TestCase
{
    private const CAMPOS = [
        'items.*.marca_modelo', 'items.*.precio_unitario', 'validez_dias',
        'plazo_ejecucion_dias', 'forma_pago_dias', 'plazo_garantia',
        'contacto_nombre', 'contacto_telefono', 'contacto_correo',
        'declaracion_jurada', 'sustento',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        app()->setLocale('es');
        config(['archivo.max_mb' => 10]);
    }

    private function errores(array $datos): array
    {
        $solicitud = new OfertaStoreRequest;

        return Validator::make(
            $datos,
            Arr::only($solicitud->rules(), self::CAMPOS),
            $solicitud->messages(),
            $solicitud->attributes(),
        )->errors()->toArray();
    }

    private function valido(array $cambios = []): array
    {
        return array_replace([
            'items' => [
                ['marca_modelo' => 'Samsung', 'precio_unitario' => '5400'],
                ['marca_modelo' => 'Samsung', 'precio_unitario' => '445'],
            ],
            'validez_dias' => '15',
            'plazo_ejecucion_dias' => '44',
            'forma_pago_dias' => '44',
            'plazo_garantia' => '15 dias',
            'contacto_nombre' => 'Eduardo Perez',
            'contacto_telefono' => '949898556',
            'contacto_correo' => 'eduardo@gmail.com',
            'declaracion_jurada' => UploadedFile::fake()->create('dj.pdf', 100, 'application/pdf'),
            'sustento' => UploadedFile::fake()->create('sustento.pdf', 100, 'application/pdf'),
        ], $cambios);
    }

    public function test_el_caso_real_un_correo_con_punto_antes_de_la_arroba_dice_que_corregir(): void
    {
        $errores = $this->errores($this->valido(['contacto_correo' => 'eduardo.@gmail.com']));

        $this->assertSame(['contacto_correo'], array_keys($errores));
        $this->assertStringContainsString('Escriba un correo válido', $errores['contacto_correo'][0]);
        $this->assertStringContainsString('nombre@dominio.com', $errores['contacto_correo'][0]);
    }

    public function test_un_postulacion_correcta_no_tiene_errores(): void
    {
        $this->assertSame([], $this->errores($this->valido()));
    }

    public function test_los_errores_de_los_items_indican_el_numero_de_item(): void
    {
        $errores = $this->errores($this->valido(['items' => [
            ['marca_modelo' => 'Samsung', 'precio_unitario' => '10'],
            ['marca_modelo' => '', 'precio_unitario' => '-5'],
        ]]));

        $this->assertStringContainsString('marca/modelo del ítem 2', $errores['items.1.marca_modelo'][0]);
        $this->assertSame('El precio unitario del ítem 2 no puede ser negativo.', $errores['items.1.precio_unitario'][0]);
        $this->assertArrayNotHasKey('items.0.marca_modelo', $errores);
    }

    public function test_los_campos_de_texto_respetan_el_limite_y_el_mensaje_nombra_el_campo(): void
    {
        $errores = $this->errores($this->valido([
            'plazo_garantia' => str_repeat('a', OfertaStoreRequest::MAX_PLAZO_GARANTIA + 1),
            'contacto_telefono' => str_repeat('9', OfertaStoreRequest::MAX_CONTACTO_TELEFONO + 1),
        ]));

        $this->assertStringContainsString('plazo de garantía', $errores['plazo_garantia'][0]);
        $this->assertStringContainsString((string) OfertaStoreRequest::MAX_PLAZO_GARANTIA, $errores['plazo_garantia'][0]);
        $this->assertStringContainsString('teléfono de contacto', $errores['contacto_telefono'][0]);
    }

    public function test_los_dias_deben_ser_enteros_y_la_validez_15_o_30(): void
    {
        $errores = $this->errores($this->valido([
            'plazo_ejecucion_dias' => '4.5',
            'forma_pago_dias' => '0',
            'validez_dias' => '20',
        ]));

        $this->assertSame('El plazo de ejecución debe ser un número entero de días.', $errores['plazo_ejecucion_dias'][0]);
        $this->assertSame('La forma de pago debe ser de al menos 1 día.', $errores['forma_pago_dias'][0]);
        $this->assertSame('La validez de la oferta debe ser de 15 o 30 días.', $errores['validez_dias'][0]);
    }

    public function test_los_archivos_indican_formato_y_peso_permitidos(): void
    {
        $errores = $this->errores($this->valido([
            'declaracion_jurada' => UploadedFile::fake()->create('dj.exe', 100),
            'sustento' => UploadedFile::fake()->create('sustento.pdf', 11 * 1024, 'application/pdf'),
        ]));

        $this->assertSame('La declaración jurada debe ser un archivo PDF, DOC o DOCX.', $errores['declaracion_jurada'][0]);
        $this->assertSame('El sustento no puede pesar más de 10 MB.', $errores['sustento'][0]);
    }

    public function test_sin_archivos_pide_adjuntarlos(): void
    {
        $errores = $this->errores($this->valido(['declaracion_jurada' => null, 'sustento' => null]));

        $this->assertSame('Adjunte la declaración jurada firmada.', $errores['declaracion_jurada'][0]);
        $this->assertSame('Adjunte el archivo de sustento.', $errores['sustento'][0]);
    }

    public function test_los_limites_que_recibe_el_frontend_son_los_de_las_reglas(): void
    {
        $limites = OfertaStoreRequest::limites();

        $this->assertSame(150, $limites['marca_modelo']);
        $this->assertSame(120, $limites['contacto_correo']);
        $this->assertSame(20, $limites['contacto_telefono']);
        $this->assertSame(10, $limites['archivo_max_mb']);
        $this->assertSame(['pdf', 'doc', 'docx'], $limites['archivo_formatos']);
        $this->assertContains('max:150', (new OfertaStoreRequest)->rules()['items.*.marca_modelo']);
    }
}
