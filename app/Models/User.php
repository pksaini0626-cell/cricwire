<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'google_id',
        'avatar',
        'role',
        'last_login_at',
        'player_code',
        'referred_by_user_id',
        'referral_rewarded',
        'cric_gems',
        'login_streak',
        'last_streak_date',
        'battles_played',
        'battles_won',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'referral_rewarded' => 'boolean',
            'cric_gems' => 'integer',
            'login_streak' => 'integer',
            'last_streak_date' => 'date:Y-m-d',
            'battles_played' => 'integer',
            'battles_won' => 'integer',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return in_array($this->role, ['admin', 'editor']);
    }

    public function newsArticles()
    {
        return $this->hasMany(NewsArticle::class, 'author_id');
    }

    public function pollVotes()
    {
        return $this->hasMany(PollVote::class);
    }

    public function referrer()
    {
        return $this->belongsTo(User::class, 'referred_by_user_id');
    }

    public function referrals()
    {
        return $this->hasMany(User::class, 'referred_by_user_id');
    }

    public function gemTransactions()
    {
        return $this->hasMany(CricGemTransaction::class);
    }

    public function sentFriendRequests()
    {
        return $this->hasMany(Friendship::class, 'sender_id');
    }

    public function receivedFriendRequests()
    {
        return $this->hasMany(Friendship::class, 'receiver_id');
    }

    public function createdBattles()
    {
        return $this->hasMany(Battle::class, 'creator_id');
    }

    public function joinedBattles()
    {
        return $this->hasMany(Battle::class, 'opponent_id');
    }

    public function wonBattles()
    {
        return $this->hasMany(Battle::class, 'winner_id');
    }
}
