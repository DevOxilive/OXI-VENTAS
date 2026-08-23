<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte general de ventas</title>
    <style>
        body { color: #241719; font-family: DejaVu Sans, sans-serif; font-size: 7px; }
        h1 { color: #C91424; font-size: 16px; margin: 0 0 6px; text-align: center; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #E7CDD1; padding: 4px; text-align: left; vertical-align: middle; }
        th { background: #C91424; color: #fff; font-weight: bold; text-align: center; }
        .meta td { border: 0; padding: 2px 4px; }
        .label { color: #C91424; font-weight: bold; }
        .date-row td { background: #F2E5BD; color: #8A6100; font-weight: bold; }
        .ticket-row td { background: #F2DFE2; color: #241719; font-weight: bold; }
        .right { text-align: right; }
        .summary { margin-top: 12px; width: 45%; margin-left: auto; }
        .summary th { text-align: left; }
        .summary .total td { background: #F6DADD; color: #8F0C17; font-weight: bold; }
    </style>
</head>
<body>
    @php
        $money = fn ($value) => '$'.number_format((float) ($value ?? 0), 2);
        $paymentAmount = function (array $operation, array $detail, string $target): float {
            $amount = (float) ($detail['report_amount'] ?? $detail['subtotal'] ?? 0);

            if (($operation['operation_type'] ?? 'sale') === 'payment') {
                return $target === 'abono' ? $amount : 0.0;
            }

            $method = str($operation['payment_method'] ?? '')->lower()->ascii()->toString();

            if ($target === 'cash') {
                return str_contains($method, 'efectivo') ? $amount : 0.0;
            }

            if ($target === 'card') {
                return (str_contains($method, 'tarjeta') || str_contains($method, 'debito') || str_contains($method, 'credito') && ! str_contains($method, 'empleado')) ? $amount : 0.0;
            }

            if ($target === 'credit') {
                return (! str_contains($method, 'efectivo') && ! str_contains($method, 'tarjeta') && ! str_contains($method, 'debito')) ? $amount : 0.0;
            }

            return 0.0;
        };
        $operationLabel = fn (array $operation) => (($operation['operation_type'] ?? 'sale') === 'payment' ? 'Abono ' : 'Venta ').($operation['payment_method'] ?? 'Sin metodo');
        $rowsByDate = collect($rows)->groupBy('date_only');
        $statusLabels = [
            'completed' => 'Completadas',
            'cancelled' => 'Canceladas',
            'payment' => 'Abonos aplicados',
        ];
        $paymentLabels = [
            'cash' => 'Efectivo',
            'card' => 'Tarjeta',
            'credit' => 'Credito empleado',
            'payment' => 'Abonos',
        ];
    @endphp

    <h1>Reporte General de Ventas</h1>

    <table class="meta">
        <tr>
            <td class="label">Documento:</td>
            <td>Todos</td>
            <td class="label">Periodo:</td>
            <td>{{ $filters['date_from'] ?? '-' }} 00:00 - {{ $filters['date_to'] ?? '-' }} 23:59</td>
            <td class="label">Detalle:</td>
            <td>Si</td>
        </tr>
        <tr>
            <td class="label">Cliente:</td>
            <td>Todos</td>
            <td class="label">Estado:</td>
            <td>{{ $statusLabels[$filters['status'] ?? ''] ?? 'Todos' }}</td>
            <td class="label">Orden:</td>
            <td>Fecha</td>
        </tr>
        <tr>
            <td class="label">Vendedor:</td>
            <td>Todos</td>
            <td class="label">Forma de pago:</td>
            <td>{{ $paymentLabels[$filters['payment_method'] ?? ''] ?? 'Todas' }}</td>
            <td class="label">Caja:</td>
            <td>Todas</td>
        </tr>
    </table>

    <table style="margin-top: 8px;">
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Usuario</th>
                <th>Producto</th>
                <th>Pza/Kg</th>
                <th>Precio</th>
                <th>Importe</th>
                <th>Estado</th>
                <th>Efectivo</th>
                <th>Tarjeta</th>
                <th>Credito</th>
                <th>Abono</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rowsByDate as $date => $operations)
                <tr class="date-row">
                    <td>{{ $date }}</td>
                    <td colspan="10">Fecha de operacion: {{ $date }}</td>
                </tr>

                @foreach ($operations as $operation)
                    <tr class="ticket-row">
                        <td>{{ $operation['date_only'] ?? '-' }}</td>
                        <td>Numero de ticket:</td>
                        <td>{{ $operation['folio'] ?? '-' }}</td>
                        <td>{{ $operationLabel($operation) }}</td>
                        <td>{{ $operation['payment_folio'] ?? '' }}</td>
                        <td>Sucursal:</td>
                        <td>{{ $operation['branch'] ?? '-' }}</td>
                        <td>Caja:</td>
                        <td>{{ $operation['cash_box'] ?? '-' }}</td>
                        <td>Importe total:</td>
                        <td class="right">{{ $money($operation['total'] ?? 0) }}</td>
                    </tr>

                    @foreach (($operation['details'] ?? []) as $detail)
                        <tr>
                            <td>{{ $operation['date_only'] ?? '-' }}</td>
                            <td>{{ $operation['seller'] ?? '-' }}</td>
                            <td>{{ $detail['product'] ?? '-' }}</td>
                            <td class="right">{{ $detail['quantity_display'] ?? '0' }}</td>
                            <td class="right">{{ $money($detail['unit_price'] ?? 0) }}</td>
                            <td class="right">{{ $money($detail['report_amount'] ?? $detail['subtotal'] ?? 0) }}</td>
                            <td>{{ $operation['status_label'] ?? 'Completada' }}</td>
                            <td class="right">{{ $money($paymentAmount($operation, $detail, 'cash')) }}</td>
                            <td class="right">{{ $money($paymentAmount($operation, $detail, 'card')) }}</td>
                            <td class="right">{{ $money($paymentAmount($operation, $detail, 'credit')) }}</td>
                            <td class="right">{{ $money($paymentAmount($operation, $detail, 'abono')) }}</td>
                        </tr>
                    @endforeach
                @endforeach
            @empty
                <tr>
                    <td colspan="11">No se encontraron ventas para este reporte.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <table class="summary">
        <thead>
            <tr>
                <th>Concepto</th>
                <th class="right">Importe</th>
            </tr>
        </thead>
        <tbody>
            <tr><td>Ventas en efectivo</td><td class="right">{{ $money($summary['cash'] ?? 0) }}</td></tr>
            <tr><td>Ventas con tarjeta</td><td class="right">{{ $money($summary['card'] ?? 0) }}</td></tr>
            <tr><td>Ventas a credito</td><td class="right">{{ $money($summary['credit'] ?? 0) }}</td></tr>
            <tr><td>Abonos a credito</td><td class="right">{{ $money($summary['credit_payments'] ?? 0) }}</td></tr>
            <tr class="total"><td>Total vendido</td><td class="right">{{ $money($summary['sold_total'] ?? 0) }}</td></tr>
            <tr class="total"><td>Total cobrado</td><td class="right">{{ $money($summary['collected_total'] ?? 0) }}</td></tr>
        </tbody>
    </table>
</body>
</html>
