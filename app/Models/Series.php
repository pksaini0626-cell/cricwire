<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Series extends Model
{
    use HasFactory;

    protected $table = 'series';

    protected $fillable = [
        'api_series_id',
        'name',
        'slug',
        'type',
        'start_date',
        'end_date',
        'is_popular',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'is_popular' => 'boolean',
        ];
    }

    public function matches()
    {
        return $this->hasMany(CricketMatch::class, 'series_id');
    }

    public function newsArticles()
    {
        return $this->hasMany(NewsArticle::class, 'series_id');
    }
}

