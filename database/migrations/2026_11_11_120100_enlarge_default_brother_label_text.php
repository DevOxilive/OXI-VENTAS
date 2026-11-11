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
            if (! $this->isCurrentSystemDefault($settings[$key] ?? null)) {
                continue;
            }

            $settings[$key]['elements'][0] = [
                ...$settings[$key]['elements'][0],
                'y' => 2,
                'height' => 8,
                'font_size' => 16,
            ];
            $settings[$key]['elements'][1] = [
                ...$settings[$key]['elements'][1],
                'y' => 12,
                'height' => 14,
                'font_size' => 32,
            ];
            $updated = true;
        }

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
        // No se revierte para no reemplazar cambios posteriores de Sistemas.
    }

    private function isCurrentSystemDefault(mixed $layout): bool
    {
        $elements = is_array($layout) ? ($layout['elements'] ?? []) : [];

        return is_array($layout)
            && ($layout['paper_format'] ?? null) === 'continuous-62'
            && (int) ($layout['dpi'] ?? 0) === 300
            && (float) ($layout['width'] ?? 0) === 62.0
            && (float) ($layout['height'] ?? 0) === 29.0
            && ($layout['auto_height'] ?? null) === false
            && count($elements) === 2
            && ($elements[0]['type'] ?? null) === 'field'
            && ($elements[0]['binding'] ?? null) === 'name'
            && (float) ($elements[0]['x'] ?? 0) === 2.0
            && (float) ($elements[0]['y'] ?? 0) === 3.0
            && (float) ($elements[0]['width'] ?? 0) === 58.0
            && (float) ($elements[0]['height'] ?? 0) === 9.0
            && (int) ($elements[0]['font_size'] ?? 0) === 11
            && ($elements[0]['bold'] ?? null) === true
            && ($elements[0]['align'] ?? null) === 'center'
            && ($elements[1]['type'] ?? null) === 'field'
            && ($elements[1]['binding'] ?? null) === 'price'
            && (float) ($elements[1]['x'] ?? 0) === 2.0
            && (float) ($elements[1]['y'] ?? 0) === 14.0
            && (float) ($elements[1]['width'] ?? 0) === 58.0
            && (float) ($elements[1]['height'] ?? 0) === 10.0
            && (int) ($elements[1]['font_size'] ?? 0) === 19;
    }
};
