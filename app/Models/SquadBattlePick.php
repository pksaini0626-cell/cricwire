<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SquadBattlePick extends Model
{
    use HasFactory;

    protected $fillable = [
        'squad_battle_id',
        'user_id',
        'player_id',
        'player_name',
        'player_team',
        'player_role',
        'pick_order',
        'is_playing_xi',
        'runs_scored',
        'did_not_bat',
    ];

    protected function casts(): array
    {
        return [
            'pick_order' => 'integer',
            'is_playing_xi' => 'boolean',
            'runs_scored' => 'integer',
            'did_not_bat' => 'boolean',
        ];
    }

    public function battle(): BelongsTo
    {
        return $this->belongsTo(SquadBattle::class, 'squad_battle_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
