<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Resultados {{ $convocatoria->CONV_NUMERO }}</title>
    <style>
        body { font-family: sans-serif; font-size: 11px; }
        h1 { font-size: 14px; margin-bottom: 2px; }
        h2 { font-size: 12px; font-weight: normal; margin-top: 0; color: #555; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { border: 1px solid #999; padding: 4px 6px; text-align: left; }
        th { background-color: #eee; }
    </style>
</head>
<body>
    <h1>{{ $convocatoria->CONV_NUMERO }} — {{ $convocatoria->CONV_NOMBRE_SERVICIO }}</h1>
    <h2>{{ $convocatoria->entidad->ENTI_RAZON_SOCIAL }}</h2>

    <table>
        <thead>
            <tr>
                <th>N.°</th>
                <th>RUC</th>
                <th>Razón Social</th>
                <th>Ev. Técnica</th>
                <th>Motivo (Técnica)</th>
                <th>Ev. Logística</th>
                <th>Motivo (Logística)</th>
                <th>Resultado</th>
                <th>Monto</th>
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
                    <td>{{ $oferta->OFER_EVAL_LOGISTICA ?? '—' }}</td>
                    <td>{{ $oferta->OFER_EVAL_LOGISTICA_MOTIVO ?? '—' }}</td>
                    <td>{{ $oferta->estado->ESTA_NOMBRE }}</td>
                    <td>{{ number_format((float) $oferta->OFER_TOTAL_GENERAL, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
