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
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Conditional;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PhysicalCountUserSheet implements FromArray, ShouldAutoSize, WithColumnFormatting, WithEvents, WithStyles, WithTitle
{
    protected Collection $entries;
    protected Collection $reportRows;

    public function __construct(
        protected array $payload,
        protected object $user,
        protected string $sourceSheetTitle = 'Concentrado'
    ) {
        $this->entries = collect($payload['entries'] ?? [])
            ->where('user_id', $user->id)
            ->values();
        $this->reportRows = collect($payload['reportRows'] ?? [])
            ->where('row_type', 'counted')
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
            'Dañado',
            'Caducado',
            'NO EXHIBIDO',
        ];
    }

    protected function rows(): array
    {
        $entriesByProduct = $this->entries->groupBy('branch_product_id');

        return $this->reportRows
            ->map(function (array $row, int $index) use ($entriesByProduct) {
                $sheetRow = $index + 2;
                $group = $entriesByProduct->get($row['branch_product_id'] ?? null, collect());
                $counted = (float) $group->sum('counted_quantity');
                $damaged = (float) $group->sum('damaged_quantity');
                $expired = (float) $group->sum('expired_quantity');

                return [
                    $counted > 0 ? '☑' : '☐',
                    "='{$this->escapedSourceSheetTitle()}'!B{$sheetRow}",
                    "='{$this->escapedSourceSheetTitle()}'!C{$sheetRow}",
                    "='{$this->escapedSourceSheetTitle()}'!D{$sheetRow}",
                    $counted > 0 ? $counted : null,
                    $damaged > 0 ? $damaged : null,
                    $expired > 0 ? $expired : null,
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
                'font' => ['bold' => false, 'color' => ['rgb' => 'FFFFFF']],
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
                $sheet->getStyle("A1:H{$highestRow}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
                $sheet->getStyle('A1:H1')->getAlignment()->setWrapText(true);
                $sheet->getStyle("A1:H{$highestRow}")->getBorders()->getAllBorders()->setBorderStyle('thin');
                $sheet->getStyle("A2:A{$highestRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("H2:H{$highestRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("A2:A{$highestRow}")->getFont()->setName('Segoe UI Symbol')->setSize(12);
                $sheet->getStyle("H2:H{$highestRow}")->getFont()->setName('Segoe UI Symbol')->setSize(12);
                $sheet->getStyle("D2:G{$highestRow}")->getNumberFormat()->setFormatCode('#,##0');
                $sheet->getColumnDimension('A')->setWidth(15);
                $sheet->getColumnDimension('B')->setWidth(21.25);
                $sheet->getColumnDimension('C')->setWidth(52.38);
                $sheet->getColumnDimension('D')->setWidth(7.75);
                $sheet->getColumnDimension('E')->setWidth(10);
                $sheet->getColumnDimension('F')->setWidth(7);
                $sheet->getColumnDimension('G')->setWidth(7);
                $sheet->getColumnDimension('H')->setWidth(15);

                $this->applyCheckboxValidation($sheet, $highestRow);
                $this->applyCheckedRowHighlight($sheet, $highestRow);
            },
        ];
    }

    public function title(): string
    {
        $title = preg_replace('/[\\\\\\/?*\\[\\]:]/', '', (string) $this->user->name);
        $title = trim($title) ?: 'Usuario';

        return mb_substr($title, 0, 31);
    }

    protected function escapedSourceSheetTitle(): string
    {
        return str_replace("'", "''", $this->sourceSheetTitle);
    }

    protected function applyCheckboxValidation(Worksheet $sheet, int $highestRow): void
    {
        if ($highestRow < 2) {
            return;
        }

        foreach (['A', 'H'] as $column) {
            for ($row = 2; $row <= $highestRow; $row++) {
                $validation = $sheet->getCell("{$column}{$row}")->getDataValidation();
                $validation->setType(DataValidation::TYPE_LIST);
                $validation->setErrorStyle(DataValidation::STYLE_STOP);
                $validation->setAllowBlank(false);
                $validation->setShowDropDown(true);
                $validation->setShowInputMessage(true);
                $validation->setFormula1('"☐,☑"');
                $validation->setPromptTitle('Marcar producto');
                $validation->setPrompt('Selecciona ☑ cuando el producto ya este contado o identificado.');
                $validation->setErrorTitle('Valor no valido');
                $validation->setError('Selecciona unicamente ☐ o ☑.');
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
        $checkedRow->addCondition('OR($A2="☑",$H2="☑")');
        $checkedRow->getStyle()
            ->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()
            ->setRGB('DBEAFE');
        $checkedRow->getStyle()->getFont()->getColor()->setRGB('1E3A8A');

        $sheet->getStyle("A2:H{$highestRow}")->setConditionalStyles([$checkedRow]);
    }
}
