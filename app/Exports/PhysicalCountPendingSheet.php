<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PhysicalCountPendingSheet implements FromArray, WithColumnFormatting, WithEvents, WithStyles, WithTitle
{
    public function __construct(protected array $payload) {}

    public function array(): array
    {
        return [
            ['Sucursal', 'Auditoría', 'Folio', 'Fecha', 'Código', 'Producto', 'Categoría', 'Stock inicial', 'Estado'],
            ...collect($this->payload['reportRows'] ?? [])
                ->where('row_type', 'pending')
                ->map(fn (array $row) => [
                    $row['branch_name'] ?? 'Sin sucursal',
                    $row['audit_name'] ?? 'Sin auditoría',
                    $row['folio'] ?? 'Sin folio',
                    $row['audit_date'] ?? null,
                    $row['scanned_code'] ?? '-',
                    $row['product_name'] ?? 'Sin producto',
                    $row['category_name'] ?? 'Sin categoría',
                    (float) ($row['system_stock'] ?? 0),
                    'No encontrado',
                ])->values()->all(),
        ];
    }

    public function columnFormats(): array
    {
        return ['D' => 'yyyy-mm-dd', 'E' => NumberFormat::FORMAT_TEXT, 'H' => '#,##0.00'];
    }

    public function styles(Worksheet $sheet): array
    {
        return [1 => ['font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2563EB']]]];
    }

    public function registerEvents(): array
    {
        return [AfterSheet::class => function (AfterSheet $event) {
            $sheet = $event->sheet->getDelegate();
            $highestRow = $sheet->getHighestRow();
            $sheet->setAutoFilter("A1:I{$highestRow}");
            $sheet->freezePane('A2');
            $sheet->setShowGridlines(false);
            $sheet->getTabColor()->setRGB('2563EB');
            $sheet->getStyle("A1:I{$highestRow}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
            $sheet->getStyle("A1:I{$highestRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
            for ($row = 2; $row <= $highestRow; $row++) {
                $sheet->getCell("E{$row}")->setValueExplicit((string) $sheet->getCell("E{$row}")->getValue(), DataType::TYPE_STRING);
            }
            foreach (range('A', 'I') as $column) {
                $sheet->getColumnDimension($column)->setWidth(match ($column) {
                    'B', 'F' => 34, 'G', 'H' => 24, 'E' => 22, default => 16,
                });
            }
            $sheet->getPageSetup()->setOrientation('landscape')->setFitToWidth(1)->setFitToHeight(0);
            $sheet->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd(1, 1);
        }];
    }

    public function title(): string
    {
        return 'No encontrados';
    }
}
