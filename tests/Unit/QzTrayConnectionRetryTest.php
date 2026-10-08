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
        $this->assertStringContainsString('retries: QZ_CONNECTION_RETRIES,', $composable);
        $this->assertStringContainsString('delay: QZ_CONNECTION_DELAY_SECONDS,', $composable);
    }
}
