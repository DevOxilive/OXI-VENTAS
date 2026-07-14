<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithCharts;
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
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PhysicalCountDashboardSheet implements FromArray, ShouldAutoSize, WithCharts, WithEvents, WithStyles, WithTitle
{
    public function __construct(
        protected array $payload,
        protected array $filterLabels,
        protected string $branchName,
        protected string $sourceSheetTitle = 'Concentrado'
    ) {}

    public function array(): array
    {
        $lastRow = max(2, count($this->payload['reportRows'] ?? []) + 1);
        $sheet = $this->quotedSheetTitle();

        return [
            ['Total Productos', "=COUNTA({$sheet}!B2:B{$lastRow})"],
            ['Avance', "=IFERROR(1-(COUNTIF({$sheet}!D2:D{$lastRow},\"S/D\")/COUNTA({$sheet}!B2:B{$lastRow})),0)"],
            [null, null],
            ['Macheados', "=SUMPRODUCT(({$sheet}!C2:C{$lastRow}={$sheet}!D2:D{$lastRow})*({$sheet}!D2:D{$lastRow}<>\"S/D\"))"],
            ['Sobrantes', "=SUMPRODUCT(({$sheet}!D2:D{$lastRow}>{$sheet}!C2:C{$lastRow})*({$sheet}!D2:D{$lastRow}<>\"S/D\"))"],
            ['Faltantes', "=SUMPRODUCT(({$sheet}!D2:D{$lastRow}<{$sheet}!C2:C{$lastRow})*({$sheet}!D2:D{$lastRow}<>\"S/D\"))"],
            ['No encontrados', "=COUNTIF({$sheet}!D2:D{$lastRow},\"S/D\")"],
            [null, null],
            ['TOTAL', '=SUM(B4:B6)'],
            ['Descontando el total producto - Total mach,dif,stock bajo', '=B1-B9'],
            [null, null],
            ['Sucursal', $this->branchName],
            ['Auditoria', $this->filterLabels['audit'] ?? 'Todas'],
            ['Usuario(s)', $this->filterLabels['user'] ?? 'Todos'],
            ['Resultado', $this->filterLabels['status'] ?? 'Todos'],
            ['Generado', now()->format('d/m/Y H:i')],
        ];
    }

    public function charts()
    {
        $labels = [
            new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, 'Dashboard!$B$3', null, 1),
        ];

        $categories = [
            new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, 'Dashboard!$A$4:$A$7', null, 4),
        ];

        $values = [
            new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, 'Dashboard!$B$4:$B$7', null, 4),
        ];

        $series = new DataSeries(
            DataSeries::TYPE_PIECHART,
            null,
            [0],
            $labels,
            $categories,
            $values
        );

        $chart = new Chart(
            'BalanceAuditoria',
            new Title('Balance de auditoria'),
            new Legend(Legend::POSITION_RIGHT, null, false),
            new PlotArea(null, [$series])
        );

        $chart->setTopLeftPosition('C1');
        $chart->setBottomRightPosition('P10');

        return $chart;
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '111827']],
            ],
            4 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '16A34A']],
            ],
            5 => [
                'font' => ['bold' => true, 'color' => ['rgb' => '111827']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FDE047']],
            ],
            6 => [
                'font' => ['bold' => true, 'color' => ['rgb' => '111827']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FB923C']],
            ],
            7 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2563EB']],
            ],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $sheet->mergeCells('C1:P10');
                $sheet->mergeCells('A11:P16');
                $sheet->getStyle('A1:B16')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
                $sheet->getStyle('A1:B10')->getBorders()->getOutline()->setBorderStyle(Border::BORDER_THIN);
                $sheet->getStyle('A12:B16')->getBorders()->getOutline()->setBorderStyle(Border::BORDER_THIN);
                $sheet->getStyle('B2')->getNumberFormat()->setFormatCode('0.00%');
                $sheet->getColumnDimension('A')->setWidth(48);
                $sheet->getColumnDimension('B')->setWidth(18);
                $sheet->setShowGridlines(false);
            },
        ];
    }

    public function title(): string
    {
        return 'Dashboard';
    }

    protected function quotedSheetTitle(): string
    {
        return "'" . str_replace("'", "''", $this->sourceSheetTitle) . "'";
    }
}
