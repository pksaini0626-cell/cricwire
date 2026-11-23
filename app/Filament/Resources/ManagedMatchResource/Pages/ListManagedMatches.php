<?php

namespace App\Filament\Resources\ManagedMatchResource\Pages;

use App\Filament\Resources\ManagedMatchResource;
use App\Services\Cricket\CricketLiveApiService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Cache;

class ListManagedMatches extends ListRecords
{
    protected static string $resource = ManagedMatchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('syncMatchesNow')
                ->label('🔄 Sync Live & Upcoming Now')
                ->color('info')
                ->action(function (CricketLiveApiService $service) {
                    try {
                        // 1. Sync Live & Upcoming to database
                        $service->syncMatchCatalog();

                        // 2. Invalidate cache so fresh live data is fetched immediately
                        Cache::forget('live:home:payload');
                        $service->getFastHomeData();

                        Notification::make()
                            ->title('Live & Upcoming Matches Synced')
                            ->body('Catalog and real-time live cache updated immediately with fresh data.')
                            ->success()
                            ->send();
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->title('Sync Failed')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
            Actions\CreateAction::make(),
        ];
    }
}
