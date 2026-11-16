<?php

namespace Tests\Unit;

use App\Http\Requests\Requerimiento\RequerimientoStoreRequest;
use App\Http\Requests\Requerimiento\RequerimientoUpdateRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Lo que el formulario promete (contadores y límites) lo exige también el
 * backend, y los errores nombran el ítem. Solo reglas sin consultas a la BD.
 */
class RequerimientoRequestTest extends TestCase
{
    private const CAMPOS = [
        'objeto', 'descripcion', 'tipo', 'items', 'items.*.numero', 'items.*.descripcion',
        'items.*.cantidad', 'items.*.presentacion',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        app()->setLocale('es');

        // Las reglas de editar leen la entidad del requerimiento existente.
        Schema::create('REQUERIMIENTO', function ($t) {
            $t->unsignedBigInteger('REQU_ID')->primary();
            $t->unsignedBigInteger('REQU_ENTIDAD_ID')->nullable();
            $t->timestamp('REQU_ELIMINADO_EN')->nullable();
        });
    }

    private function errores(string $clase, array $datos): array
    {
        $solicitud = new $clase;
        $solicitud->setUserResolver(fn () => new class
        {
            public int $entidad_id = 1;

            public function hasRole(string $rol): bool
            {
                return true;
            }
        });
        $reglas = Arr::only($solicitud->rules(), self::CAMPOS);

        return Validator::make($datos, $reglas, $solicitud->messages(), $solicitud->attributes())->errors()->toArray();
    }

    private function valido(array $cambios = []): array
    {
        return array_replace([
            'objeto' => 'Compra de laptops',
            'descripcion' => 'Se requieren laptops para el área de TI.',
            'tipo' => 'BIEN',
            'items' => [
                ['numero' => 1, 'descripcion' => 'Laptop 14"', 'cantidad' => 5, 'presentacion' => 'caja x1'],
                ['numero' => 2, 'descripcion' => 'Mouse', 'cantidad' => 10, 'presentacion' => null],
            ],
        ], $cambios);
    }

    public static function clases(): array
    {
        return [[RequerimientoStoreRequest::class], [RequerimientoUpdateRequest::class]];
    }

    #[DataProvider('clases')]
    public function test_un_requerimiento_correcto_no_tiene_errores(string $clase): void
    {
        $this->assertSame([], $this->errores($clase, $this->valido()));
    }

    #[DataProvider('clases')]
    public function test_el_backend_exige_los_mismos_limites_que_muestra_el_formulario(string $clase): void
    {
        $errores = $this->errores($clase, $this->valido([
            'objeto' => str_repeat('a', 251),
            'descripcion' => str_repeat('a', 2001),
            'items' => [[
                'numero' => 1,
                'descripcion' => str_repeat('a', 501),
                'cantidad' => 5,
                'presentacion' => str_repeat('a', 121),
            ]],
        ]));

        $this->assertArrayHasKey('objeto', $errores);
        $this->assertArrayHasKey('descripcion', $errores);
        $this->assertArrayHasKey('items.0.descripcion', $errores);
        $this->assertArrayHasKey('items.0.presentacion', $errores);
        $this->assertStringContainsString('2000', $errores['descripcion'][0]);
        $this->assertStringContainsString('descripción del ítem 1', $errores['items.0.descripcion'][0]);
    }

    #[DataProvider('clases')]
    public function test_los_errores_de_cantidad_dicen_que_item_es(string $clase): void
    {
        $errores = $this->errores($clase, $this->valido(['items' => [
            ['numero' => 1, 'descripcion' => 'A', 'cantidad' => 5],
            ['numero' => 2, 'descripcion' => 'B', 'cantidad' => 0],
            ['numero' => 3, 'descripcion' => 'C', 'cantidad' => 20000000],
        ]]));

        $this->assertSame('La cantidad del ítem 2 debe ser al menos 1.', $errores['items.1.cantidad'][0]);
        $this->assertStringContainsString('ítem 3 no puede superar 10.000.000', $errores['items.2.cantidad'][0]);
        $this->assertArrayNotHasKey('items.0.cantidad', $errores);
    }

    #[DataProvider('clases')]
    public function test_un_item_sin_descripcion_pide_completarla(string $clase): void
    {
        $errores = $this->errores($clase, $this->valido(['items' => [['numero' => 1, 'descripcion' => '', 'cantidad' => 1]]]));

        $this->assertSame('Indique la descripción del ítem 1.', $errores['items.0.descripcion'][0]);
    }
}
