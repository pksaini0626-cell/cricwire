<?php

namespace App\Filament\Resources\CricketMatchResource\Pages;

use App\Filament\Resources\CricketMatchResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCricketMatches extends ListRecords
{
    protected static string $resource = CricketMatchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
