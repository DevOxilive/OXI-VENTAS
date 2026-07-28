<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolvePasskeyOrigin
{
    /**
     * Aligns the WebAuthn configuration with the address the device is using.
     *
     * Cloudflare Quick Tunnels change their subdomain on every start. Their
     * common parent domain is a valid relying-party domain, while the exact
     * HTTPS origin remains restricted to the current tunnel address.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $host = strtolower($request->getHost());
        $origin = $request->getSchemeAndHttpHost();

        if ($host === 'trycloudflare.com' || str_ends_with($host, '.trycloudflare.com')) {
            config()->set('passkeys.relying_party_id', 'trycloudflare.com');
        }

        if ($host === 'onrender.com' || str_ends_with($host, '.onrender.com')) {
            config()->set('passkeys.relying_party_id', $host);
        }

        config()->set('passkeys.allowed_origins', [$origin]);

        return $next($request);
    }
}
