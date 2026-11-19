<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class SeoRedirect extends Model
{
    use HasFactory;

    protected $table = 'seo_redirects';

    protected $fillable = [
        'old_path',
        'new_path',
        'status_code',
        'hits_count',
        'is_active',
    ];

    protected $casts = [
        'status_code' => 'integer',
        'hits_count' => 'integer',
        'is_active' => 'boolean',
    ];

    public static function boot(): void
    {
        parent::boot();

        static::saved(function (self $model) {
            self::clearRedirectCache($model->old_path);
        });

        static::deleted(function (self $model) {
            self::clearRedirectCache($model->old_path);
        });
    }

    public static function clearRedirectCache(?string $oldPath = null): void
    {
        if ($oldPath) {
            $normalized = '/' . ltrim($oldPath, '/');
            Cache::forget('seo_redirect:' . md5($normalized));
        }
        Cache::forget('seo_redirects:all_active');
    }
}
