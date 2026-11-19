<?php

namespace App\Helpers;

use Illuminate\Support\Str;

class CricketSlugHelper
{
    /**
     * Generate series slug dynamically from API match data.
     */
    public static function makeSeriesSlug(array $match): string
    {
        $series = $match['series_name'] ?? $match['series'] ?? $match['tournament'] ?? $match['series_title'] ?? 'cricket-tournament';
        $season = $match['season'] ?? $match['year'] ?? '';
        $raw = trim("{$series} {$season}");
        return Str::slug($raw) ?: 'cricket-match-series';
    }

    /**
     * Generate match slug dynamically from actual team names, match stage/round, and append match ID.
     */
    public static function makeMatchSlug(array $match, string|int $matchId): string
    {
        $teamA = $match['team_a'] ?? $match['team_a_short'] ?? $match['team1'] ?? 'team-a';
        $teamB = $match['team_b'] ?? $match['team_b_short'] ?? $match['team2'] ?? 'team-b';
        $stage = $match['match_desc'] ?? $match['matchs'] ?? $match['match_title'] ?? $match['match_stage'] ?? $match['round'] ?? '';

        // Avoid generic fallback like "team-a-vs-team-b" if real names exist in nested first_team/second_team
        if (in_array(strtolower($teamA), ['team a', 'team-a', 'tma', '']) && !empty($match['raw']['first_team']['full_name'])) {
            $teamA = $match['raw']['first_team']['full_name'];
        }
        if (in_array(strtolower($teamB), ['team b', 'team-b', 'tmb', '']) && !empty($match['raw']['second_team']['full_name'])) {
            $teamB = $match['raw']['second_team']['full_name'];
        }

        $title = trim("{$teamA} vs {$teamB} {$stage}");
        $slug = Str::slug($title);

        // Append integer match ID at the end for instant extraction without database overhead
        return $slug ? "{$slug}-{$matchId}" : "match-{$matchId}";
    }

    /**
     * Extract match ID reliably from the trailing segment of the slug.
     */
    public static function extractMatchId(string $matchSlug): ?string
    {
        if (preg_match('/-(\d+)$/', $matchSlug, $matches)) {
            return $matches[1];
        }
        return is_numeric($matchSlug) ? (string) $matchSlug : null;
    }

    /**
     * Build the full SEO match detail route URL.
     */
    public static function getMatchUrl(array $match, string $tab = 'live-line'): string
    {
        $matchId = $match['match_id'] ?? $match['id'] ?? $match['api_match_id'] ?? 1;
        $seriesSlug = self::makeSeriesSlug($match);
        $matchSlug = self::makeMatchSlug($match, $matchId);

        $allowedTabs = ['live-line', 'commentary', 'scorecard', 'squad', 'odds', 'info'];
        $cleanTab = in_array(strtolower(trim($tab)), $allowedTabs, true) ? strtolower(trim($tab)) : 'live-line';

        return route('match.detail', [
            'seriesSlug' => $seriesSlug,
            'tab' => $cleanTab,
            'matchSlug' => $matchSlug,
        ]);
    }
}

