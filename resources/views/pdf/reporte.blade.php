<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Reporte {{ $tipo }}</title>
    <style>
        body { font-family: sans-serif; font-size: 10px; }
        h1 { font-size: 14px; margin-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #999; padding: 3px 5px; text-align: left; }
        th { background-color: #eee; }
    </style>
</head>
<body>
    <h1>Reporte: {{ $tipo }}</h1>
    <table>
        <thead>
            <tr>
                @foreach ($cabeceras as $cabecera)
                    <th>{{ $cabecera }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($filas as $fila)
                <tr>
                    @foreach ($fila as $valor)
                        <td>{{ $valor }}</td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
