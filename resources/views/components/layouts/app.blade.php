<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark bg-slate-950">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">

        <title>{{ $title ?? 'CricWire - Live Cricket Scores, Fast Ball-by-Ball Live Line' }}</title>

        <!-- CricWire Favicons & PWA -->
        <link rel="icon" type="image/x-icon" href="{{ asset('icons/cricwire_favicons/favicon.ico') }}">
        <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('icons/cricwire_favicons/favicon-32x32.png') }}">
        <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('icons/cricwire_favicons/favicon-16x16.png') }}">
        <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('icons/cricwire_favicons/apple-touch-icon.png') }}">
        <link rel="manifest" href="/manifest.json">
        <meta name="theme-color" content="#020617">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">

        <!-- Tailwind CSS CDN fallback & Vite assets -->
        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @else
            <script src="https://cdn.tailwindcss.com"></script>
            <script>
                tailwind.config = {
                    darkMode: 'class',
                    theme: {
                        extend: {
                            colors: {
                                brand: {
                                    primary: '#0ea5e9',
                                    accent: '#ef4444',
                                    gold: '#eab308',
                                }
                            }
                        }
                    }
                }
            </script>
        @endif

        @livewireStyles
    </head>
    <style>
    /* Livewire SPA Top Navigation Bar */
    [wire\:navigate-progress] {
        background-color: #f59e0b !important; /* Neon Amber */
        height: 3px !important;
        box-shadow: 0 0 10px #f59e0b, 0 0 5px #10b981;
    }
</style>
    <body class="min-h-screen bg-slate-950 text-slate-100 font-sans antialiased selection:bg-amber-500 selection:text-black">
        <!-- Mobile-Only App Welcome / Splash Screen -->
        <x-mobile-welcome-splash />

        <!-- Global SPA Navigation & Request Loader -->
        <x-cricket-loader />

        <div class="min-h-screen bg-slate-950 text-slate-100 font-sans antialiased selection:bg-amber-500 selection:text-black flex flex-col">
            {{ $slot ?? '' }}
            @yield('content')
        </div>

        @livewireScripts

        <!-- PWA Service Worker Registration -->
        <script>
            if ('serviceWorker' in navigator) {
                window.addEventListener('load', () => {
                    navigator.serviceWorker.register('/sw.js')
                        .then((reg) => {
                            console.log('Cricwire SW registered:', reg.scope);
                        })
                        .catch((err) => {
                            console.error('Cricwire SW registration failed:', err);
                        });
                });
            }
        </script>
    </body>
</html>
