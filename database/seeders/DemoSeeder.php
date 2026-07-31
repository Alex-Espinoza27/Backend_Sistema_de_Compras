<?php

namespace Database\Seeders;

use App\Models\Archivo;
use App\Models\Auditoria;
use App\Models\Convocatoria;
use App\Models\Entidad;
use App\Models\Estado;
use App\Models\Notificacion;
use App\Models\Oferta;
use App\Models\OfertaItem;
use App\Models\Proveedor;
use App\Models\ProveedorRubro;
use App\Models\Requerimiento;
use App\Models\RequerimientoItem;
use App\Models\Rubro;
use App\Models\TipoComprobante;
use App\Models\TipoDocumento;
use App\Models\UnidadMedida;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

/**
 * Datos de demostración (solo local, 02C Parte II) para probar cada módulo
 * sin cargar nada a mano: entidades/proveedores en distintos estados,
 * convocatorias en los 6 estados y ofertas en los 6 estados de evaluación.
 */
class DemoSeeder extends Seeder
{
    private array $estados;

    private array $tipoDocumento;

    private array $tipoComprobante;

    private array $unidadMedida;

    private array $rubros;

    public function run(): void
    {
        $this->estados = Estado::all()->groupBy('ESTA_CONTEXTO')
            ->map(fn ($grupo) => $grupo->keyBy('ESTA_CODIGO'))
            ->toArray();
        $this->tipoDocumento = TipoDocumento::all()->keyBy('TIDO_CODIGO')->toArray();
        $this->tipoComprobante = TipoComprobante::all()->keyBy('TICO_CODIGO')->toArray();
        $this->unidadMedida = UnidadMedida::all()->keyBy('UNME_CODIGO')->toArray();
        $this->rubros = Rubro::all()->values()->toArray();

        $this->seedAuditor();
        $entidades = $this->seedEntidades();
        $proveedores = $this->seedProveedores();
        $convocatorias = $this->seedRequerimientosYConvocatorias($entidades['aprobada']);
        $this->seedOfertas($convocatorias, $proveedores);
        $this->seedNotificacionesYAuditoria($entidades, $proveedores, $convocatorias);
    }

    private function estadoId(string $contexto, string $codigo): int
    {
        return (int) $this->estados[$contexto][$codigo]['ESTA_ID'];
    }

    private function seedAuditor(): void
    {
        $auditor = User::updateOrCreate(
            ['email' => 'auditor@munilaperla.gob.pe'],
            ['name' => 'Auditor de Plataforma', 'password' => Hash::make('Password123')],
        );

        if (! $auditor->hasRole('AUDITOR')) {
            $auditor->assignRole('AUDITOR');
        }
    }

    private function seedEntidades(): array
    {
        $aprobada = Entidad::updateOrCreate(
            ['ENTI_NUMERO_DOCUMENTO' => '20100000001'],
            [
                'ENTI_TIPO_DOCUMENTO_ID' => $this->tipoDocumento['RUC']['TIDO_ID'],
                'ENTI_RAZON_SOCIAL' => 'Municipalidad Distrital de La Perla',
                'ENTI_SIGLA' => 'MDLP',
                'ENTI_CORREO' => 'contacto@munilaperla.gob.pe',
                'ENTI_TELEFONO' => '014567890',
                'ENTI_DIRECCION' => 'Av. Guardia Chalaca 100, La Perla, Callao',
                'ENTI_PERSONA_CONTACTO' => 'Jefe de Abastecimiento',
                'ENTI_ESTADO_ID' => $this->estadoId('ENTIDAD', 'APROBADA'),
                'ENTI_FECHA_SOLICITUD' => now()->subDays(60),
                'ENTI_FECHA_RESOLUCION' => now()->subDays(59),
            ],
        );

        $solicitada = Entidad::updateOrCreate(
            ['ENTI_NUMERO_DOCUMENTO' => '20100000002'],
            [
                'ENTI_TIPO_DOCUMENTO_ID' => $this->tipoDocumento['RUC']['TIDO_ID'],
                'ENTI_RAZON_SOCIAL' => 'Municipalidad Distrital Ejemplo Solicitante',
                'ENTI_SIGLA' => 'MDES',
                'ENTI_CORREO' => 'contacto@ejemplo-solicitante.gob.pe',
                'ENTI_TELEFONO' => '014567891',
                'ENTI_DIRECCION' => 'Av. Ejemplo 200',
                'ENTI_PERSONA_CONTACTO' => 'Encargado de TI',
                'ENTI_ESTADO_ID' => $this->estadoId('ENTIDAD', 'SOLICITADA'),
                'ENTI_FECHA_SOLICITUD' => now()->subDays(3),
            ],
        );

        $suspendida = Entidad::updateOrCreate(
            ['ENTI_NUMERO_DOCUMENTO' => '20100000003'],
            [
                'ENTI_TIPO_DOCUMENTO_ID' => $this->tipoDocumento['RUC']['TIDO_ID'],
                'ENTI_RAZON_SOCIAL' => 'Municipalidad Distrital Ejemplo Suspendida',
                'ENTI_SIGLA' => 'MDESU',
                'ENTI_CORREO' => 'contacto@ejemplo-suspendida.gob.pe',
                'ENTI_TELEFONO' => '014567892',
                'ENTI_DIRECCION' => 'Av. Ejemplo 300',
                'ENTI_PERSONA_CONTACTO' => 'Encargado de Abastecimiento',
                'ENTI_ESTADO_ID' => $this->estadoId('ENTIDAD', 'SUSPENDIDA'),
                'ENTI_MOTIVO_OBSERVACION' => 'Incumplimiento de las condiciones de uso de la plataforma.',
                'ENTI_FECHA_SOLICITUD' => now()->subDays(120),
                'ENTI_FECHA_RESOLUCION' => now()->subDays(10),
            ],
        );

        $entidad1 = User::updateOrCreate(
            ['email' => 'entidad@munilaperla.gob.pe'],
            ['name' => 'Usuario Entidad Uno', 'password' => Hash::make('Password123'), 'entidad_id' => $aprobada->ENTI_ID],
        );
        if (! $entidad1->hasRole('ENTIDAD')) {
            $entidad1->assignRole('ENTIDAD');
        }

        $entidad2 = User::updateOrCreate(
            ['email' => 'entidad2@munilaperla.gob.pe'],
            ['name' => 'Usuario Entidad Dos', 'password' => Hash::make('Password123'), 'entidad_id' => $aprobada->ENTI_ID],
        );
        if (! $entidad2->hasRole('ENTIDAD')) {
            $entidad2->assignRole('ENTIDAD');
        }

        return ['aprobada' => $aprobada, 'solicitada' => $solicitada, 'suspendida' => $suspendida];
    }

    private function seedProveedores(): array
    {
        $datos = [
            ['ruc' => '20200000001', 'razon' => 'Comercial Andina S.A.C.', 'persona' => 'JURIDICA', 'correo' => 'proveedor@ejemplo.com', 'estado' => 'ACTIVO'],
            ['ruc' => '20200000002', 'razon' => 'Distribuidora del Pacífico E.I.R.L.', 'persona' => 'JURIDICA', 'correo' => 'proveedor2@ejemplo.com', 'estado' => 'ACTIVO'],
            ['ruc' => '20200000003', 'razon' => 'Suministros Callao S.R.L.', 'persona' => 'JURIDICA', 'correo' => 'proveedor3@ejemplo.com', 'estado' => 'ACTIVO'],
            ['ruc' => '20200000004', 'razon' => 'Tecno Servicios del Norte S.A.C.', 'persona' => 'JURIDICA', 'correo' => 'proveedor4@ejemplo.com', 'estado' => 'ACTIVO'],
            ['ruc' => '20200000005', 'razon' => 'Mantenimientos Integrales S.A.C.', 'persona' => 'JURIDICA', 'correo' => 'proveedor5@ejemplo.com', 'estado' => 'ACTIVO'],
            ['ruc' => '20200000006', 'razon' => 'Oficentro Perú S.R.L.', 'persona' => 'JURIDICA', 'correo' => 'proveedor6@ejemplo.com', 'estado' => 'ACTIVO'],
            ['ruc' => '20200000007', 'razon' => 'Limpieza Total S.A.C.', 'persona' => 'JURIDICA', 'correo' => 'proveedor7@ejemplo.com', 'estado' => 'ACTIVO'],
            ['ruc' => '10200000008', 'razon' => 'Juana Ramírez Torres', 'persona' => 'NATURAL', 'correo' => 'proveedor8@ejemplo.com', 'estado' => 'ACTIVO', 'documento' => 'DNI'],
            ['ruc' => '10200000009', 'razon' => 'Carlos Mendoza Ríos', 'persona' => 'NATURAL', 'correo' => 'proveedor9@ejemplo.com', 'estado' => 'ACTIVO', 'documento' => 'DNI'],
            ['ruc' => '20200000010', 'razon' => 'Consultores Asociados del Callao S.A.C.', 'persona' => 'JURIDICA', 'correo' => 'proveedor10@ejemplo.com', 'estado' => 'ACTIVO'],
            ['ruc' => '20200000011', 'razon' => 'Importaciones Suspendidas S.A.C.', 'persona' => 'JURIDICA', 'correo' => 'proveedorsuspendido@ejemplo.com', 'estado' => 'SUSPENDIDO'],
        ];

        $proveedores = [];

        foreach ($datos as $i => $fila) {
            $tipoDocCodigo = $fila['documento'] ?? 'RUC';

            $user = User::updateOrCreate(
                ['email' => $fila['correo']],
                ['name' => $fila['razon'], 'password' => Hash::make('Password123')],
            );
            if (! $user->hasRole('PROVEEDOR')) {
                $user->assignRole('PROVEEDOR');
            }

            $proveedor = Proveedor::updateOrCreate(
                ['PROV_NUMERO_DOCUMENTO' => $fila['ruc']],
                [
                    'PROV_USER_ID' => $user->id,
                    'PROV_TIPO_DOCUMENTO_ID' => $this->tipoDocumento[$tipoDocCodigo]['TIDO_ID'],
                    'PROV_TIPO_PERSONA' => $fila['persona'],
                    'PROV_RAZON_SOCIAL' => $fila['razon'],
                    'PROV_CORREO' => $fila['correo'],
                    'PROV_TELEFONO' => '9' . str_pad((string) (100000000 + $i), 8, '0', STR_PAD_LEFT),
                    'PROV_PERSONA_CONTACTO' => $fila['razon'],
                    'PROV_ESTADO_ID' => $this->estadoId('PROVEEDOR', $fila['estado']),
                    'PROV_ORIGEN_DATO' => 'MANUAL',
                ],
            );

            $rubrosAsignados = collect($this->rubros)->random(min(2, count($this->rubros)));
            foreach ($rubrosAsignados as $rubro) {
                ProveedorRubro::updateOrCreate([
                    'PRRU_PROVEEDOR_ID' => $proveedor->PROV_ID,
                    'PRRU_RUBRO_ID' => $rubro['RUBR_ID'],
                ]);
            }

            $proveedores[] = $proveedor;
        }

        return $proveedores;
    }

    private function seedRequerimientosYConvocatorias(Entidad $entidad): array
    {
        $definiciones = [
            [
                'objeto' => 'Adquisición de útiles de oficina para el primer trimestre',
                'tipo' => 'BIEN',
                'rubro' => 'B-01',
                'items' => [['Papel bond A4 80gr', 'CAJA', 50], ['Lapiceros de tinta líquida', 'CAJA', 20]],
                'conv_estado' => 'BORRADOR',
            ],
            [
                'objeto' => 'Mantenimiento preventivo de equipos de aire acondicionado',
                'tipo' => 'SERVICIO',
                'rubro' => 'S-01',
                'items' => [['Mantenimiento de unidad split 24000 BTU', 'SERVICIO', 8]],
                'conv_estado' => 'PUBLICADA_NORMAL',
            ],
            [
                'objeto' => 'Adquisición de equipos de cómputo para la Oficina de TI',
                'tipo' => 'BIEN',
                'rubro' => 'B-02',
                'items' => [['Laptop core i5 8GB RAM', 'UNIDAD', 5], ['Mouse inalámbrico', 'UNIDAD', 5]],
                'conv_estado' => 'PUBLICADA_URGENTE',
            ],
            [
                'objeto' => 'Servicio de consultoría para actualización del PIA',
                'tipo' => 'SERVICIO',
                'rubro' => 'S-02',
                'items' => [['Consultoría especializada en presupuesto público', 'SERVICIO', 1]],
                'conv_estado' => 'EN_EVALUACION',
            ],
            [
                'objeto' => 'Adquisición de mobiliario para la sala de sesiones',
                'tipo' => 'BIEN',
                'rubro' => 'B-03',
                'items' => [['Silla ejecutiva giratoria', 'UNIDAD', 12], ['Mesa de reuniones', 'UNIDAD', 1]],
                'conv_estado' => 'ADJUDICADA',
            ],
            [
                'objeto' => 'Servicio de limpieza de áreas administrativas',
                'tipo' => 'SERVICIO',
                'rubro' => 'S-03',
                'items' => [['Servicio de limpieza mensual', 'SERVICIO', 3]],
                'conv_estado' => 'DESIERTA',
            ],
            [
                'objeto' => 'Adquisición de kits de primeros auxilios',
                'tipo' => 'BIEN',
                'rubro' => 'B-01',
                'items' => [['Kit de primeros auxilios completo', 'KIT', 15]],
                'conv_estado' => 'CANCELADA',
            ],
        ];

        $rubroPorCodigo = collect($this->rubros)->keyBy('RUBR_CODIGO');
        $convocatorias = [];
        $correlativo = 0;

        foreach ($definiciones as $i => $def) {
            $numero = 'REQ-' . now()->year . '-' . str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT);

            $requerimiento = Requerimiento::updateOrCreate(
                ['REQU_ENTIDAD_ID' => $entidad->ENTI_ID, 'REQU_NUMERO' => $numero],
                [
                    'REQU_RUBRO_ID' => $rubroPorCodigo[$def['rubro']]['RUBR_ID'],
                    'REQU_OBJETO' => $def['objeto'],
                    'REQU_DESCRIPCION' => "Especificaciones técnicas / TDR de: {$def['objeto']}.",
                    'REQU_TIPO' => $def['tipo'],
                    'REQU_AREA_USUARIA' => 'Oficina de Abastecimiento y Control Patrimonial',
                ],
            );

            $itemsCreados = [];
            foreach ($def['items'] as $n => [$descripcion, $unidad, $cantidad]) {
                $itemsCreados[] = RequerimientoItem::updateOrCreate(
                    ['REIT_REQUERIMIENTO_ID' => $requerimiento->REQU_ID, 'REIT_NUMERO' => $n + 1],
                    [
                        'REIT_DESCRIPCION' => $descripcion,
                        'REIT_UNIDAD_MEDIDA_ID' => $this->unidadMedida[$unidad]['UNME_ID'],
                        'REIT_CANTIDAD' => $cantidad,
                    ],
                );
            }

            $this->crearArchivoDemo('REQUERIMIENTO_TDR', $requerimiento->REQU_ID, "TDR-{$numero}.pdf",
                "Términos de referencia de ejemplo para: {$def['objeto']}.");

            [$fechaInicio, $fechaFin, $fechaPublicacion, $fechaApertura, $fechaResultado, $motivoCierre] =
                $this->fechasPorEstado($def['conv_estado']);

            $estadoCodigoReal = str_starts_with($def['conv_estado'], 'PUBLICADA') ? 'PUBLICADA' : $def['conv_estado'];

            $convNumero = null;
            if ($estadoCodigoReal !== 'BORRADOR') {
                $correlativo++;
                $convNumero = 'CM-' . str_pad((string) $correlativo, 3, '0', STR_PAD_LEFT) . '-' . now()->year . '-' . $entidad->ENTI_SIGLA;
            }

            $convocatoria = Convocatoria::updateOrCreate(
                ['CONV_REQUERIMIENTO_ID' => $requerimiento->REQU_ID],
                [
                    'CONV_ENTIDAD_ID' => $entidad->ENTI_ID,
                    'CONV_NUMERO' => $convNumero,
                    'CONV_NOMBRE_SERVICIO' => $def['objeto'],
                    'CONV_FECHA_INICIO' => $fechaInicio,
                    'CONV_FECHA_FIN' => $fechaFin,
                    'CONV_ESTADO_ID' => $this->estadoId('CONVOCATORIA', $estadoCodigoReal),
                    'CONV_MOTIVO_CIERRE' => $motivoCierre,
                    'CONV_FECHA_PUBLICACION' => $fechaPublicacion,
                    'CONV_FECHA_APERTURA' => $fechaApertura,
                    'CONV_FECHA_RESULTADO' => $fechaResultado,
                ],
            );

            if ($estadoCodigoReal !== 'BORRADOR') {
                $this->crearArchivoDemo('CONVOCATORIA_PLANTILLA_DJ', $convocatoria->CONV_ID, "DJ-Plantilla-{$convNumero}.docx",
                    'Plantilla de declaración jurada de ejemplo.');
            }

            $entidad->ENTI_CORRELATIVO_CONVOCATORIA = $correlativo;
            $entidad->ENTI_CORRELATIVO_ANIO = now()->year;
            $entidad->save();

            $convocatorias[$def['conv_estado']] = ['convocatoria' => $convocatoria, 'items' => $itemsCreados];
        }

        return $convocatorias;
    }

    private function fechasPorEstado(string $estado): array
    {
        return match ($estado) {
            'BORRADOR' => [now()->addDays(2), now()->addDays(9), null, null, null, null],
            'PUBLICADA_NORMAL' => [now()->subDays(2), now()->addDays(10), now()->subDays(2), null, null, null],
            'PUBLICADA_URGENTE' => [now()->subDays(1), now()->addDays(1), now()->subDays(1), null, null, null],
            'EN_EVALUACION' => [now()->subDays(10), now()->subDay(), now()->subDays(10), now()->subDay(), null, null],
            'ADJUDICADA' => [now()->subDays(20), now()->subDays(10), now()->subDays(20), now()->subDays(10), now()->subDays(5), null],
            'DESIERTA' => [now()->subDays(20), now()->subDays(10), now()->subDays(20), now()->subDays(10), now()->subDays(5),
                'Ningún postor cumplió los requisitos técnicos y logísticos exigidos.'],
            'CANCELADA' => [now()->subDays(15), now()->addDays(5), now()->subDays(15), null, now()->subDays(3),
                'Cancelada por reformulación del requerimiento a solicitud del área usuaria.'],
            default => [now(), now()->addDays(7), null, null, null, null],
        };
    }

    private function seedOfertas(array $convocatorias, array $proveedores): void
    {
        $tico = $this->tipoComprobante['FACTURA']['TICO_ID'];

        // PUBLICADA (vigente, normal): 1 oferta recién enviada.
        $this->crearOferta($convocatorias['PUBLICADA_NORMAL'], $proveedores[0], 'ENVIADA', $tico);

        // PUBLICADA (vigente, urgente): 1 oferta recién enviada.
        $this->crearOferta($convocatorias['PUBLICADA_URGENTE'], $proveedores[1], 'ENVIADA', $tico);

        // EN_EVALUACION: una en evaluación, una aprobada (ambas compuertas), una desaprobada.
        $this->crearOferta($convocatorias['EN_EVALUACION'], $proveedores[2], 'EN_EVALUACION', $tico);
        $this->crearOferta($convocatorias['EN_EVALUACION'], $proveedores[3], 'APROBADA', $tico, evaluada: true);
        $this->crearOferta($convocatorias['EN_EVALUACION'], $proveedores[4], 'DESAPROBADA', $tico, evaluada: true,
            motivoTecnico: 'No cumple con las especificaciones técnicas mínimas requeridas.');

        // ADJUDICADA: ganadora + dos no adjudicadas (una aprobada con mayor precio, otra desaprobada).
        $ganadora = $this->crearOferta($convocatorias['ADJUDICADA'], $proveedores[5], 'ADJUDICADA', $tico, evaluada: true, precioBase: 1000);
        $this->crearOferta($convocatorias['ADJUDICADA'], $proveedores[6], 'NO_ADJUDICADA', $tico, evaluada: true, precioBase: 1500);
        $this->crearOferta($convocatorias['ADJUDICADA'], $proveedores[7], 'NO_ADJUDICADA', $tico, evaluada: true,
            motivoTecnico: 'No adjunta documentación de sustento completa.');

        /** @var Convocatoria $convAdjudicada */
        $convAdjudicada = $convocatorias['ADJUDICADA']['convocatoria'];
        $convAdjudicada->CONV_OFERTA_ADJUDICADA_ID = $ganadora->OFER_ID;
        $convAdjudicada->save();

        $this->crearArchivoDemo('RESULTADO_PDF', $convAdjudicada->CONV_ID, "Resultados-{$convAdjudicada->CONV_NUMERO}.pdf",
            'PDF de resultados de ejemplo con el cuadro comparativo de postores.');

        // DESIERTA: ofertas presentadas pero ninguna elegible.
        $this->crearOferta($convocatorias['DESIERTA'], $proveedores[8], 'NO_ADJUDICADA', $tico, evaluada: true,
            motivoTecnico: 'No cumple con las especificaciones técnicas mínimas requeridas.');

        // CANCELADA: quedó una oferta enviada antes de la cancelación (nunca evaluada).
        $this->crearOferta($convocatorias['CANCELADA'], $proveedores[9], 'ENVIADA', $tico);
    }

    private function crearOferta(
        array $convocatoriaConItems,
        Proveedor $proveedor,
        string $estadoCodigo,
        int $tipoComprobanteId,
        bool $evaluada = false,
        ?string $motivoTecnico = null,
        float $precioBase = 500,
    ): Oferta {
        /** @var Convocatoria $convocatoria */
        $convocatoria = $convocatoriaConItems['convocatoria'];
        /** @var RequerimientoItem[] $items */
        $items = $convocatoriaConItems['items'];

        $evalTecnica = $motivoTecnico ? 'DESAPROBADO' : ($evaluada ? 'APROBADO' : null);
        $evalLogistica = $evaluada && ! $motivoTecnico ? 'APROBADO' : null;

        $oferta = Oferta::updateOrCreate(
            ['OFER_CONVOCATORIA_ID' => $convocatoria->CONV_ID, 'OFER_PROVEEDOR_ID' => $proveedor->PROV_ID],
            [
                'OFER_ESTADO_ID' => $this->estadoId('OFERTA', $estadoCodigo),
                'OFER_TIPO_COMPROBANTE_ID' => $tipoComprobanteId,
                'OFER_VALIDEZ_DIAS' => 30,
                'OFER_PLAZO_EJECUCION_SEGUN_TDR' => true,
                'OFER_FORMA_PAGO_SEGUN_TDR' => true,
                'OFER_PLAZO_GARANTIA' => '12 meses',
                'OFER_CONTACTO_NOMBRE' => $proveedor->PROV_PERSONA_CONTACTO,
                'OFER_CONTACTO_TELEFONO' => $proveedor->PROV_TELEFONO,
                'OFER_CONTACTO_CORREO' => $proveedor->PROV_CORREO,
                'OFER_CONSTANCIA_CODIGO' => 'CONST-' . strtoupper(uniqid()),
                'OFER_FECHA_ENVIO' => now()->subDays(rand(1, 15)),
                'OFER_EVAL_TECNICA' => $evalTecnica,
                'OFER_EVAL_TECNICA_MOTIVO' => $motivoTecnico,
                'OFER_EVAL_LOGISTICA' => $evalLogistica,
                'OFER_EVAL_LOGISTICA_MOTIVO' => null,
                'OFER_EVALUADA_POR' => $evaluada ? User::where('email', 'entidad@munilaperla.gob.pe')->value('id') : null,
                'OFER_FECHA_EVALUACION' => $evaluada ? now()->subDays(rand(1, 5)) : null,
            ],
        );

        foreach ($items as $n => $item) {
            OfertaItem::updateOrCreate(
                ['OFIT_OFERTA_ID' => $oferta->OFER_ID, 'OFIT_REQUERIMIENTO_ITEM_ID' => $item->REIT_ID],
                [
                    'OFIT_MARCA_MODELO' => 'Marca genérica de ejemplo',
                    'OFIT_PRECIO_UNITARIO' => $precioBase + ($n * 25) + ($proveedor->PROV_ID % 10),
                ],
            );
        }

        DB::statement('SELECT fn_oferta_recalcular_total(?)', [$oferta->OFER_ID]);

        $this->crearArchivoDemo('OFERTA_DJ', $oferta->OFER_ID, "DJ-Firmada-{$oferta->OFER_ID}.pdf",
            'Declaración jurada firmada de ejemplo.');
        $this->crearArchivoDemo('OFERTA_SUSTENTO', $oferta->OFER_ID, "Sustento-{$oferta->OFER_ID}.pdf",
            'Documentación de sustento de ejemplo.');

        return $oferta->fresh();
    }

    private function crearArchivoDemo(string $contexto, int $referenciaId, string $nombreOriginal, string $contenido): Archivo
    {
        $ruta = 'demo/' . strtolower($contexto) . '/' . $referenciaId . '/' . $nombreOriginal;
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

    private function seedNotificacionesYAuditoria(array $entidades, array $proveedores, array $convocatorias): void
    {
        $entidadUser = User::where('email', 'entidad@munilaperla.gob.pe')->first();
        $proveedorUser = $proveedores[5]->user;
        $adminUser = User::where('email', 'admin@munilaperla.gob.pe')->first();

        /** @var Convocatoria $convAdjudicada */
        $convAdjudicada = $convocatorias['ADJUDICADA']['convocatoria'];

        Notificacion::create([
            'NOTI_USER_ID' => $proveedorUser->id,
            'NOTI_TIPO' => 'ADJUDICACION',
            'NOTI_TITULO' => 'Has sido adjudicado',
            'NOTI_MENSAJE' => "Tu oferta a la convocatoria {$convAdjudicada->CONV_NUMERO} fue adjudicada.",
            'NOTI_REFERENCIA_TIPO' => 'CONVOCATORIA',
            'NOTI_REFERENCIA_ID' => $convAdjudicada->CONV_ID,
        ]);

        Notificacion::create([
            'NOTI_USER_ID' => $entidadUser->id,
            'NOTI_TIPO' => 'OFERTA_RECIBIDA',
            'NOTI_TITULO' => 'Nueva postulación recibida',
            'NOTI_MENSAJE' => 'Se registró una nueva postulación en una de tus convocatorias vigentes.',
            'NOTI_REFERENCIA_TIPO' => 'CONVOCATORIA',
            'NOTI_REFERENCIA_ID' => $convocatorias['PUBLICADA_NORMAL']['convocatoria']->CONV_ID,
            'NOTI_LEIDA' => true,
        ]);

        Notificacion::create([
            'NOTI_USER_ID' => $adminUser->id,
            'NOTI_TIPO' => 'ENTIDAD_RESOLUCION',
            'NOTI_TITULO' => 'Solicitud de entidad pendiente',
            'NOTI_MENSAJE' => "La entidad {$entidades['solicitada']->ENTI_RAZON_SOCIAL} está a la espera de resolución.",
            'NOTI_REFERENCIA_TIPO' => 'ENTIDAD',
            'NOTI_REFERENCIA_ID' => $entidades['solicitada']->ENTI_ID,
        ]);

        $auditoriaBase = [
            ['modulo' => 'AUTENTICACION', 'accion' => 'LOGIN', 'user' => $adminUser->id, 'tabla' => null, 'registro' => null],
            ['modulo' => 'CONVOCATORIAS', 'accion' => 'PUBLICAR', 'user' => $entidadUser->id, 'tabla' => 'CONVOCATORIA', 'registro' => $convocatorias['PUBLICADA_NORMAL']['convocatoria']->CONV_ID],
            ['modulo' => 'EVALUACION', 'accion' => 'EVALUAR', 'user' => $entidadUser->id, 'tabla' => 'OFERTA', 'registro' => null],
            ['modulo' => 'ADJUDICACION', 'accion' => 'ADJUDICAR', 'user' => $entidadUser->id, 'tabla' => 'CONVOCATORIA', 'registro' => $convAdjudicada->CONV_ID],
            ['modulo' => 'ADJUDICACION', 'accion' => 'DESIERTA', 'user' => $entidadUser->id, 'tabla' => 'CONVOCATORIA', 'registro' => $convocatorias['DESIERTA']['convocatoria']->CONV_ID],
            ['modulo' => 'CONVOCATORIAS', 'accion' => 'CANCELAR', 'user' => $entidadUser->id, 'tabla' => 'CONVOCATORIA', 'registro' => $convocatorias['CANCELADA']['convocatoria']->CONV_ID],
        ];

        foreach ($auditoriaBase as $i => $fila) {
            Auditoria::create([
                'AUDI_USER_ID' => $fila['user'],
                'AUDI_MODULO' => $fila['modulo'],
                'AUDI_ACCION' => $fila['accion'],
                'AUDI_TABLA' => $fila['tabla'],
                'AUDI_REGISTRO_ID' => $fila['registro'],
                'AUDI_IP' => '127.0.0.1',
                'AUDI_USER_AGENT' => 'DemoSeeder/1.0',
                'AUDI_ENDPOINT' => '/api/v1/demo-seed',
                'AUDI_FECHA' => now()->subDays(10 - $i),
            ]);
        }
    }
}
