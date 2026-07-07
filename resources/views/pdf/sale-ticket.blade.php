<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Ticket de venta</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #111827; font-size: 10px; margin: 14px; }
        .center { text-align: center; }
        .muted { color: #6b7280; }
        .divider { border-top: 1px dashed #9ca3af; margin: 10px 0; }
        h1 { font-size: 16px; margin: 0 0 4px; }
        h2 { font-size: 11px; margin: 0; }
        p { margin: 2px 0; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 4px 0; vertical-align: top; }
        th { font-size: 9px; color: #6b7280; text-transform: uppercase; }
        .right { text-align: right; }
        .total { font-size: 15px; font-weight: bold; }
        .small { font-size: 9px; }
    </style>
</head>
<body>
    <div class="center">
        <h1>OXI LIVE</h1>
        <h2>Ticket de venta</h2>
        <p>{{ $sale->branch?->name ?? 'Sucursal' }}</p>
        <p class="muted">{{ $sale->folio ?? 'Sin folio' }}</p>
    </div>

    <div class="divider"></div>

    <p><strong>Fecha:</strong> {{ optional($sale->date)->format('d/m/Y H:i') }}</p>
    <p><strong>Pago:</strong> {{ $sale->paymentMethod?->name ?? 'Sin método' }}</p>
    <p>
        <strong>Atendio:</strong>
        {{ trim(($sale->employee?->first_name ?? '') . ' ' . ($sale->employee?->last_name ?? '')) ?: 'Sin empleado' }}
    </p>

    <div class="divider"></div>

    <table>
        <thead>
            <tr>
                <th>Producto</th>
                <th class="right">Imp.</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($sale->details as $detail)
                <tr>
                    <td>
                        <strong>{{ $detail->product?->name ?? 'Producto' }}</strong><br>
                        <span class="small muted">
                            {{ number_format((float) $detail->quantity, 2) }} x ${{ number_format((float) $detail->unit_price, 2) }}
                        </span>
                        @if ((float) ($detail->discount_percentage ?? 0) > 0)
                            <br>
                            <span class="small muted">
                                Desc. {{ number_format((float) $detail->discount_percentage, 2) }}% -
                                ${{ number_format((float) ($detail->discount_amount ?? 0), 2) }}
                            </span>
                        @endif
                    </td>
                    <td class="right">${{ number_format((float) $detail->subtotal, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="divider"></div>

    <table>
        <tr>
            <td>Subtotal</td>
            <td class="right">${{ number_format((float) $sale->total, 2) }}</td>
        </tr>
        <tr>
            <td>Recibido</td>
            <td class="right">${{ number_format((float) $sale->cash_received, 2) }}</td>
        </tr>
        <tr>
            <td><strong>Cambio</strong></td>
            <td class="right"><strong>${{ number_format((float) $sale->change_due, 2) }}</strong></td>
        </tr>
        <tr>
            <td class="total">Total</td>
            <td class="right total">${{ number_format((float) $sale->total, 2) }}</td>
        </tr>
    </table>

    <div class="divider"></div>

    <p class="center muted small">
        Gracias por tu compra
    </p>
</body>
</html>
