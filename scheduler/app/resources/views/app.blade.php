<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="light">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title inertia>{{ config('app.name', 'Laravel') }}</title>

        {{-- Paint the saved theme before first paint so a reload never flashes
             the wrong background. Mirrors the default in useTheme(). --}}
        <script>
            (function () {
                try {
                    var t = localStorage.getItem('scheduleistic.theme');
                    if (t !== 'light' && t !== 'dark') t = 'light';
                    document.documentElement.setAttribute('data-theme', t);
                    document.documentElement.style.background = t === 'dark' ? '#08092F' : '#FFFFFF';
                } catch (e) {}
            })();
        </script>

        {{-- Platform favicons, shared by the app and scheduleistic.com so the
             browser tab matches either side of the sign-in boundary.
             Note: these are the platform marks on every domain. Per-tenant
             favicons are not wired up — config/scheduleistic.php has a
             `favicon` key but brandingConfig() does not expose it and nothing
             swaps these tags at runtime. --}}
        <link rel="icon" href="/brand/favicon.svg" type="image/svg+xml">
        <link rel="icon" href="/brand/favicon-32x32.png" sizes="32x32" type="image/png">
        <link rel="apple-touch-icon" href="/brand/apple-touch-icon.png">
        <link rel="mask-icon" href="/brand/mask-icon.svg" color="#6366F1">
        <meta name="theme-color" content="#6366F1">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:400,500,600,700,800,900&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @routes
        @vite(['resources/js/app.js', "resources/js/Pages/{$page['component']}.vue"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
