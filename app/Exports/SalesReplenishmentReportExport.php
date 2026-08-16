<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class SalesReplenishmentReportExport implements FromArray, ShouldAutoSize, WithEvents, WithStyles, WithTitle
{
    private array $sectionRows = [];

    public function __construct(
        private array $report,
        private string $activeTab = 'pedido',
    ) {}

    public function array(): array
    {
        $this->sectionRows = [];
        $rows = [];
        $branches = $this->report['branches'] ?? [];
        $sections = $this->report['sections'][$this->activeTab] ?? [];

        $rows[] = $this->headerRow($branches);

        foreach ($sections as $section) {
            $this->sectionRows[] = count($rows) + 1;
            $rows[] = $this->sectionHeaderRow($branches, $section['label'] ?? 'Sin seccion');

            foreach ($section['rows'] ?? [] as $row) {
                $rows[] = $this->productRow($row, $branches);
            }
        }

        if (count($rows) === 1) {
            $rows[] = ['No hay productos para esta seccion con los filtros seleccionados.'];
        }

        return $rows;
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => [
                    'bold' => true,
                    'color' => ['rgb' => 'FFFFFF'],
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '7030A0'],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                    'wrapText' => true,
                ],
            ],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $highestColumn = $sheet->getHighestColumn();
                $highestRow = $sheet->getHighestRow();
                $highestColumnIndex = Coordinate::columnIndexFromString($highestColumn);

                $sheet->freezePane('F2');
                $sheet->getStyle("A1:{$highestColumn}{$highestRow}")
                    ->getBorders()
                    ->getAllBorders()
                    ->setBorderStyle(Border::BORDER_THIN)
                    ->getColor()
                    ->setRGB('111827');

                $sheet->getStyle("A1:{$highestColumn}{$highestRow}")
                    ->getAlignment()
                    ->setWrapText(true)
                    ->setVertical(Alignment::VERTICAL_CENTER);

                foreach ($this->sectionRows as $rowNumber) {
                    $sheet->getStyle("A{$rowNumber}:{$highestColumn}{$rowNumber}")->applyFromArray([
                        'font' => [
                            'bold' => true,
                            'color' => ['rgb' => 'FFFFFF'],
                        ],
                        'fill' => [
                            'fillType' => Fill::FILL_SOLID,
                            'startColor' => ['rgb' => '7ACD62'],
                        ],
                        'alignment' => [
                            'horizontal' => Alignment::HORIZONTAL_CENTER,
                            'vertical' => Alignment::VERTICAL_CENTER,
                            'wrapText' => true,
                        ],
                    ]);
                }

                for ($column = 1; $column <= $highestColumnIndex; $column++) {
                    $letter = Coordinate::stringFromColumnIndex($column);
                    $sheet->getColumnDimension($letter)->setAutoSize(false);
                    $sheet->getColumnDimension($letter)->setWidth($this->columnWidth($column));
                }

                for ($row = 1; $row <= $highestRow; $row++) {
                    $sheet->getRowDimension($row)->setRowHeight($row === 1 || in_array($row, $this->sectionRows, true) ? 34 : 42);
                }
            },
        ];
    }

    public function title(): string
    {
        return match ($this->activeTab) {
            'transferencias' => 'Transferencias',
            'sin-movimiento' => 'Sin movimiento',
            'pedido-tiendas' => 'Pedido a tiendas',
            default => 'Pedido',
        };
    }

    private function headerRow(array $branches): array
    {
        $row = ['CODIGO DE BARRAS'];

        if (! $this->isTransferTab()) {
            $row[] = 'P';
        }

        $row = [...$row, 'V', 'E', 'PRODUCTO'];

        foreach ($branches as $branch) {
            $row[] = 'E '.$branch['name'];
            $row[] = 'MES';
            if (! $this->isStoreOrderTab()) {
                $row[] = $this->metricLabel();
            }
        }

        return [...$row, 'P. P.', 'P.C.', 'OBSERVACIONES'];
    }

    private function sectionHeaderRow(array $branches, string $label): array
    {
        $row = ['CODIGO DE BARRAS'];

        if (! $this->isTransferTab()) {
            $row[] = 'P';
        }

        $row = [...$row, 'V', 'E', strtoupper($label)];

        foreach ($branches as $branch) {
            $row[] = 'E '.$branch['name'];
            $row[] = 'MES';
            if (! $this->isStoreOrderTab()) {
                $row[] = $this->metricLabel();
            }
        }

        return [...$row, 'P. P.', 'P.C.', 'OBSERVACIONES'];
    }

    private function productRow(array $row, array $branches): array
    {
        $values = [
            $row['code'] ?? '-',
        ];

        if (! $this->isTransferTab()) {
            $values[] = $row['total_suggested'] ?? 0;
        }

        $values = [
            ...$values,
            $row['total_sold'] ?? 0,
            $row['total_stock'] ?? 0,
            $row['product'] ?? 'Producto sin nombre',
        ];

        foreach ($branches as $branch) {
            $values[] = $this->branchMetric($row, $branch, 'stock');
            $values[] = $this->branchMetric($row, $branch, 'monthly_sales');
            if (! $this->isStoreOrderTab()) {
                $values[] = $this->branchMetric($row, $branch, $this->metricKey());
            }
        }

        $values[] = (float) ($row['sale_price'] ?? 0);
        $values[] = (float) ($row['cost'] ?? 0);
        $values[] = $this->isTransferTab() ? ($row['observation'] ?? '') : '';

        return $values;
    }

    private function branchMetric(array $row, array $branch, string $key): float|int
    {
        $metrics = collect($row['branches'] ?? [])
            ->first(fn ($item) => (int) ($item['branch_id'] ?? 0) === (int) ($branch['id'] ?? 0));

        if ($this->isTransferTab() && $key === 'transfer_in') {
            $transferOut = (float) ($metrics['transfer_out'] ?? 0);

            return $transferOut > 0
                ? -$transferOut
                : ($metrics['transfer_in'] ?? 0);
        }

        return $metrics[$key] ?? 0;
    }

    private function metricKey(): string
    {
        return match ($this->activeTab) {
            'transferencias' => 'transfer_in',
            'sin-movimiento' => 'stock',
            default => 'suggested',
        };
    }

    private function metricLabel(): string
    {
        return match ($this->activeTab) {
            'transferencias' => 'MOV',
            'sin-movimiento' => 'E',
            default => 'PED',
        };
    }

    private function isTransferTab(): bool
    {
        return $this->activeTab === 'transferencias';
    }

    private function isStoreOrderTab(): bool
    {
        return $this->activeTab === 'pedido-tiendas';
    }

    private function columnWidth(int $column): int
    {
        if ($column === 1) {
            return 18;
        }

        if ($column === ($this->isTransferTab() ? 4 : 5)) {
            return 36;
        }

        return $column > 5 ? 13 : 8;
    }
}
