<?php

namespace App\Filament\Resources\PushNotificationResource\Pages;

use App\Filament\Resources\PushNotificationResource;
use App\Jobs\SendPushNotificationJob;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreatePushNotification extends CreateRecord
{
    protected static string $resource = PushNotificationResource::class;

    protected function afterCreate(): void
    {
        $data = $this->form->getRawState();

        if (! empty($data['send_immediately'])) {
            $this->record->update(['status' => 'queued']);
            SendPushNotificationJob::dispatch($this->record->id);
        }
    }
}
