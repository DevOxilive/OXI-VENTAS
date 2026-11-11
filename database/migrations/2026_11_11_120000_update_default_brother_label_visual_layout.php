<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $template = DB::table('ticket_templates')
            ->where('slug', 'visual-v2-label')
            ->first();

        if (! $template) {
            return;
        }

        $settings = json_decode((string) $template->settings, true);

        if (! is_array($settings)) {
            return;
        }

        $updated = false;

        foreach (['draft', 'published'] as $key) {
            if (! $this->isPreviousSystemDefault($settings[$key] ?? null)) {
                continue;
            }

            $settings[$key] = $this->brotherDefault();
            $updated = true;
        }

        // Solo se actualiza la plantilla que todavía coincide con el valor base
        // anterior. Un diseño ajustado por Sistemas se conserva intacto.
        if ($updated) {
            DB::table('ticket_templates')
                ->where('id', $template->id)
                ->update([
                    'settings' => json_encode($settings, JSON_UNESCAPED_UNICODE),
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        // No se revierte para evitar reemplazar un diseño que pudo editarse después.
    }

    private function isPreviousSystemDefault(mixed $layout): bool
    {
        if (! is_array($layout)
            || ($layout['version'] ?? null) !== 2
            || (int) ($layout['dpi'] ?? 0) !== 300
            || (float) ($layout['width'] ?? 0) !== 62.0
            || (float) ($layout['height'] ?? 0) !== 40.0
            || ($layout['auto_height'] ?? null) !== false) {
            return false;
        }

        $elements = $layout['elements'] ?? [];

        return count($elements) === 3
            && ($elements[0]['type'] ?? null) === 'field'
            && ($elements[0]['binding'] ?? null) === 'name'
            && ($elements[1]['type'] ?? null) === 'field'
            && ($elements[1]['binding'] ?? null) === 'price'
            && ($elements[2]['type'] ?? null) === 'barcode';
    }

    private function brotherDefault(): array
    {
        return [
            'version' => 2,
            'paper_format' => 'continuous-62',
            'dpi' => 300,
            'width' => 62,
            'height' => 29,
            'auto_height' => false,
            'elements' => [
                [
                    'id' => 'label-name',
                    'type' => 'field',
                    'binding' => 'name',
                    'text' => '',
                    'x' => 2,
                    'y' => 3,
                    'width' => 58,
                    'height' => 9,
                    'font_size' => 11,
                    'bold' => true,
                    'align' => 'center',
                    'fill' => '#000000',
                    'stroke' => 0,
                    'visible' => true,
                    'locked' => false,
                ],
                [
                    'id' => 'label-price',
                    'type' => 'field',
                    'binding' => 'price',
                    'text' => '',
                    'x' => 2,
                    'y' => 14,
                    'width' => 58,
                    'height' => 10,
                    'font_size' => 19,
                    'bold' => true,
                    'align' => 'center',
                    'fill' => '#000000',
                    'stroke' => 0,
                    'visible' => true,
                    'locked' => false,
                ],
            ],
        ];
    }
};
