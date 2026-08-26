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
     * Cloudflare Quick Tunnels change their subdomain on every start. Some
     * mobile browsers reject their shared parent domain as an RP ID, so the
     * current tunnel hostname must be used exactly.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $host = strtolower($request->getHost());
        $origin = $request->getSchemeAndHttpHost();

        if ($this->shouldUseRequestHost($request, $host)) {
            config()->set('passkeys.relying_party_id', $host);
        }

        config()->set('passkeys.allowed_origins', [$origin]);

        return $next($request);
    }

    private function shouldUseRequestHost(Request $request, string $host): bool
    {
        if ($host === 'localhost' || filter_var($host, FILTER_VALIDATE_IP)) {
            return false;
        }

        return $request->isSecure()
            || $host === 'trycloudflare.com'
            || str_ends_with($host, '.trycloudflare.com');
    }
}
