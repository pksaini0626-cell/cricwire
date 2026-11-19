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
        'series_name',
        'match_desc',
        'format',
        'team_a',
        'team_b',
        'state',
        'start_time',
        'is_covered',
        'is_featured',
    ];

    protected $casts = [
        'match_id' => 'integer',
        'is_covered' => 'boolean',
        'is_featured' => 'boolean',
    ];
}
