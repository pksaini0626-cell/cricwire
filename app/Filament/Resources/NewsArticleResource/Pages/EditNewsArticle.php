<?php

namespace App\Filament\Resources\NewsArticleResource\Pages;

use App\Filament\Resources\NewsArticleResource;
use App\Jobs\SendPushNotificationJob;
use App\Models\PushNotification;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Str;

class EditNewsArticle extends EditRecord
{
    protected static string $resource = NewsArticleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function afterSave(): void
    {
        $data = $this->form->getRawState();

        if (! empty($data['send_push_notification']) && $this->record->status === 'published') {
            $imageUrl = null;
            if ($this->record->featured_image) {
                $imageUrl = Str::startsWith($this->record->featured_image, ['http://', 'https://'])
                    ? $this->record->featured_image
                    : asset('storage/' . $this->record->featured_image);
            }

            $notification = PushNotification::create([
                'title'      => '🚨 ' . $this->record->title,
                'body'       => Str::limit(strip_tags($this->record->summary ?? $this->record->content), 140),
                'image_url'  => $imageUrl,
                'type'       => 'news',
                'target_url' => '/cricket-news/' . $this->record->slug,
                'audience'   => 'all',
                'status'     => 'queued',
                'created_by' => auth()->id(),
            ]);

            SendPushNotificationJob::dispatch($notification->id);
        }
    }
}
