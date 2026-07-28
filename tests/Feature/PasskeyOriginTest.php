<?php

namespace Tests\Feature;

use App\Http\Middleware\ResolvePasskeyOrigin;
use Illuminate\Http\Request;
use Tests\TestCase;

class PasskeyOriginTest extends TestCase
{
    public function test_quick_tunnel_uses_its_exact_hostname_as_the_relying_party_domain(): void
    {
        $request = Request::create('https://previously-fin-skins-sweet.trycloudflare.com/user/passkeys/options');

        app(ResolvePasskeyOrigin::class)->handle($request, fn () => response()->noContent());

        $this->assertSame('previously-fin-skins-sweet.trycloudflare.com', config('passkeys.relying_party_id'));
        $this->assertSame([
            'https://previously-fin-skins-sweet.trycloudflare.com',
        ], config('passkeys.allowed_origins'));
    }

    public function test_local_development_keeps_its_configured_relying_party_id(): void
    {
        config()->set('passkeys.relying_party_id', '127.0.0.1');
        $request = Request::create('http://127.0.0.1:8000/user/passkeys/options');

        app(ResolvePasskeyOrigin::class)->handle($request, fn () => response()->noContent());

        $this->assertSame('127.0.0.1', config('passkeys.relying_party_id'));
        $this->assertSame(['http://127.0.0.1:8000'], config('passkeys.allowed_origins'));
    }

    public function test_render_uses_its_fixed_subdomain_for_the_relying_party_id(): void
    {
        $request = Request::create('https://oxi-ventas.onrender.com/user/passkeys/options');

        app(ResolvePasskeyOrigin::class)->handle($request, fn () => response()->noContent());

        $this->assertSame('oxi-ventas.onrender.com', config('passkeys.relying_party_id'));
        $this->assertSame(['https://oxi-ventas.onrender.com'], config('passkeys.allowed_origins'));
    }
}
