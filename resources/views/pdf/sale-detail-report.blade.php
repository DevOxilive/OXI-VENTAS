<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Detalle de venta</title>
    <style>
        body { color: #111827; font-family: DejaVu Sans, sans-serif; font-size: 9px; }
        h1 { font-size: 18px; margin: 0 0 4px; }
        h2 { font-size: 13px; margin: 18px 0 6px; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #d1d5db; padding: 6px; text-align: left; }
        th { background: #f3f4f6; font-weight: bold; }
        .muted { color: #6b7280; }
        .right { text-align: right; }
    </style>
</head>
<body>
    <h1>Detalle de venta {{ $sale['folio'] ?? '' }}</h1>

    <p class="muted">
        Sucursal: {{ $sale['branch'] ?? '-' }}<br>
        Vendedor: {{ $sale['seller'] ?? '-' }}<br>
        Fecha: {{ $sale['date_display'] ?? '-' }}<br>
        Total pagado: ${{ number_format((float) ($sale['total'] ?? 0), 2) }}<br>
        Generado: {{ now()->format('d/m/Y H:i') }}
    </p>

    <h2>Productos vendidos</h2>
    <table>
        <thead>
            <tr>
                <th>Producto</th>
                <th>Código</th>
                <th>Presentación</th>
                <th>Cantidad visual</th>
                <th>Cantidad base descontada</th>
                <th>Precio unitario</th>
                <th>Descuento</th>
                <th>Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($sale['details'] ?? [] as $detail)
                <tr>
                    <td>{{ $detail['product'] ?? '-' }}</td>
                    <td>{{ $detail['code'] ?? '-' }}</td>
                    <td>{{ $detail['presentation'] ?? '-' }}</td>
                    <td class="right">{{ $detail['quantity_display'] ?? '0' }}</td>
                    <td class="right">{{ $detail['base_quantity_display'] ?? '0' }}</td>
                    <td class="right">${{ number_format((float) ($detail['unit_price'] ?? 0), 2) }}</td>
                    <td class="right">${{ number_format((float) ($detail['discount_amount'] ?? 0), 2) }}</td>
                    <td class="right">${{ number_format((float) ($detail['subtotal'] ?? 0), 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="8">Esta venta no tiene productos registrados.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
