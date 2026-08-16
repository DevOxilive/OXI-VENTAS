<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de auditoría</title>
    <style>
        @page { margin: 26px 24px 34px; }
        body { margin: 0; font-family: DejaVu Sans, sans-serif; font-size: 8px; color: #111827; }
        h1 { margin: 0 0 3px; font-size: 17px; color: #4c3575; }
        h2 { margin: 12px 0 6px; font-size: 11px; color: #111827; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #d1d5db; padding: 4px; text-align: left; vertical-align: middle; }
        th { background: #5b3f86; color: #fff; font-size: 7px; }
        .muted { color: #6b7280; }
        .meta { margin: 0 0 8px; line-height: 1.45; }
        .summary-table td { width: 12%; }
        .summary-table th { width: 13%; background: #f3f4f6; color: #374151; }
        .filters-table th { width: 14%; background: #f3f4f6; color: #374151; }
        .filters-table td { width: 36%; }
        .detail-table { table-layout: fixed; }
        .detail-table thead { display: table-header-group; }
        .detail-table td { overflow-wrap: anywhere; word-wrap: break-word; }
        .detail-table .participants { display: block; margin-top: 3px; color: #64748b; font-size: 6.5px; }
        .text-center { text-align: center; }
        .result { font-weight: bold; text-align: center; }
        .result-matched { color: #15803d; }
        .result-missing { color: #c2410c; }
        .result-surplus { color: #a16207; }
        .result-pending { color: #1d4ed8; }
        .footer {
            position: fixed;
            right: 0;
            bottom: -22px;
            left: 0;
            color: #6b7280;
            text-align: center;
            font-size: 7px;
        }
        .footer .page-number:after { content: counter(page); }
    </style>
</head>
<body>
    <div class="footer">Super Kay · Reporte de auditoría · Página <span class="page-number"></span></div>

    <h1>Reporte de auditoría</h1>

    <p class="muted meta">
        Sucursal: {{ $branch->name ?? 'Sin sucursal' }}<br>
        Generado: {{ now()->format('d/m/Y H:i') }}<br>
        Tipo de reporte: {{ $sectionTitle }}
    </p>

    <h2>Resumen general</h2>
    <table class="summary-table">
        <tr>
            <th>Auditorías</th>
            <td>{{ $summary['audits'] ?? 0 }}</td>
            <th>Registros</th>
            <td>{{ $summary['records'] ?? 0 }}</td>
        </tr>
        <tr>
            <th>Contados</th>
            <td>{{ $summary['counted_products'] ?? 0 }}</td>
            <th>No encontrados</th>
            <td>{{ $summary['pending_products'] ?? 0 }}</td>
        </tr>
        <tr>
            <th>Faltantes</th>
            <td>{{ $summary['missing_products'] ?? 0 }}</td>
            <th>Sobrantes</th>
            <td>{{ $summary['surplus_products'] ?? 0 }}</td>
        </tr>
        <tr>
            <th>Correctos</th>
            <td>{{ $summary['matched_products'] ?? 0 }}</td>
            <th>Usuarios</th>
            <td>{{ $summary['participants'] ?? 0 }}</td>
        </tr>
    </table>

    <h2>Filtros aplicados</h2>
    <table class="filters-table">
        <tr>
            <th>Auditoría</th>
            <td>{{ $filterLabels['audit'] ?? 'Todas' }}</td>
            <th>Usuario</th>
            <td>{{ $filterLabels['user'] ?? 'Todos' }}</td>
        </tr>
        <tr>
            <th>Categoría</th>
            <td>{{ $filterLabels['category'] ?? 'Todas' }}</td>
            <th>Resultado</th>
            <td>{{ $filterLabels['status'] ?? 'Todos' }}</td>
        </tr>
        <tr>
            <th>Fecha de auditoría</th>
            <td>{{ $filterLabels['report_date'] ?? 'Sin fecha' }}</td>
            <th>Búsqueda</th>
            <td>{{ $filterLabels['search'] ?? 'Sin filtro' }}</td>
        </tr>
    </table>

    <h2>Detalle de productos</h2>
    <table class="detail-table">
        <colgroup>
            <col style="width: 12%">
            <col style="width: 24%">
            <col style="width: 12%">
            <col style="width: 8%">
            <col style="width: 8%">
            <col style="width: 8%">
            <col style="width: 8%">
            <col style="width: 8%">
            <col style="width: 12%">
        </colgroup>
        <thead>
            <tr>
                <th>Código</th>
                <th>Producto</th>
                <th>Categoría</th>
                <th class="text-center">Stock sistema</th>
                <th class="text-center">Conteo físico</th>
                <th class="text-center">Dañado</th>
                <th class="text-center">Caducado</th>
                <th class="text-center">Diferencia</th>
                <th class="text-center">Resultado</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($detailRows as $row)
                @php
                    $result = ($row['row_type'] ?? null) === 'pending'
                        ? 'pending'
                        : ($row['status'] ?? 'pending');
                    $participants = implode(', ', $row['participants'] ?? []);
                @endphp
                <tr>
                    <td>{{ $row['scanned_code'] ?? '-' }}</td>
                    <td>
                        {{ $row['product_name'] ?? 'Sin producto' }}
                        <span class="participants">
                            <strong>Participantes:</strong> {{ $participants ?: 'Sin captura registrada' }}
                        </span>
                    </td>
                    <td>{{ $row['category_name'] ?? 'Sin categoría' }}</td>
                    <td class="text-center">{{ $row['system_stock_display'] ?? \App\Support\QuantityFormatter::format($row['system_stock'] ?? 0, $row['inventory_unit'] ?? 'pza') }}</td>
                    <td class="text-center">{{ $row['counted_stock_display'] ?? \App\Support\QuantityFormatter::format($row['counted_stock'] ?? 0, $row['inventory_unit'] ?? 'pza') }}</td>
                    <td class="text-center">{{ $row['damaged_stock_display'] ?? \App\Support\QuantityFormatter::format($row['damaged_stock'] ?? 0, $row['inventory_unit'] ?? 'pza') }}</td>
                    <td class="text-center">{{ $row['expired_stock_display'] ?? \App\Support\QuantityFormatter::format($row['expired_stock'] ?? 0, $row['inventory_unit'] ?? 'pza') }}</td>
                    <td class="text-center">
                        {{ $row['difference_label'] ?? (($row['difference'] ?? null) === null ? '-' : \App\Support\QuantityFormatter::format($row['difference'], $row['inventory_unit'] ?? 'pza')) }}
                    </td>
                    <td class="result result-{{ $result }}">{{ $row['status_label'] ?? 'Pendiente' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="9">No hay resultados con los filtros seleccionados.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
