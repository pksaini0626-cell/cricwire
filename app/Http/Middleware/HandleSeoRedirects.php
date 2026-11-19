<?php

namespace App\Http\Middleware;

use App\Models\SeoRedirect;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class HandleSeoRedirects
{
    /**
     * Handle an incoming request.
     * Check cached SEO redirects table before continuing.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Don't intercept livewire requests or internal filament assets
        if ($request->is('livewire/*') || $request->is('filament/*') || $request->is('api/*')) {
            return $next($request);
        }

        $path = '/' . ltrim($request->path(), '/');
        $cacheKey = 'seo_redirect:' . md5($path);

        $redirect = Cache::remember($cacheKey, 3600, function () use ($path) {
            return SeoRedirect::query()
                ->where('is_active', true)
                ->where(function ($query) use ($path) {
                    $query->where('old_path', $path)
                        ->orWhere('old_path', ltrim($path, '/'));
                })
                ->first();
        });

        if ($redirect && $redirect->is_active) {
            // Increment hit counter asynchronously/silently
            try {
                $redirect->increment('hits_count');
            } catch (\Throwable $e) {
                // Ignore DB lock on hit count
            }

            $target = $redirect->new_path;
            // Retain query string if present
            if ($queryString = $request->getQueryString()) {
                $separator = str_contains($target, '?') ? '&' : '?';
                $target .= $separator . $queryString;
            }

            return redirect()->to($target, (int) $redirect->status_code);
        }

        return $next($request);
    }
}
