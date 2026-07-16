<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PhysicalCountUserSheet implements FromArray, ShouldAutoSize, WithColumnFormatting, WithEvents, WithStyles, WithTitle
{
    protected Collection $entries;
    protected Collection $reportRows;

    public function __construct(
        protected array $payload,
        protected object $user
    ) {
        $this->entries = collect($payload['entries'] ?? [])
            ->where('user_id', $user->id)
            ->values();
        $this->reportRows = collect($payload['reportRows'] ?? [])->values();
    }

    public function array(): array
    {
        return [
            $this->headings(),
            ...$this->rows(),
        ];
    }

    public function headings(): array
    {
        return [
            'Check',
            'Codigo Barras',
            'Descrip.Producto',
            'Stock.Actual',
            'Conteo Fisico',
            'Caducado',
            'NO EXHIBIDO',
        ];
    }

    protected function rows(): array
    {
        $entriesByProduct = $this->entries->groupBy('branch_product_id');

        return $this->reportRows
            ->map(function (array $row) use ($entriesByProduct) {
                $group = $entriesByProduct->get($row['branch_product_id'] ?? null, collect());
                $counted = (float) $group->sum('counted_quantity');

                return [
                    $counted > 0,
                    $row['scanned_code'] ?? '-',
                    $row['product_name'] ?? 'Sin producto',
                    (float) ($row['system_stock'] ?? 0),
                    $counted > 0 ? $counted : null,
                    (float) $group->sum('expired_quantity'),
                    false,
                ];
            })
            ->values()
            ->all();
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '5B3F86']],
            ],
        ];
    }

    public function columnFormats(): array
    {
        return [
            'B' => NumberFormat::FORMAT_TEXT,
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $highestRow = $sheet->getHighestRow();

                $sheet->freezePane('A2');
                $sheet->getStyle("A1:G{$highestRow}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
                $sheet->getStyle("A1:G1")->getAlignment()->setWrapText(true);
                $sheet->getStyle("A1:G{$highestRow}")->getBorders()->getAllBorders()->setBorderStyle('thin');
                $sheet->getStyle("D2:F{$highestRow}")->getNumberFormat()->setFormatCode('#,##0.00');
                $sheet->getColumnDimension('A')->setWidth(15);
                $sheet->getColumnDimension('B')->setWidth(21.25);
                $sheet->getColumnDimension('C')->setWidth(45);
                $sheet->getColumnDimension('D')->setWidth(8);
                $sheet->getColumnDimension('E')->setWidth(10);
                $sheet->getColumnDimension('F')->setWidth(7);
                $sheet->getColumnDimension('G')->setWidth(15);
            },
        ];
    }

    public function title(): string
    {
        $title = preg_replace('/[\\\\\\/?*\\[\\]:]/', '', (string) $this->user->name);
        $title = trim($title) ?: 'Usuario';
        $suffix = '-' . $this->user->id;

        return mb_substr($title, 0, 31 - mb_strlen($suffix)) . $suffix;
    }
}
