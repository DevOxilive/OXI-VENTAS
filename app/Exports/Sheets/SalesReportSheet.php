<?php

namespace App\Exports\Sheets;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class SalesReportSheet implements FromArray, ShouldAutoSize, WithEvents, WithStrictNullComparison, WithStyles, WithTitle
{
    public function __construct(
        private array $rows,
        private string $sheetTitle,
        private int $headerRow = 1,
        private array $currencyColumns = [],
        private array $columnWidths = [],
        private bool $hasFilters = true,
        private int $currencyStartRow = 0,
        private array $secondaryHeaderRows = [],
        private array $highlightRows = [],
        private bool $hasTitleRow = false,
        private array $sectionRows = [],
        private array $ticketRows = [],
        private array $metadataLabelCells = [],
        private array $dateColumns = [],
    ) {}

    public function array(): array
    {
        return $this->rows;
    }

    public function title(): string
    {
        return $this->sheetTitle;
    }

    public function styles(Worksheet $sheet): array
    {
        $headerStyle = [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'C91424']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ];
        $styles = [$this->headerRow => $headerStyle];

        foreach ($this->secondaryHeaderRows as $row) {
            $styles[$row] = $headerStyle;
        }

        return $styles;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $lastColumn = $sheet->getHighestColumn();
                $lastRow = $sheet->getHighestRow();
                $headerRange = "A{$this->headerRow}:{$lastColumn}{$this->headerRow}";

                $sheet->setShowGridlines(false);
                $sheet->getParent()->getDefaultStyle()->getFont()->setName('Aptos')->setSize(10);
                $sheet->freezePane('A'.($this->headerRow + 1));
                $sheet->getStyle("A1:{$lastColumn}{$lastRow}")
                    ->getAlignment()
                    ->setVertical(Alignment::VERTICAL_CENTER);
                $sheet->getStyle($headerRange)
                    ->getBorders()
                    ->getBottom()
                    ->setBorderStyle(Border::BORDER_MEDIUM)
                    ->setColor(new Color('8F0C17'));
                $sheet->getRowDimension($this->headerRow)->setRowHeight(26);
                if ($this->hasTitleRow) {
                    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16)->getColor()->setRGB('C91424');
                }

                foreach ($this->highlightRows as $row) {
                    $sheet->getStyle("A{$row}:{$lastColumn}{$row}")->applyFromArray([
                        'font' => ['bold' => true, 'color' => ['rgb' => '8F0C17']],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F6DADD']],
                    ]);
                }

                foreach ($this->sectionRows as $row) {
                    $sheet->getStyle("A{$row}:{$lastColumn}{$row}")->applyFromArray([
                        'font' => ['bold' => true, 'color' => ['rgb' => '8A6100']],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F2E5BD']],
                    ]);
                }

                foreach ($this->ticketRows as $row) {
                    $sheet->getStyle("A{$row}:{$lastColumn}{$row}")->applyFromArray([
                        'font' => ['bold' => true, 'color' => ['rgb' => '241719']],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F2DFE2']],
                    ]);
                }

                foreach ($this->metadataLabelCells as $cell) {
                    $sheet->getStyle($cell)->getFont()->setBold(true)->getColor()->setRGB('C91424');
                }

                if ($this->hasFilters && $lastRow > $this->headerRow) {
                    $sheet->setAutoFilter("A{$this->headerRow}:{$lastColumn}{$lastRow}");
                    $sheet->getStyle("A".($this->headerRow + 1).":{$lastColumn}{$lastRow}")
                        ->getBorders()
                        ->getBottom()
                        ->setBorderStyle(Border::BORDER_HAIR)
                        ->setColor(new Color('E7CDD1'));
                }

                foreach ($this->currencyColumns as $column) {
                    $currencyStartRow = $this->currencyStartRow ?: $this->headerRow + 1;
                    $sheet->getStyle("{$column}{$currencyStartRow}:{$column}{$lastRow}")
                        ->getNumberFormat()
                        ->setFormatCode('"$"#,##0.00');
                }

                foreach ($this->dateColumns as $column) {
                    $sheet->getStyle("{$column}".($this->headerRow + 1).":{$column}{$lastRow}")
                        ->getNumberFormat()
                        ->setFormatCode('dd/mm/yyyy');
                }

                foreach ($this->columnWidths as $column => $width) {
                    $sheet->getColumnDimension($column)->setAutoSize(false);
                    $sheet->getColumnDimension($column)->setWidth($width);
                }

                foreach (range(1, Coordinate::columnIndexFromString($lastColumn)) as $index) {
                    $column = Coordinate::stringFromColumnIndex($index);
                    if (! array_key_exists($column, $this->columnWidths)) {
                        $sheet->getColumnDimension($column)->setAutoSize(true);
                    }
                }
            },
        ];
    }
}
