<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserNotification extends Model
{
    use HasFactory;

    public const TYPE_BATTLE_CHALLENGE = 'battle_challenge';
    public const TYPE_BATTLE_ACCEPTED = 'battle_accepted';
    public const TYPE_BATTLE_PLAYING_XI = 'battle_playing_xi';
    public const TYPE_BATTLE_RESULT = 'battle_result';

    protected $fillable = [
        'user_id',
        'type',
        'title',
        'body',
        'action_url',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
