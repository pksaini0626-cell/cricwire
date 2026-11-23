<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CompletedMatchScorecard extends Model
{
    use HasFactory;

    protected $fillable = [
        'match_id',
        'series_name',
        'match_title',
        'result_text',
        'json_path',
        'scorecard_payload',
    ];

    protected $casts = [
        'scorecard_payload' => 'array',
    ];
}
