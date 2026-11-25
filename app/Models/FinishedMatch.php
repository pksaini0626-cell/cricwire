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
        'match_json',
    ];

    protected $casts = [
        'match_json' => 'array',
    ];
}
