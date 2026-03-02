<?php

namespace App\Notifications\Channels;

use App\Support\Notifications\WebPushNotificationService;
use Illuminate\Notifications\Notification;

class WebPushChannel
{
    public function __construct(private readonly WebPushNotificationService $webPushService)
    {
    }

    public function send(mixed $notifiable, Notification $notification): void
    {
        if (!method_exists($notification, 'toWebPush')) {
            return;
        }

        $payload = $notification->toWebPush($notifiable);
        if (!is_array($payload)) {
            return;
        }

        $this->webPushService->sendToUser($notifiable, $payload);
    }
}

