<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reportes de auditoria</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #111827; }
        h1 { font-size: 18px; margin-bottom: 4px; }
        h2 { font-size: 13px; margin-top: 20px; margin-bottom: 8px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #d1d5db; padding: 5px; text-align: left; vertical-align: top; }
        th { background: #f3f4f6; }
        .muted { color: #6b7280; }
        .summary td, .summary th { width: 25%; }
        .filters td, .filters th { width: 25%; }
    </style>
</head>
<body>
    <h1>Reporte de auditoria</h1>

    <p class="muted">
        Sucursal: {{ $branch->name ?? 'Sin sucursal' }}<br>
        Generado: {{ now()->format('d/m/Y H:i') }}
    </p>

    <h2>Resumen</h2>
    <table class="summary">
        <tr>
            <th>Auditorias</th>
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
    <table class="filters">
        <tr>
            <th>Auditoria</th>
            <td>{{ $filters['physical_count_id'] ?: 'Todas' }}</td>
            <th>Usuario</th>
            <td>{{ $filters['user_id'] ?: 'Todos' }}</td>
        </tr>
        <tr>
            <th>Categoria</th>
            <td>{{ $filters['category_id'] ?: 'Todas' }}</td>
            <th>Estado</th>
            <td>{{ $filters['status'] ?: 'Todos' }}</td>
        </tr>
        <tr>
            <th>Periodo</th>
            <td>{{ $filters['start_date'] ?: '-' }} al {{ $filters['end_date'] ?: '-' }}</td>
            <th>Busqueda</th>
            <td>{{ $filters['search'] ?: 'Sin filtro' }}</td>
        </tr>
    </table>

    <h2>Resultados</h2>
    <table>
        <thead>
            <tr>
                <th>Auditoria</th>
                <th>Producto</th>
                <th>Categoria</th>
                <th>Codigo</th>
                <th>Tipo</th>
                <th>Estado</th>
                <th>Sistema</th>
                <th>Conteo</th>
                <th>Diferencia</th>
                <th>Usuarios</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($reportRows as $row)
                <tr>
                    <td>{{ $row['audit_name'] ?? 'Sin auditoria' }}<br>{{ $row['folio'] ?? '' }}</td>
                    <td>{{ $row['product_name'] ?? '' }}</td>
                    <td>{{ $row['category_name'] ?? '' }}</td>
                    <td>{{ $row['scanned_code'] ?? '' }}</td>
                    <td>{{ $row['row_type_label'] ?? '' }}</td>
                    <td>{{ $row['status_label'] ?? '' }}</td>
                    <td>{{ $row['system_stock'] ?? 0 }}</td>
                    <td>{{ $row['counted_stock'] ?? 0 }}</td>
                    <td>{{ $row['difference'] ?? '-' }}</td>
                    <td>{{ implode(', ', $row['participants'] ?? []) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="10">No hay resultados con los filtros seleccionados.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
