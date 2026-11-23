<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ManagedMatch extends Model
{
    use HasFactory;

    protected $table = 'managed_matches';

    protected $fillable = [
        'match_id',
        'series_id',
        'series_name',
        'match_number',
        'match_desc',
        'format',
        'team_a',
        'team_b',
        'state',
        'start_time',
        'start_time_ist',
        'is_covered',
        'series_skipped',
        'pre_match_archived',
        'toss_fetched',
        'toss_text',
        'toss_fetched_at',
        'playing_xi_fetched',
        'lifecycle_stage',
        'is_featured',
    ];

    protected $casts = [
        'match_id' => 'integer',
        'is_covered' => 'boolean',
        'series_skipped' => 'boolean',
        'pre_match_archived' => 'boolean',
        'toss_fetched' => 'boolean',
        'playing_xi_fetched' => 'boolean',
        'is_featured' => 'boolean',
        'start_time_ist' => 'datetime',
        'toss_fetched_at' => 'datetime',
    ];
}
