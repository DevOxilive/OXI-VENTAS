<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte general de cortes</title>
    <style>
        @page { margin: 18px 10px; }
        body { color: #241719; font-family: DejaVu Sans, sans-serif; font-size: 6px; }
        h1 { color: #C91424; font-size: 14px; margin: 0 0 6px; text-align: center; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #E7CDD1; padding: 3px 2px; text-align: right; vertical-align: middle; }
        th { background: #C91424; color: #fff; font-weight: bold; text-align: center; }
        .date-row td { background: #F2E5BD; color: #8A6100; border-color: #D5B86B; font-weight: bold; text-align: left; }
        .ticket-row td { background: #F2DFE2; color: #241719; font-weight: bold; text-align: left; }
        .meta { margin-bottom: 8px; }
        .meta td { border: 0; padding: 2px 3px; text-align: left; }
        .label { color: #C91424; font-weight: bold; }
        .left { text-align: left; }
        .center { text-align: center; }
        .total td { background: #F6DADD; color: #8F0C17; font-weight: bold; }
    </style>
</head>
<body>
    @php $money = fn ($value) => '$'.number_format((float) ($value ?? 0), 2); @endphp
    <h1>Reporte General de Cortes</h1>

    <table class="meta">
        <tr><td class="label">Sucursal:</td><td>{{ $branch->name }}</td><td class="label">Periodo:</td><td>{{ $filters['date_from'] ?: '-' }} a {{ $filters['date_to'] ?: '-' }}</td><td class="label">Folio:</td><td>{{ $filters['folio'] ?: 'Todos' }}</td></tr>
        <tr><td class="label">Usuario:</td><td>{{ $filters['user_name'] ?: 'Todos' }}</td><td class="label">Estado:</td><td>{{ $filters['status_label'] }}</td><td class="label">Orden:</td><td>Fecha de corte</td></tr>
    </table>

    <table>
        <thead><tr>
            <th>Fecha</th><th>Desglose</th><th>Pago efectivo</th><th>Pago tarjeta</th><th>Abono efectivo</th><th>Abono tarjeta</th><th>Otros</th><th>Dev. efectivo</th><th>Dev. tarjeta</th><th>Efectivo esperado</th><th>Tarjeta esperada</th><th>Efectivo contado</th><th>Tarjeta capturada</th><th>Deja en caja</th><th>Retiro</th><th>Dif. efectivo</th><th>Dif. tarjeta</th><th>Total</th>
        </tr></thead>
        <tbody>
        @forelse($rows as $row)
            <tr class="date-row">
                <td class="center">{{ $row['date'] }}</td><td colspan="17">Fecha de corte: {{ $row['date'] }} &nbsp;&nbsp;&nbsp; Responsable: {{ $row['user'] }}</td>
            </tr>
            <tr class="ticket-row">
                <td class="center">{{ $row['date'] }}</td><td>Folio de corte:</td><td colspan="2">{{ $row['folio'] }}</td><td>Sucursal:</td><td colspan="2">{{ $branch->name }}</td><td>Caja:</td><td colspan="2">{{ $row['cash_box'] }}</td><td>Importe total:</td><td colspan="7">{{ $money($row['collected_total']) }}</td>
            </tr>
            <tr>
                <td class="center">{{ $row['date'] }}</td><td class="left">Desglose del corte</td>
                @foreach(['cash_payments', 'card_payments', 'cash_credit_payments', 'card_credit_payments', 'other_payments', 'refund_cash', 'refund_card', 'expected_cash', 'expected_card', 'counted_cash', 'counted_card', 'cash_left', 'cash_withdrawal', 'cash_difference', 'card_difference', 'collected_total'] as $field)<td>{{ $money($row[$field]) }}</td>@endforeach
            </tr>
        @empty
            <tr><td colspan="20" class="left">No se encontraron cortes para los filtros seleccionados.</td></tr>
        @endforelse
            <tr class="total">
                <td class="left">Totales</td><td></td>
                @foreach(['cash_payments', 'card_payments', 'cash_credit_payments', 'card_credit_payments', 'other_payments', 'refund_cash', 'refund_card', 'expected_cash', 'expected_card', 'counted_cash', 'counted_card', 'cash_left', 'cash_withdrawal', 'cash_difference', 'card_difference', 'collected_total'] as $field)<td>{{ $money($totals[$field]) }}</td>@endforeach
            </tr>
        </tbody>
    </table>
</body>
</html>
