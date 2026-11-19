<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark bg-slate-950">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        @php
            $seo = $seoData ?? [];
            $pageTitle = $seo['title'] ?? $title ?? \App\Support\SeoConfig::get('seo_home_title', \App\Support\SeoConfig::DEFAULTS['seo_home_title']);
            $pageDesc = $seo['description'] ?? \App\Support\SeoConfig::get('seo_home_description', \App\Support\SeoConfig::DEFAULTS['seo_home_description']);
            $pageKeywords = $seo['keywords'] ?? \App\Support\SeoConfig::get('seo_home_keywords', \App\Support\SeoConfig::DEFAULTS['seo_home_keywords']);
            $canonicalUrl = $seo['canonical'] ?? url()->current();
            $robots = $seo['robots'] ?? 'index, follow';
            $ogType = $seo['og_type'] ?? 'website';
            $ogImage = $seo['og_image'] ?? asset('icons/cricwire_favicons/android-chrome-512x512.png');
            $siteName = \App\Support\SeoConfig::get('seo_org_name', 'Cricwire');
            $googleVerification = \App\Support\SeoConfig::get('google_site_verification');
            $bingVerification = \App\Support\SeoConfig::get('bing_site_verification');
        @endphp

        <!-- Primary Meta Tags -->
        <title>{{ $pageTitle }}</title>
        <meta name="title" content="{{ $pageTitle }}">
        <meta name="description" content="{{ $pageDesc }}">
        @if(!empty($pageKeywords))
            <meta name="keywords" content="{{ $pageKeywords }}">
        @endif
        <meta name="robots" content="{{ $robots }}">
        <link rel="canonical" href="{{ $canonicalUrl }}">

        <!-- Search Engine Verification Codes -->
        @if(!empty($googleVerification))
            <meta name="google-site-verification" content="{{ $googleVerification }}">
        @endif
        @if(!empty($bingVerification))
            <meta name="msvalidate.01" content="{{ $bingVerification }}">
        @endif

        <!-- Open Graph / Facebook -->
        <meta property="og:type" content="{{ $ogType }}">
        <meta property="og:url" content="{{ $canonicalUrl }}">
        <meta property="og:title" content="{{ $pageTitle }}">
        <meta property="og:description" content="{{ $pageDesc }}">
        <meta property="og:image" content="{{ $ogImage }}">
        <meta property="og:site_name" content="{{ $siteName }}">

        <!-- Twitter Card -->
        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:url" content="{{ $canonicalUrl }}">
        <meta name="twitter:title" content="{{ $pageTitle }}">
        <meta name="twitter:description" content="{{ $pageDesc }}">
        <meta name="twitter:image" content="{{ $ogImage }}">

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

        <!-- Dynamic JSON-LD Structured Data Schemas -->
        @if(!empty($seo['schemas']) && is_array($seo['schemas']))
            @foreach($seo['schemas'] as $schemaItem)
                <script type="application/ld+json">
{!! json_encode($schemaItem, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) !!}
                </script>
            @endforeach
        @endif

        @stack('seo_schema')

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

        <!-- Livewire 419 Auto-Recovery Handler -->
        <script>
            document.addEventListener('livewire:init', () => {
                if (window.Livewire && window.Livewire.hook) {
                    window.Livewire.hook('request', ({ fail }) => {
                        fail(({ status, preventDefault }) => {
                            if (status === 419) {
                                preventDefault();
                                window.location.reload();
                            }
                        });
                    });
                }
            });
        </script>

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
