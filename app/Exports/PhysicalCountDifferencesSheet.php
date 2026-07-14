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

class PhysicalCountDifferencesSheet implements FromArray, ShouldAutoSize, WithColumnFormatting, WithEvents, WithStyles, WithTitle
{
    protected Collection $rows;

    public function __construct(protected array $payload)
    {
        $this->rows = collect($payload['reportRows'] ?? [])
            ->filter(fn (array $row) => ($row['row_type'] ?? null) === 'counted')
            ->filter(fn (array $row) => abs((float) ($row['difference'] ?? 0)) > 0)
            ->sortByDesc(fn (array $row) => abs((float) ($row['difference'] ?? 0)))
            ->values();
    }

    public function array(): array
    {
        return [
            $this->headings(),
            ...$this->rows
                ->map(function (array $row, int $index) {
                    $sheetRow = $index + 2;

                    return [
                        $row['branch_name'] ?? 'Sin sucursal',
                        $row['audit_name'] ?? 'Sin auditoria',
                        $row['folio'] ?? 'Sin folio',
                        $row['product_name'] ?? 'Sin producto',
                        $row['category_name'] ?? 'Sin categoria',
                        $row['scanned_code'] ?? '-',
                        (float) ($row['system_stock'] ?? 0),
                        "=I{$sheetRow}-J{$sheetRow}",
                        (float) ($row['counted_stock'] ?? 0),
                        (float) ($row['expired_stock'] ?? 0),
                        "=H{$sheetRow}-G{$sheetRow}",
                        $row['status_label'] ?? $this->statusLabel($row['status'] ?? ''),
                        implode(', ', $row['participants'] ?? []),
                        $row['audit_date'] ?? '',
                    ];
                })
                ->all(),
        ];
    }

    public function headings(): array
    {
        return [
            'Sucursal',
            'Auditoria',
            'Folio',
            'Producto',
            'Categoria',
            'Codigo',
            'Stock Actual',
            'Stock.Nuevo',
            'Conteo Fisico',
            'Caducado',
            'Diferencia',
            'Resultado',
            'Usuarios',
            'Fecha',
        ];
    }

    public function columnFormats(): array
    {
        return [
            'F' => NumberFormat::FORMAT_TEXT,
            'G' => '#,##0.00',
            'H' => '#,##0.00',
            'I' => '#,##0.00',
            'J' => '#,##0.00',
            'K' => '#,##0.00',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '7C2D12']],
            ],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $highestRow = $sheet->getHighestRow();

                $sheet->setAutoFilter("A1:N{$highestRow}");
                $sheet->freezePane('A2');
                $sheet->getTabColor()->setRGB('EA580C');
                $sheet->getStyle("A1:N{$highestRow}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
                $sheet->getStyle('A1:N1')->getAlignment()->setWrapText(true);
                $sheet->setShowGridlines(false);

                $this->applyResultColors($sheet);
            },
        ];
    }

    public function title(): string
    {
        return 'Diferencias';
    }

    protected function applyResultColors(Worksheet $sheet): void
    {
        $this->rows->each(function (array $row, int $index) use ($sheet) {
            $sheetRow = $index + 2;
            $color = match ($row['status'] ?? null) {
                'missing' => 'FFEDD5',
                'surplus' => 'FEF9C3',
                default => null,
            };

            if (! $color) {
                return;
            }

            $sheet->getStyle("A{$sheetRow}:N{$sheetRow}")
                ->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()
                ->setRGB($color);
        });
    }

    protected function statusLabel(string $status): string
    {
        return match ($status) {
            'missing' => 'Faltante',
            'surplus' => 'Sobrante',
            'matched' => 'Macheado',
            default => 'Pendiente',
        };
    }
}
