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
    public function headings(): array { return ['Empleado', 'Rol', 'Sucursal', 'Fecha', 'Hora', 'Tipo', 'Estado', 'Autenticación']; }
    public function map($record): array { return [trim(($record->employee?->first_name ?? $record->user?->name ?? '') . ' ' . ($record->employee?->last_name ?? '')), $record->user?->role?->name, $record->branch?->name, optional($record->attendance_date)->format('d/m/Y'), optional($record->recorded_at)->format('H:i'), $record->type, $record->status, $record->authentication_method]; }
}
