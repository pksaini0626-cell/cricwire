<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CricketMatch extends Model
{
    use HasFactory;

    protected $table = 'matches';

    protected $fillable = [
        'api_match_id',
        'series_id',
        'team_a_id',
        'team_b_id',
        'slug',
        'match_title',
        'format',
        'venue',
        'start_time',
        'status',
        'is_pinned',
        'toss_info',
        'result_summary',
        'scorecard_json',
        'seo_title',
        'seo_description',
    ];

    protected function casts(): array
    {
        return [
            'start_time' => 'datetime',
            'is_pinned' => 'boolean',
            'scorecard_json' => 'array',
        ];
    }

    public function series()
    {
        return $this->belongsTo(Series::class, 'series_id');
    }

    public function teamA()
    {
        return $this->belongsTo(Team::class, 'team_a_id');
    }

    public function teamB()
    {
        return $this->belongsTo(Team::class, 'team_b_id');
    }

    public function polls()
    {
        return $this->hasMany(Poll::class, 'match_id');
    }

    public function newsArticles()
    {
        return $this->hasMany(NewsArticle::class, 'match_id');
    }
}

