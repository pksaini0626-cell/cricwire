<?php

namespace App\Jobs;

use App\Models\CricketMatch;
use App\Models\Series;
use App\Models\Team;
use App\Services\RapidApiAdapter;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SyncSchedulesAndSeriesJob implements ShouldQueue
{
    use Queueable;

    /**
     * Execute the job.
     */
    public function handle(RapidApiAdapter $api): void
    {
        Log::info('SyncSchedulesAndSeriesJob: Starting scheduled synchronization...');

        $data = $api->getSchedules();
        if (!$data || !isset($data['matchScheduleMap'])) {
            Log::warning('SyncSchedulesAndSeriesJob: No match schedule data received from RapidAPI.');
            return;
        }

        foreach ($data['matchScheduleMap'] as $entry) {
            if (!isset($entry['scheduleAdWrapper'])) {
                continue;
            }

            $schedule = $entry['scheduleAdWrapper'];
            $seriesName = $schedule['seriesName'] ?? 'International Cricket Fixtures';
            $apiSeriesId = (string) ($schedule['seriesId'] ?? Str::slug($seriesName));

            // Upsert Series
            $series = Series::updateOrCreate(
                ['api_series_id' => $apiSeriesId],
                [
                    'name' => $seriesName,
                    'slug' => Str::slug($seriesName . '-' . $apiSeriesId),
                    'type' => 'international',
                    'start_date' => Carbon::now()->toDateString(),
                    'end_date' => Carbon::now()->addMonths(2)->toDateString(),
                    'is_popular' => true,
                    'status' => 'upcoming',
                ]
            );

            // Match details inside series
            if (!empty($schedule['matchScheduleList'])) {
                foreach ($schedule['matchScheduleList'] as $matchGroup) {
                    $matchInfoList = $matchGroup['matchInfo'] ?? [];
                    foreach ($matchInfoList as $info) {
                        $apiMatchId = (string) ($info['matchId'] ?? '');
                        if (!$apiMatchId) {
                            continue;
                        }

                        $teamAData = $info['team1'] ?? [];
                        $teamBData = $info['team2'] ?? [];

                        $teamA = Team::updateOrCreate(
                            ['api_team_id' => (string) ($teamAData['teamId'] ?? Str::slug($teamAData['teamName'] ?? 'Team A'))],
                            [
                                'name' => $teamAData['teamName'] ?? 'Team A',
                                'short_name' => $teamAData['teamSName'] ?? 'TMA',
                                'slug' => Str::slug($teamAData['teamName'] ?? 'Team A'),
                                'logo_url' => $teamAData['imageId'] ? "https://static.cricbuzz.com/a/img/v1/i1/c{$teamAData['imageId']}/i.jpg" : null,
                            ]
                        );

                        $teamB = Team::updateOrCreate(
                            ['api_team_id' => (string) ($teamBData['teamId'] ?? Str::slug($teamBData['teamName'] ?? 'Team B'))],
                            [
                                'name' => $teamBData['teamName'] ?? 'Team B',
                                'short_name' => $teamBData['teamSName'] ?? 'TMB',
                                'slug' => Str::slug($teamBData['teamName'] ?? 'Team B'),
                                'logo_url' => $teamBData['imageId'] ? "https://static.cricbuzz.com/a/img/v1/i1/c{$teamBData['imageId']}/i.jpg" : null,
                            ]
                        );

                        $format = strtoupper($info['matchFormat'] ?? 'T20');
                        if (!in_array($format, ['T20', 'ODI', 'TEST', 'T10', 'SIXES'])) {
                            $format = 'T20';
                        }

                        $state = strtolower($info['state'] ?? 'upcoming');
                        $status = match (true) {
                            str_contains($state, 'in progress') || str_contains($state, 'live') => 'live',
                            str_contains($state, 'stump') => 'stumps',
                            str_contains($state, 'complete') || str_contains($state, 'finished') => 'finished',
                            str_contains($state, 'abandon') || str_contains($state, 'cancel') => 'abandoned',
                            default => 'upcoming',
                        };

                        $matchDesc = $info['matchDesc'] ?? 'Match';
                        $slug = Str::slug("{$teamA->short_name}-vs-{$teamB->short_name}-{$matchDesc}-{$apiMatchId}");

                        $startDate = isset($info['startDate'])
                            ? Carbon::createFromTimestampMs((int) $info['startDate'])
                            : Carbon::now()->addDays(1);

                        CricketMatch::updateOrCreate(
                            ['api_match_id' => $apiMatchId],
                            [
                                'series_id' => $series->id,
                                'team_a_id' => $teamA->id,
                                'team_b_id' => $teamB->id,
                                'slug' => $slug,
                                'match_title' => $matchDesc,
                                'format' => $format,
                                'venue' => $info['venueInfo']['ground'] ?? 'International Cricket Ground',
                                'start_time' => $startDate,
                                'status' => $status,
                                'is_pinned' => false,
                                'toss_info' => $info['status'] ?? null,
                                'result_summary' => $info['status'] ?? null,
                                'seo_title' => "{$teamA->name} vs {$teamB->name} Live Cricket Score - {$seriesName}",
                                'seo_description' => "Ball by ball live score and updates for {$teamA->name} vs {$teamB->name}, {$matchDesc} of {$seriesName}.",
                            ]
                        );
                    }
                }
            }
        }

        Log::info('SyncSchedulesAndSeriesJob: Synchronized schedules successfully.');
    }
}

