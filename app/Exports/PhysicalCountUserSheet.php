<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Conditional;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PhysicalCountUserSheet implements FromArray, WithColumnFormatting, WithEvents, WithStyles, WithTitle
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
            'Check',
            'Auditoría',
            'Folio',
            'Código de barras',
            'Descripción del producto',
            'Categoría',
            'Stock inicial',
            'Conteo físico',
            'Dañado',
            'Caducado',
            'Diferencia',
            'Resultado',
            'No exhibido',
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
                $newStock = $group->isEmpty() ? null : max(0, $counted - $damaged - $expired);
                $difference = $newStock === null ? null : $newStock - (float) ($row['system_stock'] ?? 0);

                return [
                    $group->isNotEmpty() ? '☑' : '☐',
                    $row['audit_name'] ?? 'Sin auditoría',
                    $row['folio'] ?? 'Sin folio',
                    $row['scanned_code'] ?? '-',
                    $row['product_name'] ?? 'Sin producto',
                    $row['category_name'] ?? 'Sin categoría',
                    (float) ($row['system_stock'] ?? 0),
                    $group->isNotEmpty() ? $counted : null,
                    $damaged > 0 ? $damaged : null,
                    $expired > 0 ? $expired : null,
                    $difference,
                    $group->isEmpty()
                        ? 'No contado por usuario'
                        : ($difference < 0 ? 'Faltante' : ($difference > 0 ? 'Sobrante' : 'Coincidente')),
                    '☐',
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
            'D' => NumberFormat::FORMAT_TEXT,
            'G:K' => '#,##0.00',
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $highestRow = $sheet->getHighestRow();

                $sheet->setAutoFilter("A1:M{$highestRow}");
                $sheet->freezePane('A2');
                $sheet->setShowGridlines(false);
                $this->writeExecutiveSummary($sheet, $highestRow);
                $sheet->getStyle("A1:M{$highestRow}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
                $sheet->getStyle("A1:M{$highestRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
                $sheet->getStyle('A1:M1')->getAlignment()->setWrapText(true);
                $sheet->getStyle("A2:A{$highestRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("M2:M{$highestRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("A2:A{$highestRow}")->getFont()->setName('Segoe UI Symbol')->setSize(12);
                $sheet->getStyle("M2:M{$highestRow}")->getFont()->setName('Segoe UI Symbol')->setSize(12);
                for ($row = 2; $row <= $highestRow; $row++) {
                    $sheet->getCell("D{$row}")->setValueExplicit((string) $sheet->getCell("D{$row}")->getValue(), DataType::TYPE_STRING);
                }

                foreach (range('A', 'M') as $column) {
                    $sheet->getColumnDimension($column)->setWidth(match ($column) {
                        'A', 'M' => 13,
                        'B' => 28,
                        'C' => 17,
                        'D' => 22,
                        'E' => 42,
                        'F' => 24,
                        'L' => 24,
                        default => 14,
                    });
                }

                $sheet->getPageSetup()->setOrientation('landscape')->setFitToWidth(1)->setFitToHeight(0);
                $sheet->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd(1, 1);
                $sheet->getHeaderFooter()->setOddFooter('&LSuper Kay&C' . $this->sheetTitle . '&RGenerado: &D &T');

                $this->applyCheckboxValidation($sheet, $highestRow);
                $this->applyCheckedRowHighlight($sheet, $highestRow);
            },
        ];
    }

    protected function writeExecutiveSummary(Worksheet $sheet, int $highestRow): void
    {
        $dataRows = max(0, $highestRow - 1);
        $sheet->setCellValue('O1', 'SUPER KAY | RESUMEN DE USUARIO');
        $sheet->setCellValue('O2', 'Usuario');
        $sheet->setCellValue('P2', (string) ($this->user->name ?? 'Usuario'));
        $sheet->setCellValue('O3', 'Fecha de exportación');
        $sheet->setCellValue('P3', now()->format('d/m/Y H:i'));
        $sheet->setCellValue('O4', 'Productos contados');
        $sheet->setCellValue('P4', "=COUNT(H2:H{$highestRow})");
        $sheet->setCellValue('O5', 'Faltantes / Sobrantes');
        $sheet->setCellValue('P5', "=COUNTIF(L2:L{$highestRow},\"Faltante\")+COUNTIF(L2:L{$highestRow},\"Sobrante\")");
        $sheet->setCellValue('O6', 'Coincidentes');
        $sheet->setCellValue('P6', "=COUNTIF(L2:L{$highestRow},\"Coincidente\")");
        $sheet->mergeCells('O1:P1');
        $sheet->getStyle('O1:P1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('111827');
        $sheet->getStyle('O1:P1')->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle('O2:O6')->getFont()->setBold(true);
        $sheet->getStyle('O1:P6')->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle('O1:P6')->getBorders()->getAllBorders()->getColor()->setRGB('D1D5DB');
        $sheet->getColumnDimension('O')->setWidth(24);
        $sheet->getColumnDimension('P')->setWidth(24);
    }

    public function title(): string
    {
        return $this->sheetTitle;
    }

    protected function applyCheckboxValidation(Worksheet $sheet, int $highestRow): void
    {
        if ($highestRow < 2) {
            return;
        }

        foreach (['A', 'M'] as $column) {
            for ($row = 2; $row <= $highestRow; $row++) {
                $validation = $sheet->getCell("{$column}{$row}")->getDataValidation();
                $validation->setType(DataValidation::TYPE_LIST);
                $validation->setErrorStyle(DataValidation::STYLE_STOP);
                $validation->setAllowBlank(false);
                $validation->setShowDropDown(true);
                $validation->setShowInputMessage(true);
                $validation->setFormula1('"☐,☑"');
                $validation->setPromptTitle('Marcar producto');
                $validation->setPrompt('Selecciona ☑ cuando el producto ya esté contado o identificado.');
                $validation->setErrorTitle('Valor no válido');
                $validation->setError('Selecciona únicamente ☐ o ☑.');
            }
        }
    }

    protected function applyCheckedRowHighlight(Worksheet $sheet, int $highestRow): void
    {
        if ($highestRow < 2) {
            return;
        }

        $checkedRow = new Conditional();
        $checkedRow->setConditionType(Conditional::CONDITION_EXPRESSION);
        $checkedRow->addCondition('OR($A2="☑",$M2="☑")');
        $checkedRow->getStyle()
            ->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()
            ->setRGB('DBEAFE');
        $checkedRow->getStyle()->getFont()->getColor()->setRGB('1E3A8A');

        $sheet->getStyle("A2:M{$highestRow}")->setConditionalStyles([$checkedRow]);
    }
}
