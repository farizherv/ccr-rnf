<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;


class CcrReviewedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public int $reportId,
        public string $type,
        public string $status,      // approved / rejected
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
            'status'    => $this->status,
            'by'        => $this->byUsername,
            'title'     => "CCR {$this->type} {$this->status}",
            'message'   => "CCR kamu sudah {$this->status} oleh {$this->byUsername}",
            'url'       => route('ccr.index'),
        ];
    }
}
