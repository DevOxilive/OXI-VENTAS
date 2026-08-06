<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#e0000f">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="{{ config('app.name', 'Super-Kay') }}">
    <meta name="application-name" content="{{ config('app.name', 'Super-Kay') }}">
    <meta name="mobile-web-app-capable" content="yes">

    <title inertia>{{ config('app.name', 'Laravel') }}</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet" />
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

        if ($realtimeTunnelConfig) {
            $realtimeConfig = [
                'key' => config('broadcasting.connections.reverb.key'),
                'host' => $realtimeTunnelConfig['host'],
                'port' => $realtimeTunnelConfig['port'] ?? 443,
                'scheme' => $realtimeTunnelConfig['scheme'],
            ];
        } elseif (app()->environment('production')) {
            $realtimeConfig = [
                'key' => config('broadcasting.connections.reverb.key'),
                'host' => request()->getHost(),
                'port' => request()->isSecure() ? 443 : request()->getPort(),
                'scheme' => request()->getScheme(),
            ];
        } else {
            $realtimeConfig = [
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
