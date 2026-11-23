<?php

namespace App\Filament\Resources\SeriesCoverageResource\Pages;

use App\Filament\Resources\SeriesCoverageResource;
use App\Models\SkippedSeries;
use App\Services\Cricket\CricketLiveApiService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListSeriesCoverages extends ListRecords
{
    protected static string $resource = SeriesCoverageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('syncSeriesNow')
                ->label('🔄 Sync Series Now')
                ->color('primary')
                ->icon('heroicon-o-arrow-path')
                ->action(function () {
                    try {
                        app(CricketLiveApiService::class)->syncMatchCatalog();
                        Notification::make()
                            ->title('Series catalog synchronized successfully!')
                            ->success()
                            ->send();
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->title('Sync failed')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All Series')
                ->badge(fn () => SkippedSeries::count()),

            'live_now' => Tab::make('Live Now')
                ->badge(fn () => SkippedSeries::where('live_matches_count', '>', 0)->count())
                ->badgeColor('success')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('live_matches_count', '>', 0)),

            'covered' => Tab::make('Covered')
                ->badge(fn () => SkippedSeries::where('is_skipped', false)->count())
                ->badgeColor('success')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('is_skipped', false)),

            'skipped' => Tab::make('Skipped')
                ->badge(fn () => SkippedSeries::where('is_skipped', true)->count())
                ->badgeColor('danger')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('is_skipped', true)),
        ];
    }
}
