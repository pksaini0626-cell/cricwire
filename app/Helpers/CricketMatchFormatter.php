<?php

namespace App\Helpers;

use App\Services\Cricket\CricketLiveApiService;

class CricketMatchFormatter
{
    /**
     * Normalize every match item so that ALL score, over, team, status, and date keys
     * are guaranteed to exist on every match array passed to views and components.
     */
    public static function formatMatchForCard(array $m): array
    {
        $id = $m['match_id'] ?? $m['id'] ?? $m['raw']['match_id'] ?? null;

        // Team names and shorts
        $teamA = $m['team_a'] ?? $m['team1'] ?? $m['raw']['first_team']['name'] ?? $m['raw']['first_team']['full_name'] ?? 'Team A';
        if (in_array(strtolower($teamA), ['team a', 'team-a', 'tma', ''], true) && !empty($m['raw']['first_team']['full_name'])) {
            $teamA = $m['raw']['first_team']['full_name'];
        }

        $teamB = $m['team_b'] ?? $m['team2'] ?? $m['raw']['second_team']['name'] ?? $m['raw']['second_team']['full_name'] ?? 'Team B';
        if (in_array(strtolower($teamB), ['team b', 'team-b', 'tmb', ''], true) && !empty($m['raw']['second_team']['full_name'])) {
            $teamB = $m['raw']['second_team']['full_name'];
        }

        $teamAShort = $m['team_a_short'] ?? $m['team1_short'] ?? $m['raw']['first_team']['short_name'] ?? $m['raw']['first_team']['name'] ?? CricketLiveApiService::generateTeamShort($teamA);
        $teamBShort = $m['team_b_short'] ?? $m['team2_short'] ?? $m['raw']['second_team']['short_name'] ?? $m['raw']['second_team']['name'] ?? CricketLiveApiService::generateTeamShort($teamB);

        // Team images
        $teamAImg = $m['team_a_img'] ?? $m['team_a_flag'] ?? $m['team1_flag'] ?? $m['raw']['first_team']['team_image_url'] ?? '';
        $teamBImg = $m['team_b_img'] ?? $m['team_b_flag'] ?? $m['team2_flag'] ?? $m['raw']['second_team']['team_image_url'] ?? '';

        // Raw scores & overs
        $rawScoreA = (string) ($m['team_a_scores'] ?? $m['score_a'] ?? $m['team_a_score'] ?? $m['score'] ?? $m['raw']['first_team']['score'] ?? '');
        $rawScoreB = (string) ($m['team_b_scores'] ?? $m['score_b'] ?? $m['team_b_score'] ?? $m['raw']['second_team']['score'] ?? '');

        $overA = (string) ($m['team_a_over'] ?? $m['over_a'] ?? '');
        $overB = (string) ($m['team_b_over'] ?? $m['over_b'] ?? '');

        // If score string contains formatted overs e.g. "109/4 (9.6 ov)", separate them cleanly
        $scoreA = $rawScoreA;
        if (preg_match('/^(.*?)\s*\(([0-9.]+(?:\s*ov)?)\)$/i', trim($rawScoreA), $mA)) {
            $scoreA = trim($mA[1]);
            if ($overA === '') {
                $overA = $mA[2];
            }
        }

        $scoreB = $rawScoreB;
        if (preg_match('/^(.*?)\s*\(([0-9.]+(?:\s*ov)?)\)$/i', trim($rawScoreB), $mB)) {
            $scoreB = trim($mB[1]);
            if ($overB === '') {
                $overB = $mB[2];
            }
        }

        // Clean over strings so they don't have redundant "ov"
        $cleanOverA = preg_replace('/\s*ov\b/i', '', $overA);
        $cleanOverB = preg_replace('/\s*ov\b/i', '', $overB);

        // State / Status / Result
        $state = (string) ($m['state'] ?? $m['raw']['state'] ?? '');
        $statusNote = (string) ($m['status_note'] ?? $m['raw']['status_detail'] ?? '');
        $result = (string) ($m['result'] ?? (str_contains(strtolower($statusNote), 'won') ? $statusNote : ''));
        $status = (string) ($m['status'] ?? $m['raw']['status'] ?? $m['raw']['short_status'] ?? $m['match_status'] ?? $state);

        // Date and Time
        $startTime = (string) ($m['start_time'] ?? $m['match_date'] ?? $m['raw']['date'] ?? '');
        if ($startTime === '' && preg_match('/starts at\s+(.+)$/i', $statusNote, $startMatches)) {
            $startTime = trim($startMatches[1]);
        }
        $matchDate = (string) ($m['match_date'] ?? $m['date'] ?? $startTime);
        $matchTime = (string) ($m['match_time'] ?? $m['time'] ?? '');

        // Format and Match Desc
        $format = (string) ($m['format'] ?? $m['raw']['match_format'] ?? $m['raw']['format'] ?? $m['match_type'] ?? 'Match');
        $matchType = (string) ($m['match_type'] ?? $m['raw']['match_type'] ?? $format);
        $series = (string) ($m['series_name'] ?? $m['series'] ?? $m['raw']['series_name'] ?? 'Cricket Series');
        $matchDesc = (string) ($m['match_desc'] ?? $m['matchs'] ?? $m['raw']['match_desc'] ?? 'Match');
        $venue = (string) ($m['venue'] ?? $m['raw']['venue'] ?? '');

        return array_merge($m, [
            'match_id' => $id,
            'id' => $id,
            'team_a' => $teamA,
            'team_b' => $teamB,
            'team_a_short' => $teamAShort,
            'team_b_short' => $teamBShort,
            'team_a_img' => $teamAImg,
            'team_b_img' => $teamBImg,
            'score' => $scoreA,
            'team_a_scores' => $scoreA,
            'team_b_scores' => $scoreB,
            'score_a' => $scoreA,
            'score_b' => $scoreB,
            'team_a_over' => $cleanOverA,
            'team_b_over' => $cleanOverB,
            'over_a' => $cleanOverA,
            'over_b' => $cleanOverB,
            'raw_score_a' => $rawScoreA,
            'raw_score_b' => $rawScoreB,
            'state' => $state,
            'status' => $status,
            'status_note' => $statusNote,
            'result' => $result,
            'start_time' => $startTime,
            'match_date' => $matchDate,
            'match_time' => $matchTime,
            'format' => $format,
            'match_type' => $matchType,
            'series' => $series,
            'series_name' => $series,
            'match_desc' => $matchDesc,
            'matchs' => $matchDesc,
            'venue' => $venue,
        ]);
    }
}
