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
            'Sucursal',
            'Auditoria',
            'Folio',
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
                    $row['branch_name'] ?? 'Sin sucursal',
                    $row['audit_name'] ?? 'Sin auditoria',
                    $row['folio'] ?? 'Sin folio',
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
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1F2937']],
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

                $sheet->setAutoFilter("A1:J{$highestRow}");
                $sheet->freezePane('A2');
                $sheet->getStyle("A1:J{$highestRow}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
                $sheet->getStyle("A1:J1")->getAlignment()->setWrapText(true);
                $sheet->getStyle("D2:F{$highestRow}")->getNumberFormat()->setFormatCode('#,##0.00');
                $sheet->getColumnDimension('C')->setWidth(45);
                $sheet->getColumnDimension('H')->setWidth(24);
                $sheet->getColumnDimension('I')->setWidth(30);
                $sheet->getColumnDimension('J')->setWidth(18);
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
