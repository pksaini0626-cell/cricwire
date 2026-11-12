<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ranking extends Model
{
    use HasFactory;

    protected $fillable = [
        'gender',
        'format',
        'category',
        'rank_data_json',
    ];

    protected function casts(): array
    {
        return [
            'rank_data_json' => 'array',
        ];
    }
}

