<?php

namespace Tests\Unit;

use Tests\TestCase;

class QzTrayConnectionRetryTest extends TestCase
{
    public function test_qz_connection_waits_for_the_local_app_to_finish_starting(): void
    {
        $composable = file_get_contents(resource_path('js/Composables/useQzTray.js'));

        $this->assertStringContainsString('const QZ_CONNECTION_RETRIES = 8;', $composable);
        $this->assertStringContainsString('const QZ_CONNECTION_DELAY_SECONDS = 1;', $composable);
        $this->assertStringContainsString('const QZ_STARTUP_TIMEOUT_MS = 60000;', $composable);
        $this->assertStringContainsString('const QZ_STARTUP_RETRY_DELAY_MS = 1500;', $composable);
        $this->assertStringContainsString('connectionPromise = connectQzTrayDuringStartup()', $composable);
        $this->assertStringContainsString('while (Date.now() < deadline)', $composable);
        $this->assertStringContainsString('retries: QZ_CONNECTION_RETRIES,', $composable);
        $this->assertStringContainsString('delay: QZ_CONNECTION_DELAY_SECONDS,', $composable);
    }

    public function test_printer_discovery_waits_for_the_configured_windows_queue(): void
    {
        $composable = file_get_contents(resource_path('js/Composables/useQzTray.js'));

        $this->assertStringContainsString('const QZ_PRINTER_DISCOVERY_TIMEOUT_MS = 30000;', $composable);
        $this->assertStringContainsString('const QZ_PRINTER_DISCOVERY_RETRY_DELAY_MS = 1000;', $composable);
        $this->assertStringContainsString('const printerDiscoveryPromises = new Map();', $composable);
        $this->assertStringContainsString('async function findPrintersDuringStartup(expectedPrinterNames = [])', $composable);
        $this->assertStringContainsString('expectedPrinterNames.every((printerName) => lastPrinters.includes(printerName))', $composable);
        $this->assertStringContainsString('export async function getQzPrinters({ expectedPrinterNames = [] } = {})', $composable);
    }
}
