<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SquadBattle extends Model
{
    use HasFactory;

    public const STATUS_PENDING_ACCEPT = 'pending_accept';
    public const STATUS_DRAFTING = 'drafting';
    public const STATUS_LOCKED_LIVE = 'locked_live';
    public const STATUS_SETTLED = 'settled';
    public const STATUS_DECLINED = 'declined';
    public const STATUS_EXPIRED_INCOMPLETE = 'expired_incomplete';

    protected $fillable = [
        'slug',
        'match_id',
        'match_title',
        'match_format',
        'series_slug',
        'match_slug',
        'team_a',
        'team_b',
        'team_size',
        'challenger_id',
        'opponent_id',
        'toss_winner_id',
        'current_turn_user_id',
        'challenger_last_seen_at',
        'opponent_last_seen_at',
        'status',
        'match_starts_at',
        'locks_at',
        'challenger_runs',
        'opponent_runs',
        'run_margin',
        'winner_id',
        'settled_at',
    ];

    protected function casts(): array
    {
        return [
            'team_size' => 'integer',
            'challenger_last_seen_at' => 'datetime',
            'opponent_last_seen_at' => 'datetime',
            'match_starts_at' => 'datetime',
            'locks_at' => 'datetime',
            'challenger_runs' => 'integer',
            'opponent_runs' => 'integer',
            'run_margin' => 'integer',
            'settled_at' => 'datetime',
        ];
    }

    public function challenger(): BelongsTo
    {
        return $this->belongsTo(User::class, 'challenger_id');
    }

    public function opponent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opponent_id');
    }

    public function tossWinner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'toss_winner_id');
    }

    public function currentTurnUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'current_turn_user_id');
    }

    public function winner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'winner_id');
    }

    public function picks(): HasMany
    {
        return $this->hasMany(SquadBattlePick::class, 'squad_battle_id')->orderBy('pick_order');
    }

    public function isParticipant(?int $userId): bool
    {
        if (!$userId) {
            return false;
        }

        return (int) $this->challenger_id === $userId || (int) $this->opponent_id === $userId;
    }

    public function otherCaptainId(int $userId): ?int
    {
        if ((int) $this->challenger_id === $userId) {
            return (int) $this->opponent_id;
        }
        if ((int) $this->opponent_id === $userId) {
            return (int) $this->challenger_id;
        }

        return null;
    }

    public function isBeforeLock(): bool
    {
        if (!$this->locks_at) {
            return false;
        }

        return now('Asia/Kolkata')->lt($this->locks_at->copy()->timezone('Asia/Kolkata'));
    }

    public function modeLabel(): string
    {
        return "{$this->team_size}v{$this->team_size}";
    }
}
