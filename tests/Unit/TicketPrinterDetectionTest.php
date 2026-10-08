<?php

namespace Tests\Unit;

use Tests\TestCase;

class TicketPrinterDetectionTest extends TestCase
{
    public function test_ticket_printers_are_identified_without_using_the_windows_default_or_first_queue(): void
    {
        $composable = file_get_contents(resource_path('js/Composables/useQzTray.js'));

        $this->assertStringContainsString('const TICKET_PRINTER_STORAGE_KEY = "ventas_ticket_printer_name";', $composable);
        $this->assertStringContainsString('const TICKET_PRINTER_IDENTIFIERS = ["3nstar", "rpt006", "pos-58", "pos58", "pos 58"];', $composable);
        $this->assertStringContainsString('export function findTicketPrinter(printers = [])', $composable);
        $this->assertStringContainsString('const legacyStoredPrinter = getStoredPrinterName();', $composable);
        $this->assertStringContainsString('saveStoredTicketPrinterName(storedPrinter);', $composable);
        $this->assertStringNotContainsString('return detectedPrinter || availablePrinters[0]', $composable);
    }

    public function test_ticket_screens_use_the_shared_detector_and_do_not_render_ticket_printer_selectors(): void
    {
        $ticketPages = [
            resource_path('js/Pages/Ventas/Home.vue'),
            resource_path('js/Pages/Ventas/EmployeeCreditAccounts.vue'),
            resource_path('js/Pages/Ventas/CashRegisterClosures.vue'),
            resource_path('js/Pages/Ventas/SalesHistory.vue'),
        ];

        foreach ($ticketPages as $ticketPage) {
            $content = file_get_contents($ticketPage);

            $this->assertStringContainsString('findTicketPrinter', $content, basename($ticketPage).' no usa el identificador de tickets.');
            $this->assertStringNotContainsString('getDefaultQzPrinter', $content, basename($ticketPage).' aun depende de la impresora predeterminada de Windows.');
        }

        $salesToolbar = file_get_contents(resource_path('js/config/ToolbarConfigs/salesToolbarConfig.js'));
        $this->assertStringNotContainsString('ticket_printer', $salesToolbar);
    }
}
