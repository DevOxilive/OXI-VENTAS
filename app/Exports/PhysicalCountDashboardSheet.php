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
        $formulaLastRow = max(6000, $lastRow);
        $sheet = $this->quotedSheetTitle();

        return [
            ['📦 Total Productos', "=COUNTA({$sheet}!B2:B1001)"],
            ['📈 Avance', "=IFERROR(1-(COUNTIF({$sheet}!E2:E{$formulaLastRow},\"S/D\")/COUNTA({$sheet}!B2:B{$formulaLastRow})),0)"],
            [null, null],
            ['🟢 Stock Mach', "=SUMPRODUCT(({$sheet}!D2:D{$formulaLastRow}={$sheet}!E2:E{$formulaLastRow})*({$sheet}!E2:E{$formulaLastRow}<>\"S/D\"))"],
            ['🟡 Diferencias', "=SUMPRODUCT(({$sheet}!E2:E{$formulaLastRow}>{$sheet}!D2:D{$formulaLastRow})*({$sheet}!E2:E{$formulaLastRow}<>\"S/D\"))"],
            ['🔴Stock Bajo al actual', "=SUMPRODUCT(({$sheet}!E2:E{$formulaLastRow}<{$sheet}!D2:D{$formulaLastRow})*({$sheet}!E2:E{$formulaLastRow}<>\"S/D\"))"],
            ['⚪ Sin revisar', "=COUNTIF({$sheet}!E2:E{$formulaLastRow},\"S/D\")"],
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
            'A1:A7' => ['font' => ['size' => 20]],
            'B1:B7' => ['font' => ['size' => 20]],
            9 => ['font' => ['bold' => true]],
            10 => ['font' => ['bold' => true]],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $sheet->mergeCells('C1:P10');
                $sheet->mergeCells('A11:P32');
                $sheet->getStyle('A1:B32')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
                $sheet->getStyle('A1:B10')->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
                $sheet->getStyle('A11:P32')->getBorders()->getOutline()->setBorderStyle(Border::BORDER_THIN);
                $sheet->getStyle('B2')->getNumberFormat()->setFormatCode('0.00%');
                $sheet->getStyle('B1:B10')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $sheet->getColumnDimension('A')->setWidth(35.38);
                $sheet->getColumnDimension('B')->setWidth(17.63);
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
