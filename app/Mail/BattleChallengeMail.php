<?php

namespace App\Mail;

use App\Models\SquadBattle;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BattleChallengeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public SquadBattle $battle
    ) {}

    public function envelope(): Envelope
    {
        $challengerName = $this->battle->challenger?->captainTitle() ?? 'A Captain';

        return new Envelope(
            subject: "⚔️ {$challengerName} Challenged You: {$this->battle->match_title} ({$this->battle->modeLabel()}) | Cricwire"
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.battle-challenge',
            with: [
                'battle' => $this->battle,
                'challengerName' => $this->battle->challenger?->captainTitle() ?? 'Captain',
                'opponentName' => $this->battle->opponent?->captainTitle() ?? 'Captain',
                'battleUrl' => route('games.battle.show', $this->battle->slug),
                'locksAtIst' => $this->battle->locks_at?->copy()->timezone('Asia/Kolkata')->format('d M Y, h:i A') . ' IST',
            ],
        );
    }
}
