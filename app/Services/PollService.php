<?php

namespace App\Services;

use App\Models\Poll;
use App\Models\PollVote;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class PollService
{
    /**
     * Submit vote into Redis for high-frequency writes.
     */
    public function vote(int $pollId, ?int $userId, string $ipAddress, string $option): array
    {
        $option = strtoupper($option);
        if (!in_array($option, ['A', 'B', 'C'])) {
            return ['success' => false, 'message' => 'Invalid option selected.'];
        }

        $poll = Poll::find($pollId);
        if (!$poll || !$poll->is_active || ($poll->expires_at && $poll->expires_at->isPast())) {
            return ['success' => false, 'message' => 'Poll is not active or has expired.'];
        }

        // Concurrency duplicate vote check
        if ($userId) {
            $userVotedKey = "poll:{$pollId}:user:{$userId}";
            if (Redis::get($userVotedKey) || PollVote::where('poll_id', $pollId)->where('user_id', $userId)->exists()) {
                return ['success' => false, 'message' => 'You have already voted on this poll.'];
            }
            Redis::setex($userVotedKey, 86400 * 7, $option);
        } else {
            // IP check for anonymous voting
            $ipVotedKey = "poll:{$pollId}:ip:{$ipAddress}";
            if (Redis::get($ipVotedKey) || PollVote::where('poll_id', $pollId)->where('ip_address', $ipAddress)->exists()) {
                return ['success' => false, 'message' => 'Vote already registered from this network address.'];
            }
            Redis::setex($ipVotedKey, 86400 * 2, $option);
        }

        // Store vote log in a Redis list for sync worker
        $voteRecord = json_encode([
            'poll_id' => $pollId,
            'user_id' => $userId,
            'ip_address' => $ipAddress,
            'selected_option' => $option,
            'created_at' => now()->toDateTimeString(),
        ]);
        Redis::rpush('poll:votes:queue', $voteRecord);

        // Atomic counters in Redis
        $redisKey = "poll:{$pollId}:counts";
        $field = strtolower("votes_{$option}");
        $newCount = Redis::hincrby($redisKey, $field, 1);

        // Track active modified polls
        Redis::sadd('poll:dirty_set', $pollId);

        return [
            'success' => true,
            'message' => 'Vote counted successfully.',
            'votes' => Redis::hgetall($redisKey),
        ];
    }

    /**
     * Get real-time vote breakdown combining Redis and DB cache.
     */
    public function getPollCounts(int $pollId): array
    {
        $redisKey = "poll:{$pollId}:counts";
        $cached = Redis::hgetall($redisKey);

        if (!empty($cached)) {
            return [
                'votes_a' => (int) ($cached['votes_a'] ?? 0),
                'votes_b' => (int) ($cached['votes_b'] ?? 0),
                'votes_c' => (int) ($cached['votes_c'] ?? 0),
            ];
        }

        $poll = Poll::find($pollId);
        if (!$poll) {
            return ['votes_a' => 0, 'votes_b' => 0, 'votes_c' => 0];
        }

        Redis::hmset($redisKey, [
            'votes_a' => $poll->votes_a,
            'votes_b' => $poll->votes_b,
            'votes_c' => $poll->votes_c,
        ]);
        Redis::expire($redisKey, 86400);

        return [
            'votes_a' => (int) $poll->votes_a,
            'votes_b' => (int) $poll->votes_b,
            'votes_c' => (int) $poll->votes_c,
        ];
    }

    /**
     * Flush queued votes and totals to MySQL.
     */
    public function syncVotesToDatabase(): void
    {
        // 1. Drain vote queue
        $queuedVotes = [];
        while ($item = Redis::lpop('poll:votes:queue')) {
            $data = json_decode($item, true);
            if ($data) {
                $queuedVotes[] = [
                    'poll_id' => $data['poll_id'],
                    'user_id' => $data['user_id'],
                    'ip_address' => $data['ip_address'],
                    'selected_option' => $data['selected_option'],
                    'created_at' => $data['created_at'],
                    'updated_at' => $data['created_at'],
                ];
            }
        }

        if (!empty($queuedVotes)) {
            // Bulk insert ignore duplicates
            DB::table('poll_votes')->insertOrIgnore($queuedVotes);
        }

        // 2. Sync totals from Redis into polls table
        $dirtyPollIds = Redis::smembers('poll:dirty_set');
        foreach ($dirtyPollIds as $pollId) {
            $counts = Redis::hgetall("poll:{$pollId}:counts");
            if (!empty($counts)) {
                Poll::where('id', $pollId)->update([
                    'votes_a' => (int) ($counts['votes_a'] ?? 0),
                    'votes_b' => (int) ($counts['votes_b'] ?? 0),
                    'votes_c' => (int) ($counts['votes_c'] ?? 0),
                ]);
            }
            Redis::srem('poll:dirty_set', $pollId);
        }
    }
}

