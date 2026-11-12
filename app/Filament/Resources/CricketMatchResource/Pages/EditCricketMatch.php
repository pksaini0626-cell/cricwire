<?php

namespace App\Filament\Resources\CricketMatchResource\Pages;

use App\Filament\Resources\CricketMatchResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCricketMatch extends EditRecord
{
    protected static string $resource = CricketMatchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
