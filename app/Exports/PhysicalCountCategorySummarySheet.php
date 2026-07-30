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

class PhysicalCountCategorySummarySheet implements FromArray, ShouldAutoSize, WithCharts, WithColumnFormatting, WithEvents, WithStyles, WithTitle
{
    public function __construct(protected array $payload) {}

    public function array(): array
    {
        return [
            ['Categoría', 'Productos', 'Contados', 'Pendientes', 'Coincidentes', 'Faltantes', 'Sobrantes', 'Avance'],
            ...collect($this->payload['categorySummary'] ?? [])
                ->map(function (array $row) {
                    $products = (int) ($row['products'] ?? 0);
                    $counted = (int) ($row['counted_products'] ?? 0);

                    return [
                        $row['category_name'] ?? 'Sin categoría',
                        $products,
                        $counted,
                        (int) ($row['pending_products'] ?? 0),
                        (int) ($row['matched_products'] ?? 0),
                        (int) ($row['missing_products'] ?? 0),
                        (int) ($row['surplus_products'] ?? 0),
                        $products > 0 ? $counted / $products : 0,
                    ];
                })->values()->all(),
        ];
    }

    public function columnFormats(): array
    {
        return ['B:G' => '#,##0', 'H' => NumberFormat::FORMAT_PERCENTAGE_00];
    }

    public function charts(): array
    {
        $lastRow = count($this->payload['categorySummary'] ?? []) + 1;
        if ($lastRow < 2) {
            return [];
        }

        $series = new DataSeries(
            DataSeries::TYPE_BARCHART,
            DataSeries::GROUPING_CLUSTERED,
            [0, 1, 2],
            [
                new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, "'Resumen categorias'!\$C\$1", null, 1),
                new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, "'Resumen categorias'!\$D\$1", null, 1),
                new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, "'Resumen categorias'!\$F\$1", null, 1),
            ],
            [new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, "'Resumen categorias'!\$A\$2:\$A\${$lastRow}", null, $lastRow - 1)],
            [
                new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, "'Resumen categorias'!\$C\$2:\$C\${$lastRow}", null, $lastRow - 1),
                new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, "'Resumen categorias'!\$D\$2:\$D\${$lastRow}", null, $lastRow - 1),
                new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, "'Resumen categorias'!\$F\$2:\$F\${$lastRow}", null, $lastRow - 1),
            ]
        );
        $series->setPlotDirection(DataSeries::DIRECTION_COL);

        $chart = new Chart('BalancePorCategoria', new Title('Resultados por categoría'), new Legend(Legend::POSITION_RIGHT, null, false), new PlotArea(null, [$series]));
        $chart->setTopLeftPosition('J2');
        $chart->setBottomRightPosition('S18');

        return [$chart];
    }

    public function styles(Worksheet $sheet): array
    {
        return [1 => ['font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0F766E']]]];
    }

    public function registerEvents(): array
    {
        return [AfterSheet::class => function (AfterSheet $event) {
            $sheet = $event->sheet->getDelegate();
            $highestRow = $sheet->getHighestRow();
            $sheet->setAutoFilter("A1:H{$highestRow}");
            $sheet->freezePane('A2');
            $sheet->setShowGridlines(false);
            $sheet->getTabColor()->setRGB('0F766E');
            $sheet->getStyle("A1:H{$highestRow}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
            $sheet->getStyle("A1:H{$highestRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
            $sheet->getColumnDimension('A')->setWidth(34);
            foreach (range('B', 'H') as $column) {
                $sheet->getColumnDimension($column)->setWidth(16);
            }
            $sheet->getPageSetup()->setFitToWidth(1)->setFitToHeight(0);
        }];
    }

    public function title(): string
    {
        return 'Resumen categorias';
    }
}
