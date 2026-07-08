<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TicketTemplate extends Model
{
    private const LEGACY_SETTING_KEYS = [
        'logo_path',
        'logo_url',
        'logo_enabled',
        'logo_width_percent',
    ];

    protected $fillable = [
        'name',
        'slug',
        'is_active',
        'settings',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'settings' => 'array',
    ];

    public static function defaultSettings(): array
    {
        return [
            'paper_width' => 48,
            'print_engine' => 'raw',
            'feed_lines' => 1,
            'auto_cut' => true,
            'open_cash_drawer' => true,
            'header_text' => 'SUPER KAY',
            'subheader_text' => 'TICKET DE VENTA',
            'cash_box_text' => 'CAJA',
            'footer_text' => 'Gracias por tu compra',
            'show_dividers' => true,
            'blocks' => [
                ['key' => 'cash_box', 'enabled' => true, 'position_percent' => 100, 'size_percent' => 104],
                ['key' => 'brand_title', 'enabled' => true, 'position_percent' => 50, 'size_percent' => 118],
                ['key' => 'divider_header', 'enabled' => true, 'position_percent' => 0, 'size_percent' => 90],
                ['key' => 'folio', 'enabled' => true, 'position_percent' => 0, 'size_percent' => 104],
                ['key' => 'date', 'enabled' => true, 'position_percent' => 0, 'size_percent' => 104],
                ['key' => 'divider_folio', 'enabled' => true, 'position_percent' => 0, 'size_percent' => 90],
                ['key' => 'document_title', 'enabled' => true, 'position_percent' => 50, 'size_percent' => 104],
                ['key' => 'seller_user', 'enabled' => true, 'position_percent' => 0, 'size_percent' => 90],
                ['key' => 'payment_method', 'enabled' => false, 'position_percent' => 0, 'size_percent' => 90],
                ['key' => 'divider_items', 'enabled' => true, 'position_percent' => 0, 'size_percent' => 90],
                ['key' => 'items', 'enabled' => true, 'position_percent' => 0, 'size_percent' => 90],
                ['key' => 'divider_totals', 'enabled' => true, 'position_percent' => 0, 'size_percent' => 90],
                ['key' => 'totals', 'enabled' => true, 'position_percent' => 0, 'size_percent' => 100],
                ['key' => 'divider_footer', 'enabled' => true, 'position_percent' => 0, 'size_percent' => 90],
                ['key' => 'footer_text', 'enabled' => true, 'position_percent' => 50, 'size_percent' => 104],
            ],
        ];
    }

    public static function salesTemplate(): self
    {
        $template = static::query()->firstOrCreate(
            ['slug' => 'sales-ticket'],
            [
                'name' => 'Ticket principal',
                'is_active' => true,
                'settings' => static::defaultSettings(),
            ]
        );

        $normalizedSettings = static::sanitizeSettings(
            static::upgradeLegacySettings($template->settings ?? [])
        );

        if (!$template->settings || $normalizedSettings !== ($template->settings ?? [])) {
            $template->update([
                'settings' => $normalizedSettings,
            ]);
        }

        return $template->fresh();
    }

    public static function sanitizeSettings(array $settings = []): array
    {
        $defaults = static::defaultSettings();
        $allowedBlockKeys = collect($defaults['blocks'])->pluck('key')->all();

        foreach (self::LEGACY_SETTING_KEYS as $key) {
            unset($settings[$key]);
        }

        $incomingBlocks = collect($settings['blocks'] ?? [])
            ->filter(fn ($block) => is_array($block) && in_array($block['key'] ?? null, $allowedBlockKeys, true))
            ->keyBy('key');

        $settings['blocks'] = collect($defaults['blocks'])
            ->map(function (array $defaultBlock) use ($incomingBlocks) {
                $incoming = $incomingBlocks->get($defaultBlock['key'], []);

                return [
                    'key' => $defaultBlock['key'],
                    'enabled' => array_key_exists('enabled', $incoming)
                        ? filter_var($incoming['enabled'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $defaultBlock['enabled']
                        : $defaultBlock['enabled'],
                    'position_percent' => match ($defaultBlock['key']) {
                        'cash_box' => 100,
                        'folio', 'date' => 0,
                        default => (int) ($incoming['position_percent'] ?? $defaultBlock['position_percent']),
                    },
                    'size_percent' => (int) ($incoming['size_percent'] ?? $defaultBlock['size_percent']),
                ];
            })
            ->all();

        return [
            'paper_width' => 48,
            'print_engine' => in_array(($settings['print_engine'] ?? $defaults['print_engine']), ['raw', 'visual'], true)
                ? (string) ($settings['print_engine'] ?? $defaults['print_engine'])
                : $defaults['print_engine'],
            'feed_lines' => 1,
            'auto_cut' => filter_var($settings['auto_cut'] ?? $defaults['auto_cut'], FILTER_VALIDATE_BOOLEAN),
            'open_cash_drawer' => filter_var($settings['open_cash_drawer'] ?? $defaults['open_cash_drawer'], FILTER_VALIDATE_BOOLEAN),
            'header_text' => static::nonBlankText($settings['header_text'] ?? null, $defaults['header_text']),
            'subheader_text' => static::nonBlankText($settings['subheader_text'] ?? null, $defaults['subheader_text']),
            'cash_box_text' => static::nonBlankText($settings['cash_box_text'] ?? null, $defaults['cash_box_text']),
            'footer_text' => static::nonBlankText($settings['footer_text'] ?? null, $defaults['footer_text']),
            'show_dividers' => true,
            'blocks' => $settings['blocks'],
        ];
    }

    private static function upgradeLegacySettings(array $settings): array
    {
        $defaults = static::defaultSettings();
        $legacyBlockSizes = [
            'brand_title' => [132, 145, 160, 172, 180],
            'document_title' => [118, 108, 122, 128, 138],
            'folio' => [108, 100, 112, 114, 120],
            'date' => [108, 110, 112],
            'payment_method' => [108, 110, 112],
            'items' => [102, 100, 106, 108, 112],
            'totals' => [126, 108, 122, 128, 138],
            'footer_text' => [108, 100, 110, 116, 120],
        ];
        $legacyBlockPositions = [
            'date' => [0, 2, 4],
            'payment_method' => [0, 2, 4],
            'items' => [0, 2],
            'totals' => [0, 2],
        ];

        $looksLegacy = (
            (int) ($settings['feed_lines'] ?? 0) < $defaults['feed_lines']
            && !filter_var($settings['auto_cut'] ?? false, FILTER_VALIDATE_BOOLEAN)
        ) || (int) ($settings['paper_width'] ?? 0) !== 48
            || (int) ($settings['feed_lines'] ?? 0) !== $defaults['feed_lines']
            || !filter_var($settings['show_dividers'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $blocks = collect($settings['blocks'] ?? [])
            ->map(function (array $block) use ($defaults, $legacyBlockSizes, $legacyBlockPositions) {
                $defaultBlock = collect($defaults['blocks'])->firstWhere('key', $block['key']);

                if (!$defaultBlock) {
                    return $block;
                }

                $legacySize = $legacyBlockSizes[$block['key']] ?? null;
                $legacyPosition = $legacyBlockPositions[$block['key']] ?? null;

                if ($legacySize !== null && in_array((int) ($block['size_percent'] ?? 0), $legacySize, true)) {
                    $block['size_percent'] = $defaultBlock['size_percent'];
                }

                if ($legacyPosition !== null && in_array((int) ($block['position_percent'] ?? 0), $legacyPosition, true)) {
                    $block['position_percent'] = $defaultBlock['position_percent'];
                }

                return $block;
            })
            ->values()
            ->all();

        if (!$looksLegacy) {
            return [
                ...$settings,
                'blocks' => $blocks,
            ];
        }

        return [
            ...$settings,
            'paper_width' => $defaults['paper_width'],
            'feed_lines' => $defaults['feed_lines'],
            'auto_cut' => $defaults['auto_cut'],
            'show_dividers' => $defaults['show_dividers'],
            'blocks' => $defaults['blocks'],
        ];
    }

    private static function nonBlankText(mixed $value, string $fallback): string
    {
        $text = trim((string) ($value ?? ''));

        return $text === '' ? $fallback : $text;
    }
}
