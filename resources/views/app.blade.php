<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">

        {{-- Matches the page ground (--st-ground); the pre-paint script below keeps it in
             step with the saved theme, which may disagree with the OS preference. --}}
        <meta name="theme-color" content="#0a0c0d">

        <title inertia>{{ \App\Models\Setting::get('store_name') ?: config('app.name', 'Laravel') }}</title>

        {{-- Apply the saved theme before first paint so dark mode never flashes white. --}}
        <script>
            (function () {
                const appearance = localStorage.getItem('appearance') || 'dark';

                const dark = appearance === 'dark' || (appearance === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);

                if (dark) {
                    document.documentElement.classList.add('dark');
                }

                const themeColor = document.querySelector('meta[name="theme-color"]');

                if (themeColor) {
                    themeColor.setAttribute('content', dark ? '#0a0c0d' : '#f1f1ec');
                }
            })();
        </script>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link rel="preconnect" href="https://fonts.bunny.net" crossorigin>

        {{-- The two faces the first screen actually paints in: 400 and 500, in the
             locale's own script. `font-display: swap` is on the @font-face rules. --}}
        @php($fontScript = app()->getLocale() === 'ar' ? 'arabic' : 'latin')
        <link rel="preload" as="font" type="font/woff2" crossorigin
              href="https://fonts.bunny.net/tajawal/files/tajawal-{{ $fontScript }}-400-normal.woff2">
        <link rel="preload" as="font" type="font/woff2" crossorigin
              href="https://fonts.bunny.net/tajawal/files/tajawal-{{ $fontScript }}-500-normal.woff2">

        @routes
        @vite(['resources/js/app.ts'])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
