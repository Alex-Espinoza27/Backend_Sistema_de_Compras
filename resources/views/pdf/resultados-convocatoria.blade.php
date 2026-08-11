<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Indagación de Mercado {{ $convocatoria->CONV_NUMERO_INDAGACION }}</title>
    <style>
        body { font-family: sans-serif; font-size: 11px; }
        .encabezado { text-align: center; margin-bottom: 10px; }
        .encabezado .entidad { font-size: 12px; font-weight: bold; text-transform: uppercase; }
        .encabezado .titulo { font-size: 15px; font-weight: bold; margin-top: 4px; }
        .metadatos { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        .metadatos td { padding: 2px 6px; font-size: 11px; vertical-align: top; }
        .metadatos td.etiqueta { font-weight: bold; width: 220px; }
        .aviso-desierta { border: 1px solid #b71c1c; background-color: #fdecea; color: #b71c1c; padding: 8px; margin-bottom: 10px; font-weight: bold; }
        table.postores { width: 100%; border-collapse: collapse; margin-top: 8px; }
        table.postores th, table.postores td { border: 1px solid #999; padding: 4px 6px; text-align: left; }
        table.postores th { background-color: #eee; }
        .pie { margin-top: 16px; font-size: 9px; color: #555; }
    </style>
</head>
<body>
    <div class="encabezado">
        <div class="entidad">{{ $convocatoria->entidad->ENTI_RAZON_SOCIAL }}</div>
        <div class="titulo">INDAGACIÓN DE MERCADO N° {{ $convocatoria->CONV_NUMERO_INDAGACION }}</div>
    </div>

    <table class="metadatos">
        <tr>
            <td class="etiqueta">FECHA DE INICIO DE CONVOCATORIA</td>
            <td>{{ optional($convocatoria->CONV_FECHA_INICIO)->format('d/m/Y') ?? '—' }}</td>
        </tr>
        <tr>
            <td class="etiqueta">FECHA DE CULMINACIÓN DE ESTUDIO DE MERCADO</td>
            <td>{{ optional($convocatoria->CONV_FECHA_RESULTADO)->format('d/m/Y') ?? '—' }}</td>
        </tr>
        <tr>
            <td class="etiqueta">CENTRO DE COSTO</td>
            <td>{{ $convocatoria->requerimiento?->area?->AREA_NOMBRE ?? '—' }}</td>
        </tr>
        <tr>
            <td class="etiqueta">OBJETO DE LA CONTRATACIÓN</td>
            <td>{{ $convocatoria->requerimiento?->REQU_OBJETO ?? $convocatoria->CONV_NOMBRE_SERVICIO }}</td>
        </tr>
        <tr>
            <td class="etiqueta">REQUERIMIENTO DE BB Y SS</td>
            <td>{{ $convocatoria->requerimiento?->REQU_NUMERO ?? '—' }}</td>
        </tr>
        <tr>
            <td class="etiqueta">TIPO DE CONTRATACIÓN</td>
            <td>{{ $convocatoria->requerimiento?->REQU_TIPO === 'BIEN' ? 'Bien' : 'Servicio' }}</td>
        </tr>
    </table>

    @if ($convocatoria->estado->ESTA_CODIGO === 'DESIERTA')
        <div class="aviso-desierta">
            La convocatoria {{ $convocatoria->CONV_NOMBRE_SERVICIO }} fue DECLARADA DESIERTA por el motivo siguiente:
            {{ $convocatoria->CONV_MOTIVO_CIERRE }}
        </div>
    @endif

    <table class="postores">
        <thead>
            <tr>
                <th>Ítem</th>
                <th>N.° RUC</th>
                <th>Razón Social</th>
                <th>Evaluación Técnica</th>
                <th>Motivo de Desaprobación Ev. Técnica</th>
                <th>Evaluación Logística (S/)</th>
                <th>Motivo de Desaprobación Ev. Logística</th>
                <th>Resultado</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($ofertas as $indice => $oferta)
                <tr>
                    <td>{{ $indice + 1 }}</td>
                    <td>{{ $oferta->proveedor->PROV_NUMERO_DOCUMENTO }}</td>
                    <td>{{ $oferta->proveedor->PROV_RAZON_SOCIAL }}</td>
                    <td>{{ $oferta->OFER_EVAL_TECNICA ?? '—' }}</td>
                    <td>{{ $oferta->OFER_EVAL_TECNICA_MOTIVO ?? '—' }}</td>
                    <td>{{ number_format((float) $oferta->OFER_TOTAL_GENERAL, 2) }}</td>
                    <td>{{ $oferta->OFER_EVAL_LOGISTICA_MOTIVO ?? '—' }}</td>
                    <td>{{ $oferta->estado->ESTA_NOMBRE }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="pie">
        Los precios están expresados en soles e incluyen todo concepto (impuestos, transporte y otros) salvo indicación distinta en las bases.
        Los resultados se determinan de acuerdo a la evaluación técnica y logística registradas por la entidad convocante.
    </div>
</body>
</html>
