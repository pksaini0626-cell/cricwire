<?php

namespace App\Filament\Resources\ManagedMatchResource\Pages;

use App\Filament\Resources\ManagedMatchResource;
use App\Services\Cricket\CricketLiveApiService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListManagedMatches extends ListRecords
{
    protected static string $resource = ManagedMatchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('syncMatchesNow')
                ->label('🔄 Sync Matches List Now')
                ->color('info')
                ->action(function (CricketLiveApiService $service) {
                    try {
                        $service->syncMatchCatalog();
                        Notification::make()
                            ->title('Matches Catalog Synced Successfully')
                            ->body('Live, Upcoming, and Recent matches have been updated in Managed Matches.')
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
