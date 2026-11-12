<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Team extends Model
{
    use HasFactory;

    protected $fillable = [
        'api_team_id',
        'name',
        'short_name',
        'slug',
        'logo_url',
    ];

    public function homeMatches()
    {
        return $this->hasMany(CricketMatch::class, 'team_a_id');
    }

    public function awayMatches()
    {
        return $this->hasMany(CricketMatch::class, 'team_b_id');
    }
}

