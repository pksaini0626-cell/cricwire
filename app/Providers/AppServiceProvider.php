<?php

namespace App\Providers;

use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Suppress redundant preload tags for stylesheets (e.g. app.css) to avoid browser preload warnings
        // and redundant fetches when Livewire SPA wire:navigate evaluates or re-evaluates the <head>.
        Vite::usePreloadTagAttributes(function (string $src, string $url, array|null $chunk, array|null $manifest) {
            if (preg_match('/\.(css|less|sass|scss|styl|stylus|pcss|postcss)(\?[^\.]*)?$/', $url)) {
                return false;
            }

            return [];
        });
    }
}

