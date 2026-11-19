<?php

namespace App\Support;

use App\Models\AppSetting;
use Illuminate\Support\Facades\Cache;

class SeoConfig
{
    protected const CACHE_PREFIX = 'seo_settings:';
    protected const CACHE_TTL = 3600; // 1 hour

    /**
     * Default template values
     */
    public const DEFAULTS = [
        'seo_home_title' => 'Cricwire - Ultra-Fast Live Cricket Score, Ball by Ball Commentary & News',
        'seo_home_description' => 'Follow fastest live cricket scores, ball by ball commentary, match schedules, tournament standings, and trending cricket news on Cricwire.',
        'seo_home_keywords' => 'cricket live score, live cricket score ball by ball, cricket schedule, match commentary, ipl live score, cricwire',
        'seo_og_default_image' => null,
        'google_site_verification' => null,
        'bing_site_verification' => null,

        'seo_live_match_title_tpl' => '{team_a} vs {team_b} Live Score, Ball by Ball Commentary & Scorecard - {series}',
        'seo_live_match_desc_tpl' => 'Get {team_a} vs {team_b} live cricket score, ultra-fast ball-by-ball commentary, live odds, and full scorecard from {venue} on {date}.',
        'seo_upcoming_match_title_tpl' => '{team_a} vs {team_b} Match Prediction, Pitch Report, Playing 11 & Live Score - {series}',
        'seo_upcoming_match_desc_tpl' => 'Detailed match preview, pitch report, weather forecast, playing 11 squad, and prediction for {team_a} vs {team_b} at {venue}. Match starts {date}.',
        'seo_finished_match_title_tpl' => '{team_a} vs {team_b} Full Scorecard, Highlights & Match Result - {series}',
        'seo_finished_match_desc_tpl' => '{team_a} vs {team_b} complete cricket match results, full scorecard, player statistics, and match report from {venue}.',

        'seo_auto_noindex_old_matches_days' => 30,
        'seo_robots_txt_content' => "User-agent: *\nAllow: /\nDisallow: /admin\nDisallow: /livewire\nSitemap: https://cricwire.in/sitemap.xml",

        'seo_org_name' => 'Cricwire',
        'seo_org_logo' => null,
        'seo_same_as_links' => [],
    ];

    /**
     * Get a setting by key.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $fallback = $default ?? (self::DEFAULTS[$key] ?? null);

        return Cache::remember(self::CACHE_PREFIX . $key, self::CACHE_TTL, function () use ($key, $fallback) {
            try {
                $setting = AppSetting::where('key', $key)->first();
                if ($setting !== null && $setting->value !== null) {
                    $decoded = json_decode($setting->value, true);
                    return (json_last_error() === JSON_ERROR_NONE && (is_array($decoded) || is_object($decoded))) 
                        ? $decoded 
                        : $setting->value;
                }
                return $fallback;
            } catch (\Throwable $e) {
                return $fallback;
            }
        });
    }

    /**
     * Set a setting in DB and invalidate cache.
     */
    public static function set(string $key, mixed $value): void
    {
        $storedValue = is_array($value) ? json_encode($value) : (is_bool($value) ? ($value ? '1' : '0') : (string) ($value ?? ''));

        AppSetting::updateOrCreate(
            ['key' => $key],
            ['value' => $storedValue]
        );

        Cache::forget(self::CACHE_PREFIX . $key);
    }

    /**
     * Clear all SEO settings caches.
     */
    public static function clearAll(): void
    {
        foreach (array_keys(self::DEFAULTS) as $key) {
            Cache::forget(self::CACHE_PREFIX . $key);
        }
    }
}
