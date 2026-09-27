<?php

namespace Tests\Unit;

use App\Http\Middleware\NormalizeInertiaDocumentRequest;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class NormalizeInertiaDocumentRequestTest extends TestCase
{
    public function test_document_navigation_cannot_keep_inertia_headers(): void
    {
        $request = Request::create('/ventas', 'GET', server: [
            'HTTP_X_INERTIA' => 'true',
            'HTTP_X_INERTIA_VERSION' => 'old-build',
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
            'HTTP_SEC_FETCH_MODE' => 'navigate',
            'HTTP_SEC_FETCH_DEST' => 'document',
        ]);

        $response = app(NormalizeInertiaDocumentRequest::class)->handle(
            $request,
            fn () => new Response('ok'),
        );

        $this->assertSame('ok', $response->getContent());
        $this->assertFalse($request->headers->has('X-Inertia'));
        $this->assertFalse($request->headers->has('X-Requested-With'));
    }

    public function test_internal_inertia_visit_keeps_its_headers(): void
    {
        $request = Request::create('/ventas', 'GET', server: [
            'HTTP_X_INERTIA' => 'true',
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ]);

        app(NormalizeInertiaDocumentRequest::class)->handle(
            $request,
            fn () => new Response('ok'),
        );

        $this->assertTrue($request->headers->has('X-Inertia'));
        $this->assertTrue($request->headers->has('X-Requested-With'));
    }
}
