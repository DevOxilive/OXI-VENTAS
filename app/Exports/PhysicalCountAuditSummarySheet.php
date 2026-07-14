<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PhysicalCountAuditSummarySheet implements FromArray, ShouldAutoSize, WithColumnFormatting, WithEvents, WithStyles, WithTitle
{
    public function __construct(protected array $payload)
    {
    }

    public function array(): array
    {
        return [
            $this->headings(),
            ...collect($this->payload['auditSummary'] ?? [])
                ->map(fn (array $row) => [
                    $row['branch_name'] ?? 'Sin sucursal',
                    $row['audit_name'] ?? 'Sin auditoria',
                    $row['folio'] ?? 'Sin folio',
                    $row['audit_date'] ?? '',
                    (int) ($row['products'] ?? 0),
                    (int) ($row['counted_products'] ?? 0),
                    (int) ($row['pending_products'] ?? 0),
                    (int) ($row['matched_products'] ?? 0),
                    (int) ($row['missing_products'] ?? 0),
                    (int) ($row['surplus_products'] ?? 0),
                    (float) ($row['advance'] ?? 0),
                    (float) ($row['absolute_difference_units'] ?? 0),
                ])
                ->values()
                ->all(),
        ];
    }

    public function headings(): array
    {
        return [
            'Sucursal',
            'Auditoria',
            'Folio',
            'Fecha',
            'Productos',
            'Contados',
            'No encontrados',
            'Macheados',
            'Faltantes',
            'Sobrantes',
            'Avance',
            'Diferencia absoluta',
        ];
    }

    public function columnFormats(): array
    {
        return [
            'K' => NumberFormat::FORMAT_PERCENTAGE_00,
            'L' => '#,##0.00',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1F2937']],
            ],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $highestRow = $sheet->getHighestRow();

                $sheet->setAutoFilter("A1:L{$highestRow}");
                $sheet->freezePane('A2');
                $sheet->getTabColor()->setRGB('334155');
                $sheet->getStyle("A1:L{$highestRow}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
                $sheet->getStyle('A1:L1')->getAlignment()->setWrapText(true);
                $sheet->setShowGridlines(false);
            },
        ];
    }

    public function title(): string
    {
        return 'Resumen auditorias';
    }
}
