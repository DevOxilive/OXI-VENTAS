<?php

namespace App\Exports;

use App\Models\Employee;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class EmployeeExport implements FromCollection, ShouldAutoSize, WithEvents, WithHeadings, WithMapping, WithStyles, WithTitle
{
    protected $employmentStatus;

    protected $department;

    protected $position;

    protected $search;

    protected $startDateFrom;

    protected $startDateTo;

    public function __construct($employmentStatus = null, $department = null, $position = null, $search = null, $startDateFrom = null, $startDateTo = null)
    {
        $this->employmentStatus = $employmentStatus;
        $this->department = $department;
        $this->position = $position;
        $this->search = $search;
        $this->startDateFrom = $startDateFrom;
        $this->startDateTo = $startDateTo;
    }

    public function collection()
    {
        $query = Employee::query()->with('position.department');

        if ($this->employmentStatus) {
            $query->where('employment_status', $this->employmentStatus);
        }

        if ($this->department) {
            $query->whereHas('position', fn ($positionQuery) => $positionQuery->where('department_id', $this->department));
        }

        if ($this->position) {
            $query->where('position_id', $this->position);
        }

        if ($this->startDateFrom) {
            $query->whereDate('start_date', '>=', $this->startDateFrom);
        }

        if ($this->startDateTo) {
            $query->whereDate('start_date', '<=', $this->startDateTo);
        }

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('first_name', 'like', '%'.$this->search.'%')
                    ->orWhere('last_name', 'like', '%'.$this->search.'%')
                    ->orWhereHas('position', fn ($positionQuery) => $positionQuery->where('name', 'like', '%'.$this->search.'%'))
                    ->orWhereHas('position.department', fn ($departmentQuery) => $departmentQuery->where('name', 'like', '%'.$this->search.'%'));
            });
        }

        return $query->get();
    }

    public function headings(): array
    {
        return [
            'Nombre Completo',
            'Fecha de Nacimiento',
            'Edad',
            'Correo',
            'Teléfono',
            'Contacto de Emergencia',
            'Parentesco',
            'Teléfono de Emergencia',
            'Segundo Contacto',
            'Parentesco Secundario',
            'Teléfono Secundario',
            'Domicilio',
            'Fecha de Inicio',
            'Estado',
            'Puesto',
            'Departamento',
            'Banco',
            'Número de Cuenta',
            'CLABE',
            'Número de Tarjeta',
            'Grado de Estudios',
            'Especialidad',
            'Tipo de Contrato',
            'Antigüedad',
            'NSS',
            'RFC',
        ];
    }

    private function ageLabel($birthDate): string
    {
        if (! $birthDate) {
            return '';
        }

        return Carbon::parse($birthDate)->age.' años';
    }

    private function seniorityLabel($startDate): string
    {
        if (! $startDate) {
            return '';
        }

        $diff = Carbon::parse($startDate)->diff(Carbon::now('America/Mexico_City'));

        return "{$diff->y} años {$diff->m} meses";
    }

    public function map($employee): array
    {
        $address = collect([
            $employee->street,
            $employee->external_number,
            $employee->internal_number,
            $employee->neighborhood,
            $employee->municipality,
            $employee->address_state,
            $employee->postal_code,
        ])->filter()->join(', ');

        return [
            $employee->first_name.' '.$employee->last_name,
            $employee->birth_date?->toDateString(),
            $this->ageLabel($employee->birth_date),
            $employee->email,
            $employee->phone,
            $employee->emergency_contact_name,
            $employee->emergency_contact_relationship,
            $employee->emergency_contact_phone,
            $employee->secondary_emergency_contact_name,
            $employee->secondary_emergency_contact_relationship,
            $employee->secondary_emergency_contact_phone,
            $address,
            $employee->start_date?->toDateString(),
            $employee->employment_status,
            $employee->position?->name,
            $employee->position?->department?->name,
            $employee->bank,
            $employee->account_number,
            $employee->bank_clabe,
            $employee->bank_card_number,
            $employee->education_level,
            $employee->specialty,
            $employee->contract_type,
            $this->seniorityLabel($employee->start_date),
            $employee->nss,
            $employee->rfc,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => [
                    'bold' => true,
                    'size' => 12,
                    'color' => ['rgb' => 'FFFFFF'],
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '1F1D2B'],
                ],
            ],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {

                $sheet = $event->sheet;

                $highestRow = $sheet->getHighestRow();
                $highestColumn = $sheet->getHighestColumn();

                $sheet->setAutoFilter("A1:{$highestColumn}{$highestRow}");
                $sheet->freezePane('A2');

                $sheet->insertNewRowBefore(1, 2);
                $sheet->mergeCells("A1:{$highestColumn}1");
                $sheet->setCellValue('A1', 'REPORTE GENERAL DE EMPLEADOS');

                $sheet->mergeCells("A2:{$highestColumn}2");
                $sheet->setCellValue('A2', 'Generado el '.Carbon::now()->format('d/m/Y H:i:s'));

                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
                $sheet->getStyle('A2')->getFont()->setItalic(true)->setSize(10);
            },
        ];
    }

    public function title(): string
    {
        return 'Empleados';
    }
}
