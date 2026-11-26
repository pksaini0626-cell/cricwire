<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Friendship extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_DECLINED = 'declined';

    protected $fillable = [
        'sender_id',
        'receiver_id',
        'status',
        'accepted_at',
    ];

    protected function casts(): array
    {
        return [
            'accepted_at' => 'datetime',
        ];
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'receiver_id');
    }

    /**
     * Scope to find any friendship record between two user IDs regardless of direction.
     */
    public function scopeBetweenUsers(Builder $query, int $userAId, int $userBId): Builder
    {
        return $query->where(function (Builder $q) use ($userAId, $userBId) {
            $q->where(function (Builder $pair) use ($userAId, $userBId) {
                $pair->where('sender_id', $userAId)->where('receiver_id', $userBId);
            })->orWhere(function (Builder $pair) use ($userAId, $userBId) {
                $pair->where('sender_id', $userBId)->where('receiver_id', $userAId);
            });
        });
    }
}
