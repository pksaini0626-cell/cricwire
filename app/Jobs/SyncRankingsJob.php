<?php

namespace App\Jobs;

use App\Models\Ranking;
use App\Services\RapidApiAdapter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SyncRankingsJob implements ShouldQueue
{
    use Queueable;

    /**
     * Execute the job.
     */
    public function handle(RapidApiAdapter $api): void
    {
        Log::info('SyncRankingsJob: Fetching ICC rankings...');

        $formats = ['TEST', 'ODI', 'T20'];
        $categories = ['team', 'batter', 'bowler', 'all_rounder'];
        $genders = ['men', 'women'];

        foreach ($genders as $gender) {
            foreach ($formats as $format) {
                foreach ($categories as $category) {
                    $apiCat = match ($category) {
                        'batter' => 'batsmen',
                        'bowler' => 'bowlers',
                        'all_rounder' => 'allrounders',
                        default => 'teams',
                    };

                    $response = $api->getRankings($apiCat, strtolower($format));
                    $rankData = $response['rank'] ?? $response['values'] ?? [];

                    if (!empty($rankData)) {
                        Ranking::updateOrCreate(
                            [
                                'gender' => $gender,
                                'format' => $format,
                                'category' => $category,
                            ],
                            [
                                'rank_data_json' => $rankData,
                            ]
                        );
                    }
                }
            }
        }

        Log::info('SyncRankingsJob: Rankings sync completed.');
    }
}

