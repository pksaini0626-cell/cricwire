<?php

namespace App\Http\Controllers\Cricket;

use App\Helpers\CricketMatchFormatter;
use App\Http\Controllers\Controller;
use App\Models\FinishedMatch;
use App\Services\Cricket\CricketLiveApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class CompletedMatchController extends Controller
{
    /**
     * Detect whether a match payload represents a finished / recent / completed state.
     */
    public static function isFinishedMatch(array $match): bool
    {
        $state = strtolower(trim((string) ($match['state'] ?? $match['status'] ?? $match['match_status'] ?? '')));
        $result = strtolower(trim((string) ($match['result'] ?? $match['result_text'] ?? '')));

        if (in_array($state, ['finished', 'complete', 'completed', '3'], true)) {
            return true;
        }

        $keywords = [
            'finished',
            'complete',
            'completed',
            'won by',
            'match drawn',
            'match tied',
            'no result',
            'abandoned',
        ];

        foreach ($keywords as $kw) {
            if ($state !== '' && str_contains($state, $kw)) {
                return true;
            }
            if ($result !== '' && str_contains($result, $kw)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Load a finished match from DB (0 external API calls) or fetch once, normalize scores,
     * persist JSON to `finished_matches`, and return the structured payload.
     */
    public function getOrStoreFinishedMatch(string|int $matchId, array $contextMatch = []): array
    {
        $matchKey = (string) $matchId;

        $existing = FinishedMatch::where('match_id', $matchKey)->first();
        if ($existing) {
            $storedPayload = is_array($existing->match_data) && !empty($existing->match_data)
                ? $existing->match_data
                : $existing->match_json;

            if (is_array($storedPayload) && !empty($storedPayload)) {
                return $storedPayload;
            }
        }

        $apiService = app(CricketLiveApiService::class);

        $baseMatch = $contextMatch;
        if (empty($baseMatch)) {
            try {
                $baseMatch = $apiService->getFastMatchData($matchId) ?? [];
            } catch (\Throwable $e) {
                Log::warning("CompletedMatchController: getFastMatchData failed for {$matchKey}: " . $e->getMessage());
                $baseMatch = [];
            }
        }

        $scorecard = [];
        try {
            $scorecardRes = $apiService->getScorecard($matchId);
            $scorecard = $scorecardRes['innings'] ?? $scorecardRes ?? [];
        } catch (\Throwable $e) {
            Log::warning("CompletedMatchController: getScorecard failed for {$matchKey}: " . $e->getMessage());
        }

        if (empty($scorecard) && !empty($baseMatch['scorecard']) && is_array($baseMatch['scorecard'])) {
            $scorecard = $baseMatch['scorecard'];
        }

        $normalized = $this->normalizeScoresAndPayload($baseMatch, $scorecard, $matchKey);

        $isFinished = self::isFinishedMatch($normalized) || $apiService->isFinishedMatch($matchId);
        if ($isFinished) {
            $normalized['is_live'] = false;
            $normalized['match_status'] = 'Finished';

            try {
                FinishedMatch::updateOrCreate(
                    ['match_id' => $matchKey],
                    [
                        'series_name' => $normalized['series'] ?? $normalized['series_name'] ?? null,
                        'match_title' => $normalized['matchs'] ?? $normalized['match_desc'] ?? trim(($normalized['team_a'] ?? '') . ' vs ' . ($normalized['team_b'] ?? '')),
                        'result_text' => $normalized['result'] ?? null,
                        'match_data' => $normalized,
                        'match_json' => $normalized,
                        'fetched_at' => now(),
                    ]
                );
            } catch (\Throwable $e) {
                Log::error("CompletedMatchController: failed to save finished match {$matchKey}: " . $e->getMessage());
            }
        }

        return $normalized;
    }

    /**
     * JSON endpoint for finished match data.
     */
    public function showJson(string|int $matchId): JsonResponse
    {
        $payload = $this->getOrStoreFinishedMatch($matchId);

        return response()->json([
            'status' => !empty($payload),
            'match_id' => (string) $matchId,
            'data' => $payload,
        ]);
    }

    /**
     * Normalize both teams' scores from innings/scorecard/context so neither team shows '-' when scores exist.
     */
    protected function normalizeScoresAndPayload(array $match, array $scorecard, string $matchId): array
    {
        $teamA = trim((string) ($match['team_a'] ?? ''));
        $teamB = trim((string) ($match['team_b'] ?? ''));
        $teamAShort = trim((string) ($match['team_a_short'] ?? ''));
        $teamBShort = trim((string) ($match['team_b_short'] ?? ''));

        $scoreA = $this->cleanScore($match['score_a'] ?? $match['team_a_scores'] ?? $match['team_a_score'] ?? null);
        $scoreB = $this->cleanScore($match['score_b'] ?? $match['team_b_scores'] ?? $match['team_b_score'] ?? null);

        // Extract scores from innings/scorecard if either team score is missing
        $inningsList = is_array($scorecard) ? array_values($scorecard) : [];
        foreach ($inningsList as $idx => $inn) {
            if (!is_array($inn)) {
                continue;
            }
            $innScore = $this->formatInningScore($inn);
            if ($innScore === null) {
                continue;
            }

            $innTeam = strtolower(trim((string) ($inn['team'] ?? $inn['bat_team'] ?? $inn['team_name'] ?? '')));
            $matchedA = $this->teamMatches($innTeam, $teamA, $teamAShort);
            $matchedB = $this->teamMatches($innTeam, $teamB, $teamBShort);

            if ($matchedA && $scoreA === null) {
                $scoreA = $innScore;
            } elseif ($matchedB && $scoreB === null) {
                $scoreB = $innScore;
            } elseif ($idx === 0 && $scoreA === null) {
                $scoreA = $innScore;
            } elseif ($idx === 1 && $scoreB === null) {
                $scoreB = $innScore;
            }
        }

        if ($scoreA !== null) {
            $match['score_a'] = $scoreA;
            $match['team_a_scores'] = $scoreA;
        }
        if ($scoreB !== null) {
            $match['score_b'] = $scoreB;
            $match['team_b_scores'] = $scoreB;
        }

        if (empty($match['score']) && ($scoreA !== null || $scoreB !== null)) {
            $parts = array_filter([$scoreA, $scoreB], fn ($s) => $s !== null && $s !== '');
            $match['score'] = implode(' vs ', $parts);
        }

        $match['match_id'] = $match['match_id'] ?? $matchId;
        if (!empty($scorecard)) {
            $match['scorecard'] = $scorecard;
        }

        return $match;
    }

    protected function cleanScore(mixed $value): ?string
    {
        if (!is_string($value) && !is_numeric($value)) {
            return null;
        }
        $normalized = CricketMatchFormatter::normalizeScoreString((string) $value);
        $trimmed = trim((string) $normalized);
        if ($trimmed === '' || $trimmed === '-' || $trimmed === 'N/A') {
            return null;
        }
        return $trimmed;
    }

    protected function formatInningScore(array $inn): ?string
    {
        if (!empty($inn['score_str']) || !empty($inn['score'])) {
            $raw = (string) ($inn['score_str'] ?? $inn['score']);
            return $this->cleanScore($raw);
        }

        $runs = $inn['runs'] ?? $inn['total_runs'] ?? $inn['score_runs'] ?? null;
        if ($runs === null || $runs === '') {
            return null;
        }

        $wickets = $inn['wickets'] ?? $inn['wkts'] ?? $inn['total_wickets'] ?? null;
        $overs = $inn['overs'] ?? $inn['total_overs'] ?? null;

        $score = $wickets !== null && $wickets !== '' ? "{$runs}/{$wickets}" : (string) $runs;
        if ($overs !== null && $overs !== '') {
            $score .= " ({$overs})";
        }

        return $this->cleanScore($score);
    }

    protected function teamMatches(string $innTeam, string $teamName, string $teamShort): bool
    {
        if ($innTeam === '') {
            return false;
        }
        $a = strtolower($teamName);
        $s = strtolower($teamShort);
        if ($a !== '' && (str_contains($innTeam, $a) || str_contains($a, $innTeam))) {
            return true;
        }
        if ($s !== '' && ($innTeam === $s || str_contains($innTeam, $s))) {
            return true;
        }
        return false;
    }
}
