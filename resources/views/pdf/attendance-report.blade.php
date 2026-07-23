<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>body{font-family:DejaVu Sans;font-size:10px;color:#24191b}table{width:100%;border-collapse:collapse}th,td{border:1px solid #cda1a8;padding:6px;text-align:left}th{background:#f5e4e7}h1{color:#d71930}</style>
</head>
<body>
@php
    $typeLabels = ['check_in' => 'Entrada', 'meal_start' => 'Inicio de comida', 'meal_end' => 'Fin de comida', 'check_out' => 'Salida'];
    $statusLabels = ['on_time' => 'Puntual', 'late' => 'Retardo', 'absent' => 'Falta', 'justified' => 'Justificada', 'outside_zone' => 'Fuera de zona', 'pending' => 'Pendiente', 'corrected' => 'Corregida'];
@endphp
<h1>Reporte de asistencias</h1>
<p>Generado: {{ $generatedAt->format('d/m/Y H:i') }}</p>
<table>
    <thead>
        <tr><th>Empleado</th><th>Sucursal</th><th>Fecha</th><th>Hora</th><th>Tipo</th><th>Estado</th></tr>
    </thead>
    <tbody>
        @foreach($records as $record)
            <tr>
                <td>{{ $record->employee ? trim($record->employee->first_name.' '.$record->employee->last_name) : $record->user?->name }}</td>
                <td>{{ $record->branch?->name }}</td>
                <td>{{ optional($record->attendance_date)->format('d/m/Y') }}</td>
                <td>{{ optional($record->recorded_at)->format('H:i') }}</td>
                <td>{{ $typeLabels[$record->type] ?? $record->type }}</td>
                <td>{{ $statusLabels[$record->status] ?? $record->status }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
</body>
</html>
