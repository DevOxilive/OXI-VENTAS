<?php

namespace App\Exports;

use Illuminate\Support\Collection;
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

class PhysicalCountUserSheet implements FromArray, WithColumnFormatting, WithEvents, WithStrictNullComparison, WithStyles, WithTitle
{
    protected Collection $entries;
    protected Collection $reportRows;

    public function __construct(
        protected array $payload,
        protected object $user,
        protected string $sheetTitle = 'Usuario'
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
            'Código(s) de barras',
            'Categoría',
            'Nombre del producto',
            'Stock actual',
            'Conteo físico',
            'Caducado',
            'Dañado',
            'Exhibido',
            'Observaciones',
        ];
    }

    protected function rows(): array
    {
        $entriesByProduct = $this->entries
            ->groupBy(fn ($entry) => $entry->physical_count_id . ':' . $entry->branch_product_id);

        // La hoja individual debe mostrar únicamente los productos que este
        // usuario contó; los productos pendientes pertenecen al concentrado.
        return $this->reportRows
            ->filter(function (array $row) use ($entriesByProduct) {
                $key = ($row['physical_count_id'] ?? 0) . ':' . ($row['branch_product_id'] ?? 0);

                return $entriesByProduct->get($key, collect())->isNotEmpty();
            })
            ->map(function (array $row) use ($entriesByProduct) {
                $key = ($row['physical_count_id'] ?? 0) . ':' . ($row['branch_product_id'] ?? 0);
                $group = $entriesByProduct->get($key, collect());
                $counted = (float) $group->sum('counted_quantity');
                $damaged = (float) $group->sum('damaged_quantity');
                $expired = (float) $group->sum('expired_quantity');
                $usableStock = max(0, $counted - $damaged - $expired);
                $observations = $group
                    ->pluck('notes')
                    ->filter(fn ($note) => filled($note))
                    ->map(fn ($note) => trim((string) $note))
                    ->unique()
                    ->join('; ');

                return [
                    PhysicalCountBarcodeList::fromRow($row),
                    $row['category_name'] ?? 'Sin categoría',
                    $row['product_name'] ?? 'Sin producto',
                    (float) ($row['system_stock'] ?? 0),
                    $counted,
                    $expired > 0 ? $expired : null,
                    $damaged > 0 ? $damaged : null,
                    $usableStock > 0 ? 'Sí' : 'No',
                    $observations ?: 'Sin observaciones',
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
            'A' => NumberFormat::FORMAT_TEXT,
            'D:G' => '#,##0.00',
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $highestRow = $sheet->getHighestRow();

                $sheet->setAutoFilter("A1:I{$highestRow}");
                $sheet->freezePane('A2');
                $sheet->setShowGridlines(false);
                $sheet->getStyle("A1:I{$highestRow}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
                $sheet->getStyle("A1:I{$highestRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
                $sheet->getStyle('A1:I1')->getAlignment()->setWrapText(true);
                $sheet->getStyle("H2:H{$highestRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                for ($row = 2; $row <= $highestRow; $row++) {
                    $sheet->getCell("A{$row}")->setValueExplicit((string) $sheet->getCell("A{$row}")->getValue(), DataType::TYPE_STRING);
                }

                foreach (range('A', 'I') as $column) {
                    $sheet->getColumnDimension($column)->setWidth(match ($column) {
                        'A' => 24,
                        'B' => 24,
                        'C' => 44,
                        'H' => 14,
                        'I' => 42,
                        default => 16,
                    });
                }

                $sheet->getPageSetup()->setOrientation('landscape')->setFitToWidth(1)->setFitToHeight(0);
                $sheet->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd(1, 1);
                $sheet->getHeaderFooter()->setOddFooter('&LSuper Kay&C' . $this->sheetTitle . '&RGenerado: &D &T');
            },
        ];
    }

    public function title(): string
    {
        return $this->sheetTitle;
    }

}
