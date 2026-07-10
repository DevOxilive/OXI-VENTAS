<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PhysicalCountConcentratedSheet implements FromArray, ShouldAutoSize, WithEvents, WithStyles, WithTitle
{
    protected Collection $entries;

    public function __construct(
        protected array $payload,
        protected Collection $users
    ) {
        $this->entries = collect($payload['entries'] ?? []);
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
        $headings = [
            'Codigo de barras',
            'Descripcion Producto',
            'Stock Actual',
        ];

        foreach ($this->users as $user) {
            $headings[] = 'Conteo Fisico ' . $user->name;
            $headings[] = 'Caducado detec. ' . $user->name;
        }

        return [
            ...$headings,
            'Stock.Nuevo',
            'Diferencia',
            'Resultado',
            'Usuarios',
        ];
    }

    protected function rows(): array
    {
        $entriesByProductUser = $this->entries
            ->groupBy(fn ($entry) => $entry->branch_product_id . ':' . $entry->user_id)
            ->map(fn ($group) => [
                'counted' => (float) $group->sum('counted_quantity'),
                'expired' => (float) $group->sum('expired_quantity'),
            ]);

        return collect($this->payload['reportRows'] ?? [])
            ->map(function (array $row) use ($entriesByProductUser) {
                $counted = (float) ($row['counted_stock'] ?? 0);
                $damaged = (float) ($row['damaged_stock'] ?? 0);
                $expired = (float) ($row['expired_stock'] ?? 0);
                $newStock = $row['row_type'] === 'pending'
                    ? 'S/D'
                    : max(0, $counted - $damaged - $expired);

                $line = [
                    $row['scanned_code'] ?? '-',
                    $row['product_name'] ?? 'Sin producto',
                    (float) ($row['system_stock'] ?? 0),
                ];

                foreach ($this->users as $user) {
                    $key = ($row['branch_product_id'] ?? 0) . ':' . $user->id;
                    $userCount = $entriesByProductUser->get($key, ['counted' => '', 'expired' => '']);

                    $line[] = $userCount['counted'] === 0.0 ? '' : $userCount['counted'];
                    $line[] = $userCount['expired'] === 0.0 ? '' : $userCount['expired'];
                }

                return [
                    ...$line,
                    $newStock,
                    $row['difference'] ?? '',
                    $row['status_label'] ?? 'Pendiente',
                    implode(', ', $row['participants'] ?? []),
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
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '111827']],
            ],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $highestRow = $sheet->getHighestRow();
                $highestColumn = $sheet->getHighestColumn();

                $sheet->setAutoFilter("A1:{$highestColumn}{$highestRow}");
                $sheet->freezePane('A2');
                $sheet->getStyle("A1:{$highestColumn}1")->getAlignment()->setWrapText(true);
                $sheet->getStyle("A1:{$highestColumn}{$highestRow}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);

                for ($column = 1; $column <= Coordinate::columnIndexFromString($highestColumn); $column++) {
                    $letter = Coordinate::stringFromColumnIndex($column);
                    $sheet->getColumnDimension($letter)->setWidth($column === 2 ? 44 : 16);
                }
            },
        ];
    }

    public function title(): string
    {
        return 'Concentrado';
    }
}
