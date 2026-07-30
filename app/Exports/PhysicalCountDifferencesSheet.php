<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithCharts;
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
use PhpOffice\PhpSpreadsheet\Chart\Chart;
use PhpOffice\PhpSpreadsheet\Chart\DataSeries;
use PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues;
use PhpOffice\PhpSpreadsheet\Chart\Legend;
use PhpOffice\PhpSpreadsheet\Chart\PlotArea;
use PhpOffice\PhpSpreadsheet\Chart\Title;

class PhysicalCountDifferencesSheet implements FromArray, WithCharts, WithColumnFormatting, WithEvents, WithStyles, WithTitle
{
    protected Collection $rows;

    public function __construct(protected array $payload)
    {
        $this->rows = collect($payload['reportRows'] ?? [])
            ->filter(fn (array $row) => ($row['row_type'] ?? null) === 'counted')
            ->filter(fn (array $row) => abs((float) ($row['difference'] ?? 0)) > 0)
            ->sortByDesc(fn (array $row) => abs((float) ($row['difference'] ?? 0)))
            ->values();
    }

    public function array(): array
    {
        return [
            $this->headings(),
            ...$this->rows
                ->map(function (array $row, int $index) {
                    $sheetRow = $index + 2;

                    return [
                        $row['branch_name'] ?? 'Sin sucursal',
                        $row['audit_name'] ?? 'Sin auditoria',
                        $row['folio'] ?? 'Sin folio',
                        $row['product_name'] ?? 'Sin producto',
                        $row['category_name'] ?? 'Sin categoria',
                        $row['scanned_code'] ?? '-',
                        (float) ($row['system_stock'] ?? 0),
                        "=I{$sheetRow}-J{$sheetRow}-K{$sheetRow}",
                        (float) ($row['counted_stock'] ?? 0),
                        (float) ($row['damaged_stock'] ?? 0),
                        (float) ($row['expired_stock'] ?? 0),
                        "=H{$sheetRow}-G{$sheetRow}",
                        "=IFERROR(L{$sheetRow}/ABS(G{$sheetRow}),0)",
                        $row['status_label'] ?? $this->statusLabel($row['status'] ?? ''),
                        implode(', ', $row['participants'] ?? []),
                        $row['audit_date'] ?? '',
                        $row['last_entry_at'] ?? '',
                    ];
                })
                ->all(),
        ];
    }

    public function headings(): array
    {
        return [
            'Sucursal',
            'Auditoria',
            'Folio',
            'Producto',
            'Categoria',
            'Codigo',
            'Stock Actual',
            'Stock.Nuevo',
            'Conteo Fisico',
            'Dañado',
            'Caducado',
            'Diferencia',
            'Diferencia %',
            'Resultado',
            'Usuarios',
            'Fecha',
            'Última captura',
        ];
    }

    public function columnFormats(): array
    {
        return [
            'F' => NumberFormat::FORMAT_TEXT,
            'G' => '#,##0.00',
            'H' => '#,##0.00',
            'I' => '#,##0.00',
            'J' => '#,##0.00',
            'K' => '#,##0.00',
            'L' => '#,##0.00',
            'M' => '0.00%',
        ];
    }

    public function charts(): array
    {
        $lastRow = min($this->rows->count(), 10) + 1;
        if ($lastRow < 2) {
            return [];
        }

        $series = new DataSeries(
            DataSeries::TYPE_BARCHART,
            DataSeries::GROUPING_CLUSTERED,
            [0],
            [new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, "'Diferencias'!\$L\$1", null, 1)],
            [new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, "'Diferencias'!\$D\$2:\$D\${$lastRow}", null, $lastRow - 1)],
            [new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, "'Diferencias'!\$L\$2:\$L\${$lastRow}", null, $lastRow - 1)]
        );
        $series->setPlotDirection(DataSeries::DIRECTION_BAR);

        $chart = new Chart('TopDiferencias', new Title('Top de diferencias en unidades'), new Legend(Legend::POSITION_RIGHT, null, false), new PlotArea(null, [$series]));
        $chart->setTopLeftPosition('S2');
        $chart->setBottomRightPosition('AB20');

        return [$chart];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '7C2D12']],
            ],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $highestRow = $sheet->getHighestRow();

                $sheet->setAutoFilter("A1:Q{$highestRow}");
                $sheet->freezePane('A2');
                $sheet->getTabColor()->setRGB('EA580C');
                $sheet->getStyle("A1:Q{$highestRow}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
                $sheet->getStyle("A1:Q{$highestRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
                $sheet->getStyle('A1:Q1')->getAlignment()->setWrapText(true);
                for ($row = 2; $row <= $highestRow; $row++) {
                    $sheet->getCell("F{$row}")->setValueExplicit((string) $sheet->getCell("F{$row}")->getValue(), DataType::TYPE_STRING);
                }
                $sheet->setShowGridlines(false);
                $sheet->getPageSetup()->setOrientation('landscape')->setFitToWidth(1)->setFitToHeight(0);
                $sheet->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd(1, 1);

                $this->applyResultColors($sheet);
            },
        ];
    }

    public function title(): string
    {
        return 'Diferencias';
    }

    protected function applyResultColors(Worksheet $sheet): void
    {
        $this->rows->each(function (array $row, int $index) use ($sheet) {
            $sheetRow = $index + 2;
            $color = match ($row['status'] ?? null) {
                'missing' => 'FFEDD5',
                'surplus' => 'FEF9C3',
                default => null,
            };

            if (! $color) {
                return;
            }

            $sheet->getStyle("A{$sheetRow}:Q{$sheetRow}")
                ->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()
                ->setRGB($color);
        });
    }

    protected function statusLabel(string $status): string
    {
        return match ($status) {
            'missing' => 'Faltante',
            'surplus' => 'Sobrante',
            'matched' => 'Macheado',
            default => 'Pendiente',
        };
    }
}
