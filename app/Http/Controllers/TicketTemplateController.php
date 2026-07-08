<?php

namespace App\Http\Controllers;

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

    public function update(Request $request, TicketTemplate $ticketTemplate)
    {
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

        $ticketTemplate->update([
            'name' => $data['name'],
            'is_active' => $data['is_active'],
            'settings' => TicketTemplate::sanitizeSettings($data['settings']),
        ]);

        return back()->with('success', 'Plantilla de ticket actualizada correctamente.');
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
}
