<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CcrInboxAlertMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(public array $payload)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: (string) ($this->payload['subject'] ?? '[CCR] Notification')
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.ccr_inbox_alert_html',
            text: 'emails.ccr_inbox_alert_text',
            with: ['payload' => $this->payload],
        );
    }
}

