<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#e0000f">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="Super-Kay">
    <meta name="application-name" content="Super-Kay">
    <meta name="mobile-web-app-capable" content="yes">

    <title inertia>Super-Kay</title>

    <!-- Fonts and first-screen assets -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preload" href="/icons/icon-192.png" as="image" type="image/png" fetchpriority="high">
    <link rel="preload" href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <link rel="preload" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined&display=swap" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <noscript>
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet">
        <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined&display=swap" rel="stylesheet">
    </noscript>
    <link rel="manifest" href="/pwa/manifest.webmanifest">
    <link rel="icon" href="/favicon.ico">
    <link rel="icon" type="image/png" sizes="192x192" href="/icons/icon-192.png">
    <link rel="apple-touch-icon" href="/icons/apple-touch-icon.png">
    <script>
        (() => {
            const storedTheme = window.localStorage.getItem('color-theme');
            const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            const useDark = storedTheme ? storedTheme === 'dark' : prefersDark;

            document.documentElement.classList.toggle('dark', useDark);
        })();
    </script>

    @php
        $broadcastConnection = config('broadcasting.default');
        $broadcastConfig = config("broadcasting.connections.{$broadcastConnection}", []);
        $realtimeTunnelConfig = null;
        $realtimeTunnelPath = storage_path('framework/realtime-tunnel.json');

        if (is_readable($realtimeTunnelPath)) {
            $decodedRealtimeTunnel = json_decode(file_get_contents($realtimeTunnelPath), true);

            if (
                is_array($decodedRealtimeTunnel)
                && filled($decodedRealtimeTunnel['host'] ?? null)
                && filled($decodedRealtimeTunnel['scheme'] ?? null)
            ) {
                $realtimeTunnelConfig = $decodedRealtimeTunnel;
            }
        }

        if ($broadcastConnection === 'pusher') {
            $pusherOptions = $broadcastConfig['options'] ?? [];

            $realtimeConfig = [
                'broadcaster' => 'pusher',
                'key' => $broadcastConfig['key'] ?? null,
                'cluster' => $pusherOptions['cluster'] ?? null,
                'host' => filled(env('PUSHER_HOST')) ? ($pusherOptions['host'] ?? null) : null,
                'port' => $pusherOptions['port'] ?? 443,
                'scheme' => $pusherOptions['scheme'] ?? 'https',
            ];
        } elseif ($realtimeTunnelConfig) {
            $realtimeConfig = [
                'broadcaster' => 'reverb',
                'key' => config('broadcasting.connections.reverb.key'),
                'host' => $realtimeTunnelConfig['host'],
                'port' => $realtimeTunnelConfig['port'] ?? 443,
                'scheme' => $realtimeTunnelConfig['scheme'],
            ];
        } elseif (app()->environment('production')) {
            $reverbOptions = config('broadcasting.connections.reverb.options', []);

            $realtimeConfig = [
                'broadcaster' => 'reverb',
                'key' => config('broadcasting.connections.reverb.key'),
                'host' => $reverbOptions['host'] ?: request()->getHost(),
                'port' => $reverbOptions['port'] ?: (request()->isSecure() ? 443 : request()->getPort()),
                'scheme' => $reverbOptions['scheme'] ?: request()->getScheme(),
            ];
        } else {
            $realtimeConfig = [
                'broadcaster' => 'reverb',
                'key' => config('broadcasting.connections.reverb.key'),
                'host' => config('broadcasting.connections.reverb.options.host'),
                'port' => config('broadcasting.connections.reverb.options.port'),
                'scheme' => config('broadcasting.connections.reverb.options.scheme'),
            ];
        }
    @endphp
    @if (filled($realtimeConfig['key']) && filled($realtimeConfig['host']))
        <script>
            window.__OXIVENTAS_REALTIME__ = @json($realtimeConfig);
        </script>
    @endif

    <!-- Scripts -->
    @routes
    @vite('resources/js/app.js')
    @inertiaHead
</head>

<body class="font-sans antialiased">
    @inertia
    <div id="pwa-install-root"></div>
</body>

</html>
