<?php

namespace App\Services\Cricket;

use App\Models\CricketMatch;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class CricketUrlService
{
    /**
     * Allowed tabs for match detail pages.
     */
    public const ALLOWED_TABS = ['live-line', 'commentary', 'scorecard', 'squad', 'odds', 'info'];

    /**
     * Generate an SEO-friendly series slug from series name and season/year.
     * e.g. "csa-t20-challenge-2026"
     */
    public static function generateSeriesSlug(?string $seriesName, ?string $season = null): string
    {
        $name = trim((string) ($seriesName ?: 'cricket-series'));
        $suffix = trim((string) $season);

        $clean = $suffix !== '' && !str_contains(strtolower($name), strtolower($suffix))
            ? "{$name}-{$suffix}"
            : $name;

        return Str::slug($clean) ?: 'cricket-series';
    }

    /**
     * Generate an SEO-friendly match slug from teams, round/identifier, and match ID.
     * Format: {teamA}-vs-{teamB}-{matchDesc}-{matchId}
     * e.g. "warriors-vs-boland-super-eights-15444"
     */
    public static function generateMatchSlug(
        ?string $teamA,
        ?string $teamB,
        ?string $matchDesc = null,
        string|int|null $matchId = null
    ): string {
        $a = trim((string) ($teamA ?: 'team-a'));
        $b = trim((string) ($teamB ?: 'team-b'));
        $desc = trim((string) ($matchDesc ?: 'match'));

        $base = Str::slug("{$a}-vs-{$b}-{$desc}");

        if ($matchId !== null && $matchId !== '') {
            return "{$base}-{$matchId}";
        }

        return $base ?: 'match-detail';
    }

    /**
     * Generate full SEO match detail URL from match data or model.
     *
     * @param array|CricketMatch $match
     * @param string $tab Default 'live-line'
     * @return string
     */
    public static function getMatchUrl(array|CricketMatch $match, string $tab = 'live-line'): string
    {
        $tab = self::normalizeTab($tab);

        if ($match instanceof CricketMatch) {
            $matchId = $match->api_match_id ?: $match->id;
            $seriesName = $match->series?->name ?? 'cricket-series';
            $season = $match->series?->season ?? null;
            $teamAName = $match->teamA?->name ?? 'team-a';
            $teamBName = $match->teamB?->name ?? 'team-b';
            $matchDesc = $match->match_title ?? 'match';
        } else {
            $matchId = $match['match_id'] ?? $match['id'] ?? $match['api_match_id'] ?? null;
            $seriesName = $match['series'] ?? $match['series_name'] ?? 'cricket-series';
            $season = $match['season'] ?? null;
            $teamAName = $match['team_a'] ?? $match['team_a_short'] ?? 'team-a';
            $teamBName = $match['team_b'] ?? $match['team_b_short'] ?? 'team-b';
            $matchDesc = $match['matchs'] ?? $match['match_desc'] ?? $match['match_type'] ?? 'match';
        }

        $seriesSlug = self::generateSeriesSlug($seriesName, $season);
        $matchSlug = self::generateMatchSlug($teamAName, $teamBName, $matchDesc, $matchId);

        // Cache slug to matchId mapping for fast lookup (1 day)
        if ($matchId) {
            self::cacheSlugMapping($matchSlug, (string) $matchId);
        }

        return route('match.detail', [
            'seriesSlug' => $seriesSlug,
            'tab' => $tab,
            'matchSlug' => $matchSlug,
        ]);
    }

    /**
     * Resolve match ID from route slug or parameter.
     * 1. Checks trailing digits in slug: e.g. "warriors-vs-boland-super-eights-15444" -> 15444
     * 2. Checks cached slug mapping: Cache::get("match_slug:{$matchSlug}")
     * 3. Checks CricketMatch database record by slug or api_match_id
     * 4. Returns pure numeric fallback if slug is an ID.
     */
    public static function resolveMatchId(string|int|null $slugOrId): string|int|null
    {
        if (empty($slugOrId)) {
            return null;
        }

        $val = trim((string) $slugOrId);

        // If purely numeric, return directly
        if (ctype_digit($val)) {
            return (int) $val;
        }

        // 1. Extract trailing integer from slug (e.g., "-15444")
        if (preg_match('/-(\d+)$/', $val, $matches)) {
            return (int) $matches[1];
        }

        // 2. Check cached slug mapping
        $cachedId = Cache::get("match_slug:{$val}");
        if ($cachedId !== null) {
            return $cachedId;
        }

        // 3. Database lookup in CricketMatch
        try {
            $dbMatch = CricketMatch::where('slug', $val)
                ->orWhere('api_match_id', $val)
                ->first();

            if ($dbMatch) {
                return $dbMatch->api_match_id ?: $dbMatch->id;
            }
        } catch (\Throwable $e) {
            // DB table might not be seeded or configured in all test setups
        }

        return $val;
    }

    /**
     * Cache slug to matchId mapping.
     */
    public static function cacheSlugMapping(string $matchSlug, string|int $matchId): void
    {
        Cache::put("match_slug:{$matchSlug}", (string) $matchId, 86400); // 24 hours
    }

    /**
     * Normalize tab to supported tabs list.
     */
    public static function normalizeTab(?string $tab): string
    {
        $clean = strtolower(trim((string) $tab));
        return in_array($clean, self::ALLOWED_TABS, true) ? $clean : 'live-line';
    }
}

