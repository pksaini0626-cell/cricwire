<?php

namespace App\Filament\Resources\PollResource\Pages;

use App\Filament\Resources\PollResource;
use App\Jobs\SendPushNotificationJob;
use App\Models\PushNotification;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreatePoll extends CreateRecord
{
    protected static string $resource = PollResource::class;

    protected function afterCreate(): void
    {
        $data = $this->form->getRawState();

        if (! empty($data['send_push_notification'])) {
            $notification = PushNotification::create([
                'title'      => '📊 New Poll: ' . $this->record->question,
                'body'       => 'Vote now and see live fan predictions on Cricwire!',
                'type'       => 'poll',
                'target_url' => '/polls',
                'audience'   => 'all',
                'status'     => 'queued',
                'created_by' => auth()->id(),
            ]);

            SendPushNotificationJob::dispatch($notification->id);
        }
    }
}
