<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithCharts;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Chart\Chart;
use PhpOffice\PhpSpreadsheet\Chart\DataSeries;
use PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues;
use PhpOffice\PhpSpreadsheet\Chart\Legend;
use PhpOffice\PhpSpreadsheet\Chart\PlotArea;
use PhpOffice\PhpSpreadsheet\Chart\Title;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PhysicalCountBranchSummarySheet implements FromArray, ShouldAutoSize, WithCharts, WithColumnFormatting, WithEvents, WithStyles, WithTitle
{
    public function __construct(protected array $payload)
    {
    }

    public function array(): array
    {
        return [
            $this->headings(),
            ...collect($this->payload['branchSummary'] ?? [])
                ->map(fn (array $row) => [
                    $row['branch_name'] ?? 'Sin sucursal',
                    (int) ($row['audits'] ?? 0),
                    (int) ($row['products'] ?? 0),
                    (int) ($row['counted_products'] ?? 0),
                    (int) ($row['pending_products'] ?? 0),
                    (int) ($row['matched_products'] ?? 0),
                    (int) ($row['missing_products'] ?? 0),
                    (int) ($row['surplus_products'] ?? 0),
                    (float) ($row['advance'] ?? 0),
                    (float) ($row['difference_units'] ?? 0),
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
            'Auditorias',
            'Productos',
            'Contados',
            'No encontrados',
            'Macheados',
            'Faltantes',
            'Sobrantes',
            'Avance',
            'Diferencia neta',
            'Diferencia absoluta',
        ];
    }

    public function charts()
    {
        $lastRow = count($this->payload['branchSummary'] ?? []) + 1;
        if ($lastRow < 2) {
            return [];
        }

        $labels = [
            new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, "'Resumen sucursales'!\$F\$1", null, 1),
            new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, "'Resumen sucursales'!\$G\$1", null, 1),
            new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, "'Resumen sucursales'!\$H\$1", null, 1),
            new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, "'Resumen sucursales'!\$E\$1", null, 1),
        ];

        $categories = [
            new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, "'Resumen sucursales'!\$A\$2:\$A\${$lastRow}", null, $lastRow - 1),
        ];

        $values = [
            new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, "'Resumen sucursales'!\$F\$2:\$F\${$lastRow}", null, $lastRow - 1),
            new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, "'Resumen sucursales'!\$G\$2:\$G\${$lastRow}", null, $lastRow - 1),
            new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, "'Resumen sucursales'!\$H\$2:\$H\${$lastRow}", null, $lastRow - 1),
            new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, "'Resumen sucursales'!\$E\$2:\$E\${$lastRow}", null, $lastRow - 1),
        ];

        $series = new DataSeries(
            DataSeries::TYPE_BARCHART,
            DataSeries::GROUPING_CLUSTERED,
            range(0, count($values) - 1),
            $labels,
            $categories,
            $values
        );
        $series->setPlotDirection(DataSeries::DIRECTION_COL);

        $chart = new Chart(
            'BalancePorSucursal',
            new Title('Balance por sucursal'),
            new Legend(Legend::POSITION_RIGHT, null, false),
            new PlotArea(null, [$series])
        );

        $chart->setTopLeftPosition('M2');
        $chart->setBottomRightPosition('V18');

        return [$chart];
    }

    public function columnFormats(): array
    {
        return [
            'I' => NumberFormat::FORMAT_PERCENTAGE_00,
            'J' => '#,##0.00',
            'K' => '#,##0.00',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '111827']],
            ],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $highestRow = $sheet->getHighestRow();

                $sheet->setAutoFilter("A1:K{$highestRow}");
                $sheet->freezePane('A2');
                $sheet->getTabColor()->setRGB('0F766E');
                $sheet->getStyle("A1:K{$highestRow}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
                $sheet->getStyle("A1:K1")->getAlignment()->setWrapText(true);
                $sheet->setShowGridlines(false);
            },
        ];
    }

    public function title(): string
    {
        return 'Resumen sucursales';
    }
}
