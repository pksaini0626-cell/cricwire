<?php

namespace App\Http\Controllers\Cricket;

use App\Http\Controllers\Controller;
use App\Services\Cricket\RapidApiCricketService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SessionController extends Controller
{
    /**
     * Cache TTL in seconds for real-time min/max tracking.
     */
    protected const TRACKER_TTL = 86400; // 24 hours

    /**
     * HTTP API Endpoint: Return complete structured session data for a match.
     *
     * @param string|int $matchId
     * @param RapidApiCricketService $service
     * @return JsonResponse
     */
    public function show(string|int $matchId, RapidApiCricketService $service): JsonResponse
    {
        $rawDetail = $service->getMatchDetail($matchId);
        $match = $rawDetail['data'] ?? $rawDetail ?? [];

        $table = self::getMatchSessionTable($matchId, $service, $match);

        return response()->json([
            'status' => true,
            'match_id' => $matchId,
            'data' => $table,
        ]);
    }

    /**
     * Core Hybrid Engine: Generates structured session tables for Innings 1 and Innings 2.
     *
     * @param string|int $matchId
     * @param RapidApiCricketService $service
     * @param array $matchDetail
     * @return array{innings_1: array, innings_2: array, active_inning: int, summary: array}
     */
    public static function getMatchSessionTable(string|int $matchId, RapidApiCricketService $service, array $matchDetail = []): array
    {
        // 1. Fetch /fancy and /overHistory strictly from cache (allowUpstreamFetch = false)
        $fancyData = $service->getMatchFancy($matchId, false);
        $overData = $service->getMatchOverHistory($matchId, false);

        // 2. Fetch min/max cache tracker
        $tracker = self::getMinMaxTracker($matchId);

        // 3. Update tracker from live /fancy or home data if new rates are present
        $tracker = self::updateMinMaxFromFancy($matchId, $fancyData, $matchDetail, $tracker);

        // 4. Determine format and default milestones
        $matchType = strtoupper(trim((string) ($matchDetail['match_type'] ?? 'T20')));
        $isODI = str_contains($matchType, 'ODI') || str_contains($matchType, '50');
        $defaultMilestones = $isODI ? [0, 10, 20, 30, 35, 40, 50] : [0, 6, 10, 15, 20];

        // 5. Parse innings over histories from /match/{matchId}/overHistory
        $inningsOverHistory = self::normalizeOverHistory($overData);

        // 6. Parse fancy sessions (grouped by inning)
        $parsedFancy = self::parseFancyPayload($fancyData, $matchDetail);

        // 7. Extract match team names and current odds
        $teamAName = $matchDetail['team_a_short'] ?? $matchDetail['team_a'] ?? 'Team 1';
        $teamBName = $matchDetail['team_b_short'] ?? $matchDetail['team_b'] ?? 'Team 2';
        $favTeam = $matchDetail['fav_team'] ?? $teamAName;

        $teamALogo = $matchDetail['team_a_img'] ?? '';
        $teamBLogo = $matchDetail['team_b_img'] ?? '';

        // Determine batting team for inning 1 & 2
        $currentInning = (int) ($matchDetail['current_inning'] ?? 1);
        $battingTeamInning1 = $teamAName;
        $battingTeamInning2 = $teamBName;
        $inning1Logo = $teamALogo;
        $inning2Logo = $teamBLogo;

        // If team_b batted first (e.g. toss decision or scores)
        if (!empty($matchDetail['toss'])) {
            $toss = strtolower($matchDetail['toss']);
            if (str_contains($toss, strtolower($teamBName)) && str_contains($toss, 'bat')) {
                $battingTeamInning1 = $teamBName;
                $battingTeamInning2 = $teamAName;
                $inning1Logo = $teamBLogo;
                $inning2Logo = $teamALogo;
            } elseif (str_contains($toss, strtolower($teamAName)) && str_contains($toss, 'bowl')) {
                $battingTeamInning1 = $teamBName;
                $battingTeamInning2 = $teamAName;
                $inning1Logo = $teamBLogo;
                $inning2Logo = $teamALogo;
            }
        }

        // Build Innings 1 Rows
        $rowsInnings1 = self::buildInningsRows(
            inning: 1,
            fancyItems: $parsedFancy['innings_1'] ?? [],
            overHistory: $inningsOverHistory[1] ?? [],
            defaultMilestones: $defaultMilestones,
            tracker: $tracker,
            matchDetail: $matchDetail,
            teamName: $battingTeamInning1
        );

        // Build Innings 2 Rows
        $rowsInnings2 = self::buildInningsRows(
            inning: 2,
            fancyItems: $parsedFancy['innings_2'] ?? [],
            overHistory: $inningsOverHistory[2] ?? [],
            defaultMilestones: $defaultMilestones,
            tracker: $tracker,
            matchDetail: $matchDetail,
            teamName: $battingTeamInning2
        );

        return [
            'innings_1' => [
                'inning' => 1,
                'team_name' => $battingTeamInning1,
                'team_logo' => $inning1Logo,
                'rows' => $rowsInnings1,
                'is_completed' => $currentInning > 1 || !empty($matchDetail['result']),
                'is_active' => $currentInning === 1,
            ],
            'innings_2' => [
                'inning' => 2,
                'team_name' => $battingTeamInning2,
                'team_logo' => $inning2Logo,
                'rows' => $rowsInnings2,
                'is_completed' => !empty($matchDetail['result']),
                'is_active' => $currentInning === 2,
            ],
            'active_inning' => $currentInning,
            'summary' => [
                'match_type' => $matchType,
                'fav_team' => $favTeam,
                'min_rate' => $matchDetail['min_rate'] ?? null,
                'max_rate' => $matchDetail['max_rate'] ?? null,
            ],
        ];
    }

    /**
     * Build row records for a specific innings using Hybrid Priority (API-first dynamic overs + Fallback).
     */
    protected static function buildInningsRows(
        int $inning,
        array $fancyItems,
        array $overHistory,
        array $defaultMilestones,
        array $tracker,
        array $matchDetail,
        string $teamName
    ): array {
        // Priority 1: Collect overs provided dynamically by /fancy
        $dynamicOvers = [];
        $fancyByOver = [];

        foreach ($fancyItems as $item) {
            $ov = $item['over'];
            $dynamicOvers[] = $ov;
            $fancyByOver[$ov] = $item;
        }

        // Priority 2: If dynamic overs are empty or match just started, fallback to skeleton
        if (empty($dynamicOvers)) {
            $dynamicOvers = $defaultMilestones;
        } else {
            // Ensure 0 over milestone is present if beginning of innings
            if (!in_array(0, $dynamicOvers, true)) {
                array_unshift($dynamicOvers, 0);
            }
            sort($dynamicOvers);
        }

        // Map overHistory by completed over integer
        $oversMap = [];
        foreach ($overHistory as $hist) {
            $ovNum = (int) ($hist['team']['over'] ?? 0);
            if ($ovNum > 0 && !isset($oversMap[$ovNum])) {
                $oversMap[$ovNum] = $hist;
            }
        }

        $rows = [];
        foreach ($dynamicOvers as $targetOver) {
            $trackerKey = "inn_{$inning}_ov_{$targetOver}";
            $track = $tracker[$trackerKey] ?? [];

            // Fancy session rate
            $fancy = $fancyByOver[$targetOver] ?? null;

            // Session rate range (e.g. 44-45)
            $rateMin = $fancy['min'] ?? $track['lowest'] ?? null;
            $rateMax = $fancy['max'] ?? $track['highest'] ?? null;
            $rateOpen = $track['open'] ?? null;
            $rateClose = $track['close'] ?? ($rateMax !== null && $rateMin !== null ? "{$rateMin}-{$rateMax}" : null);

            $lowest = $track['lowest'] ?? $rateMin;
            $highest = $track['highest'] ?? $rateMax;

            // Score and result matching from overHistory
            $hist = $oversMap[$targetOver] ?? null;
            $actualScore = null;
            $actualRuns = null;
            $passed = null; // null = pending / ongoing, true = pass ✔, false = fail ✘

            if ($targetOver === 0) {
                // Milestone 0 (Opening Session / 0 Overs)
                $actualScore = '0/0';
                $passed = null;
            } elseif ($hist) {
                $rawScore = (string) ($hist['team']['score'] ?? '');
                // Format score from "51-0" to "51/0" if necessary
                $formattedScore = str_replace('-', '/', $rawScore);
                $actualScore = $formattedScore ?: '-';

                // Extract runs integer for pass/fail comparison
                if (preg_match('/^(\d+)/', $actualScore, $mScore)) {
                    $actualRuns = (int) $mScore[1];
                }

                // Determine pass/fail based on session benchmark
                $benchmarkRuns = $fancy['yes_target'] ?? $rateMax ?? $rateMin ?? $track['close_runs'] ?? null;
                if ($actualRuns !== null && $benchmarkRuns !== null && is_numeric($benchmarkRuns)) {
                    $passed = $actualRuns >= (float) $benchmarkRuns;
                }
            }

            // Odds milestone format (e.g., "SL 1.13-1.14" or "BRH-W 47-48")
            $favTeamShort = $matchDetail['fav_team'] ?? $teamName;
            $minProb = $matchDetail['min_prob_1'] ?? $matchDetail['min_rate_1'] ?? $matchDetail['min_rate'] ?? '';
            $maxProb = $matchDetail['max_prob_1'] ?? $matchDetail['max_rate_1'] ?? $matchDetail['max_rate'] ?? '';
            $matchOddsStr = ($minProb && $maxProb) ? "{$favTeamShort} {$minProb}-{$maxProb}" : '-';

            $rows[] = [
                'over' => $targetOver,
                'rate_display' => $rateClose ?: ($rateMin && $rateMax ? "{$rateMin}-{$rateMax}" : '-'),
                'rate_min' => $rateMin,
                'rate_max' => $rateMax,
                'lowest' => $lowest,
                'highest' => $highest,
                'score' => $actualScore ?? '-',
                'actual_runs' => $actualRuns,
                'passed' => $passed,
                'match_odds' => $matchOddsStr,
                'status' => $hist ? 'completed' : ($targetOver === 0 ? 'opened' : 'pending'),
            ];
        }

        return $rows;
    }

    /**
     * Parse and normalize the raw `/fancy` API response into innings-indexed session objects.
     */
    protected static function parseFancyPayload(array $fancyData, array $matchDetail): array
    {
        $result = [
            'innings_1' => [],
            'innings_2' => [],
        ];

        $rawList = $fancyData['data'] ?? [];
        if (!is_array($rawList) || empty($rawList)) {
            // Also check matchDetail fallback: home matches sometimes have 's_ovr', 's_min', 's_max'
            $sOvr = (int) ($matchDetail['s_ovr'] ?? 0);
            $sMin = $matchDetail['s_min'] ?? 0;
            $sMax = $matchDetail['s_max'] ?? 0;
            $currentInning = (int) ($matchDetail['current_inning'] ?? 1);

            if ($sOvr > 0 && ($sMin > 0 || $sMax > 0)) {
                $targetKey = $currentInning === 2 ? 'innings_2' : 'innings_1';
                $result[$targetKey][] = [
                    'over' => $sOvr,
                    'title' => "{$sOvr} Over Runs",
                    'min' => $sMin,
                    'max' => $sMax,
                    'yes_target' => $sMax ?: $sMin,
                ];
            }

            return $result;
        }

        foreach ($rawList as $item) {
            if (!is_array($item)) {
                continue;
            }

            // Extract title/name
            $title = $item['title'] ?? $item['name'] ?? $item['sess_no'] ?? '';
            
            // Extract over number: e.g. "6 Over Runs", "10 Ovr", "1st Innings 20 Over Runs"
            $over = null;
            if (isset($item['over']) && is_numeric($item['over'])) {
                $over = (int) $item['over'];
            } elseif (preg_match('/(\d+)\s*(?:over|ovr|ov)\b/i', $title, $m)) {
                $over = (int) $m[1];
            }

            if ($over === null) {
                continue;
            }

            // Extract inning: check title or inning parameter
            $inning = 1;
            if (isset($item['inning']) && (int) $item['inning'] === 2) {
                $inning = 2;
            } elseif (stripos($title, '2nd inn') !== false || stripos($title, 'innings 2') !== false) {
                $inning = 2;
            }

            // Extract rates: supports standard keys (min/max, runsNo/runsYes, back_price/lay_price, s_min/s_max)
            $min = $item['min'] ?? $item['runs_no'] ?? $item['runsNo'] ?? $item['back_price'] ?? $item['s_min'] ?? null;
            $max = $item['max'] ?? $item['runs_yes'] ?? $item['runsYes'] ?? $item['lay_price'] ?? $item['s_max'] ?? null;
            $yesTarget = $max ?? $min;

            $entry = [
                'over' => $over,
                'title' => $title,
                'min' => $min,
                'max' => $max,
                'yes_target' => $yesTarget,
            ];

            $targetKey = $inning === 2 ? 'innings_2' : 'innings_1';
            $result[$targetKey][] = $entry;
        }

        return $result;
    }

    /**
     * Normalize over history data from `/match/{matchId}/overHistory`.
     * Supports formats:
     * - {"data": {"1": [...], "2": [...]}}
     * - {"data": [[...], [...]]}
     */
    protected static function normalizeOverHistory(array $overData): array
    {
        $raw = $overData['data'] ?? [];
        if (!is_array($raw)) {
            return [];
        }

        $normalized = [];

        // If keyed by "1" and "2" (innings numbers)
        if (isset($raw['1']) || isset($raw['2'])) {
            if (isset($raw['1']) && is_array($raw['1'])) {
                $normalized[1] = $raw['1'];
            }
            if (isset($raw['2']) && is_array($raw['2'])) {
                $normalized[2] = $raw['2'];
            }
            return $normalized;
        }

        // If indexed 0, 1
        if (isset($raw[0]) && is_array($raw[0])) {
            $normalized[1] = $raw[0];
        }
        if (isset($raw[1]) && is_array($raw[1])) {
            $normalized[2] = $raw[1];
        }

        return $normalized;
    }

    /**
     * Retrieve stored min/max session rates tracker from Cache.
     */
    public static function getMinMaxTracker(string|int $matchId): array
    {
        return Cache::get("cricket:match:{$matchId}:session_minmax", []);
    }

    /**
     * Update Min/Max tracking for sessions in Cache.
     */
    public static function updateMinMaxFromFancy(
        string|int $matchId,
        array $fancyData,
        array $matchDetail = [],
        ?array $existingTracker = null
    ): array {
        $tracker = $existingTracker ?? self::getMinMaxTracker($matchId);
        $dirty = false;

        $parsed = self::parseFancyPayload($fancyData, $matchDetail);

        foreach ([1 => 'innings_1', 2 => 'innings_2'] as $inningNum => $key) {
            foreach ($parsed[$key] as $item) {
                $ov = $item['over'];
                $min = $item['min'];
                $max = $item['max'];

                if ($min === null && $max === null) {
                    continue;
                }

                $effectiveVal = (float) ($max ?? $min);
                $trackKey = "inn_{$inningNum}_ov_{$ov}";

                if (!isset($tracker[$trackKey])) {
                    $tracker[$trackKey] = [
                        'open' => "{$min}-{$max}",
                        'close' => "{$min}-{$max}",
                        'close_runs' => $effectiveVal,
                        'lowest' => $min !== null ? (float) $min : $effectiveVal,
                        'highest' => $max !== null ? (float) $max : $effectiveVal,
                        'last_updated' => now()->timestamp,
                    ];
                    $dirty = true;
                } else {
                    $cur = $tracker[$trackKey];
                    $curLowest = $cur['lowest'] ?? $effectiveVal;
                    $curHighest = $cur['highest'] ?? $effectiveVal;

                    $newLowest = min($curLowest, $min !== null ? (float) $min : $effectiveVal);
                    $newHighest = max($curHighest, $max !== null ? (float) $max : $effectiveVal);

                    if ($newLowest != $curLowest || $newHighest != $curHighest || ($cur['close'] !== "{$min}-{$max}")) {
                        $tracker[$trackKey]['close'] = "{$min}-{$max}";
                        $tracker[$trackKey]['close_runs'] = $effectiveVal;
                        $tracker[$trackKey]['lowest'] = $newLowest;
                        $tracker[$trackKey]['highest'] = $newHighest;
                        $tracker[$trackKey]['last_updated'] = now()->timestamp;
                        $dirty = true;
                    }
                }
            }
        }

        if ($dirty) {
            Cache::put("cricket:match:{$matchId}:session_minmax", $tracker, self::TRACKER_TTL);
        }

        return $tracker;
    }
}
