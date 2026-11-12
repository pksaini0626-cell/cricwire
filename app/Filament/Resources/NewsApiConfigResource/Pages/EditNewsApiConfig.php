<?php

namespace App\Filament\Resources\NewsApiConfigResource\Pages;

use App\Filament\Resources\NewsApiConfigResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditNewsApiConfig extends EditRecord
{
    protected static string $resource = NewsApiConfigResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}

