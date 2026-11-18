<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LiveScoreUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $apiMatchId;
    public array $payload;

    /**
     * Create a new event instance.
     */
    public function __construct(string $apiMatchId, array $payload)
    {
        $this->apiMatchId = $apiMatchId;
        $this->payload = $payload;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel("match.{$this->apiMatchId}"),
            new Channel('matches.live.ticker'),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'LiveScoreUpdated';
    }

    /**
     * Data to broadcast.
     */
    public function broadcastWith(): array
    {
        return array_merge([
            'api_match_id' => $this->apiMatchId,
            'match_id' => $this->apiMatchId,
            'payload' => $this->payload,
            'timestamp' => now()->toISOString(),
        ], $this->payload);
    }
}

