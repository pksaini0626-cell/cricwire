<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Poll extends Model
{
    use HasFactory;

    protected $fillable = [
        'match_id',
        'question',
        'option_a',
        'option_b',
        'option_c',
        'votes_a',
        'votes_b',
        'votes_c',
        'is_active',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'expires_at' => 'datetime',
            'votes_a' => 'integer',
            'votes_b' => 'integer',
            'votes_c' => 'integer',
        ];
    }

    public function match()
    {
        return $this->belongsTo(CricketMatch::class, 'match_id');
    }

    public function votes()
    {
        return $this->hasMany(PollVote::class);
    }
}

