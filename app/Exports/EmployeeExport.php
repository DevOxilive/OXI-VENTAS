<?php

namespace App\Exports;

use App\Models\Employee;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\{
    FromCollection,
    WithHeadings,
    WithMapping,
    ShouldAutoSize,
    WithStyles,
    WithEvents,
    WithTitle
};
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Maatwebsite\Excel\Events\AfterSheet;

class EmployeeExport implements
    FromCollection,
    WithHeadings,
    WithMapping,
    ShouldAutoSize,
    WithStyles,
    WithEvents,
    WithTitle
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
                $q->where('first_name', 'like', '%' . $this->search . '%')
                    ->orWhere('last_name', 'like', '%' . $this->search . '%')
                    ->orWhereHas('position', fn ($positionQuery) => $positionQuery->where('name', 'like', '%' . $this->search . '%'))
                    ->orWhereHas('position.department', fn ($departmentQuery) => $departmentQuery->where('name', 'like', '%' . $this->search . '%'));
            });
        }

        return $query->get();
    }

    public function headings(): array
    {
        return [
            'Nombre Completo',
            'Correo',
            'Teléfono',
            'Domicilio',
            'Fecha de Inicio',
            'Estado',
            'Puesto',
            'Departamento',
            'Banco',
            'Número de Cuenta',
            'Grado de Estudios',
            'Especialidad',
            'Tipo de Contrato',
            'Antigüedad',
            'NSS',
            'RFC'
        ];
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
            $employee->first_name . ' ' . $employee->last_name,
            $employee->email,
            $employee->phone,
            $address,
            $employee->start_date,
            $employee->employment_status,
            $employee->position?->name,
            $employee->position?->department?->name,
            $employee->bank,
            $employee->account_number,
            $employee->education_level,
            $employee->specialty,
            $employee->contract_type,
            $employee->seniority,
            $employee->nss,
            $employee->rfc
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => [
                    'bold' => true,
                    'size' => 12,
                    'color' => ['rgb' => 'FFFFFF']
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '1F1D2B']
                ]
            ]
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
                $sheet->setCellValue('A2', 'Generado el ' . Carbon::now()->format('d/m/Y H:i:s'));

                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
                $sheet->getStyle('A2')->getFont()->setItalic(true)->setSize(10);
            }
        ];
    }

    public function title(): string
    {
        return 'Empleados';
    }
}
