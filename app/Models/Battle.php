<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Battle extends Model
{
    use HasFactory;

    public const STATUS_OPEN = 'open';
    public const STATUS_LOCKED = 'locked';
    public const STATUS_SETTLED = 'settled';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_REFUNDED = 'refunded';

    protected $fillable = [
        'game_type',
        'match_id',
        'match_title',
        'creator_id',
        'opponent_id',
        'entry_gems',
        'creator_pick',
        'opponent_pick',
        'winner_id',
        'status',
        'locks_at',
        'settled_at',
    ];

    protected function casts(): array
    {
        return [
            'entry_gems' => 'integer',
            'locks_at' => 'datetime',
            'settled_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function opponent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opponent_id');
    }

    public function winner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'winner_id');
    }
}
