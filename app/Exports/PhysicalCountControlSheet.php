<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PhysicalCountControlSheet implements FromArray, ShouldAutoSize, WithEvents, WithStyles, WithTitle
{
    public function __construct(
        protected array $payload,
        protected array $filterLabels,
        protected string $branchName
    ) {}

    public function array(): array
    {
        $audits = collect($this->payload['audits'] ?? []);
        $reportRows = collect($this->payload['reportRows'] ?? []);
        $entries = collect($this->payload['entries'] ?? []);
        $countedRows = $reportRows->where('row_type', 'counted');
        $countedProducts = $countedRows->count();
        $summary = [
            'total_products' => $reportRows->count(),
            'counted_products' => $countedProducts,
            'pending_products' => $reportRows->where('row_type', 'pending')->count(),
            'records' => $entries->count(),
            'participants' => $entries->pluck('user_id')->filter()->unique()->count(),
            'accuracy' => $countedProducts > 0 ? $countedRows->where('status', 'matched')->count() / $countedProducts : 0,
        ];

        $rows = [
            ['REPORTE DE AUDITORÍA DE INVENTARIO', null, null, null],
            ['Control de generación y criterios aplicados', null, null, null],
            [null, null, null, null],
            ['Dato de control', 'Valor', 'Dato de control', 'Valor'],
            ['Generado por', $this->filterLabels['generated_by'] ?? 'Usuario autenticado', 'Generado el', now()->format('d/m/Y H:i:s')],
            ['Sucursal', $this->branchName, 'Auditorías incluidas', $audits->count()],
            ['Auditoría', $this->filterLabels['audit'] ?? 'Todas', 'Productos exportados', (int) ($summary['total_products'] ?? 0)],
            ['Usuario(s)', $this->filterLabels['user'] ?? 'Todos', 'Productos contados', (int) ($summary['counted_products'] ?? 0)],
            ['Categoría', $this->filterLabels['category'] ?? 'Todas', 'Productos pendientes', (int) ($summary['pending_products'] ?? 0)],
            ['Resultado', $this->filterLabels['status'] ?? 'Todos', 'Registros de conteo', (int) ($summary['records'] ?? 0)],
            ['Fecha de auditoría', $this->filterLabels['report_date'] ?? 'Sin fecha', 'Participantes', (int) ($summary['participants'] ?? 0)],
            ['Búsqueda', $this->filterLabels['search'] ?? 'Sin filtro', 'Exactitud', (float) ($summary['accuracy'] ?? 0)],
            [null, null, null, null],
            ['Auditorías incluidas', null, null, null],
            ['Sucursal', 'Auditoría', 'Folio', 'Estado / periodo'],
        ];

        foreach ($audits as $audit) {
            $status = match ($audit->status ?? null) {
                'open' => 'Abierta',
                'closed' => 'Cerrada',
                'applied' => 'Aplicada',
                default => $audit->status ?? 'Sin estado',
            };
            $rows[] = [
                $audit->branch?->name ?? 'Sin sucursal',
                $audit->name ?? 'Sin auditoría',
                $audit->folio ?? 'Sin folio',
                trim($status . ' | ' . optional($audit->started_at)->format('d/m/Y H:i') . ' - ' . (optional($audit->closed_at)->format('d/m/Y H:i') ?: 'Abierta')),
            ];
        }

        return $rows;
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true, 'size' => 18, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '3F2A66']]],
            2 => ['font' => ['italic' => true, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '3F2A66']]],
            4 => ['font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '5B3F86']]],
            13 => ['font' => ['bold' => true, 'size' => 14]],
            14 => ['font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '5B3F86']]],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $highestRow = $sheet->getHighestRow();
                $sheet->mergeCells('A1:D1');
                $sheet->mergeCells('A2:D2');
                $sheet->mergeCells('A13:D13');
                $sheet->freezePane('A14');
                $sheet->setShowGridlines(false);
                $sheet->getStyle("A1:D{$highestRow}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
                $sheet->getStyle("A4:D{$highestRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
                $sheet->getStyle('D12')->getNumberFormat()->setFormatCode('0.00%');
                $sheet->getColumnDimension('A')->setWidth(24);
                $sheet->getColumnDimension('B')->setWidth(46);
                $sheet->getColumnDimension('C')->setWidth(24);
                $sheet->getColumnDimension('D')->setWidth(42);
                $sheet->getPageSetup()->setFitToWidth(1)->setFitToHeight(0);
            },
        ];
    }

    public function title(): string
    {
        return 'Control y filtros';
    }
}
