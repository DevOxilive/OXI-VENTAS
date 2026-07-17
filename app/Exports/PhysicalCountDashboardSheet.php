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
        protected string $sourceSheetTitle = 'Concentrado',
        protected ?Collection $concentratedUsers = null,
        protected ?string $statusFilter = null
    ) {}

    public function array(): array
    {
        $formulaLastRow = max(2, $this->filteredRows()->count() + 1);
        $sheet = $this->quotedSheetTitle();
        $users = $this->users();

        $rows = [
            ['Total Productos', "=COUNTA({$sheet}!B2:B{$formulaLastRow})"],
            ['Avance', "=IFERROR(1-(COUNTIF({$sheet}!E2:E{$formulaLastRow},\"S/D\")/B1),0)"],
            [null, null],
            ['Stock Mach', "=IF(B1=0,0,SUMPRODUCT(({$sheet}!D2:D{$formulaLastRow}={$sheet}!E2:E{$formulaLastRow})*({$sheet}!E2:E{$formulaLastRow}<>\"S/D\")))"],
            ['Diferencias', "=IF(B1=0,0,SUMPRODUCT(({$sheet}!E2:E{$formulaLastRow}>{$sheet}!D2:D{$formulaLastRow})*({$sheet}!E2:E{$formulaLastRow}<>\"S/D\")))"],
            ['Stock Bajo al actual', "=IF(B1=0,0,SUMPRODUCT(({$sheet}!E2:E{$formulaLastRow}<{$sheet}!D2:D{$formulaLastRow})*({$sheet}!E2:E{$formulaLastRow}<>\"S/D\")))"],
            ['Sin revisar', '=IF(B1=0,0,COUNTIF(' . $sheet . "!E2:E{$formulaLastRow},\"S/D\"))"],
            [null, null],
            ['TOTAL', '=SUM(B4:B6)'],
            ['Descontando el total producto - Total mach,dif,stock bajo', '=B1-B9'],
            [null, null],
            ['Sucursal', $this->branchName],
            ['Auditoria', $this->filterLabels['audit'] ?? 'Todas'],
            ['Usuario(s)', $this->filterLabels['user'] ?? 'Todos'],
            ['Resultado', $this->filterLabels['status'] ?? 'Todos'],
            ['Generado', now()->format('d/m/Y H:i')],
            [null, null],
            ['Resumen por usuario'],
            ['Usuario', 'Conteo Fisico', 'Danado', 'Caducado', 'Productos contados'],
        ];

        $users->each(function (object|array $user, int $index) use (&$rows, $sheet, $formulaLastRow) {
            $excelRow = count($rows) + 1;
            $countColumn = Coordinate::stringFromColumnIndex(6 + ($index * 3));
            $damagedColumn = Coordinate::stringFromColumnIndex(7 + ($index * 3));
            $expiredColumn = Coordinate::stringFromColumnIndex(8 + ($index * 3));

            $rows[] = [
                $this->userName($user),
                "=SUM({$sheet}!{$countColumn}2:{$countColumn}{$formulaLastRow})",
                "=SUM({$sheet}!{$damagedColumn}2:{$damagedColumn}{$formulaLastRow})",
                "=SUM({$sheet}!{$expiredColumn}2:{$expiredColumn}{$formulaLastRow})",
                "=COUNT({$sheet}!{$countColumn}2:{$countColumn}{$formulaLastRow})",
            ];
        });

        if ($users->isEmpty()) {
            $rows[] = ['Sin usuarios con conteos', 0, 0, 0, 0];
        }

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
            18 => ['font' => ['bold' => true, 'size' => 14]],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $highestRow = $sheet->getHighestRow();

                $sheet->mergeCells('C1:P10');
                $sheet->mergeCells('A18:E18');
                $sheet->getStyle("A1:F{$highestRow}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
                $sheet->getStyle('A1:B10')->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
                $sheet->getStyle("A18:E{$highestRow}")->getBorders()->getOutline()->setBorderStyle(Border::BORDER_THIN);
                $sheet->getStyle("A19:E{$highestRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
                $sheet->getStyle('A19:E19')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('5B3F86');
                $sheet->getStyle('A19:E19')->getFont()->getColor()->setRGB('FFFFFF');
                $sheet->getStyle('B1:B10')->getNumberFormat()->setFormatCode('#,##0');
                $sheet->getStyle('B2')->getNumberFormat()->setFormatCode('0.00%');
                $sheet->getStyle("B20:E{$highestRow}")->getNumberFormat()->setFormatCode('#,##0');
                $sheet->getStyle('B1:B10')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $sheet->getColumnDimension('A')->setWidth(35.38);
                $sheet->getColumnDimension('B')->setWidth(17.63);
                $sheet->getColumnDimension('C')->setWidth(17.63);
                $sheet->getColumnDimension('D')->setWidth(17.63);
                $sheet->getColumnDimension('E')->setWidth(20);
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
            return collect();
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
