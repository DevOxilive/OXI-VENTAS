<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Product;
use App\Models\TicketTemplate;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TicketTemplateController extends Controller
{
    public function index()
    {
        $template = TicketTemplate::salesTemplate();

        return Inertia::render('Printers/Tickets', [
            'template' => [
                'id' => $template->id,
                'name' => $template->name,
                'slug' => $template->slug,
                'is_active' => $template->is_active,
                'settings' => $this->resolvedSettings($template),
            ],
            'samplePrintJob' => $this->samplePrintJob(),
        ]);
    }

    public function labels()
    {
        $template = TicketTemplate::productLabelTemplate();
        $branchId = auth()->user()?->branch_id;
        $branchName = Branch::query()->whereKey($branchId)->value('name') ?? '';

        $products = Product::query()
            ->with([
                'category:id,name',
                'barcodes' => fn ($query) => $query
                    ->where('active', true)
                    ->orderBy('id'),
                'branchInventories' => fn ($query) => $query
                    ->when($branchId, fn ($branchQuery) => $branchQuery->where('branch_id', $branchId)),
            ])
            ->orderBy('name')
            ->get()
            ->map(function (Product $product) use ($branchName) {
                $barcode = $product->barcodes->first()?->code ?? '';
                $inventory = $product->branchInventories->first();

                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'category' => $product->category?->name ?? 'Sin categoria',
                    'barcode' => $barcode,
                    'sku' => $barcode,
                    'price' => (float) $product->sale_price,
                    'stock' => (float) ($inventory?->current_stock ?? 0),
                    'branch' => $branchName,
                    'date' => now()->format('d/m/Y'),
                ];
            })
            ->values();

        return Inertia::render('Printers/Labels', [
            'template' => [
                'id' => $template->id,
                'name' => $template->name,
                'slug' => $template->slug,
                'is_active' => $template->is_active,
                'settings' => TicketTemplate::sanitizeLabelSettings($template->settings ?? []),
            ],
            'products' => $products,
            'sampleProduct' => $products->first() ?? $this->sampleProduct(),
        ]);
    }

    public function cashClosures()
    {
        $template = TicketTemplate::cashClosureTemplate();

        return Inertia::render('Printers/Tickets', [
            'template' => [
                'id' => $template->id,
                'name' => $template->name,
                'slug' => $template->slug,
                'is_active' => $template->is_active,
                'settings' => $this->resolvedSettings($template),
            ],
            'samplePrintJob' => $this->sampleCashClosurePrintJob(),
            'templateContext' => [
                'title' => 'Tickets de corte',
                'subtitle' => 'Configura la impresion del cierre de caja con vista previa.',
                'defaultName' => 'Ticket de corte',
                'defaultSubheader' => 'CORTE DE CAJA',
                'defaultFooter' => 'Corte realizado correctamente',
                'fieldLabels' => [
                    'name' => 'Plantilla de corte',
                    'header' => 'Encabezado',
                    'subheader' => 'Titulo del corte',
                    'cashBox' => 'Caja detectada',
                    'footer' => 'Mensaje final',
                ],
                'blockLabels' => [
                    'cash_box' => 'Caja detectada',
                    'brand_title' => 'Encabezado',
                    'divider_header' => 'Linea despues del encabezado',
                    'folio' => 'Folio de corte',
                    'date' => 'Fecha del corte',
                    'divider_folio' => 'Linea despues del folio/fecha',
                    'document_title' => 'Titulo del corte',
                    'seller_user' => 'Usuario',
                    'payment_method' => 'Metodo de pago',
                    'divider_items' => 'Linea antes de denominaciones',
                    'items' => 'Denominaciones',
                    'divider_totals' => 'Linea antes del resumen',
                    'totals' => 'Resumen del corte',
                    'divider_footer' => 'Linea antes del mensaje final',
                    'footer_text' => 'Mensaje final',
                ],
            ],
        ]);
    }

    public function update(Request $request, TicketTemplate $ticketTemplate)
    {
        $routeName = (string) $request->route()?->getName();

        if (str_contains($routeName, 'cash-closure-tickets.')) {
            abort_unless($ticketTemplate->slug === 'cash-closure-ticket', 404);
        } else {
            abort_unless($ticketTemplate->slug === 'sales-ticket', 404);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'is_active' => ['required', 'boolean'],
            'settings' => ['required', 'array'],
            'settings.paper_width' => ['required', 'integer', 'in:32,42,48'],
            'settings.print_engine' => ['required', 'string', 'in:raw,visual'],
            'settings.feed_lines' => ['required', 'integer', 'min:1', 'max:1'],
            'settings.auto_cut' => ['required', 'boolean'],
            'settings.open_cash_drawer' => ['required', 'boolean'],
            'settings.header_text' => ['nullable', 'string', 'max:120'],
            'settings.subheader_text' => ['nullable', 'string', 'max:120'],
            'settings.cash_box_text' => ['nullable', 'string', 'max:80'],
            'settings.footer_text' => ['nullable', 'string', 'max:160'],
            'settings.show_dividers' => ['required', 'boolean'],
            'settings.blocks' => ['required', 'array', 'min:1'],
            'settings.blocks.*.key' => ['required', 'string', 'max:60'],
            'settings.blocks.*.enabled' => ['required', 'boolean'],
            'settings.blocks.*.position_percent' => ['required', 'integer', 'min:0', 'max:100'],
            'settings.blocks.*.size_percent' => ['required', 'integer', 'min:60', 'max:180'],
        ]);

        $defaults = $ticketTemplate->slug === 'cash-closure-ticket'
            ? TicketTemplate::cashClosureDefaultSettings()
            : TicketTemplate::defaultSettings();

        $ticketTemplate->update([
            'name' => $data['name'],
            'is_active' => $data['is_active'],
            'settings' => TicketTemplate::sanitizeSettings($data['settings'], $defaults),
        ]);

        return back()->with('success', 'Plantilla de ticket actualizada correctamente.');
    }

    public function updateLabel(Request $request, TicketTemplate $ticketTemplate)
    {
        abort_unless($ticketTemplate->slug === 'product-label', 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'is_active' => ['required', 'boolean'],
            'settings' => ['required', 'array'],
            'settings.label_width_mm' => ['required', 'integer', 'min:20', 'max:110'],
            'settings.label_height_mm' => ['required', 'integer', 'min:12', 'max:90'],
            'settings.print_engine' => ['required', 'string', 'in:visual'],
            'settings.barcode_height_mm' => ['required', 'integer', 'min:6', 'max:28'],
            'settings.barcode_width_percent' => ['required', 'integer', 'min:45', 'max:100'],
            'settings.show_border' => ['required', 'boolean'],
            'settings.header_text' => ['nullable', 'string', 'max:120'],
            'settings.footer_text' => ['nullable', 'string', 'max:160'],
            'settings.custom_text' => ['nullable', 'string', 'max:240'],
            'settings.blocks' => ['required', 'array', 'min:1'],
            'settings.blocks.*.key' => ['required', 'string', 'max:60'],
            'settings.blocks.*.enabled' => ['required', 'boolean'],
            'settings.blocks.*.position_percent' => ['required', 'integer', 'min:0', 'max:100'],
            'settings.blocks.*.size_percent' => ['required', 'integer', 'min:50', 'max:2000'],
        ]);

        $ticketTemplate->update([
            'name' => $data['name'],
            'is_active' => $data['is_active'],
            'settings' => TicketTemplate::sanitizeLabelSettings($data['settings']),
        ]);

        return back()->with('success', 'Plantilla de etiqueta actualizada correctamente.');
    }

    private function resolvedSettings(TicketTemplate $ticketTemplate): array
    {
        return TicketTemplate::sanitizeSettings($ticketTemplate->settings ?? []);
    }

    private function samplePrintJob(): array
    {
        return [
            'sale_id' => 9999,
            'folio' => 'V-009999',
            'date' => now()->format('d/m/Y H:i'),
            'branch_name' => 'Ajusco',
            'payment_method' => 'Cash',
            'employee_name' => 'Carlos Ramirez',
            'user_name' => 'Usuario de sesion',
            'cash_box_number' => '1',
            'cash_box_text' => 'CAJA #1',
            'total' => 183.50,
            'cash_received' => 200.00,
            'change_due' => 16.50,
            'items' => [
                [
                    'product_name' => 'Cheetos',
                    'quantity' => 2,
                    'unit_price' => 22.50,
                    'subtotal' => 45.00,
                    'discount_percentage' => 0,
                    'discount_amount' => 0,
                ],
                [
                    'product_name' => 'Nebulizador',
                    'quantity' => 1,
                    'unit_price' => 138.50,
                    'subtotal' => 138.50,
                    'discount_percentage' => 0,
                    'discount_amount' => 0,
                ],
            ],
        ];
    }

    private function sampleCashClosurePrintJob(): array
    {
        return [
            'type' => 'cash_closure',
            'folio' => 'CC-20260716-000123',
            'date' => now()->format('d/m/Y H:i'),
            'branch_name' => 'Ajusco',
            'user_name' => 'Usuario de sesion',
            'cash_box_number' => '1',
            'cash_box_text' => 'CAJA #1',
            'sales_count' => 24,
            'sales_total' => 2450.50,
            'expected_cash' => 1450.50,
            'counted_cash' => 1450.50,
            'cash_left' => 500.00,
            'cash_withdrawal' => 950.50,
            'cash_difference' => 0,
            'card_total' => 1000.00,
            'counted_card' => 1000.00,
            'card_difference' => 0,
            'denomination_breakdown' => [
                '500' => 1,
                '200' => 2,
                '100' => 3,
                '50' => 3,
                '20b' => 5,
                '10' => 5,
                '0.5' => 1,
            ],
            'notes' => 'Corte cuadrado sin incidencias.',
        ];
    }

    private function sampleProduct(): array
    {
        return [
            'name' => 'Nebulizador Portatil',
            'category' => 'Equipo medico',
            'barcode' => '7501234567890',
            'sku' => 'NEB-PORT-01',
            'price' => 138.50,
            'stock' => 24,
            'branch' => 'Ajusco',
            'date' => now()->format('d/m/Y'),
        ];
    }
}
