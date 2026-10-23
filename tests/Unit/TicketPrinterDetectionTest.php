<?php

namespace Tests\Unit;

use Tests\TestCase;

class TicketPrinterDetectionTest extends TestCase
{
    public function test_printer_roles_use_separate_explicit_assignments(): void
    {
        $composable = file_get_contents(resource_path('js/Composables/useQzTray.js'));

        $this->assertStringContainsString('const TICKET_PRINTER_STORAGE_KEY = "ventas_ticket_printer_name";', $composable);
        $this->assertStringContainsString('const LABEL_PRINTER_STORAGE_KEY = "ventas_label_printer_name";', $composable);
        $this->assertStringContainsString('export function findTicketPrinter(printers = [])', $composable);
        $this->assertStringContainsString('export function findLabelPrinter(printers = [])', $composable);
        $this->assertStringContainsString('const QZ_PRINTER_DISCOVERY_TIMEOUT_MS = 30000;', $composable);
        $this->assertStringContainsString('async function findPrintersDuringStartup(expectedPrinterNames = [])', $composable);
        $this->assertStringContainsString('expectedPrinterNames.every', $composable);
        $this->assertStringNotContainsString('TICKET_PRINTER_IDENTIFIERS', $composable);
        $this->assertStringNotContainsString('getDefaultQzPrinter()', $composable);
        $this->assertStringNotContainsString('availablePrinters[0]', $composable);
    }

    public function test_ticket_screens_use_the_shared_detector_and_do_not_render_ticket_printer_selectors(): void
    {
        $ticketPages = [
            resource_path('js/Pages/Ventas/Home.vue'),
            resource_path('js/Pages/Ventas/EmployeeCreditAccounts.vue'),
            resource_path('js/Pages/Ventas/CashRegisterClosures.vue'),
            resource_path('js/Pages/Ventas/CashRegisterClosureReports.vue'),
            resource_path('js/Pages/Ventas/SalesHistory.vue'),
        ];

        foreach ($ticketPages as $ticketPage) {
            $content = file_get_contents($ticketPage);

            $this->assertStringContainsString('findTicketPrinter', $content, basename($ticketPage).' no usa el identificador de tickets.');
            $this->assertStringContainsString('expectedPrinterNames', $content, basename($ticketPage).' no espera la cola configurada.');
            $this->assertStringNotContainsString('getDefaultQzPrinter', $content, basename($ticketPage).' aun depende de la impresora predeterminada de Windows.');
        }

        $salesToolbar = file_get_contents(resource_path('js/config/ToolbarConfigs/salesToolbarConfig.js'));
        $this->assertStringNotContainsString('ticket_printer', $salesToolbar);
    }

    public function test_printer_setup_page_configures_and_tests_both_roles(): void
    {
        $setupPage = file_get_contents(resource_path('js/Pages/Printers/QzTraySetup.vue'));
        $labelsPage = file_get_contents(resource_path('js/Pages/Printers/Labels.vue'));

        $this->assertStringContainsString('Configurar impresoras', $setupPage);
        $this->assertStringContainsString('saveStoredTicketPrinterName', $setupPage);
        $this->assertStringContainsString('saveStoredLabelPrinterName', $setupPage);
        $this->assertStringContainsString('printTicketTest', $setupPage);
        $this->assertStringContainsString('printLabelTest', $setupPage);
        $this->assertStringContainsString('https://3nstar.com/wp-content/uploads/2023/07/RPT-Drivers.zip', $setupPage);
        $this->assertStringContainsString('https://support.brother.com/g/b/downloadtop.aspx?c=mx&lang=es&prod=lpql1110nwbeus', $setupPage);
        $this->assertStringContainsString('https://qz.io/download/', $setupPage);
        $this->assertStringContainsString('2. Instala QZ Tray', $setupPage);
        $this->assertStringContainsString('3. Al final, instala el certificado', $setupPage);
        $this->assertLessThan(
            strpos($setupPage, '3. Al final, instala el certificado'),
            strpos($setupPage, '2. Instala QZ Tray')
        );
        $this->assertStringContainsString('getStoredLabelPrinterName', $labelsPage);
        $this->assertStringContainsString('expectedPrinterNames', $labelsPage);
        $this->assertStringNotContainsString('getDefaultQzPrinter', $labelsPage);
    }
}
