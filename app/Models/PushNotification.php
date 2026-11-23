<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PushNotification extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'body',
        'image_url',
        'type',
        'target_url',
        'audience',
        'status',
        'total_targeted',
        'sent_count',
        'failed_count',
        'sent_at',
        'created_by',
    ];

    protected $casts = [
        'total_targeted' => 'integer',
        'sent_count' => 'integer',
        'failed_count' => 'integer',
        'sent_at' => 'datetime',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
