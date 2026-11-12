<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NewsArticle extends Model
{
    use HasFactory;

    protected $fillable = [
        'author_id',
        'series_id',
        'match_id',
        'title',
        'slug',
        'category',
        'featured_image',
        'summary',
        'content',
        'source_name',
        'source_url',
        'status',
        'youtube_video_url',
        'seo_meta_title',
        'seo_meta_description',
        'seo_keywords',
        'is_published',
        'is_featured',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'is_featured' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function series()
    {
        return $this->belongsTo(Series::class, 'series_id');
    }

    public function match()
    {
        return $this->belongsTo(CricketMatch::class, 'match_id');
    }

    /**
     * Resolve the featured image URL whether stored locally or an external URL.
     */
    public function getResolvedImageUrlAttribute(): ?string
    {
        if (empty($this->featured_image)) {
            return null;
        }

        if (filter_var($this->featured_image, FILTER_VALIDATE_URL)) {
            return $this->featured_image;
        }

        // Handle path with or without storage prefix
        $cleanPath = ltrim($this->featured_image, '/');
        if (str_starts_with($cleanPath, 'storage/')) {
            return asset($cleanPath);
        }

        return asset('storage/' . $cleanPath);
    }
}
