<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PhysicalCountUserSheet implements FromArray, ShouldAutoSize, WithEvents, WithStyles, WithTitle
{
    protected Collection $entries;

    public function __construct(
        protected array $payload,
        protected object $user
    ) {
        $this->entries = collect($payload['entries'] ?? [])
            ->where('user_id', $user->id)
            ->values();
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
            'Lotes',
            'Ultima captura',
        ];
    }

    protected function rows(): array
    {
        return $this->entries
            ->groupBy('branch_product_id')
            ->map(function (Collection $group) {
                $first = $group->first();
                $branchProduct = $first->branchProduct;
                $product = $branchProduct?->product;
                $batches = $group
                    ->pluck('productBatch.lot_number')
                    ->filter()
                    ->unique()
                    ->values();
                $counted = (float) $group->sum('counted_quantity');

                return [
                    $counted > 0,
                    $first->scanned_code ?: ($branchProduct?->barcode ?? '-'),
                    $product?->name ?? 'Sin producto',
                    (float) ($branchProduct?->stock ?? 0),
                    $counted,
                    (float) $group->sum('expired_quantity'),
                    false,
                    $batches->isEmpty() ? 'Sin lote' : $batches->join(', '),
                    optional($group->sortByDesc('created_at')->first()?->created_at)->format('d/m/Y H:i'),
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

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $highestRow = $sheet->getHighestRow();

                $sheet->setAutoFilter("A1:I{$highestRow}");
                $sheet->freezePane('A2');
                $sheet->getStyle("A1:I{$highestRow}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
                $sheet->getStyle("A1:I1")->getAlignment()->setWrapText(true);
                $sheet->getColumnDimension('C')->setWidth(45);
                $sheet->getColumnDimension('H')->setWidth(28);
                $sheet->getColumnDimension('I')->setWidth(18);
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
