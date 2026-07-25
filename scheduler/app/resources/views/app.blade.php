<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="dark">
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
                    if (t !== 'light' && t !== 'dark') t = 'dark';
                    document.documentElement.setAttribute('data-theme', t);
                    document.documentElement.style.background = t === 'light' ? '#F0F2FF' : '#070B14';
                } catch (e) {}
            })();
        </script>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:400,500,600,700,800&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @routes
        @vite(['resources/js/app.js', "resources/js/Pages/{$page['component']}.vue"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
