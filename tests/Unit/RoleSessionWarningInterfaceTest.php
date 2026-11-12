<?php

namespace Tests\Unit;

use Tests\TestCase;

class RoleSessionWarningInterfaceTest extends TestCase
{
    public function test_the_global_layout_warns_and_offers_to_continue_or_close_the_session(): void
    {
        $modal = file_get_contents(resource_path('js/Components/Modales/SessionExpiryWarningModal.vue'));

        $this->assertStringContainsString('GlobalModal', $modal);
        $this->assertStringContainsString('¿Continuar sesión?', $modal);
        $this->assertStringContainsString('Por seguridad la sesión se cerrara en', $modal);
        $this->assertStringContainsString('variant="primary"', $modal);
        $this->assertStringContainsString('variant="secondary"', $modal);
        $this->assertStringContainsString('rounded-3xl', $modal);
    }

    public function test_the_global_layout_renews_the_session_when_the_user_interacts(): void
    {
        $layout = file_get_contents(resource_path('js/Layouts/AdminLayout.vue'));

        $this->assertStringContainsString('ROLE_SESSION_ACTIVITY_EVENTS', $layout);
        $this->assertStringContainsString("document.addEventListener(eventName, renewRoleSessionFromActivity", $layout);
        $this->assertStringContainsString("route('session.continue')", $layout);
    }
}
