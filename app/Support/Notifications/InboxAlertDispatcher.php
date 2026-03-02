<?php

namespace App\Support\Notifications;

use App\Models\CcrReport;
use App\Mail\CcrInboxAlertMail;
use App\Models\InboxMessage;
use App\Models\NotificationRecipient;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

class InboxAlertDispatcher
{
    public function __construct(
        private readonly WebPushNotificationService $webPushService,
    ) {
    }

    public function hasAnyChannelEnabled(): bool
    {
        return $this->mailEnabled() || $this->webPushService->isEnabled();
    }

    public function dispatchFromInboxMessageId(int $inboxMessageId): void
    {
        $message = InboxMessage::query()
            ->with(['toUser:id,name,username,email', 'fromUser:id,name,username'])
            ->find($inboxMessageId);

        if (!$message) {
            return;
        }

        if ($message->getAttribute('deleted_at')) {
            return;
        }

        $recipient = $message->toUser;
        if (!$recipient instanceof User) {
            return;
        }

        $payload = $this->buildPayload($message);
        $status = strtolower((string) ($payload['status'] ?? 'info'));
        $mailTargets = $this->resolveMailTargets($recipient, $status);
        $mailStats = $this->mailEnabled()
            ? $this->dispatchMailTargets($mailTargets, $payload, (int) $message->id, (int) $recipient->id)
            : ['attempted' => 0, 'sent' => 0, 'failed' => 0];

        $pushStats = ['sent' => 0, 'failed' => 0, 'skipped' => true];
        if ($this->webPushService->isEnabled()) {
            try {
                $pushStats = $this->webPushService->sendToUser($recipient, [
                    'title' => (string) ($payload['push_title'] ?? $payload['title'] ?? 'CCR Notification'),
                    'body' => (string) ($payload['push_body'] ?? $payload['summary'] ?? ''),
                    'url' => (string) ($payload['open_url'] ?? ''),
                    'status' => (string) ($payload['status'] ?? 'info'),
                    'tag' => 'ccr:' . ((string) ($payload['type'] ?? 'info')) . ':' . ((string) ($payload['status'] ?? 'info')),
                ]);
            } catch (\Throwable $e) {
                $pushStats = ['sent' => 0, 'failed' => 1, 'skipped' => false];
                Log::warning('CCR web push alert failed', [
                    'inbox_message_id' => (int) $message->id,
                    'user_id' => (int) $recipient->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $pushAttempted = $pushStats['skipped'] ? 0 : max(1, (int) $pushStats['sent'] + (int) $pushStats['failed']);
        $attemptedTotal = (int) $mailStats['attempted'] + $pushAttempted;
        $sentTotal = (int) $mailStats['sent'] + (int) $pushStats['sent'];
        $failedTotal = (int) $mailStats['failed'] + (int) $pushStats['failed'];

        if ($attemptedTotal === 0) {
            Log::info('CCR notification skipped: no eligible channel target', [
                'inbox_message_id' => (int) $message->id,
                'user_id' => (int) $recipient->id,
                'status' => $status,
            ]);
            return;
        }

        if ($sentTotal <= 0) {
            throw new RuntimeException('All notification channels failed for inbox_message_id=' . (int) $message->id);
        }

        Log::info('CCR notification dispatched', [
            'inbox_message_id' => (int) $message->id,
            'user_id' => (int) $recipient->id,
            'mail_attempted' => (int) $mailStats['attempted'],
            'mail_sent' => (int) $mailStats['sent'],
            'mail_failed' => (int) $mailStats['failed'],
            'push_sent' => (int) $pushStats['sent'],
            'push_failed' => (int) $pushStats['failed'],
            'status' => $status,
            'failed_total' => $failedTotal,
        ]);
    }

    private function mailEnabled(): bool
    {
        return (bool) config('ccr_notifications.mail_enabled', true);
    }

    private function isEligibleEmail(?string $email): bool
    {
        $value = trim((string) $email);
        if ($value === '' || filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
            return false;
        }

        $allowLocal = (bool) config('ccr_notifications.mail_allow_local_test', false);
        if (!$allowLocal && str_ends_with(strtolower($value), '@local.test')) {
            return false;
        }

        return true;
    }

    /**
     * @return array<int,array{email:string,target_key:string,recipient_id:int|null}>
     */
    private function resolveMailTargets(User $recipient, string $status): array
    {
        $targets = [];

        $recipientSetting = $this->resolveRecipientSettingForUser($recipient, $status);
        if ($recipientSetting) {
            $recipientEmail = strtolower(trim((string) $recipientSetting->email));
            if ($this->isEligibleEmail($recipientEmail)) {
                $targets[$recipientEmail] = [
                    'email' => $recipientEmail,
                    'target_key' => 'inbox-user:' . (int) $recipient->id,
                    'recipient_id' => (int) $recipientSetting->id,
                ];
            }
        }

        return array_values($targets);
    }

    private function resolveRecipientSettingForUser(User $recipient, string $status): ?NotificationRecipient
    {
        // First try by user_id (proper link)
        $byUserId = NotificationRecipient::query()
            ->select(['id', 'email', 'user_id', 'is_active', 'notify_waiting', 'notify_approved', 'notify_rejected'])
            ->where('user_id', (int) $recipient->id)
            ->active()
            ->forStatus($status)
            ->first();

        if ($byUserId) {
            return $byUserId;
        }

        // Fallback: match by email for legacy records without user_id
        $email = strtolower(trim((string) $recipient->email));
        if ($email === '') {
            return null;
        }

        return NotificationRecipient::query()
            ->select(['id', 'email', 'user_id', 'is_active', 'notify_waiting', 'notify_approved', 'notify_rejected'])
            ->active()
            ->forStatus($status)
            ->whereRaw('LOWER(email) = ?', [$email])
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @param array<int,array{email:string,target_key:string,recipient_id:int|null}> $targets
     * @param array<string,string> $payload
     * @return array{attempted:int,sent:int,failed:int}
     */
    private function dispatchMailTargets(array $targets, array $payload, int $inboxMessageId, int $userId): array
    {
        if (empty($targets)) {
            return ['attempted' => 0, 'sent' => 0, 'failed' => 0];
        }

        $stats = ['attempted' => 0, 'sent' => 0, 'failed' => 0];
        $cooldownSeconds = max(3, (int) config('ccr_notifications.cooldown_seconds', 8));

        foreach ($targets as $target) {
            $targetKey = (string) ($target['target_key'] ?? '');
            if ($targetKey === '') {
                continue;
            }

            $cooldownKey = $this->cooldownKey($targetKey, $payload);
            if (!Cache::add($cooldownKey, time(), now()->addSeconds($cooldownSeconds))) {
                continue;
            }

            $stats['attempted']++;

            try {
                Mail::to((string) $target['email'])->send(new CcrInboxAlertMail($payload));
                $stats['sent']++;

                if (!empty($target['recipient_id'])) {
                    NotificationRecipient::query()
                        ->where('id', (int) $target['recipient_id'])
                        ->update([
                            'last_notified_at' => now(),
                            'last_error' => null,
                            'updated_at' => now(),
                        ]);
                }
            } catch (\Throwable $e) {
                $stats['failed']++;

                if (!empty($target['recipient_id'])) {
                    NotificationRecipient::query()
                        ->where('id', (int) $target['recipient_id'])
                        ->update([
                            'last_error' => $this->cleanText($e->getMessage(), 255),
                            'updated_at' => now(),
                        ]);
                }

                Log::warning('CCR mail alert failed', [
                    'inbox_message_id' => $inboxMessageId,
                    'user_id' => $userId,
                    'email' => (string) ($target['email'] ?? ''),
                    'target_key' => $targetKey,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $stats;
    }

    /**
     * @return array<string, string>
     */
    private function buildPayload(InboxMessage $message): array
    {
        $title = $this->cleanText((string) ($message->title ?? 'CCR Notification'), 120);
        $rawMessage = $this->cleanText((string) ($message->message ?? ''), 900);
        $status = $this->detectStatus($rawMessage, (string) ($message->type ?? 'info'));
        $actor = $this->extractActor($rawMessage, $message);
        $summary = $this->buildSummary($rawMessage, $status);
        $openUrl = $this->absoluteUrl((string) ($message->url ?? ''));
        $reportMeta = $this->resolveReportMeta($openUrl);
        $component = $this->cleanText((string) ($reportMeta['component'] ?? $title), 120);
        $customer = $this->cleanText((string) ($reportMeta['customer'] ?? '-'), 120);
        $type = $this->detectType((string) ($message->type ?? ''), $openUrl, (string) ($reportMeta['type'] ?? ''));
        $timeText = optional($message->created_at)->format('d M Y H:i') ?? now()->format('d M Y H:i');
        $typeUpper = strtoupper($type !== '' ? $type : 'CCR');
        $subjectPrefix = trim((string) config('ccr_notifications.mail_subject_prefix', '[CCR-RNF]'));
        $subject = trim($subjectPrefix . ' CCR ' . $typeUpper);

        $logoUrl = trim((string) config('ccr_notifications.logo_url', ''));
        if ($logoUrl === '') {
            $logoUrl = url('/rnf-logo.png');
        } else {
            $logoUrl = $this->absoluteUrl($logoUrl);
        }

        return [
            'subject' => $subject,
            'title' => $component,
            'mail_heading' => 'CCR ' . $typeUpper,
            'status' => $status,
            'summary' => $summary,
            'component' => $component,
            'type' => $type,
            'customer' => $customer,
            'actor' => $actor,
            'actor_label' => $status === 'waiting' ? 'Submitted by' : 'Reviewed by',
            'time_text' => $timeText,
            'open_url' => $openUrl,
            'logo_url' => $logoUrl,
            'push_title' => 'CCR ' . $typeUpper . ' - ' . $component,
            'push_body' => $summary,
        ];
    }

    private function detectStatus(string $messageText, string $type): string
    {
        $m = strtolower($messageText);
        $t = strtolower($type);

        if (str_contains($m, 'approved') || str_contains($t, 'approved')) {
            return 'approved';
        }
        if (str_contains($m, 'rejected') || str_contains($t, 'rejected')) {
            return 'rejected';
        }
        if (str_contains($m, 'submit') || str_contains($t, 'submitted')) {
            return 'waiting';
        }

        return 'info';
    }

    private function detectType(string $type, string $openUrl, string $reportType = ''): string
    {
        if ($reportType !== '') {
            return strtolower($reportType) === 'seat' ? 'seat' : (strtolower($reportType) === 'engine' ? 'engine' : 'ccr');
        }

        $value = strtolower(trim($type));
        if (str_contains($value, 'seat') || str_contains($openUrl, '/seat/')) {
            return 'seat';
        }
        if (str_contains($value, 'engine') || str_contains($openUrl, '/engine/')) {
            return 'engine';
        }
        return 'ccr';
    }

    private function extractActor(string $messageText, InboxMessage $message): string
    {
        $from = $message->fromUser;
        if ($from instanceof User) {
            $displayName = trim((string) $from->name);
            if ($displayName !== '') {
                return $this->cleanText($displayName, 80);
            }

            $username = trim((string) $from->username);
            if ($username !== '') {
                return $this->cleanText($username, 80);
            }
        }

        if (preg_match('/\b(?:oleh|by)\s+([A-Za-z0-9_.\- ]{1,80}?)(?:[.,]|$)/iu', $messageText, $matches)) {
            $value = trim((string) ($matches[1] ?? ''));
            if ($value !== '') {
                return $this->cleanText($value, 80);
            }
        }

        return 'System';
    }

    private function buildSummary(string $messageText, string $status): string
    {
        $text = preg_replace('/\.?\s*Catatan:\s*.*/iu', '', $messageText) ?? $messageText;
        $text = preg_replace('/\s+/', ' ', trim($text)) ?? '';
        $statusSummary = match ($status) {
            'approved' => 'The CCR has been approved. Please proceed with the next required action.',
            'rejected' => 'The CCR has been rejected. Please review and submit the required revision.',
            'waiting' => 'A CCR has been submitted. Please review the CCR at your earliest convenience.',
            default => 'There is a new update on the CCR record.',
        };

        if ($text === '' || preg_match('/\b(disubmit|submit|approved|rejected)\b/iu', $text) === 1) {
            $text = $statusSummary;
        }

        return $this->cleanText($text, 240);
    }

    private function absoluteUrl(string $value): string
    {
        $text = trim($value);
        if ($text === '') {
            return url('/inbox');
        }
        if (str_starts_with($text, '/')) {
            return url($text);
        }
        if (filter_var($text, FILTER_VALIDATE_URL) !== false) {
            $candidateHost = parse_url($text, PHP_URL_HOST);
            $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);
            if ($candidateHost !== null && $appHost !== null && strtolower((string) $candidateHost) === strtolower((string) $appHost)) {
                return $text;
            }
        }

        return url('/inbox');
    }

    /**
     * @return array{component:string,customer:string,type:string}|array{}
     */
    private function resolveReportMeta(string $url): array
    {
        if ($url === '') {
            return [];
        }

        $reportId = 0;
        if (preg_match('~/(?:engine|seat)/(\d+)~', $url, $matches)) {
            $reportId = (int) ($matches[1] ?? 0);
        } else {
            $query = (string) parse_url($url, PHP_URL_QUERY);
            if ($query !== '') {
                $params = [];
                parse_str($query, $params);
                $reportId = (int) ($params['open'] ?? 0);
            }
        }

        if ($reportId <= 0) {
            return [];
        }

        $report = CcrReport::query()->find($reportId);
        if (!$report) {
            return [];
        }

        return [
            'component' => (string) ($report->component ?? ''),
            'customer' => (string) ($report->customer ?? ''),
            'type' => (string) ($report->type ?? ''),
        ];
    }

    /**
     * @param array<string, string> $payload
     */
    private function cooldownKey(string $targetKey, array $payload): string
    {
        $fingerprint = hash(
            'sha256',
            implode('|', [
                $targetKey,
                (string) ($payload['title'] ?? ''),
                (string) ($payload['status'] ?? ''),
                (string) ($payload['open_url'] ?? ''),
            ])
        );

        return 'ccr:notify:cooldown:' . $fingerprint;
    }

    private function cleanText(string $value, int $max): string
    {
        $text = strip_tags($value);
        $text = preg_replace('/\s+/u', ' ', trim($text)) ?? '';
        if ($text === '') {
            return '';
        }
        if (mb_strlen($text) <= $max) {
            return $text;
        }

        return rtrim(mb_substr($text, 0, $max));
    }
}
