<?php

namespace App\Jobs;

use App\Models\PushNotification;
use App\Services\Notifications\FcmPushService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendPushNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [10, 30, 60];

    public function __construct(public int $notificationId)
    {
    }

    /**
     * Execute the job.
     */
    public function handle(FcmPushService $fcmService): void
    {
        $notification = PushNotification::find($this->notificationId);

        if (! $notification) {
            Log::warning("SendPushNotificationJob: PushNotification #{$this->notificationId} not found.");
            return;
        }

        try {
            $fcmService->sendToNotification($notification);
        } catch (Throwable $e) {
            Log::error("SendPushNotificationJob: Exception sending notification #{$this->notificationId}: " . $e->getMessage(), [
                'exception' => get_class($e),
            ]);

            if ($this->attempts() >= $this->tries || config('queue.default') === 'sync') {
                $notification->update(['status' => 'failed']);
            }

            throw $e;
        }
    }

    /**
     * Handle permanent job failure after retries are exhausted.
     */
    public function failed(Throwable $exception): void
    {
        Log::error("SendPushNotificationJob: Permanently failed for notification #{$this->notificationId}: " . $exception->getMessage());

        PushNotification::where('id', $this->notificationId)
            ->update(['status' => 'failed']);
    }
}
