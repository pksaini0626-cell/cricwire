<?php

namespace App\Support;

use App\Models\AppSetting;
use Illuminate\Support\Facades\Cache;

class LiveEngineConfig
{
    protected const CACHE_PREFIX = 'app_settings:';
    protected const CACHE_TTL = 3600; // 1 hour

    /**
     * Check if background live poller is enabled.
     */
    public static function isEnabled(): bool
    {
        $val = self::get('live_engine_enabled', '1');
        return filter_var($val, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Polling intervals format-wise (in seconds)
     */
    public static function pollIntervalT20(): int
    {
        $val = (int) self::get('poll_interval_t20', 10);
        return max(3, min(120, $val));
    }

    public static function pollIntervalOdi(): int
    {
        $val = (int) self::get('poll_interval_odi', 25);
        return max(5, min(180, $val));
    }

    public static function pollIntervalTest(): int
    {
        $val = (int) self::get('poll_interval_test', 30);
        return max(5, min(300, $val));
    }

    /**
     * Daily API call limit / safety cap
     */
    public static function dailyApiCallLimit(): int
    {
        $val = (int) self::get('daily_api_call_limit', 4800);
        return max(100, $val);
    }

    /**
     * Check if market rates and session odds are enabled (Safe-Mode toggle).
     */
    public static function isMarketRatesEnabled(): bool
    {
        $val = self::get('show_market_rates_and_session', '1');
        return filter_var($val, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Get configured polling interval in seconds (clamped between 1 and 120s, default 30s).
     */
    public static function pollingInterval(): int
    {
        $val = (int) self::get('polling_interval_seconds', 30);
        return max(1, min(120, $val));
    }

    /**
     * Retrieve all active social media links with visibility toggles.
     */
    public static function getActiveSocialLinks(): array
    {
        $platforms = [
            'telegram' => [
                'name' => 'Telegram',
                'icon' => 'telegram',
                'url' => (string) self::get('social_telegram_url', 'https://t.me/cricwireofficial'),
                'enabled' => filter_var(self::get('social_telegram_enabled', '1'), FILTER_VALIDATE_BOOLEAN),
                'color' => 'from-sky-500 to-blue-600',
            ],
            'instagram' => [
                'name' => 'Instagram',
                'icon' => 'instagram',
                'url' => (string) self::get('social_instagram_url', 'https://instagram.com/cricwire.in'),
                'enabled' => filter_var(self::get('social_instagram_enabled', '0'), FILTER_VALIDATE_BOOLEAN),
                'color' => 'from-pink-500 via-rose-500 to-yellow-500',
            ],
            'twitter' => [
                'name' => 'X (Twitter)',
                'icon' => 'twitter',
                'url' => (string) self::get('social_twitter_url', 'https://x.com/cricwire'),
                'enabled' => filter_var(self::get('social_twitter_enabled', '0'), FILTER_VALIDATE_BOOLEAN),
                'color' => 'from-slate-700 to-slate-900',
            ],
            'youtube' => [
                'name' => 'YouTube',
                'icon' => 'youtube',
                'url' => (string) self::get('social_youtube_url', 'https://www.youtube.com/@cricwire.official'),
                'enabled' => filter_var(self::get('social_youtube_enabled', '0'), FILTER_VALIDATE_BOOLEAN),
                'color' => 'from-red-600 to-rose-700',
            ],
            'facebook' => [
                'name' => 'Facebook',
                'icon' => 'facebook',
                'url' => (string) self::get('social_facebook_url', 'https://facebook.com/cricwire'),
                'enabled' => filter_var(self::get('social_facebook_enabled', '0'), FILTER_VALIDATE_BOOLEAN),
                'color' => 'from-blue-600 to-indigo-700',
            ],
        ];

        return array_filter($platforms, function ($platform) {
            return $platform['enabled'] && !empty(trim($platform['url']));
        });
    }

    /**
     * Get a setting by key from cache or database.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        return Cache::remember(self::CACHE_PREFIX . $key, self::CACHE_TTL, function () use ($key, $default) {
            try {
                $setting = AppSetting::where('key', $key)->first();
                return $setting !== null && $setting->value !== null ? $setting->value : $default;
            } catch (\Throwable $e) {
                return $default;
            }
        });
    }

    /**
     * Set a setting in database and clear its cache.
     */
    public static function set(string $key, mixed $value): void
    {
        AppSetting::updateOrCreate(
            ['key' => $key],
            ['value' => is_bool($value) ? ($value ? '1' : '0') : (string) $value]
        );

        self::clearCache($key);
    }

    /**
     * Clear cached setting key.
     */
    public static function clearCache(?string $key = null): void
    {
        if ($key) {
            Cache::forget(self::CACHE_PREFIX . $key);
        } else {
            Cache::forget(self::CACHE_PREFIX . 'live_engine_enabled');
            Cache::forget(self::CACHE_PREFIX . 'poll_interval_t20');
            Cache::forget(self::CACHE_PREFIX . 'poll_interval_odi');
            Cache::forget(self::CACHE_PREFIX . 'poll_interval_test');
            Cache::forget(self::CACHE_PREFIX . 'daily_api_call_limit');
            Cache::forget(self::CACHE_PREFIX . 'show_market_rates_and_session');
            Cache::forget(self::CACHE_PREFIX . 'polling_interval_seconds');
            Cache::forget(self::CACHE_PREFIX . 'social_facebook_url');
            Cache::forget(self::CACHE_PREFIX . 'social_facebook_enabled');
            Cache::forget(self::CACHE_PREFIX . 'social_twitter_url');
            Cache::forget(self::CACHE_PREFIX . 'social_twitter_enabled');
            Cache::forget(self::CACHE_PREFIX . 'social_instagram_url');
            Cache::forget(self::CACHE_PREFIX . 'social_instagram_enabled');
            Cache::forget(self::CACHE_PREFIX . 'social_telegram_url');
            Cache::forget(self::CACHE_PREFIX . 'social_telegram_enabled');
            Cache::forget(self::CACHE_PREFIX . 'social_youtube_url');
            Cache::forget(self::CACHE_PREFIX . 'social_youtube_enabled');
        }
    }
}
