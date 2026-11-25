<?php

namespace App\Services\Cricket;

use Carbon\Carbon;

class MatchStateNormalizer
{
    /**
     * Keywords in state/status/match_status/match_desc indicating a finished match.
     */
    private const FINISHED_STATE_KEYWORDS = [
        'complete',
        'completed',
        'finished',
        'final',
        'result',
        'abandon',
        'abandoned',
        'cancel',
        'cancelled',
        'draw',
        'drawn',
        'tie',
        'tied',
        'no result',
        'ended',
    ];

    /**
     * Phrases in result/status text indicating a finished match.
     */
    private const FINISHED_RESULT_PHRASES = [
        'won by',
        'beat ',
        'defeated',
        'match drawn',
        'match tied',
        'no result',
        'abandoned',
        'series drawn',
        'awarded',
        'target chased',
    ];

    /**
     * Keywords in state/status indicating an active live match.
     */
    private const LIVE_STATE_KEYWORDS = [
        'live',
        'in progress',
        'innings break',
        'stumps',
        'lunch',
        'tea',
        'day ',
        'rain',
        'delay',
    ];

    /**
     * Single authoritative classifier: returns 'live', 'upcoming', or 'finished'.
     *
     * @param array<string, mixed> $match
     * @param array<int|string, bool|int|string> $archivedIds Map or list of archived match IDs from finished_matches
     */
    public static function classify(array $match, array $archivedIds = []): string
    {
        $matchId = (string) ($match['match_id'] ?? $match['id'] ?? '');

        // Rule A — DB Archive Override (Highest Priority)
        if ($matchId !== '' && (isset($archivedIds[$matchId]) || in_array($matchId, $archivedIds, true))) {
            return 'finished';
        }

        // Rule B — Finished / Completed Detection
        if (self::isFinished($match)) {
            return 'finished';
        }

        // Rule C — Live Detection
        if (self::isLive($match)) {
            return 'live';
        }

        // Rule D — Upcoming Default
        return 'upcoming';
    }

    /**
     * Rule B: Determine if a match is finished/completed.
     */
    public static function isFinished(array $match): bool
    {
        $state = strtolower(trim((string) ($match['state'] ?? '')));
        $status = strtolower(trim((string) ($match['status'] ?? '')));
        $matchStatus = strtolower(trim((string) ($match['match_status'] ?? '')));
        $matchDesc = strtolower(trim((string) ($match['match_desc'] ?? '')));
        $result = strtolower(trim((string) ($match['result'] ?? $match['result_text'] ?? '')));

        if (in_array($state, ['3', 'finished', 'complete', 'completed', 'final'], true)) {
            return true;
        }

        $stateFields = array_filter([$state, $status, $matchStatus, $matchDesc], fn ($v) => $v !== '');
        foreach ($stateFields as $field) {
            foreach (self::FINISHED_STATE_KEYWORDS as $kw) {
                if (str_contains($field, $kw)) {
                    return true;
                }
            }
        }

        $resultFields = array_filter([$result, $status], fn ($v) => $v !== '');
        foreach ($resultFields as $field) {
            foreach (self::FINISHED_RESULT_PHRASES as $phrase) {
                if (str_contains($field, $phrase)) {
                    return true;
                }
            }
        }

        // Chasing innings target achieved or 2nd innings all out / overs exhausted
        $target = (int) ($match['target'] ?? $match['target_runs'] ?? 0);
        if ($target > 0) {
            $currentRuns = self::extractCurrentRuns($match);
            if ($currentRuns !== null && $currentRuns >= $target) {
                return true;
            }

            $liveInning = $match['live_inning'] ?? [];
            if (is_array($liveInning) && !empty($liveInning)) {
                $wickets = (int) ($liveInning['wickets'] ?? $liveInning['wkts'] ?? 0);
                if ($wickets >= 10) {
                    return true;
                }
            }
        }

        // Stale Live Timeout Guard
        if (self::isStaleLiveMatch($match, $state, $status, $result)) {
            return true;
        }

        return false;
    }

    /**
     * Rule C: Determine if a match is actively live.
     */
    public static function isLive(array $match): bool
    {
        if (self::isFinished($match)) {
            return false;
        }

        $state = strtolower(trim((string) ($match['state'] ?? '')));
        $status = strtolower(trim((string) ($match['status'] ?? $match['match_status'] ?? '')));

        foreach (self::LIVE_STATE_KEYWORDS as $kw) {
            if (($state !== '' && str_contains($state, $kw)) || ($status !== '' && str_contains($status, $kw))) {
                return true;
            }
        }

        $scores = self::extractRawScores($match);
        if (!self::isEmptyScore($scores['score_a']) || !self::isEmptyScore($scores['score_b'])) {
            return true;
        }

        $needRunBall = trim((string) ($match['need_run_ball'] ?? ''));
        $target = (int) ($match['target'] ?? $match['target_runs'] ?? 0);
        $toss = trim((string) ($match['toss'] ?? ''));

        if ($needRunBall !== '' || $target > 0 || $toss !== '') {
            return true;
        }

        return false;
    }

    /**
     * Normalize both teams' scores from all possible keys & optional scorecard innings,
     * enforcing the strict Anti-"Yet to bat" Guarantee.
     *
     * @return array{score_a: string, score_b: string}
     */
    public static function normalizeScores(array $match, array $scorecard = [], ?string $bucket = null): array
    {
        $bucket = $bucket ?? self::classify($match);
        $raw = self::extractRawScores($match);
        $scoreA = $raw['score_a'];
        $scoreB = $raw['score_b'];

        // Hydrate from scorecard innings if either score is missing
        if ((self::isEmptyScore($scoreA) || self::isEmptyScore($scoreB)) && !empty($scorecard)) {
            $innings = $scorecard['innings'] ?? $scorecard['scorecard'] ?? $scorecard;
            if (is_array($innings)) {
                $teamAName = strtolower(trim((string) ($match['team_a'] ?? $match['team_a_short'] ?? '')));
                $teamBName = strtolower(trim((string) ($match['team_b'] ?? $match['team_b_short'] ?? '')));

                foreach ($innings as $idx => $inn) {
                    if (!is_array($inn)) {
                        continue;
                    }
                    $formatted = self::formatInningScore($inn);
                    if ($formatted === '') {
                        continue;
                    }

                    $innTeam = strtolower(trim((string) ($inn['batting_team'] ?? $inn['team_name'] ?? $inn['team'] ?? '')));
                    $matchedA = $teamAName !== '' && $innTeam !== '' && (str_contains($innTeam, $teamAName) || str_contains($teamAName, $innTeam));
                    $matchedB = $teamBName !== '' && $innTeam !== '' && (str_contains($innTeam, $teamBName) || str_contains($teamBName, $innTeam));

                    if ($matchedA && self::isEmptyScore($scoreA)) {
                        $scoreA = $formatted;
                    } elseif ($matchedB && self::isEmptyScore($scoreB)) {
                        $scoreB = $formatted;
                    } elseif ((int) $idx === 0 && self::isEmptyScore($scoreA)) {
                        $scoreA = $formatted;
                    } elseif ((int) $idx === 1 && self::isEmptyScore($scoreB)) {
                        $scoreB = $formatted;
                    }
                }
            }
        }

        // Clean placeholder strings
        if (self::isEmptyScore($scoreA)) {
            $scoreA = '';
        }
        if (self::isEmptyScore($scoreB)) {
            $scoreB = '';
        }

        // Absolute "Yet to bat" Rendering Rule
        if ($bucket === 'finished') {
            if ($scoreA === '') {
                $scoreA = 'DNB';
            }
            if ($scoreB === '') {
                $scoreB = 'DNB';
            }
            return ['score_a' => $scoreA, 'score_b' => $scoreB];
        }

        if ($bucket === 'live') {
            $hasPlayStarted = trim((string) ($match['need_run_ball'] ?? '')) !== ''
                || (int) ($match['target'] ?? $match['target_runs'] ?? 0) > 0
                || trim((string) ($match['toss'] ?? '')) !== '';

            // 1st innings: one team has a score, the other may show "Yet to bat"
            if ($scoreA !== '' && $scoreB === '') {
                $scoreB = 'Yet to bat';
            } elseif ($scoreB !== '' && $scoreA === '') {
                $scoreA = 'Yet to bat';
            } elseif ($scoreA === '' && $scoreB === '' && $hasPlayStarted) {
                // Never render "Yet to bat" on BOTH teams when play has begun
                $scoreA = 'In Progress';
                $scoreB = '-';
            }
        }

        return ['score_a' => $scoreA, 'score_b' => $scoreB];
    }

    /**
     * Extract raw score strings from all known payload keys.
     *
     * @return array{score_a: string, score_b: string}
     */
    public static function extractRawScores(array $match): array
    {
        $scoreA = self::firstNonEmptyString([
            $match['score_a'] ?? null,
            $match['team_a_scores'] ?? null,
            $match['team_a_score'] ?? null,
        ]);

        $scoreB = self::firstNonEmptyString([
            $match['score_b'] ?? null,
            $match['team_b_scores'] ?? null,
            $match['team_b_score'] ?? null,
        ]);

        if (($scoreA === '' || $scoreB === '') && !empty($match['live_inning']) && is_array($match['live_inning'])) {
            $liveInnFormatted = self::formatInningScore($match['live_inning']);
            if ($liveInnFormatted !== '') {
                if ($scoreA === '') {
                    $scoreA = $liveInnFormatted;
                } elseif ($scoreB === '') {
                    $scoreB = $liveInnFormatted;
                }
            }
        }

        return ['score_a' => $scoreA, 'score_b' => $scoreB];
    }

    /**
     * Format an inning array into "runs/wickets (overs ov)" or "runs/wickets (overs)".
     */
    public static function formatInningScore(array $inn): string
    {
        if (!empty($inn['score']) && is_string($inn['score'])) {
            return trim($inn['score']);
        }

        $runs = $inn['runs'] ?? $inn['score_runs'] ?? null;
        if ($runs === null || $runs === '') {
            return '';
        }

        $wickets = $inn['wickets'] ?? $inn['wkts'] ?? 0;
        $overs = $inn['overs'] ?? $inn['over'] ?? '';

        $base = "{$runs}/{$wickets}";
        if ($overs !== '' && $overs !== null) {
            $base .= " ({$overs} ov)";
        }

        return $base;
    }

    /**
     * Check if a score string is effectively empty or a placeholder.
     */
    public static function isEmptyScore(?string $score): bool
    {
        if ($score === null) {
            return true;
        }
        $lower = strtolower(trim($score));
        return in_array($lower, ['', 'yet to bat', 'null', 'n/a', '-', '0/0', '0/0 (0)', '0/0 (0.0)'], true);
    }

    /**
     * Extract current runs from live_inning or score_b/score_a for target comparison.
     */
    private static function extractCurrentRuns(array $match): ?int
    {
        if (!empty($match['live_inning']) && is_array($match['live_inning'])) {
            $runs = $match['live_inning']['runs'] ?? null;
            if (is_numeric($runs)) {
                return (int) $runs;
            }
        }

        $raw = self::extractRawScores($match);
        foreach ([$raw['score_b'], $raw['score_a']] as $score) {
            if ($score !== '' && preg_match('/^(\d+)\s*\//', trim($score), $m)) {
                return (int) $m[1];
            }
        }

        return null;
    }

    /**
     * Stale Live Timeout Guard:
     * If state says live/in progress, result is empty, and start_time_ist was > 6h ago (T20/T10/Hundred)
     * or > 12h ago (ODI), classify as finished.
     */
    private static function isStaleLiveMatch(array $match, string $state, string $status, string $result): bool
    {
        if ($result !== '') {
            return false;
        }

        $looksLive = str_contains($state, 'live') || str_contains($state, 'in progress')
            || str_contains($status, 'live') || str_contains($status, 'in progress');

        if (!$looksLive) {
            return false;
        }

        $startRaw = $match['start_time_ist'] ?? $match['match_time'] ?? $match['date_wise'] ?? null;
        if (empty($startRaw) || !is_string($startRaw)) {
            return false;
        }

        try {
            $startTime = Carbon::parse($startRaw, 'Asia/Kolkata');
        } catch (\Throwable) {
            return false;
        }

        $format = strtolower((string) ($match['match_type'] ?? $match['format'] ?? $match['matchs'] ?? ''));
        $maxHours = str_contains($format, 'odi') ? 12 : 6;

        // Do not apply 6h/12h timeout to multi-day Test matches
        if (str_contains($format, 'test')) {
            return false;
        }

        return $startTime->diffInHours(now('Asia/Kolkata'), false) > $maxHours;
    }

    private static function firstNonEmptyString(array $candidates): string
    {
        foreach ($candidates as $val) {
            if (is_string($val) && trim($val) !== '') {
                return trim($val);
            }
            if (is_numeric($val)) {
                return (string) $val;
            }
        }
        return '';
    }
}
