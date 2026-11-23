<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class SkippedSeries extends Model
{
    use HasFactory;

    protected $table = 'skipped_series';

    protected $fillable = [
        'series_id',
        'series_name',
        'series_slug',
        'format',
        'live_matches_count',
        'upcoming_matches_count',
        'recent_matches_count',
        'is_skipped',
    ];

    protected $casts = [
        'is_skipped' => 'boolean',
        'live_matches_count' => 'integer',
        'upcoming_matches_count' => 'integer',
        'recent_matches_count' => 'integer',
    ];

    protected static function booted(): void
    {
        static::saving(function (SkippedSeries $model) {
            if (empty($model->series_slug) || $model->isDirty('series_name')) {
                $model->series_slug = Str::slug($model->series_name);
            }
        });

        $clearCacheAndSyncMatches = function (SkippedSeries $model) {
            // 1. Clear Redis/Cache keys
            Cache::forget('cricket:skipped_series:lookup');
            Cache::forget('cricket:redis:home');
            Cache::forget('cricket:redis:fast_home_data');
            Cache::forget('live:home:payload');
            if (!empty($model->series_slug)) {
                Cache::forget("cricket:redis:series:{$model->series_slug}");
            }

            // 2. Sync ManagedMatch rows silently without events
            $query = ManagedMatch::query();
            if (!empty($model->series_id)) {
                $query->where(function ($q) use ($model) {
                    $q->where('series_name', $model->series_name)
                      ->orWhere('series_id', (string) $model->series_id);
                });
            } else {
                $query->where('series_name', $model->series_name);
            }

            ManagedMatch::withoutEvents(function () use ($query, $model) {
                $query->update([
                    'is_covered' => !$model->is_skipped,
                ]);
            });
        };

        static::saved($clearCacheAndSyncMatches);
        static::deleted($clearCacheAndSyncMatches);
    }

    /**
     * Cached lookup of skipped series IDs and slugs
     */
    public static function getSkippedLookup(): array
    {
        return Cache::remember('cricket:skipped_series:lookup', 600, function () {
            $rows = self::where('is_skipped', true)->get(['series_id', 'series_name', 'series_slug']);
            return [
                'ids' => $rows->pluck('series_id')->filter()->map(fn ($v) => (string) $v)->values()->all(),
                'slugs' => $rows->map(fn ($r) => $r->series_slug ?: Str::slug($r->series_name))->filter()->unique()->values()->all(),
            ];
        });
    }
}
