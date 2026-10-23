<?php

namespace Tests\Unit;

use App\Models\TicketTemplate;
use Tests\TestCase;

class PrinterTemplateSettingsTest extends TestCase
{
    public function test_label_dimensions_and_text_size_survive_normalization(): void
    {
        $settings = TicketTemplate::sanitizeLabelSettings([
            'label_width_mm' => 100,
            'label_height_mm' => 150,
            'blocks' => [['key' => 'price', 'size_percent' => 800]],
        ]);
        $this->assertSame(100, $settings['label_width_mm']);
        $this->assertSame(150, $settings['label_height_mm']);
        $this->assertSame(800, $settings['blocks'][0]['size_percent']);
        $this->assertSame($settings, TicketTemplate::sanitizeLabelSettings($settings));
    }

    public function test_ticket_preserves_narrow_and_custom_formats(): void
    {
        foreach ([32, 42, 48, 56] as $columns) {
            $settings = TicketTemplate::sanitizeSettings([
                'paper_width' => $columns,
                'feed_lines' => 5,
                'show_dividers' => false,
                'open_cash_drawer' => false,
            ]);
            $this->assertSame($columns, $settings['paper_width']);
            $this->assertSame(5, $settings['feed_lines']);
            $this->assertFalse($settings['show_dividers']);
            $this->assertFalse($settings['open_cash_drawer']);
            $this->assertSame($settings, TicketTemplate::sanitizeSettings($settings));
        }
    }

    public function test_label_values_are_bounded_and_defaults_match_the_editor(): void
    {
        $defaults = TicketTemplate::defaultLabelSettings();
        $this->assertSame(62, $defaults['label_width_mm']);
        $this->assertSame(29, $defaults['label_height_mm']);
        $settings = TicketTemplate::sanitizeLabelSettings(['label_width_mm' => -5, 'label_height_mm' => 999]);
        $this->assertSame(20, $settings['label_width_mm']);
        $this->assertSame(300, $settings['label_height_mm']);
    }
}
