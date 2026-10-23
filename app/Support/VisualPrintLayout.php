<?php

namespace App\Support;

use App\Models\TicketTemplate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class VisualPrintLayout
{
    public const KINDS = ['sales', 'statement', 'closure', 'sale-return', 'credit-payment', 'label'];
    public const FIELDS = ['name', 'price', 'barcode', 'category', 'sku', 'stock', 'branch', 'date', 'folio', 'branch_name', 'user_name', 'cash_box_text', 'document_title', 'total', 'cash_received', 'change_due', 'reason', 'original_sale_folio', 'balance_before', 'balance_after', 'payment_amount', 'account_balance_before', 'account_balance_after', 'payment_method', 'expected_cash', 'counted_cash', 'cash_left', 'cash_withdrawal', 'cash_difference', 'card_total', 'counted_card', 'card_difference', 'sales_count', 'sales_total', 'notes'];

    public static function validate(array $layout): array
    {
        return Validator::make($layout, [
            'version' => ['required', 'integer', 'in:2'],
            'paper_format' => ['sometimes', Rule::in(['receipt-80', 'receipt-58', 'continuous-62', 'custom'])],
            'dpi' => ['sometimes', 'integer', 'in:203,300'],
            'width' => ['required', 'numeric', 'min:20', 'max:210'],
            'height' => ['required', 'numeric', 'min:12', 'max:500'],
            'auto_height' => ['required', 'boolean'],
            'elements' => ['required', 'array', 'min:1', 'max:100'],
            'elements.*' => ['array:id,type,x,y,width,height,text,binding,font_size,bold,align,fill,stroke,visible,locked'],
            'elements.*.id' => ['required', 'string', 'max:80', 'distinct', 'regex:/^[a-zA-Z0-9_-]+$/'],
            'elements.*.type' => ['required', Rule::in(['text', 'field', 'line', 'rect', 'logo', 'items', 'barcode'])],
            'elements.*.x' => ['required', 'numeric', 'min:0', 'max:210'],
            'elements.*.y' => ['required', 'numeric', 'min:0', 'max:500'],
            'elements.*.width' => ['required', 'numeric', 'min:1', 'max:210'],
            'elements.*.height' => ['required', 'numeric', 'min:1', 'max:500'],
            'elements.*.text' => ['present', 'nullable', 'string', 'max:1000'],
            'elements.*.binding' => ['present', 'nullable', Rule::in(self::FIELDS)],
            'elements.*.font_size' => ['required', 'numeric', 'min:4', 'max:72'],
            'elements.*.bold' => ['required', 'boolean'],
            'elements.*.align' => ['required', Rule::in(['left', 'center', 'right'])],
            'elements.*.fill' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'elements.*.stroke' => ['required', 'numeric', 'min:0', 'max:3'],
            'elements.*.visible' => ['required', 'boolean'],
            'elements.*.locked' => ['required', 'boolean'],
        ])->after(function ($validator) use ($layout) {
            $expectedWidth = match ($layout['paper_format'] ?? 'custom') {
                'receipt-80' => 72, 'receipt-58' => 52.5, 'continuous-62' => 62, default => null,
            };
            if ($expectedWidth !== null && abs((float) ($layout['width'] ?? 0) - $expectedWidth) > 0.01) {
                $validator->errors()->add('width', 'El ancho no corresponde al formato de papel seleccionado.');
            }
            foreach ($layout['elements'] ?? [] as $index => $element) {
                if (!is_array($element)) continue;
                if ((float) ($element['x'] ?? 0) + (float) ($element['width'] ?? 0) > (float) ($layout['width'] ?? 0) + 0.01 ||
                    (float) ($element['y'] ?? 0) + (float) ($element['height'] ?? 0) > (float) ($layout['height'] ?? 0) + 0.01) {
                    $validator->errors()->add("elements.$index", 'El elemento debe quedar dentro del papel.');
                }
                if (in_array($element['type'] ?? '', ['field', 'barcode'], true) && empty($element['binding'])) {
                    $validator->errors()->add("elements.$index.binding", 'Selecciona el dato que utilizará el elemento.');
                }
            }
        })->validate();
    }

    public static function record(string $kind): ?TicketTemplate
    {
        return TicketTemplate::query()->where('slug', 'visual-v2-'.$kind)->first();
    }

    public static function revision(?TicketTemplate $record): string
    {
        return $record ? hash('sha256', json_encode([$record->name, $record->settings, $record->is_active, $record->updated_at?->toJSON()])) : '';
    }

    public static function active(string $kind): ?array
    {
        $record = self::record($kind);
        return $record?->is_active ? ($record->settings['published'] ?? null) : null;
    }

    public static function withActive(string $kind, array $settings): array
    {
        $layout = self::active($kind);
        return $layout ? [...$settings, 'visual_layout' => $layout] : $settings;
    }
}
