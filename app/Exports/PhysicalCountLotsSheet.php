<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PhysicalCountLotsSheet implements FromArray, WithColumnFormatting, WithEvents, WithStrictNullComparison, WithStyles, WithTitle
{
    protected Collection $entries;
    protected Collection $reportRows;

    public function __construct(
        protected array $payload,
        protected Collection $auditIds
    ) {
        $this->entries = collect($payload['entries'] ?? [])
            ->filter(fn ($entry) => $auditIds->contains((int) $entry->physical_count_id))
            ->values();
        $this->reportRows = collect($payload['reportRows'] ?? [])
            ->keyBy(fn (array $row) => ($row['physical_count_id'] ?? 0) . ':' . ($row['branch_product_id'] ?? 0));
    }

    public function array(): array
    {
        return [$this->headings(), ...$this->rows()];
    }

    public function headings(): array
    {
        return [
            'Código(s) de barras',
            'Categoría',
            'Nombre del producto',
            'Lote',
            'Fecha de caducidad',
            'Stock actual',
            'Conteo físico',
            'Caducado',
            'Dañado',
            'Stock útil',
            'Exhibido',
            'Participantes (conteo)',
            'Ronda',
            'Observaciones',
        ];
    }

    protected function rows(): array
    {
        return $this->entries
            ->groupBy(fn ($entry) => implode(':', [
                $entry->physical_count_id,
                $entry->branch_product_id,
                $entry->product_batch_id ?: 'sin-lote',
            ]))
            ->map(function (Collection $group) {
                $first = $group->first();
                $row = $this->reportRows->get($first->physical_count_id . ':' . $first->branch_product_id, []);
                $counted = (float) $group->sum('counted_quantity');
                $expired = (float) $group->sum('expired_quantity');
                $damaged = (float) $group->sum('damaged_quantity');
                $usable = max(0, $counted - $expired - $damaged);
                $expiration = $first->productBatch?->expiration_date ?? $first->expiration_date;
                $roundNumber = $group->max(fn ($entry) => (int) ($entry->round?->round_number ?? 1));
                $roundType = $group
                    ->first(fn ($entry) => (int) ($entry->round?->round_number ?? 1) === $roundNumber)
                    ?->round?->type;
                $participantDetail = $group
                    ->groupBy('user_id')
                    ->map(function (Collection $participantEntries) {
                        $name = $participantEntries->first()?->user?->name ?? 'Sin usuario';
                        $quantity = (float) $participantEntries->sum('counted_quantity');

                        return $name . ' (' . number_format($quantity, 2, '.', ',') . ')';
                    })
                    ->values()
                    ->join(', ');

                return [
                    PhysicalCountBarcodeList::fromRow(
                        $row,
                        (string) ($first->scanned_code ?? '-')
                    ),
                    $row['category_name'] ?? $first->branchProduct?->product?->category?->name ?? 'Sin categoría',
                    $row['product_name'] ?? $first->branchProduct?->product?->name ?? 'Sin producto',
                    $first->productBatch?->lot_number ?: 'Sin lote',
                    $expiration ? Carbon::parse($expiration)->format('Y-m-d') : null,
                    (float) ($row['system_stock'] ?? 0),
                    $counted,
                    $expired > 0 ? $expired : null,
                    $damaged > 0 ? $damaged : null,
                    $usable,
                    $usable > 0 ? 'Sí' : 'No',
                    $participantDetail ?: 'Sin usuario',
                    'Ronda ' . $roundNumber . ' - ' . ($roundType === 'reopening' ? 'Reapertura' : 'Original'),
                    $group->pluck('notes')->filter(fn ($note) => filled($note))->map(fn ($note) => trim((string) $note))->unique()->join('; ') ?: 'Sin observaciones',
                ];
            })
            ->sortBy(fn (array $row) => $row[3] . ':' . $row[4])
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
            'A' => NumberFormat::FORMAT_TEXT,
            'E' => 'yyyy-mm-dd',
            'F:J' => '#,##0.00',
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $highestRow = $sheet->getHighestRow();

                $sheet->setAutoFilter("A1:N{$highestRow}");
                $sheet->freezePane('D2');
                $sheet->setShowGridlines(false);
                $sheet->getTabColor()->setRGB('5B3F86');
                $sheet->getStyle("A1:N{$highestRow}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
                $sheet->getStyle("A1:N{$highestRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
                $sheet->getStyle('A1:N1')->getAlignment()->setWrapText(true);
                $sheet->getStyle("K2:K{$highestRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                for ($row = 2; $row <= $highestRow; $row++) {
                    $sheet->getCell("A{$row}")->setValueExplicit((string) $sheet->getCell("A{$row}")->getValue(), DataType::TYPE_STRING);
                }

                $widths = [
                    'A' => 23, 'B' => 22, 'C' => 42, 'D' => 20, 'E' => 18,
                    'F' => 14, 'G' => 16, 'H' => 14, 'I' => 14, 'J' => 14,
                    'K' => 12, 'L' => 38, 'M' => 24, 'N' => 42,
                ];
                foreach ($widths as $column => $width) {
                    $sheet->getColumnDimension($column)->setWidth($width);
                }

                $sheet->getPageSetup()->setOrientation('landscape')->setFitToWidth(1)->setFitToHeight(0);
                $sheet->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd(1, 1);
                $sheet->getHeaderFooter()->setOddFooter('&LSuper Kay&CLotes&RGenerado: &D &T');
            },
        ];
    }

    public function title(): string
    {
        return 'Lotes';
    }
}
