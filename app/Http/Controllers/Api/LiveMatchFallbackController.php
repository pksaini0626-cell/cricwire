<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CricketMatch;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Redis;

class LiveMatchFallbackController extends Controller
{
    /**
     * Return live match payload backed by HTTP edge cache headers.
     */
    public function show(string $id): JsonResponse
    {
        // Try redis first
        $cachedPayload = Redis::get("cricket:match:{$id}:live_line");
        
        if ($cachedPayload) {
            $data = json_decode($cachedPayload, true);
        } else {
            // Find match from DB
            $match = CricketMatch::where('api_match_id', $id)
                ->orWhere('id', $id)
                ->orWhere('slug', $id)
                ->with(['teamA', 'teamB'])
                ->first();

            if (!$match) {
                return response()->json(['error' => 'Match not found'], 404);
            }

            $data = [
                'api_match_id' => $match->api_match_id,
                'match_title' => $match->match_title,
                'status' => $match->status,
                'venue' => $match->venue,
                'start_time' => $match->start_time->toIso8601String(),
                'team_a' => $match->teamA?->name,
                'team_b' => $match->teamB?->name,
                'scorecard' => $match->scorecard_json,
                'toss_info' => $match->toss_info,
                'result_summary' => $match->result_summary,
            ];
        }

        return response()->json($data)
            ->header('Cache-Control', 'public, max-age=5, s-maxage=5, stale-while-revalidate=10')
            ->header('ETag', md5(json_encode($data)));
    }
}

