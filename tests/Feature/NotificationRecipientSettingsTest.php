<?php

namespace Tests\Feature;

use App\Mail\CcrInboxAlertMail;
use App\Models\InboxMessage;
use App\Models\NotificationRecipient;
use App\Models\User;
use App\Support\Notifications\InboxAlertDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class NotificationRecipientSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_user_gets_default_notification_recipient_based_on_role(): void
    {
        $director = User::factory()->create([
            'role' => 'director',
            'name' => 'Dir One',
            'email' => 'dir.one@example.com',
        ]);

        $recipient = NotificationRecipient::query()
            ->where('email', 'dir.one@example.com')
            ->first();

        $this->assertNotNull($recipient);
        $this->assertSame('Dir One', $recipient->name);
        $this->assertTrue((bool) $recipient->is_active);
        $this->assertTrue((bool) $recipient->notify_waiting);
        $this->assertFalse((bool) $recipient->notify_approved);
        $this->assertFalse((bool) $recipient->notify_rejected);

        $director->update(['role' => 'operator']);
        $recipient->refresh();
        $this->assertFalse((bool) $recipient->notify_waiting);
        $this->assertTrue((bool) $recipient->notify_approved);
        $this->assertTrue((bool) $recipient->notify_rejected);
    }

    public function test_admin_can_crud_notification_recipients(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->get(route('admin.notifications.index'))
            ->assertOk();

        $this->actingAs($admin)
            ->post(route('admin.notifications.store'), [
                'email' => 'notify.ops@example.com',
                'name' => 'Ops User',
                'notify_waiting' => '1',
                'notify_approved' => '1',
                'notify_rejected' => '0',
                'is_active' => '1',
            ])
            ->assertRedirect();

        $recipient = NotificationRecipient::query()->where('email', 'notify.ops@example.com')->first();
        $this->assertNotNull($recipient);
        $this->assertSame('Ops User', $recipient->name);
        $this->assertTrue((bool) $recipient->notify_waiting);
        $this->assertTrue((bool) $recipient->notify_approved);
        $this->assertFalse((bool) $recipient->notify_rejected);

        $this->actingAs($admin)
            ->delete(route('admin.notifications.destroy', $recipient->id))
            ->assertRedirect();

        $this->assertDatabaseMissing('notification_recipients', [
            'id' => $recipient->id,
        ]);
    }

    public function test_admin_can_bulk_update_notification_recipients_in_one_submit(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'email' => 'admin.bulk@example.com',
        ]);

        $first = NotificationRecipient::query()->create([
            'email' => 'first.old@example.com',
            'name' => 'Old First',
            'is_active' => true,
            'notify_waiting' => true,
            'notify_approved' => false,
            'notify_rejected' => false,
            'created_by' => (int) $admin->id,
            'updated_by' => (int) $admin->id,
        ]);

        $second = NotificationRecipient::query()->create([
            'email' => 'second.old@example.com',
            'name' => 'Old Second',
            'is_active' => true,
            'notify_waiting' => true,
            'notify_approved' => true,
            'notify_rejected' => true,
            'created_by' => (int) $admin->id,
            'updated_by' => (int) $admin->id,
        ]);

        $toDelete = NotificationRecipient::query()->create([
            'email' => 'delete.me@example.com',
            'name' => 'Delete Me',
            'is_active' => true,
            'notify_waiting' => true,
            'notify_approved' => false,
            'notify_rejected' => false,
            'created_by' => (int) $admin->id,
            'updated_by' => (int) $admin->id,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.notifications.bulkUpdate'), [
                'recipients' => [
                    (string) $first->id => [
                        'email' => 'first.new@example.com',
                        'name' => 'New First',
                        'notify_waiting' => '1',
                        'notify_approved' => '0',
                        'notify_rejected' => '0',
                        'is_active' => '1',
                    ],
                    (string) $second->id => [
                        'email' => 'second.new@example.com',
                        'name' => 'New Second',
                        'notify_waiting' => '0',
                        'notify_approved' => '1',
                        'notify_rejected' => '1',
                        'is_active' => '0',
                    ],
                    (string) $toDelete->id => [
                        '_delete' => '1',
                    ],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $first->refresh();
        $second->refresh();

        $this->assertSame('first.new@example.com', $first->email);
        $this->assertSame('New First', $first->name);
        $this->assertTrue((bool) $first->notify_waiting);
        $this->assertFalse((bool) $first->notify_approved);
        $this->assertFalse((bool) $first->notify_rejected);
        $this->assertTrue((bool) $first->is_active);

        $this->assertSame('second.new@example.com', $second->email);
        $this->assertSame('New Second', $second->name);
        $this->assertFalse((bool) $second->notify_waiting);
        $this->assertTrue((bool) $second->notify_approved);
        $this->assertTrue((bool) $second->notify_rejected);
        $this->assertFalse((bool) $second->is_active);

        $this->assertDatabaseMissing('notification_recipients', [
            'id' => $toDelete->id,
        ]);
    }

    public function test_notification_settings_page_uses_single_edit_and_save_toggle_button(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $opsUser = User::withoutEvents(function () {
            return User::factory()->create([
                'role' => 'operator',
                'email' => 'ops.ui@example.com',
            ]);
        });

        NotificationRecipient::query()->create([
            'email' => 'ops.ui@example.com',
            'name' => $opsUser->name,
            'is_active' => true,
            'notify_waiting' => false,
            'notify_approved' => true,
            'notify_rejected' => true,
            'created_by' => (int) $admin->id,
            'updated_by' => (int) $admin->id,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.notifications.index'))
            ->assertOk()
            ->assertSee('Edit')
            ->assertSee('Simpan Perubahan')
            ->assertSee('data-label-active="Simpan Perubahan"', false)
            ->assertDontSee('Simpan Perubahan</button>', false);
    }

    public function test_dispatcher_sends_mail_to_matching_global_status_recipients(): void
    {
        config()->set('ccr_notifications.mail_enabled', true);
        config()->set('ccr_notifications.mail_allow_local_test', true);
        config()->set('ccr_notifications.web_push_enabled', false);

        Mail::fake();

        $toUser = User::factory()->create([
            'role' => 'admin',
            'email' => 'inbox.target@example.com',
        ]);

        $fromUser = User::factory()->create([
            'role' => 'director',
            'name' => 'Fariz',
            'username' => 'fariz',
        ]);

        $globalApproved = NotificationRecipient::query()
            ->where('email', 'inbox.target@example.com')
            ->firstOrFail();
        $globalApproved->fill([
            'notify_waiting' => false,
            'notify_approved' => true,
            'notify_rejected' => false,
        ])->save();

        NotificationRecipient::query()->create([
            'email' => 'global.rejected@example.com',
            'name' => 'Global Rejected',
            'is_active' => true,
            'notify_waiting' => false,
            'notify_approved' => false,
            'notify_rejected' => true,
        ]);

        $message = InboxMessage::query()->create([
            'to_user_id' => (int) $toUser->id,
            'from_user_id' => (int) $fromUser->id,
            'type' => 'ccr_approved',
            'title' => 'q10',
            'message' => 'Approved oleh fariz.',
            'url' => '/ccr/engine/edit/10',
            'is_read' => false,
        ]);

        app(InboxAlertDispatcher::class)->dispatchFromInboxMessageId((int) $message->id);

        Mail::assertSent(CcrInboxAlertMail::class, function (CcrInboxAlertMail $mail): bool {
            return $mail->hasTo('inbox.target@example.com');
        });

        Mail::assertNotSent(CcrInboxAlertMail::class, function (CcrInboxAlertMail $mail): bool {
            return $mail->hasTo('global.rejected@example.com');
        });

        $globalApproved->refresh();
        $this->assertNotNull($globalApproved->last_notified_at);
        $this->assertNull($globalApproved->last_error);
    }

    public function test_dispatcher_skips_mail_when_user_email_not_configured_in_recipient_settings(): void
    {
        config()->set('ccr_notifications.mail_enabled', true);
        config()->set('ccr_notifications.mail_allow_local_test', true);
        config()->set('ccr_notifications.web_push_enabled', false);

        Mail::fake();

        $toUser = User::withoutEvents(function () {
            return User::factory()->create([
                'role' => 'director',
                'email' => 'director.only.webpush@example.com',
            ]);
        });
        $fromUser = User::factory()->create([
            'role' => 'admin',
            'name' => 'Admin One',
            'username' => 'admin-one',
        ]);

        $message = InboxMessage::query()->create([
            'to_user_id' => (int) $toUser->id,
            'from_user_id' => (int) $fromUser->id,
            'type' => 'engine_submitted',
            'title' => 'Q10',
            'message' => 'Disubmit oleh admin-one.',
            'url' => '/director/monitoring?open=10',
            'is_read' => false,
        ]);

        app(InboxAlertDispatcher::class)->dispatchFromInboxMessageId((int) $message->id);

        Mail::assertNothingSent();
    }
}
