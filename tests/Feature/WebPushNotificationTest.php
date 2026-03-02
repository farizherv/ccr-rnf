<?php

namespace Tests\Feature;

use App\Jobs\DispatchInboxAlertJob;
use App\Mail\CcrInboxAlertMail;
use App\Models\NotificationRecipient;
use App\Models\User;
use App\Support\Inbox;
use App\Support\Notifications\InboxAlertDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WebPushNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_and_disable_web_push_subscription(): void
    {
        config()->set('ccr_notifications.web_push_enabled', true);

        $user = User::factory()->create(['role' => 'admin']);
        $endpoint = 'https://fcm.googleapis.com/fcm/send/demo-subscription-token';

        $payload = [
            'subscription' => [
                'endpoint' => $endpoint,
                'keys' => [
                    'p256dh' => 'BPUBLICKEY-123456789',
                    'auth' => 'AUTH-123456789',
                ],
                'contentEncoding' => 'aes128gcm',
            ],
            'user_agent' => 'Mozilla/5.0',
        ];

        $this->actingAs($user)
            ->postJson(route('notifications.webpush.subscribe'), $payload)
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertDatabaseHas('web_push_subscriptions', [
            'user_id' => $user->id,
            'endpoint_hash' => hash('sha256', $endpoint),
            'disabled_at' => null,
        ]);

        $this->actingAs($user)
            ->postJson(route('notifications.webpush.unsubscribe'), ['endpoint' => $endpoint])
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertDatabaseHas('web_push_subscriptions', [
            'user_id' => $user->id,
            'endpoint_hash' => hash('sha256', $endpoint),
        ]);
        $this->assertDatabaseMissing('web_push_subscriptions', [
            'user_id' => $user->id,
            'endpoint_hash' => hash('sha256', $endpoint),
            'disabled_at' => null,
        ]);
    }

    public function test_inbox_to_user_dispatches_alert_job_when_channel_enabled(): void
    {
        config()->set('ccr_notifications.mail_enabled', true);
        config()->set('ccr_notifications.web_push_enabled', false);
        config()->set('ccr_notifications.queue', 'ccr-notify');

        Queue::fake();

        $user = User::factory()->create(['role' => 'admin']);
        Inbox::toUser((int) $user->id, [
            'type' => 'ccr_submitted',
            'title' => 'q10',
            'message' => 'Disubmit oleh fariz.',
            'url' => '/director/monitoring?open=10',
        ]);

        Queue::assertPushed(DispatchInboxAlertJob::class);
    }

    public function test_dispatcher_sends_html_and_text_mail_payload(): void
    {
        config()->set('ccr_notifications.mail_enabled', true);
        config()->set('ccr_notifications.mail_allow_local_test', true);
        config()->set('ccr_notifications.web_push_enabled', false);
        config()->set('ccr_notifications.mail_subject_prefix', '[CCR-RNF]');

        Mail::fake();

        $recipient = User::factory()->create([
            'role' => 'admin',
            'email' => 'director@example.com',
        ]);
        $sender = User::factory()->create([
            'role' => 'operator',
            'name' => 'Fariz',
            'username' => 'fariz',
        ]);
        NotificationRecipient::query()->updateOrCreate(
            ['email' => 'director@example.com'],
            [
                'name' => 'Director',
                'is_active' => true,
                'notify_waiting' => false,
                'notify_approved' => false,
                'notify_rejected' => true,
            ]
        );

        $message = Inbox::toUser((int) $recipient->id, [
            'from_user_id' => (int) $sender->id,
            'type' => 'ccr_rejected',
            'title' => 'q10',
            'message' => 'Rejected oleh fariz. Catatan: revisi minor.',
            'url' => '/ccr/engine/edit/10',
        ]);

        app(InboxAlertDispatcher::class)->dispatchFromInboxMessageId((int) $message->id);

        Mail::assertSent(CcrInboxAlertMail::class, function (CcrInboxAlertMail $mail) {
            $payload = $mail->payload;
            return (string) ($payload['subject'] ?? '') === '[CCR-RNF] CCR ENGINE'
                && str_contains((string) ($payload['open_url'] ?? ''), '/ccr/engine/edit/10')
                && strtolower((string) ($payload['actor'] ?? '')) === 'fariz';
        });
    }
}
