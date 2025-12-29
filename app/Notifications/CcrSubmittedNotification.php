<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;


class CcrSubmittedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public int $reportId,
        public string $type,
        public string $component,
        public string $byUsername
    ) {}

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toArray($notifiable): array
    {
        return [
            'report_id' => $this->reportId,
            'type'      => $this->type,
            'component' => $this->component,
            'by'        => $this->byUsername,
            'title'     => "CCR {$this->type} butuh approval",
            'message'   => "{$this->component} dikirim oleh {$this->byUsername}",
            'url'       => route('director.monitoring'),
        ];
    }
}
