<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NewsApiConfig extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'endpoint_url',
        'auth_type',
        'api_key_header',
        'api_key_value',
        'additional_headers',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'additional_headers' => 'array',
            'is_active' => 'boolean',
        ];
    }
}

