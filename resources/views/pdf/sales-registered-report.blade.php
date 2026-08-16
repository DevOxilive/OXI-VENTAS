<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de ventas registradas</title>
    <style>
        body { color: #111827; font-family: DejaVu Sans, sans-serif; font-size: 8px; }
        h1 { font-size: 18px; margin: 0 0 4px; }
        h2 { font-size: 13px; margin: 18px 0 6px; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #d1d5db; padding: 5px; text-align: left; }
        th { background: #f3f4f6; font-weight: bold; }
        .muted { color: #6b7280; }
        .right { text-align: right; }
    </style>
</head>
<body>
    <h1>Reporte de ventas registradas</h1>

    <p class="muted">
        Sucursal: {{ $branch->name ?? 'Sin sucursal' }}<br>
        Desde: {{ $filters['date_from'] ?? '-' }} · Hasta: {{ $filters['date_to'] ?? '-' }}<br>
        Generado: {{ now()->format('d/m/Y H:i') }}
    </p>

    <h2>Detalle</h2>
    <table>
        <thead>
            <tr>
                <th>Folio</th>
                <th>Fecha</th>
                <th>Sucursal</th>
                <th>Vendedor</th>
                <th>Total de productos vendidos</th>
                <th>Total pagado</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td>{{ $row['folio'] ?? '-' }}</td>
                    <td>{{ $row['date_display'] ?? '-' }}</td>
                    <td>{{ $row['branch'] ?? '-' }}</td>
                    <td>{{ $row['seller'] ?? '-' }}</td>
                    <td class="right">{{ $row['total_products_sold_display'] ?? '0' }}</td>
                    <td class="right">${{ number_format((float) ($row['total'] ?? 0), 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6">No se encontraron ventas para este reporte.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
