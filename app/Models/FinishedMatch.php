<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FinishedMatch extends Model
{
    protected $table = 'finished_matches';

    protected $fillable = [
        'match_id',
        'series_name',
        'match_title',
        'result_text',
        'score_a',
        'score_b',
        'match_data',
        'match_json',
        'fetched_at',
        'archived_at',
    ];

    protected $casts = [
        'match_data' => 'array',
        'match_json' => 'array',
        'fetched_at' => 'datetime',
        'archived_at' => 'datetime',
    ];
}
