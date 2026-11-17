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
            'placement' => 'array',
            'is_active' => 'boolean',
            'clicks_count' => 'integer',
        ];
    }

    /**
     * Get placement as an array, supporting both JSON arrays and legacy plain strings.
     */
    public function getPlacementAttribute($value): array
    {
        if (empty($value)) {
            return [];
        }

        if (is_array($value)) {
            return $value;
        }

        $decoded = json_decode($value, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return $decoded;
        }

        // Backward compatibility for legacy single-string placement values (e.g., "news_top")
        return [(string) $value];
    }

    /**
     * Set placement as a JSON encoded array.
     */
    public function setPlacementAttribute($value): void
    {
        if (is_array($value)) {
            $this->attributes['placement'] = json_encode(array_values(array_filter($value)));
        } elseif (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $this->attributes['placement'] = json_encode(array_values(array_filter($decoded)));
            } else {
                $this->attributes['placement'] = json_encode([$value]);
            }
        } else {
            $this->attributes['placement'] = json_encode([]);
        }
    }

    /**
     * Scope to retrieve active banner for a specific slot.
     */
    public function scopeActiveSlot(Builder $query, string $placement): Builder
    {
        return $query->where('is_active', true)
            ->where(function (Builder $q) use ($placement) {
                $q->where('placement', $placement)
                  ->orWhere('placement', 'LIKE', '%"' . $placement . '"%')
                  ->orWhere('placement', 'LIKE', '%' . $placement . '%');
            })
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

            $cleanPath = ltrim($this->image_path, '/');
            if (str_starts_with($cleanPath, 'storage/')) {
                $cleanPath = substr($cleanPath, 8);
            }

            return asset('storage/' . $cleanPath);
        }

        if (!empty($this->image_url)) {
            return $this->image_url;
        }

        return null;
    }
}
