<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de conteo físico</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111827; }
        h1 { font-size: 20px; margin-bottom: 4px; }
        h2 { font-size: 15px; margin-top: 24px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #d1d5db; padding: 6px; text-align: left; }
        th { background: #f3f4f6; }
        .muted { color: #6b7280; }
        .summary { margin-top: 15px; }
    </style>
</head>
<body>
    <h1>Reporte de conteo físico</h1>

    <p class="muted">
        Auditoría: {{ $physicalCount->name }}<br>
        Sucursal: {{ $physicalCount->branch->name ?? 'Sin sucursal' }}<br>
        Folio: {{ $physicalCount->folio ?? 'Sin folio' }}<br>
        Estado: {{ strtoupper($physicalCount->status) }}<br>
        Fecha de inicio: {{ $physicalCount->started_at }}<br>
        Fecha de cierre: {{ $physicalCount->closed_at ?? 'Sin cerrar' }}
    </p>

    <h2>Resumen</h2>

    <table>
        <tr>
            <th>Registros</th>
            <td>{{ $summary['total_entries'] }}</td>
            <th>Cantidad contada</th>
            <td>{{ $summary['total_counted'] }}</td>
        </tr>
        <tr>
            <th>Dañados</th>
            <td>{{ $summary['total_damaged'] }}</td>
            <th>Caducados</th>
            <td>{{ $summary['total_expired'] }}</td>
        </tr>
        <tr>
            <th>Productos auditados</th>
            <td>{{ $summary['audited_products'] }}</td>
            <th>Participantes</th>
            <td>{{ $summary['participants'] }}</td>
        </tr>
    </table>

    <h2>Comparativo de inventario</h2>

    <table>
        <thead>
            <tr>
                <th>Producto</th>
                <th>Sistema</th>
                <th>Contado</th>
                <th>Dañado</th>
                <th>Caducado</th>
                <th>Diferencia</th>
                <th>Estado</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($comparison as $item)
                <tr>
                    <td>{{ $item['product_name'] }}</td>
                    <td>{{ $item['system_stock'] }}</td>
                    <td>{{ $item['counted_stock'] }}</td>
                    <td>{{ $item['damaged_stock'] }}</td>
                    <td>{{ $item['expired_stock'] }}</td>
                    <td>{{ $item['difference'] }}</td>
                    <td>{{ $item['status_label'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <h2>Firmas</h2>

    <table>
        <tr>
            <td style="height: 60px;">Responsable de auditoría</td>
            <td style="height: 60px;">Revisó</td>
            <td style="height: 60px;">Autorizó</td>
        </tr>
    </table>
</body>
</html>