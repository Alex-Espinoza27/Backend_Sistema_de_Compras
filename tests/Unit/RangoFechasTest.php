<?php

namespace Tests\Unit;

use App\Http\Requests\Convocatoria\ConvocatoriaIndexRequest;
use App\Models\Convocatoria;
use App\Support\RangoFechas;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * "Del 15/11 al 16/11" debe devolver todo lo registrado esos dos días, a
 * cualquier hora (en hora de Lima), y nada de los días vecinos.
 */
class RangoFechasTest extends TestCase
{
    /** Lo que hace la base de datos con los límites: comparar instantes. */
    private function incluido(string $registradoEnLima, ?string $desde, ?string $hasta): bool
    {
        $instante = CarbonImmutable::parse($registradoEnLima, RangoFechas::ZONA);

        return (! $desde || $instante->gte(RangoFechas::inicio($desde)))
            && (! $hasta || $instante->lt(RangoFechas::finExclusivo($hasta)));
    }

    public function test_los_limites_llevan_el_desplazamiento_de_lima(): void
    {
        $this->assertSame('2026-11-15T00:00:00-05:00', RangoFechas::inicio('2026-11-15')->toIso8601String());
        $this->assertSame('2026-11-17T00:00:00-05:00', RangoFechas::finExclusivo('2026-11-16')->toIso8601String());
    }

    public function test_lo_registrado_hoy_entra_en_el_rango_de_ayer_a_hoy_a_cualquier_hora(): void
    {
        foreach (['00:00:00', '00:00:01', '09:15:00', '12:00:00', '18:45:30', '23:59:59'] as $hora) {
            $this->assertTrue($this->incluido("2026-11-16 {$hora}", '2026-11-15', '2026-11-16'), "16/11 {$hora}");
        }
    }

    public function test_los_extremos_son_inclusivos_y_los_dias_vecinos_quedan_fuera(): void
    {
        $this->assertTrue($this->incluido('2026-11-15 00:00:00', '2026-11-15', '2026-11-16'), 'primer segundo del 15');
        $this->assertTrue($this->incluido('2026-11-16 23:59:59', '2026-11-15', '2026-11-16'), 'último segundo del 16');
        $this->assertFalse($this->incluido('2026-11-14 23:59:59', '2026-11-15', '2026-11-16'), 'día anterior');
        $this->assertFalse($this->incluido('2026-11-17 00:00:00', '2026-11-15', '2026-11-16'), 'día siguiente');
    }

    public function test_un_solo_dia_y_rangos_abiertos(): void
    {
        $this->assertTrue($this->incluido('2026-11-16 23:30:00', '2026-11-16', '2026-11-16'));
        $this->assertFalse($this->incluido('2026-11-15 23:30:00', '2026-11-16', '2026-11-16'));
        $this->assertTrue($this->incluido('2030-01-01 10:00:00', '2026-11-16', null), 'sin tope superior');
        $this->assertTrue($this->incluido('2020-01-01 10:00:00', null, '2026-11-16'), 'sin tope inferior');
        $this->assertTrue($this->incluido('2020-01-01 10:00:00', null, null), 'sin filtro');
    }

    public function test_la_hora_utc_de_la_noche_limeña_cuenta_como_el_mismo_dia(): void
    {
        // 23:30 del 16/11 en Lima es 04:30 del 17/11 en UTC: sigue siendo "16/11".
        $enUtc = CarbonImmutable::parse('2026-11-17 04:30:00', 'UTC');

        $this->assertTrue($enUtc->gte(RangoFechas::inicio('2026-11-16')) && $enUtc->lt(RangoFechas::finExclusivo('2026-11-16')));
    }

    public function test_ignora_la_hora_si_llega_una_fecha_con_hora(): void
    {
        $this->assertSame('2026-11-16T00:00:00-05:00', RangoFechas::inicio('2026-11-16T05:00:00.000Z')->toIso8601String());
    }

    public function test_aplica_los_dos_limites_a_la_columna_indicada_y_ninguno_si_no_hay_fechas(): void
    {
        $con = Convocatoria::query();
        RangoFechas::aplicar($con, 'CONV_CREADO_EN', '2026-11-15', '2026-11-16');

        $this->assertSame(['2026-11-15T00:00:00-05:00', '2026-11-17T00:00:00-05:00'], $con->getBindings());
        $this->assertStringContainsString('"CONV_CREADO_EN" >= ?', $con->toSql());
        $this->assertStringContainsString('"CONV_CREADO_EN" < ?', $con->toSql());

        $sin = Convocatoria::query();
        RangoFechas::aplicar($sin, 'CONV_CREADO_EN', null, '');
        $this->assertSame([], $sin->getBindings());
    }

    public function test_el_listado_de_convocatorias_valida_el_rango_de_registro(): void
    {
        $solicitud = new ConvocatoriaIndexRequest;
        $validar = fn (array $datos) => Validator::make($datos, $solicitud->rules())->errors()->keys();

        $this->assertSame([], $validar(['registro_desde' => '2026-11-15', 'registro_hasta' => '2026-11-16', 'ordenar_por' => 'fecha_registro']));
        $this->assertSame(['registro_hasta'], $validar(['registro_desde' => '2026-11-16', 'registro_hasta' => '2026-11-15']));
        $this->assertSame(['registro_desde'], $validar(['registro_desde' => '16/11/2026']));
    }
}
