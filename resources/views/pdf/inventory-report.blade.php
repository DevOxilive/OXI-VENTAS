<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de inventario</title>
    <style>
        body { color: #111827; font-family: DejaVu Sans, sans-serif; font-size: 8px; }
        h1 { font-size: 18px; margin: 0 0 4px; }
        h2 { font-size: 13px; margin: 18px 0 6px; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #d1d5db; padding: 5px; text-align: left; }
        th { background: #f3f4f6; font-weight: bold; }
        .muted { color: #6b7280; }
        .summary td { width: 25%; }
        .right { text-align: right; }
    </style>
</head>
<body>
    <h1>Reporte de inventario</h1>

    <p class="muted">
        Sucursal: {{ $branch->name ?? 'Sin sucursal' }}<br>
        Tipo: {{ $title }}<br>
        Generado: {{ now()->format('d/m/Y H:i') }}
    </p>

    <h2>Resumen</h2>
    <table class="summary">
        <tr>
            <th>Total productos</th>
            <td>{{ $summary['total_products'] ?? 0 }}</td>
            <th>Stock total</th>
            <td>{{ $summary['total_stock'] ?? 0 }}</td>
        </tr>
        <tr>
            <th>Caducados</th>
            <td>{{ $summary['expired_batches'] ?? 0 }}</td>
            <th>Por vencer</th>
            <td>{{ $summary['near_expiration_batches'] ?? 0 }}</td>
        </tr>
        <tr>
            <th>Stock bajo</th>
            <td>{{ $summary['low_stock'] ?? 0 }}</td>
            <th>Agotados</th>
            <td>{{ $summary['out_of_stock'] ?? 0 }}</td>
        </tr>
    </table>

    <h2>Detalle</h2>
    <table>
        <thead>
            <tr>
                <th>Producto</th>
                <th>Categoría</th>
                <th>Lote</th>
                <th>Estado</th>
                <th>Cantidad</th>
                <th>Inicial</th>
                <th>Caducidad</th>
                <th>Días</th>
                <th>Ingreso</th>
                <th>Ult. entrada</th>
                <th>Movimiento</th>
                <th>Usuario</th>
                <th>Motivo</th>
                <th>Stock ant.</th>
                <th>Stock nuevo</th>
                <th>Stock actual</th>
                <th>Costo</th>
                <th>Impacto</th>
                <th>Notas</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td>{{ $row->product ?? '-' }}</td>
                    <td>{{ $row->category ?? '-' }}</td>
                    <td>{{ $row->lot_number ?? '-' }}</td>
                    <td>{{ $row->status_label ?? '-' }}</td>
                    <td class="right">{{ number_format((float) ($row->quantity ?? 0), 2) }}</td>
                    <td class="right">{{ isset($row->initial_quantity) ? number_format((float) $row->initial_quantity, 2) : '-' }}</td>
                    <td>{{ $row->expiration_date ?? '-' }}</td>
                    <td class="right">{{ $row->days ?? '-' }}</td>
                    <td>{{ $row->received_at ?? '-' }}</td>
                    <td>{{ $row->last_entry_at ?? '-' }}</td>
                    <td>{{ $row->movement_date ?? '-' }}</td>
                    <td>{{ $row->user ?? '-' }}</td>
                    <td>{{ $row->movement_reason_label ?? $row->movement_reason ?? '-' }}</td>
                    <td class="right">{{ isset($row->previous_stock) ? number_format((float) $row->previous_stock, 2) : '-' }}</td>
                    <td class="right">{{ isset($row->new_stock) ? number_format((float) $row->new_stock, 2) : '-' }}</td>
                    <td class="right">{{ isset($row->current_stock) ? number_format((float) $row->current_stock, 2) : '-' }}</td>
                    <td class="right">${{ number_format((float) ($row->unit_cost ?? 0), 2) }}</td>
                    <td class="right">${{ number_format((float) ($row->estimated_loss ?? 0), 2) }}</td>
                    <td>{{ $row->notes ?? '-' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="19">No se encontraron registros para este reporte.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
