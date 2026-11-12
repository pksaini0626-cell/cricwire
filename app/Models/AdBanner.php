<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdBanner extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'placement',
        'type',
        'image_path',
        'image_url',
        'target_url',
        'script_code',
        'clicks_count',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'clicks_count' => 'integer',
        ];
    }

    /**
     * Scope to retrieve active banner for a specific slot.
     */
    public function scopeActiveSlot(Builder $query, string $placement): Builder
    {
        return $query->where('placement', $placement)
            ->where('is_active', true)
            ->inRandomOrder();
    }

    /**
     * Accessor to get displayable image url whether uploaded locally or external url.
     */
    public function getResolvedImageUrlAttribute(): ?string
    {
        if (!empty($this->image_path)) {
            if (filter_var($this->image_path, FILTER_VALIDATE_URL)) {
                return $this->image_path;
            }
            return asset('storage/' . ltrim($this->image_path, '/'));
        }

        if (!empty($this->image_url)) {
            return $this->image_url;
        }

        return null;
    }
}
