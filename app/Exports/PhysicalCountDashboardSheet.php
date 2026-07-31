<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithCharts;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Chart\Chart;
use PhpOffice\PhpSpreadsheet\Chart\DataSeries;
use PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues;
use PhpOffice\PhpSpreadsheet\Chart\Layout;
use PhpOffice\PhpSpreadsheet\Chart\Legend;
use PhpOffice\PhpSpreadsheet\Chart\PlotArea;
use PhpOffice\PhpSpreadsheet\Chart\Title;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;

class PhysicalCountDashboardSheet implements FromArray, ShouldAutoSize, WithCharts, WithEvents, WithStyles, WithTitle
{
    public function __construct(
        protected array $payload,
        protected array $filterLabels,
        protected string $branchName,
        protected string $sourceSheetTitle = 'Concentrado',
        protected ?Collection $concentratedUsers = null,
        protected ?string $statusFilter = null
    ) {}

    public function array(): array
    {
        $filteredRows = $this->filteredRows();
        $countedRows = $filteredRows->where('row_type', 'counted');
        $matched = $countedRows->where('status', 'matched')->count();
        $missing = $countedRows->where('status', 'missing')->count();
        $surplus = $countedRows->where('status', 'surplus')->count();
        $totalProducts = $filteredRows->count();
        $pending = $filteredRows->where('row_type', 'pending')->count();

        $rows = [
            ['Total Productos', $totalProducts],
            ['Avance', $totalProducts > 0 ? ($totalProducts - $pending) / $totalProducts : 0],
            [null, null],
            ['Coincidentes', $matched],
            ['Sobrantes', $surplus],
            ['Faltantes', $missing],
            ['Sin revisar', $pending],
            [null, null],
            ['TOTAL', $matched + $surplus + $missing],
            ['Productos pendientes de revisar', $pending],
            [null, null],
            ['Sucursal', $this->branchName],
            ['Auditoria', $this->filterLabels['audit'] ?? 'Todas'],
            ['Usuario(s)', $this->filterLabels['user'] ?? 'Todos'],
            ['Categoria', $this->filterLabels['category'] ?? 'Todas'],
            ['Resultado', $this->filterLabels['status'] ?? 'Todos'],
            ['Fecha de auditoría', $this->filterLabels['report_date'] ?? 'Todas las fechas'],
            ['Busqueda', $this->filterLabels['search'] ?? 'Sin búsqueda'],
            ['Generado', now()->format('d/m/Y H:i')],
        ];

        return $rows;
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

        $labelsConfig = new Layout();
        $labelsConfig->setShowPercent(true);
        $labelsConfig->setShowLegendKey(false);
        $labelsConfig->setShowVal(false);
        $values[0]->setLabelLayout($labelsConfig);
        $values[0]->setFillColor([
            '16A34A', // Coincidente
            'FACC15', // Sobrante
            'DC2626', // Faltante
            'DBEAFE', // No encontrado
        ]);

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
                $highestRow = $sheet->getHighestRow();

                $sheet->mergeCells('C1:P10');
                $sheet->getStyle("A1:F{$highestRow}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
                $sheet->getStyle('A1:B10')->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
                $sheet->getStyle('A1:B10')->getBorders()->getAllBorders()->getColor()->setRGB('D1D5DB');
                $sheet->getStyle("A12:B19")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
                // Tarjetas ejecutivas: lectura inmediata del estado de la auditoría.
                foreach ([
                    'A1:B2' => '1D4ED8',
                    'A4:B4' => '16A34A',
                    'A5:B5' => 'FACC15',
                    'A6:B6' => 'DC2626',
                    'A7:B7' => 'DBEAFE',
                    'A9:B10' => 'F3F4F6',
                ] as $range => $color) {
                    $sheet->getStyle($range)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($color);
                    $sheet->getStyle($range)->getFont()->setBold(true);
                }
                $sheet->getStyle('A1:B2')->getFont()->getColor()->setRGB('FFFFFF');
                $sheet->getStyle('A4:B4')->getFont()->getColor()->setRGB('FFFFFF');
                $sheet->getStyle('A5:B5')->getFont()->getColor()->setRGB('111827');
                $sheet->getStyle('A6:B6')->getFont()->getColor()->setRGB('FFFFFF');
                $sheet->getStyle('A7:B7')->getFont()->getColor()->setRGB('111827');
                $sheet->getStyle('A1:A10')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                $sheet->getStyle('B1:B10')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $sheet->getRowDimension(1)->setRowHeight(30);
                $sheet->getRowDimension(2)->setRowHeight(30);
                foreach ([4, 5, 6, 7, 9, 10] as $row) {
                    $sheet->getRowDimension($row)->setRowHeight(24);
                }
                $sheet->getStyle('A12:A19')->getFont()->setBold(true)->getColor()->setRGB('374151');
                $sheet->getStyle('B1:B10')->getNumberFormat()->setFormatCode('#,##0');
                $sheet->getStyle('B2')->getNumberFormat()->setFormatCode('0.00%');
                $sheet->getStyle('B1:B10')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $sheet->getColumnDimension('A')->setWidth(35.38);
                $sheet->getColumnDimension('B')->setWidth(17.63);
                $sheet->getColumnDimension('C')->setWidth(17.63);
                $sheet->getColumnDimension('D')->setWidth(17.63);
                $sheet->getColumnDimension('E')->setWidth(20);
                $sheet->setShowGridlines(false);
                $sheet->getHeaderFooter()->setOddFooter('&LSuper Kay&CDashboard de auditoría&RGenerado: &D &T');
                $logoPath = public_path('icons/super-kay-source.png');
                if (is_file($logoPath)) {
                    $drawing = new Drawing();
                    $drawing->setName('Super Kay');
                    $drawing->setDescription('Logotipo Super Kay');
                    $drawing->setPath($logoPath);
                    $drawing->setHeight(125);
                    // Bloque visual independiente debajo de la gráfica y junto
                    // a los filtros, sin tapar indicadores ni tablas.
                    $drawing->setCoordinates('C12');
                    $drawing->setOffsetX(4);
                    $drawing->setOffsetY(2);
                    $drawing->setWorksheet($sheet);
                }
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

    protected function users(): Collection
    {
        if ($this->concentratedUsers instanceof Collection) {
            return $this->concentratedUsers->values();
        }

        $entries = collect($this->payload['entries'] ?? []);
        $usersFromEntries = $entries
            ->map(fn ($entry) => is_array($entry) ? ($entry['user'] ?? null) : ($entry->user ?? null))
            ->filter();

        $usersFromAudits = collect($this->payload['audits'] ?? [])
            ->flatMap(fn ($audit) => is_array($audit) ? ($audit['participants'] ?? []) : ($audit->participants ?? []))
            ->filter();

        return $usersFromEntries
            ->merge($usersFromAudits)
            ->unique(fn ($user) => $this->userId($user) ?? $this->userName($user))
            ->values();
    }

    protected function filteredRows(): Collection
    {
        $rows = collect($this->payload['reportRows'] ?? []);

        if ($this->statusFilter === 'not_found') {
            return $rows
                ->where('row_type', 'pending')
                ->values();
        }

        if (! $this->statusFilter) {
            return $rows->values();
        }

        return $rows
            ->filter(function (array $row) {
                if (($row['row_type'] ?? null) !== 'counted') {
                    return false;
                }

                return match ($this->statusFilter) {
                    'matched' => ($row['status'] ?? null) === 'matched',
                    'missing' => ($row['status'] ?? null) === 'missing',
                    'surplus' => ($row['status'] ?? null) === 'surplus',
                    default => true,
                };
            })
            ->values();
    }

    protected function userId(object|array $user): mixed
    {
        return is_array($user) ? ($user['id'] ?? null) : ($user->id ?? null);
    }

    protected function userName(object|array $user): string
    {
        $name = is_array($user) ? ($user['name'] ?? null) : ($user->name ?? null);

        return trim((string) $name) ?: 'Usuario';
    }
}
