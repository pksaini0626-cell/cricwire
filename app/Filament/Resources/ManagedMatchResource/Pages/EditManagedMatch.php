<?php

namespace App\Filament\Resources\ManagedMatchResource\Pages;

use App\Filament\Resources\ManagedMatchResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditManagedMatch extends EditRecord
{
    protected static string $resource = ManagedMatchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
