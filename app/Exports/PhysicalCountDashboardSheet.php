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
        protected string $branchName
    ) {}

    public function array(): array
    {
        $summary = $this->payload['summary'] ?? [];

        return [
            ['Reporte de auditoria fisica', $this->branchName],
            ['Generado', now()->format('d/m/Y H:i')],
            ['Auditoria', $this->filterLabels['audit'] ?? 'Todas'],
            ['Usuario(s)', $this->filterLabels['user'] ?? 'Todos'],
            ['Resultado', $this->filterLabels['status'] ?? 'Todos'],
            [],
            ['Indicador', 'Valor'],
            ['Auditorias', $summary['audits'] ?? 0],
            ['Registros', $summary['records'] ?? 0],
            ['Usuarios', $summary['participants'] ?? 0],
            ['Productos contados', $summary['counted_products'] ?? 0],
            ['No encontrados', $summary['pending_products'] ?? 0],
            ['Faltantes', $summary['missing_products'] ?? 0],
            ['Sobrantes', $summary['surplus_products'] ?? 0],
            ['Correctos', $summary['matched_products'] ?? 0],
            [],
            ['Balance', 'Productos'],
            ['Correctos', $summary['matched_products'] ?? 0],
            ['Faltantes', $summary['missing_products'] ?? 0],
            ['Sobrantes', $summary['surplus_products'] ?? 0],
            ['No encontrados', $summary['pending_products'] ?? 0],
        ];
    }

    public function charts()
    {
        $labels = [
            new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, 'Dashboard!$B$17', null, 1),
        ];

        $categories = [
            new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, 'Dashboard!$A$18:$A$21', null, 4),
        ];

        $values = [
            new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, 'Dashboard!$B$18:$B$21', null, 4),
        ];

        $series = new DataSeries(
            DataSeries::TYPE_BARCHART,
            DataSeries::GROUPING_CLUSTERED,
            [0],
            $labels,
            $categories,
            $values
        );
        $series->setPlotDirection(DataSeries::DIRECTION_COL);

        $chart = new Chart(
            'BalanceAuditoria',
            new Title('Balance de auditoria'),
            new Legend(Legend::POSITION_RIGHT, null, false),
            new PlotArea(null, [$series])
        );

        $chart->setTopLeftPosition('D7');
        $chart->setBottomRightPosition('L21');

        return $chart;
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'size' => 16, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '111827']],
            ],
            7 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2563EB']],
            ],
            17 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '16A34A']],
            ],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $sheet->mergeCells('A1:B1');
                $sheet->getStyle('A1:B21')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
                $sheet->getStyle('A7:B15')->getBorders()->getOutline()->setBorderStyle(Border::BORDER_THIN);
                $sheet->getStyle('A17:B21')->getBorders()->getOutline()->setBorderStyle(Border::BORDER_THIN);
                $sheet->getColumnDimension('A')->setWidth(24);
                $sheet->getColumnDimension('B')->setWidth(20);
                $sheet->getRowDimension(1)->setRowHeight(26);
                $sheet->setShowGridlines(false);
            },
        ];
    }

    public function title(): string
    {
        return 'Dashboard';
    }
}
