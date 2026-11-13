<?php

namespace App\Console\Commands;

use App\Models\Archivo;
use App\Models\Area;
use App\Models\Convocatoria;
use App\Models\Entidad;
use App\Models\Estado;
use App\Models\Oferta;
use App\Models\OfertaItem;
use App\Models\Proveedor;
use App\Models\Requerimiento;
use App\Models\RequerimientoItem;
use App\Models\Rubro;
use App\Models\TipoComprobante;
use App\Models\TipoDocumento;
use App\Models\UnidadMedida;
use App\Models\User;
use App\Services\AuditoriaService;
use App\Services\NumeracionService;
use Faker\Factory as FakerFactory;
use Faker\Generator as FakerGenerator;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

/**
 * SOLO DESARROLLO (pedido explícitamente por el usuario): genera un volumen
 * grande de proveedores, requerimientos, convocatorias, ofertas y
 * adjudicaciones con fechas variadas (pasadas y futuras, distintas entre sí)
 * para probar listados/paginación/filtros bajo muchos registros, y en
 * particular la "ampliación de fecha fin" (05C) y el flujo de adjudicación.
 *
 * Reutiliza el mismo mecanismo real de numeración (`NumeracionService`) que
 * usan los servicios de producción, para que REQU_NUMERO/CONV_NUMERO/
 * OFER_CONSTANCIA_CODIGO salgan exactamente como saldrían en un uso real
 * (correlativo por entidad/proveedor, reinicio anual) — no se simula con
 * texto inventado. Todo lo demás se inserta directo con Eloquent (igual que
 * `DemoSeeder`), sin pasar por los FormRequest/Controllers HTTP.
 *
 * Todo registro generado lleva el marcador "[ESTRÉS]" en el objeto/nombre de
 * servicio y los proveedores usan correos "proveedor.estres.*@ejemplo.com" —
 * así se pueden filtrar o borrar (`--limpiar`) sin tocar los datos
 * curados de ProduccionSeeder/DemoSeeder.
 */
class GenerarDatosEstres extends Command
{
    private const MARCADOR = '[ESTRÉS] ';

    private const CORREO_PATRON = 'proveedor.estres.%04d@ejemplo.com';

    protected $signature = 'desa:generar-estres
        {--proveedores=60 : Cantidad de proveedores a generar}
        {--requerimientos=300 : Cantidad de requerimientos/convocatorias a generar (1 convocatoria por requerimiento)}
        {--limpiar : Antes de generar, borra los datos de estrés generados en una corrida previa}';

    protected $description = 'SOLO DESARROLLO: genera muchos registros (proveedores, requerimientos, convocatorias, ofertas, adjudicaciones) con fechas variadas, para pruebas de estrés de listados, ampliación de plazo y adjudicación.';

    private array $estados;

    private array $tipoDocumento;

    private array $tipoComprobante;

    private array $unidadMedida;

    private array $rubros;

    private Entidad $entidad;

    private array $areas;

    private FakerGenerator $faker;

    private NumeracionService $numeracion;

    private AuditoriaService $auditoria;

    public function handle(NumeracionService $numeracion, AuditoriaService $auditoria): int
    {
        if (app()->environment('production')) {
            $this->components->error('Este comando es solo para desarrollo; no se ejecuta en producción.');

            return self::FAILURE;
        }

        $this->numeracion = $numeracion;
        $this->auditoria = $auditoria;
        $this->faker = FakerFactory::create('es_ES');

        $this->components->task('Cargando catálogos', function () {
            $this->cargarCatalogos();

            return true;
        });

        if ($this->option('limpiar')) {
            $this->components->task('Borrando datos de estrés de una corrida anterior', function () {
                $this->limpiarDatosEstres();

                return true;
            });
        }

        $this->components->info('Paso 1/3 — Generando proveedores');
        $proveedores = $this->generarProveedores((int) $this->option('proveedores'));

        $this->components->info('Paso 2/3 — Generando requerimientos y convocatorias (con ofertas y adjudicaciones)');
        $this->generarRequerimientosYConvocatorias((int) $this->option('requerimientos'), $proveedores);

        $this->components->info('Paso 3/3 — Resumen');
        $this->imprimirResumen();

        return self::SUCCESS;
    }

    private function cargarCatalogos(): void
    {
        $this->estados = Estado::all()->groupBy('ESTA_CONTEXTO')
            ->map(fn ($grupo) => $grupo->keyBy('ESTA_CODIGO'))
            ->toArray();
        $this->tipoDocumento = TipoDocumento::all()->keyBy('TIDO_CODIGO')->toArray();
        $this->tipoComprobante = TipoComprobante::all()->values()->toArray();
        $this->unidadMedida = UnidadMedida::all()->values()->toArray();
        $this->rubros = Rubro::all()->groupBy('RUBR_TIPO')->toArray();

        // Se reutiliza la entidad aprobada que ya exista (MDLP) — la
        // plataforma solo aloja una por ahora (05C hardening previo).
        $this->entidad = Entidad::where('ENTI_ESTADO_ID', $this->estadoId('ENTIDAD', 'APROBADA'))->firstOrFail();

        // Áreas de la entidad: reutiliza las que ya existan y crea unas
        // cuantas más para tener variedad (antes solo había 1).
        $nombresArea = [
            'Oficina de Abastecimiento', 'Gerencia de Administración', 'Secretaría General',
            'Subgerencia de Logística', 'Oficina de Tecnologías de la Información',
            'Gerencia de Desarrollo Urbano', 'Subgerencia de Recursos Humanos',
        ];
        foreach ($nombresArea as $nombre) {
            Area::firstOrCreate(
                ['AREA_ENTIDAD_ID' => $this->entidad->ENTI_ID, 'AREA_NOMBRE' => $nombre],
                ['AREA_SIGLA' => strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $nombre), 0, 4))],
            );
        }
        $this->areas = Area::where('AREA_ENTIDAD_ID', $this->entidad->ENTI_ID)->get()->values()->toArray();
    }

    private function estadoId(string $contexto, string $codigo): int
    {
        return (int) $this->estados[$contexto][$codigo]['ESTA_ID'];
    }

    private function limpiarDatosEstres(): void
    {
        $convocatoriaIds = Convocatoria::where('CONV_NOMBRE_SERVICIO', 'like', self::MARCADOR . '%')->pluck('CONV_ID');
        $requerimientoIds = Requerimiento::where('REQU_OBJETO', 'like', self::MARCADOR . '%')->pluck('REQU_ID');
        $ofertaIds = Oferta::whereIn('OFER_CONVOCATORIA_ID', $convocatoriaIds)->pluck('OFER_ID');
        $proveedorIds = Proveedor::where('PROV_CORREO', 'like', 'proveedor.estres.%')->pluck('PROV_ID');
        $userIds = User::where('email', 'like', 'proveedor.estres.%')->pluck('id');

        OfertaItem::whereIn('OFIT_OFERTA_ID', $ofertaIds)->delete();
        Archivo::where('ARCH_CONTEXTO', 'OFERTA_DJ')->whereIn('ARCH_REFERENCIA_ID', $ofertaIds)->delete();
        Archivo::where('ARCH_CONTEXTO', 'OFERTA_SUSTENTO')->whereIn('ARCH_REFERENCIA_ID', $ofertaIds)->delete();
        Oferta::whereIn('OFER_ID', $ofertaIds)->forceDelete();

        Archivo::where('ARCH_CONTEXTO', 'RESULTADO_PDF')->whereIn('ARCH_REFERENCIA_ID', $convocatoriaIds)->delete();
        Archivo::where('ARCH_CONTEXTO', 'CONVOCATORIA_PLANTILLA_DJ')->whereIn('ARCH_REFERENCIA_ID', $convocatoriaIds)->delete();
        Convocatoria::whereIn('CONV_ID', $convocatoriaIds)->forceDelete();

        RequerimientoItem::whereIn('REIT_REQUERIMIENTO_ID', $requerimientoIds)->delete();
        Archivo::where('ARCH_CONTEXTO', 'REQUERIMIENTO_TDR')->whereIn('ARCH_REFERENCIA_ID', $requerimientoIds)->delete();
        DB::table('REQUERIMIENTO_RUBRO')->whereIn('RERU_REQUERIMIENTO_ID', $requerimientoIds)->delete();
        Requerimiento::whereIn('REQU_ID', $requerimientoIds)->forceDelete();

        DB::table('PROVEEDOR_RUBRO')->whereIn('PRRU_PROVEEDOR_ID', $proveedorIds)->delete();
        Proveedor::whereIn('PROV_ID', $proveedorIds)->forceDelete();
        User::whereIn('id', $userIds)->delete();

        Storage::disk('local')->deleteDirectory('estres');
    }

    private function generarProveedores(int $cantidad): array
    {
        $proveedores = [];
        $barra = $this->output->createProgressBar($cantidad);

        for ($i = 1; $i <= $cantidad; $i++) {
            $esNatural = $i % 7 === 0; // ~1 de cada 7, igual de proporción que DemoSeeder.

            if ($esNatural) {
                $nombre = $this->faker->firstName() . ' ' . $this->faker->lastName() . ' ' . $this->faker->lastName();
                $numeroDocumento = '7' . str_pad((string) (1000000 + $i), 7, '0', STR_PAD_LEFT);
                $tipoDocCodigo = 'DNI';
            } else {
                $formaLegal = $this->faker->randomElement(['S.A.C.', 'S.R.L.', 'E.I.R.L.', 'S.A.']);
                $nombre = ucfirst($this->faker->catchPhrase()) . ' ' . $formaLegal;
                $numeroDocumento = '20' . str_pad((string) (9000000 + $i), 9, '0', STR_PAD_LEFT);
                $tipoDocCodigo = 'RUC';
            }

            $correo = sprintf(self::CORREO_PATRON, $i);

            $user = User::create([
                'name' => $nombre,
                'email' => $correo,
                'password' => Hash::make('Password123'),
            ]);
            $user->assignRole('PROVEEDOR');

            // ~8% suspendidos, para probar el filtro de estado también en Proveedores.
            $estadoCodigo = $i % 12 === 0 ? 'SUSPENDIDO' : 'ACTIVO';

            $proveedor = Proveedor::create([
                'PROV_USER_ID' => $user->id,
                'PROV_TIPO_DOCUMENTO_ID' => $this->tipoDocumento[$tipoDocCodigo]['TIDO_ID'],
                'PROV_NUMERO_DOCUMENTO' => $numeroDocumento,
                'PROV_TIPO_PERSONA' => $esNatural ? 'NATURAL' : 'JURIDICA',
                'PROV_RAZON_SOCIAL' => $nombre,
                'PROV_CORREO' => $correo,
                'PROV_TELEFONO' => '9' . str_pad((string) (100000000 + $i), 8, '0', STR_PAD_LEFT),
                'PROV_PERSONA_CONTACTO' => $nombre,
                'PROV_ESTADO_ID' => $this->estadoId('PROVEEDOR', $estadoCodigo),
                'PROV_ORIGEN_DATO' => 'MANUAL',
            ]);

            $tipoRubro = $esNatural ? $this->faker->randomElement(['BIEN', 'SERVICIO']) : null;
            $pool = $tipoRubro ? ($this->rubros[$tipoRubro] ?? []) : array_merge(...array_values($this->rubros));
            $elegidos = collect($pool)->random(min(2, count($pool)))->pluck('RUBR_ID');
            $proveedor->rubros()->attach($elegidos);

            $proveedores[] = $proveedor;
            $barra->advance();
        }

        $barra->finish();
        $this->newLine(2);

        return $proveedores;
    }

    /** Fecha+hora aleatoria dentro de `[hoy+$desde, hoy+$hasta]` días (ambos
     * pueden ser negativos para fechas pasadas) — da la variedad de fechas
     * pedida explícitamente ("fechas posteriores y distintas"). */
    private function fechaAleatoria(int $desdeDias, int $hastaDias): Carbon
    {
        return now()
            ->addDays(rand($desdeDias, $hastaDias))
            ->setTime(rand(8, 19), $this->faker->randomElement([0, 15, 30, 45]));
    }

    private function generarRequerimientosYConvocatorias(int $cantidad, array $proveedores): void
    {
        // Distribución de estados (05C hardening, pedido explícitamente por
        // el usuario): variedad suficiente para ejercitar cada pantalla —
        // "Publicada" con fechas futuras repartidas en bandas (para probar
        // "Ampliar fecha fin" con urgencias distintas), "En evaluación" para
        // Evaluación/Adjudicación, y el resto para historial/paginación.
        $distribucion = [
            'BORRADOR' => 0.10,
            'PUBLICADA' => 0.25,
            'EN_EVALUACION' => 0.20,
            'ADJUDICADA' => 0.20,
            'DESIERTA' => 0.15,
            'CANCELADA' => 0.10,
        ];

        // `floor` en cada estado y el residuo se reparte al final (no
        // `round` en cada uno por separado) — así la suma siempre da
        // exactamente `$cantidad`, sin excederlo por redondeos acumulados.
        $plan = [];
        foreach ($distribucion as $estado => $porcentaje) {
            $plan = array_merge($plan, array_fill(0, (int) floor($cantidad * $porcentaje), $estado));
        }
        while (count($plan) < $cantidad) {
            $plan[] = 'PUBLICADA';
        }
        shuffle($plan);

        $barra = $this->output->createProgressBar($cantidad);

        foreach ($plan as $indice => $estadoConvocatoria) {
            DB::transaction(function () use ($indice, $estadoConvocatoria, $proveedores) {
                $this->generarUnoConSusOfertas($indice + 1, $estadoConvocatoria, $proveedores);
            });
            $barra->advance();
        }

        $barra->finish();
        $this->newLine(2);
    }

    private function generarUnoConSusOfertas(int $indice, string $estadoConvocatoria, array $proveedores): void
    {
        $esBien = $this->faker->boolean();
        [$objeto, $tipo] = $this->generarObjeto($esBien);

        $numeroRequerimiento = $this->numeracion->generarRequerimiento($this->entidad->ENTI_ID);
        $area = $this->faker->randomElement($this->areas);

        $requerimiento = Requerimiento::create([
            'REQU_ENTIDAD_ID' => $this->entidad->ENTI_ID,
            'REQU_NUMERO' => $numeroRequerimiento,
            'REQU_OBJETO' => self::MARCADOR . $objeto,
            'REQU_DESCRIPCION' => "Especificaciones técnicas / TDR de: {$objeto}.",
            'REQU_TIPO' => $tipo,
            'REQU_AREA_ID' => $area['AREA_ID'],
        ]);

        $rubrosPool = $this->rubros[$tipo] ?? [];
        $requerimiento->rubros()->sync(collect($rubrosPool)->random(min(1, count($rubrosPool)))->pluck('RUBR_ID'));

        $items = $this->generarItems($requerimiento, $tipo);

        $this->crearArchivoEstres('REQUERIMIENTO_TDR', $requerimiento->REQU_ID, "TDR-{$numeroRequerimiento}.pdf",
            "Términos de referencia de ejemplo (estrés) para: {$objeto}.");

        [$fechaInicio, $fechaFin, $fechaPublicacion, $fechaApertura, $fechaResultado, $motivoCierre, $fechaFinAnterior] =
            $this->fechasPorEstado($estadoConvocatoria);

        $convNumero = null;
        if ($estadoConvocatoria !== 'BORRADOR') {
            $convNumero = $this->numeracion->generar($this->entidad->ENTI_ID);
        }

        $convocatoria = Convocatoria::create([
            'CONV_REQUERIMIENTO_ID' => $requerimiento->REQU_ID,
            'CONV_ENTIDAD_ID' => $this->entidad->ENTI_ID,
            'CONV_NUMERO' => $convNumero,
            'CONV_NOMBRE_SERVICIO' => self::MARCADOR . $objeto,
            'CONV_FECHA_INICIO' => $fechaInicio,
            'CONV_FECHA_FIN' => $fechaFin,
            'CONV_FECHA_FIN_ANTERIOR' => $fechaFinAnterior,
            'CONV_ESTADO_ID' => $this->estadoId('CONVOCATORIA', $estadoConvocatoria),
            'CONV_MOTIVO_CIERRE' => $motivoCierre,
            'CONV_FECHA_PUBLICACION' => $fechaPublicacion,
            'CONV_FECHA_APERTURA' => $fechaApertura,
            'CONV_FECHA_RESULTADO' => $fechaResultado,
        ]);

        if ($fechaFinAnterior) {
            // Deja rastro de auditoría de la "ampliación" simulada, igual que
            // dejaría ConvocatoriaService::ampliarFechaFin() en un uso real.
            $this->auditoria->registrar(
                'CONVOCATORIAS', 'AMPLIAR_FECHA_FIN', 'CONVOCATORIA', $convocatoria->CONV_ID,
                ['fecha_fin' => $fechaFinAnterior], ['fecha_fin' => $fechaFin],
            );
        }

        if ($estadoConvocatoria !== 'BORRADOR') {
            $this->crearArchivoEstres('CONVOCATORIA_PLANTILLA_DJ', $convocatoria->CONV_ID, "DJ-Plantilla-{$convNumero}.docx",
                'Plantilla de declaración jurada de ejemplo (estrés).');
        }

        $this->generarOfertasSegunEstado($convocatoria, $items, $estadoConvocatoria, $proveedores);
    }

    private function generarObjeto(bool $esBien): array
    {
        if ($esBien) {
            $insumo = $this->faker->randomElement([
                'útiles de oficina', 'equipos de cómputo', 'mobiliario de oficina', 'materiales de limpieza',
                'suministros eléctricos', 'repuestos vehiculares', 'equipos de seguridad', 'material de construcción',
                'insumos de cafetería', 'uniformes institucionales', 'equipos audiovisuales', 'herramientas de mantenimiento',
            ]);
            $destino = $this->faker->randomElement([
                'la Gerencia de Administración', 'la Oficina de TI', 'el Almacén Central', 'la sala de sesiones',
                'las áreas administrativas', 'la Subgerencia de Logística', 'el Palacio Municipal', 'los locales comunales',
            ]);

            return ["Adquisición de {$insumo} para {$destino}", 'BIEN'];
        }

        $servicio = $this->faker->randomElement([
            'limpieza', 'vigilancia', 'mantenimiento de equipos', 'consultoría técnica', 'capacitación de personal',
            'soporte informático', 'fumigación', 'jardinería y áreas verdes', 'transporte de personal', 'impresión de documentos',
        ]);
        $destino = $this->faker->randomElement([
            'la Municipalidad', 'las oficinas administrativas', 'el local institucional', 'la sede central',
            'las dependencias municipales', 'el almacén municipal',
        ]);

        return ["Servicio de {$servicio} para {$destino}", 'SERVICIO'];
    }

    private function generarItems(Requerimiento $requerimiento, string $tipo): array
    {
        $cantidadItems = rand(1, 3);
        $items = [];

        for ($n = 1; $n <= $cantidadItems; $n++) {
            $descripcion = $tipo === 'BIEN'
                ? ucfirst($this->faker->words(3, true))
                : ucfirst($this->faker->words(4, true)) . ' (servicio)';

            $items[] = RequerimientoItem::create([
                'REIT_REQUERIMIENTO_ID' => $requerimiento->REQU_ID,
                'REIT_NUMERO' => $n,
                'REIT_DESCRIPCION' => $descripcion,
                'REIT_UNIDAD_MEDIDA_ID' => $this->faker->randomElement($this->unidadMedida)['UNME_ID'],
                'REIT_CANTIDAD' => rand(1, 100),
                'REIT_PRESENTACION' => $this->faker->boolean(40) ? $this->faker->randomElement(['Caja x 12 unidades', 'Paquete x 50', 'Presentación individual']) : null,
            ]);
        }

        return $items;
    }

    /** [fecha_inicio, fecha_fin, fecha_publicacion, fecha_apertura, fecha_resultado, motivo_cierre, fecha_fin_anterior] */
    private function fechasPorEstado(string $estado): array
    {
        return match ($estado) {
            'BORRADOR' => [$this->fechaAleatoria(2, 15), $this->fechaAleatoria(20, 60), null, null, null, null, null],

            'PUBLICADA' => $this->fechasPublicada(),

            'EN_EVALUACION' => [
                $this->fechaAleatoria(-60, -20), $fin = $this->fechaAleatoria(-15, -1),
                (clone $fin)->subDays(rand(10, 20)), (clone $fin)->addHours(rand(1, 24)), null, null, null,
            ],

            'ADJUDICADA' => [
                $this->fechaAleatoria(-90, -40), $fin = $this->fechaAleatoria(-35, -15),
                (clone $fin)->subDays(rand(10, 20)), (clone $fin)->addDays(rand(1, 3)), (clone $fin)->addDays(rand(5, 15)), null, null,
            ],

            'DESIERTA' => [
                $this->fechaAleatoria(-90, -40), $fin = $this->fechaAleatoria(-35, -15),
                (clone $fin)->subDays(rand(10, 20)), (clone $fin)->addDays(rand(1, 3)), (clone $fin)->addDays(rand(5, 15)),
                $this->faker->randomElement([
                    'Ningún postor cumplió los requisitos técnicos y logísticos exigidos.',
                    'No se presentaron cotizaciones dentro del plazo establecido.',
                ]),
                null,
            ],

            'CANCELADA' => [
                $this->fechaAleatoria(-60, -10), $this->fechaAleatoria(5, 30),
                $inicio = $this->fechaAleatoria(-60, -10), null, (clone $inicio)->addDays(rand(3, 10)),
                'Cancelada por reformulación del requerimiento a solicitud del área usuaria.', null,
            ],

            default => [now(), now()->addDays(7), null, null, null, null, null],
        };
    }

    /** Fecha de fin futura repartida en bandas de urgencia distintas (05C
     * hardening, pedido explícitamente por el usuario: "fechas posteriores y
     * distintas... para hacer pruebas de las ampliaciones") — y ~1 de cada 4
     * ya trae una "ampliación" simulada (fecha_fin_anterior), para probar
     * también cómo se ve una convocatoria ya ampliada en los listados. */
    private function fechasPublicada(): array
    {
        $bandas = [[1, 3], [4, 8], [10, 20], [25, 45], [60, 120], [150, 300]];
        [$desde, $hasta] = $this->faker->randomElement($bandas);
        $fin = $this->fechaAleatoria($desde, $hasta);
        $inicio = $this->fechaAleatoria(-20, 0);
        $publicacion = (clone $inicio)->subHours(rand(1, 6));

        $fechaFinAnterior = $this->faker->boolean(25) ? (clone $fin)->subDays(rand(5, 25)) : null;

        return [$inicio, $fin, $publicacion, null, null, null, $fechaFinAnterior];
    }

    private function generarOfertasSegunEstado(Convocatoria $convocatoria, array $items, string $estadoConvocatoria, array $proveedores): void
    {
        $tico = $this->faker->randomElement($this->tipoComprobante)['TICO_ID'];
        $entidadUserId = User::where('entidad_id', $this->entidad->ENTI_ID)->value('id');

        [$cantidadOfertas, $generador] = match ($estadoConvocatoria) {
            'BORRADOR' => [0, null],
            'PUBLICADA' => [rand(0, 3), fn () => 'ENVIADA'],
            // ~70% de las "En evaluación" quedan con TODAS sus ofertas ya
            // calificadas (listas para Adjudicar/Declarar desierta desde la
            // UI); el resto deja al menos una pendiente, para probar también
            // la bandeja de Evaluación.
            'EN_EVALUACION' => [rand(2, 6), $this->faker->boolean(70)
                ? fn () => $this->faker->randomElement(['APROBADA', 'APROBADA', 'DESAPROBADA'])
                : fn ($n) => $n === 0 ? 'EN_EVALUACION' : $this->faker->randomElement(['EN_EVALUACION', 'APROBADA', 'DESAPROBADA'])],
            'ADJUDICADA' => [rand(2, 6), null],
            'DESIERTA' => [rand(0, 4), fn () => 'NO_ADJUDICADA'],
            'CANCELADA' => [rand(0, 2), fn () => 'ENVIADA'],
            default => [0, null],
        };

        if ($cantidadOfertas === 0 || ! $items) {
            return;
        }

        $elegidos = collect($proveedores)->random(min($cantidadOfertas, count($proveedores)))->values();
        $ofertasCreadas = [];

        foreach ($elegidos as $n => $proveedor) {
            $estadoOferta = match (true) {
                $estadoConvocatoria === 'ADJUDICADA' => $n === 0 ? 'ADJUDICADA' : 'NO_ADJUDICADA',
                $generador !== null => is_callable($generador) ? $generador($n) : $generador,
                default => 'ENVIADA',
            };

            $evaluada = in_array($estadoOferta, ['APROBADA', 'DESAPROBADA', 'ADJUDICADA', 'NO_ADJUDICADA'], true);
            $motivoTecnico = $estadoOferta === 'DESAPROBADA' || ($estadoOferta === 'NO_ADJUDICADA' && $this->faker->boolean(50))
                ? $this->faker->randomElement([
                    'No cumple con las especificaciones técnicas mínimas requeridas.',
                    'No adjunta documentación de sustento completa.',
                    'El plazo de ejecución propuesto excede lo solicitado en el TDR.',
                ])
                : null;

            $precioBase = 300 + ($n * 150) + rand(0, 200);
            if ($estadoOferta === 'ADJUDICADA') {
                $precioBase = 300; // La ganadora siempre queda con el precio más bajo.
            }

            $oferta = $this->crearOfertaEstres($convocatoria, $proveedor, $items, $estadoOferta, $tico, $evaluada, $motivoTecnico, $precioBase, $entidadUserId);
            $ofertasCreadas[] = $oferta;
        }

        if ($estadoConvocatoria === 'ADJUDICADA') {
            $ganadora = $ofertasCreadas[0];
            $convocatoria->CONV_OFERTA_ADJUDICADA_ID = $ganadora->OFER_ID;
            $convocatoria->CONV_NUMERO_INDAGACION = $this->numeracion->generarIndagacionMercado($this->entidad->ENTI_ID);
            $convocatoria->save();

            $this->crearArchivoEstres('RESULTADO_PDF', $convocatoria->CONV_ID, "Resultados-{$convocatoria->CONV_NUMERO}.pdf",
                'PDF de resultados de ejemplo (estrés) con el cuadro comparativo de postores.');
        }

        // DESIERTA con ofertas evaluadas sí tiene documento de indagación
        // (05C: "solo tiene documento descargable cuando hubo ofertas
        // evaluadas y ninguna aprobó"); sin ofertas, se queda sin documento
        // (== "no se presentaron cotizaciones").
        if ($estadoConvocatoria === 'DESIERTA' && count($ofertasCreadas) > 0) {
            $convocatoria->CONV_NUMERO_INDAGACION = $this->numeracion->generarIndagacionMercado($this->entidad->ENTI_ID);
            $convocatoria->save();

            $this->crearArchivoEstres('RESULTADO_PDF', $convocatoria->CONV_ID, "Resultados-{$convocatoria->CONV_NUMERO}.pdf",
                'PDF de resultados de ejemplo (estrés): convocatoria declarada desierta.');
        }
    }

    private function crearOfertaEstres(
        Convocatoria $convocatoria,
        Proveedor $proveedor,
        array $items,
        string $estadoCodigo,
        int $tipoComprobanteId,
        bool $evaluada,
        ?string $motivoTecnico,
        float $precioBase,
        ?int $entidadUserId,
    ): Oferta {
        $evalTecnica = $motivoTecnico ? 'DESAPROBADO' : ($evaluada ? 'APROBADO' : null);
        $evalLogistica = $evaluada && ! $motivoTecnico ? 'APROBADO' : null;
        $fechaEnvio = (clone $convocatoria->CONV_FECHA_INICIO)->addHours(rand(1, 72));

        $oferta = Oferta::create([
            'OFER_CONVOCATORIA_ID' => $convocatoria->CONV_ID,
            'OFER_PROVEEDOR_ID' => $proveedor->PROV_ID,
            'OFER_ESTADO_ID' => $this->estadoId('OFERTA', $estadoCodigo),
            'OFER_TIPO_COMPROBANTE_ID' => $tipoComprobanteId,
            'OFER_VALIDEZ_DIAS' => $this->faker->randomElement([15, 30, 45, 60]),
            'OFER_PLAZO_EJECUCION_SEGUN_TDR' => $this->faker->boolean(70),
            'OFER_PLAZO_EJECUCION_DIAS' => $this->faker->boolean(70) ? null : rand(5, 60),
            'OFER_FORMA_PAGO_SEGUN_TDR' => $this->faker->boolean(80),
            'OFER_FORMA_PAGO_DIAS' => $this->faker->boolean(80) ? null : rand(5, 30),
            'OFER_PLAZO_GARANTIA' => $this->faker->randomElement(['6 meses', '12 meses', '24 meses', 'No aplica']),
            'OFER_CONTACTO_NOMBRE' => $proveedor->PROV_PERSONA_CONTACTO,
            'OFER_CONTACTO_TELEFONO' => $proveedor->PROV_TELEFONO,
            'OFER_CONTACTO_CORREO' => $proveedor->PROV_CORREO,
            'OFER_CONSTANCIA_CODIGO' => $this->numeracion->generarOferta($proveedor->PROV_ID),
            'OFER_FECHA_ENVIO' => $fechaEnvio,
            'OFER_EVAL_TECNICA' => $evalTecnica,
            'OFER_EVAL_TECNICA_MOTIVO' => $motivoTecnico,
            'OFER_EVAL_LOGISTICA' => $evalLogistica,
            'OFER_EVAL_LOGISTICA_MOTIVO' => null,
            'OFER_EVALUADA_POR' => $evaluada ? $entidadUserId : null,
            'OFER_FECHA_EVALUACION' => $evaluada ? (clone $fechaEnvio)->addDays(rand(1, 5)) : null,
        ]);

        foreach ($items as $n => $item) {
            OfertaItem::create([
                'OFIT_OFERTA_ID' => $oferta->OFER_ID,
                'OFIT_REQUERIMIENTO_ITEM_ID' => $item->REIT_ID,
                'OFIT_MARCA_MODELO' => $this->faker->boolean(70) ? ucfirst($this->faker->word()) : null,
                'OFIT_PRECIO_UNITARIO' => $precioBase + ($n * 25) + ($proveedor->PROV_ID % 10),
            ]);
        }

        DB::statement('SELECT fn_oferta_recalcular_total(?)', [$oferta->OFER_ID]);

        $this->crearArchivoEstres('OFERTA_DJ', $oferta->OFER_ID, "DJ-Firmada-{$oferta->OFER_ID}.pdf", 'Declaración jurada firmada de ejemplo (estrés).');
        $this->crearArchivoEstres('OFERTA_SUSTENTO', $oferta->OFER_ID, "Sustento-{$oferta->OFER_ID}.pdf", 'Documentación de sustento de ejemplo (estrés).');

        return $oferta->fresh();
    }

    private function crearArchivoEstres(string $contexto, int $referenciaId, string $nombreOriginal, string $contenido): Archivo
    {
        $ruta = 'estres/' . strtolower($contexto) . '/' . $referenciaId . '/' . $nombreOriginal;
        Storage::disk('local')->put($ruta, $contenido);

        return Archivo::create([
            'ARCH_CONTEXTO' => $contexto,
            'ARCH_REFERENCIA_ID' => $referenciaId,
            'ARCH_NOMBRE_ORIGINAL' => $nombreOriginal,
            'ARCH_RUTA' => $ruta,
            'ARCH_MIME' => str_ends_with($nombreOriginal, '.docx')
                ? 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
                : 'application/pdf',
            'ARCH_TAMANO_BYTES' => strlen($contenido),
            'ARCH_HASH_SHA256' => hash('sha256', $contenido),
        ]);
    }

    private function imprimirResumen(): void
    {
        $this->table(
            ['Entidad', 'Total'],
            [
                ['Proveedores [ESTRÉS]', Proveedor::where('PROV_CORREO', 'like', 'proveedor.estres.%')->count()],
                ['Requerimientos [ESTRÉS]', Requerimiento::where('REQU_OBJETO', 'like', self::MARCADOR . '%')->count()],
                ['Convocatorias [ESTRÉS] — Borrador', Convocatoria::where('CONV_NOMBRE_SERVICIO', 'like', self::MARCADOR . '%')->where('CONV_ESTADO_ID', $this->estadoId('CONVOCATORIA', 'BORRADOR'))->count()],
                ['Convocatorias [ESTRÉS] — Publicada', Convocatoria::where('CONV_NOMBRE_SERVICIO', 'like', self::MARCADOR . '%')->where('CONV_ESTADO_ID', $this->estadoId('CONVOCATORIA', 'PUBLICADA'))->count()],
                ['  · de ellas, ya ampliadas antes', Convocatoria::where('CONV_NOMBRE_SERVICIO', 'like', self::MARCADOR . '%')->whereNotNull('CONV_FECHA_FIN_ANTERIOR')->count()],
                ['Convocatorias [ESTRÉS] — En evaluación', Convocatoria::where('CONV_NOMBRE_SERVICIO', 'like', self::MARCADOR . '%')->where('CONV_ESTADO_ID', $this->estadoId('CONVOCATORIA', 'EN_EVALUACION'))->count()],
                ['Convocatorias [ESTRÉS] — Adjudicada', Convocatoria::where('CONV_NOMBRE_SERVICIO', 'like', self::MARCADOR . '%')->where('CONV_ESTADO_ID', $this->estadoId('CONVOCATORIA', 'ADJUDICADA'))->count()],
                ['Convocatorias [ESTRÉS] — Desierta', Convocatoria::where('CONV_NOMBRE_SERVICIO', 'like', self::MARCADOR . '%')->where('CONV_ESTADO_ID', $this->estadoId('CONVOCATORIA', 'DESIERTA'))->count()],
                ['Convocatorias [ESTRÉS] — Cancelada', Convocatoria::where('CONV_NOMBRE_SERVICIO', 'like', self::MARCADOR . '%')->where('CONV_ESTADO_ID', $this->estadoId('CONVOCATORIA', 'CANCELADA'))->count()],
                ['Ofertas [ESTRÉS]', Oferta::whereIn('OFER_CONVOCATORIA_ID', Convocatoria::where('CONV_NOMBRE_SERVICIO', 'like', self::MARCADOR . '%')->pluck('CONV_ID'))->count()],
            ],
        );

        $this->components->info('Usa el buscador de cada módulo con "[ESTRÉS]" para filtrar solo estos registros.');
        $this->components->info('Para borrarlos más adelante: php artisan desa:generar-estres --limpiar --proveedores=0 --requerimientos=0');
    }
}
