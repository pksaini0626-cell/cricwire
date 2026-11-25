<?php

namespace App\Http\Controllers\Cricket;

use App\Helpers\CricketMatchFormatter;
use App\Http\Controllers\Controller;
use App\Services\Cricket\CricketLiveApiService;
use App\Services\Cricket\MatchStateNormalizer;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UpcomingMatchController extends Controller
{
    public function __construct(
        protected CricketLiveApiService $apiService
    ) {}

    /**
     * Return genuinely unstarted 'upcoming' fixtures sorted chronologically.
     * Zero live-score/commentary API calls.
     */
    public function getUpcomingMatches(): array
    {
        $archivedIds = FinishedMatchController::getArchivedMatchIds();

        $homeData = $this->apiService->getHomeFromRedis();
        if (empty($homeData['upcoming'])) {
            $staticHomeFile = public_path('live-data/home.json');
            if (file_exists($staticHomeFile)) {
                $decoded = json_decode(@file_get_contents($staticHomeFile), true);
                if (is_array($decoded) && !empty($decoded['upcoming'])) {
                    $homeData['upcoming'] = $decoded['upcoming'];
                }
            }
        }

        $candidates = $this->apiService->filterAllowedMatches($homeData['upcoming'] ?? []);
        $upcoming = [];
        $seenIds = [];

        foreach ($candidates as $match) {
            if (!is_array($match)) {
                continue;
            }

            $matchId = (string) ($match['match_id'] ?? $match['id'] ?? '');
            if ($matchId === '' || isset($seenIds[$matchId])) {
                continue;
            }
            $seenIds[$matchId] = true;

            if (MatchStateNormalizer::classify($match, $archivedIds) !== 'upcoming') {
                continue;
            }

            $match['is_live'] = false;
            $match['is_completed'] = false;
            $upcoming[] = CricketMatchFormatter::formatMatchForCard($match);
        }

        usort($upcoming, function (array $a, array $b) {
            return $this->parseStartTimestamp($a) <=> $this->parseStartTimestamp($b);
        });

        return $upcoming;
    }

    /**
     * GET /api/matches/upcoming?page=1&per_page=10
     */
    public function index(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->query('page', 1));
        $perPage = max(1, min(50, (int) $request->query('per_page', 10)));

        $all = $this->getUpcomingMatches();
        $total = count($all);
        $offset = ($page - 1) * $perPage;
        $items = array_slice($all, $offset, $perPage);

        return response()->json([
            'status' => true,
            'data' => $items,
            'meta' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => max(1, (int) ceil($total / $perPage)),
            ],
        ]);
    }

    private function parseStartTimestamp(array $match): int
    {
        $raw = $match['start_time_ist'] ?? $match['match_time'] ?? $match['date_wise'] ?? null;
        if (empty($raw) || !is_string($raw)) {
            return PHP_INT_MAX;
        }
        try {
            return Carbon::parse($raw, 'Asia/Kolkata')->timestamp;
        } catch (\Throwable) {
            return PHP_INT_MAX;
        }
    }
}
