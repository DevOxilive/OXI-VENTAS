<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de auditoría</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #111827; }
        h1 { font-size: 18px; margin-bottom: 4px; }
        h2 { font-size: 13px; margin-top: 18px; margin-bottom: 8px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #d1d5db; padding: 5px; text-align: left; vertical-align: top; }
        th { background: #f3f4f6; }
        .muted { color: #6b7280; }
    </style>
</head>
<body>
    <h1>Reporte de auditoría</h1>

    <p class="muted">
        Sucursal: {{ $branch->name ?? 'Sin sucursal' }}<br>
        Generado: {{ now()->format('d/m/Y H:i') }}<br>
        Tipo de reporte: {{ $sectionTitle }}
    </p>

    <h2>Resumen general</h2>
    <table>
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
    <table>
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
            <th>Periodo</th>
            <td>{{ $filterLabels['report_date'] ?? 'Sin fecha' }} ({{ $filterLabels['date_scope'] ?? 'Por día' }})</td>
            <th>Búsqueda</th>
            <td>{{ $filterLabels['search'] ?? 'Sin filtro' }}</td>
        </tr>
    </table>

    <h2>{{ $sectionTitle }}</h2>
    <table>
        <thead>
            <tr>
                @foreach ($headings as $heading)
                    <th>{{ $heading }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    @foreach ($row as $value)
                        <td>{{ $value }}</td>
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td colspan="{{ count($headings) }}">No hay resultados con los filtros seleccionados.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
