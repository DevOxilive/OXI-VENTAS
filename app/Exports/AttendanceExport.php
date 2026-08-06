<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class AttendanceExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize
{
    public function __construct(private readonly Collection $records) {}
    public function collection(): Collection { return $this->records; }
    public function headings(): array { return ['Empleado', 'Rol', 'Sucursal', 'Turno', 'Fecha', 'Hora', 'Tipo', 'Estado', 'Autenticación']; }
    public function map($record): array
    {
        return [
            trim(($record->employee?->first_name ?? $record->user?->name ?? '').' '.($record->employee?->last_name ?? '')),
            $record->user?->role?->name,
            $record->branch?->name,
            $record->shift_label ?? $record->scheduleAssignment?->schedule?->name ?? 'Turno general',
            optional($record->attendance_date)->format('d/m/Y'),
            optional($record->recorded_at)->format('H:i'),
            match ($record->type) {
                'check_in' => 'Entrada',
                'meal_start' => 'Salida a comida',
                'meal_end' => 'Entrada de comida',
                'check_out' => 'Salida general',
                default => $record->type,
            },
            match ($record->status) {
                'on_time' => 'Puntual',
                'late' => 'Retardo',
                'absent' => 'Falta',
                'justified' => 'Justificada',
                'outside_zone' => 'Fuera de zona',
                'pending' => 'Pendiente',
                'corrected' => 'Corregida',
                default => $record->status,
            },
            $record->authentication_method === 'platform_biometric' ? 'Biometría del dispositivo' : $record->authentication_method,
        ];
    }
}
