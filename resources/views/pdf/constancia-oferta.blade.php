<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Constancia {{ $oferta->OFER_CONSTANCIA_CODIGO }}</title>
    <style>
        body { font-family: sans-serif; font-size: 11px; }
        h1 { font-size: 15px; margin-bottom: 2px; }
        h2 { font-size: 12px; font-weight: normal; margin-top: 0; color: #555; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { border: 1px solid #999; padding: 4px 6px; text-align: left; }
        th { background-color: #eee; }
        .datos { margin-top: 10px; }
        .datos td { border: none; padding: 2px 6px; }
        .total { font-weight: bold; }
    </style>
</head>
<body>
    <h1>Constancia de presentación</h1>
    <h2>Código: {{ $oferta->OFER_CONSTANCIA_CODIGO }}</h2>

    <table class="datos">
        <tr><td>Convocatoria</td><td>{{ $oferta->convocatoria->CONV_NUMERO }} — {{ $oferta->convocatoria->CONV_NOMBRE_SERVICIO }}</td></tr>
        <tr><td>Proveedor</td><td>{{ $oferta->proveedor->PROV_NUMERO_DOCUMENTO }} — {{ $oferta->proveedor->PROV_RAZON_SOCIAL }}</td></tr>
        <tr><td>Fecha de envío</td><td>{{ optional($oferta->OFER_FECHA_ENVIO)->format('d/m/Y H:i') }}</td></tr>
    </table>

    <table>
        <thead>
            <tr>
                <th>N.°</th>
                <th>Descripción</th>
                <th>Marca / Modelo</th>
                <th>Precio unitario</th>
                <th>Precio total</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($oferta->items as $item)
                <tr>
                    <td>{{ $item->requerimientoItem->REIT_NUMERO }}</td>
                    <td>{{ $item->requerimientoItem->REIT_DESCRIPCION }}</td>
                    <td>{{ $item->OFIT_MARCA_MODELO }}</td>
                    <td>{{ number_format((float) $item->OFIT_PRECIO_UNITARIO, 2) }}</td>
                    <td>{{ number_format((float) $item->OFIT_PRECIO_TOTAL, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="4" class="total">TOTAL GENERAL</td>
                <td class="total">{{ number_format((float) $oferta->OFER_TOTAL_GENERAL, 2) }}</td>
            </tr>
        </tfoot>
    </table>
</body>
</html>
