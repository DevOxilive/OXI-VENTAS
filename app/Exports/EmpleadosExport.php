<?php

namespace App\Exports;

use App\Models\Empleado;
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

class EmpleadosExport implements
    FromCollection,
    WithHeadings,
    WithMapping,
    ShouldAutoSize,
    WithStyles,
    WithEvents,
    WithTitle
{
    protected $estado;
    protected $departamento;
    protected $busqueda;

    public function __construct($estado = null, $departamento = null, $busqueda = null)
    {
        $this->estado = $estado;
        $this->departamento = $departamento;
        $this->busqueda = $busqueda;
    }

    public function collection()
    {
        $query = Empleado::query();

        if ($this->estado) {
            $query->where('estado', $this->estado);
        }

        if ($this->departamento) {
            $query->where('departamento', $this->departamento);
        }

        if ($this->busqueda) {
            $query->where(function ($q) {
                $q->where('nombre', 'like', '%' . $this->busqueda . '%')
                    ->orWhere('apellido', 'like', '%' . $this->busqueda . '%')
                    ->orWhere('puesto', 'like', '%' . $this->busqueda . '%')
                    ->orWhere('departamento', 'like', '%' . $this->busqueda . '%');
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

    public function map($empleado): array
    {
        return [
            $empleado->nombre . ' ' . $empleado->apellido,
            $empleado->correo,
            $empleado->telefono,
            $empleado->domicilio,
            $empleado->fecha_inicio,
            $empleado->estado,
            $empleado->puesto,
            $empleado->departamento,
            $empleado->banco,
            $empleado->numero_cuenta,
            $empleado->grado_estudios,
            $empleado->especialidad,
            $empleado->tipo_contrato,
            $empleado->antiguedad,
            $empleado->nss,
            $empleado->rfc
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

                // Filtro automático
                $sheet->setAutoFilter("A1:{$highestColumn}{$highestRow}");

                // Congelar encabezado
                $sheet->freezePane('A2');

                // Insertar título superior
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
