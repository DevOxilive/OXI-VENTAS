<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExpiredInertiaSessionTest extends TestCase
{
    public function test_expired_inertia_session_redirects_to_login_as_a_clean_navigation(): void
    {
        $this->withHeaders([
            'X-Inertia' => 'true',
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'text/html, application/xhtml+xml',
        ])
            ->get(route('dashboard'))
            ->assertStatus(409)
            ->assertHeader('X-Inertia-Location', route('login'));
    }

    public function test_document_navigation_with_stale_inertia_headers_receives_html_redirect(): void
    {
        $this->withHeaders([
            'X-Inertia' => 'true',
            'X-Requested-With' => 'XMLHttpRequest',
            'Sec-Fetch-Mode' => 'navigate',
            'Sec-Fetch-Dest' => 'document',
            'Accept' => 'text/html,application/xhtml+xml',
        ])
            ->get(route('dashboard'))
            ->assertRedirect(route('login'));
    }
}
