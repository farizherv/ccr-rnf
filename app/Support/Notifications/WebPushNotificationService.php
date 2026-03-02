<?php

namespace App\Support\Notifications;

use App\Models\User;
use App\Models\WebPushSubscription;
use Illuminate\Support\Facades\Log;

class WebPushNotificationService
{
    /**
     * @param array<string, mixed> $payload
     * @return array{sent:int,failed:int,skipped:bool}
     */
    public function sendToUser(User $user, array $payload): array
    {
        if (!$this->isEnabled()) {
            return ['sent' => 0, 'failed' => 0, 'skipped' => true];
        }

        $subscriptions = WebPushSubscription::query()
            ->where('user_id', (int) $user->id)
            ->whereNull('disabled_at')
            ->orderByDesc('updated_at')
            ->limit(20)
            ->get();

        if ($subscriptions->isEmpty()) {
            return ['sent' => 0, 'failed' => 0, 'skipped' => true];
        }

        $auth = [
            'VAPID' => [
                'subject' => (string) config('ccr_notifications.web_push_subject'),
                'publicKey' => (string) config('ccr_notifications.web_push_public_key'),
                'privateKey' => (string) config('ccr_notifications.web_push_private_key'),
            ],
        ];

        $defaultOptions = [
            'TTL' => (int) config('ccr_notifications.web_push_ttl', 300),
            'urgency' => (string) config('ccr_notifications.web_push_urgency', 'normal'),
        ];

        $webPushClass = '\\Minishlink\\WebPush\\WebPush';
        $subscriptionClass = '\\Minishlink\\WebPush\\Subscription';
        $webPush = new $webPushClass($auth, $defaultOptions);
        $message = $this->normalizePayload($payload);
        $jsonPayload = json_encode($message, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($jsonPayload) || $jsonPayload === '') {
            return ['sent' => 0, 'failed' => 0, 'skipped' => true];
        }

        foreach ($subscriptions as $subscription) {
            $minishlinkSubscription = $subscriptionClass::create([
                'endpoint' => (string) $subscription->endpoint,
                'publicKey' => (string) $subscription->public_key,
                'authToken' => (string) $subscription->auth_token,
                'contentEncoding' => (string) ($subscription->content_encoding ?: 'aesgcm'),
            ]);

            $webPush->queueNotification($minishlinkSubscription, $jsonPayload);
        }

        $sent = 0;
        $failed = 0;
        $maxFailures = max(1, (int) config('ccr_notifications.web_push_max_failures', 3));

        foreach ($webPush->flush() as $report) {
            $endpoint = $this->reportEndpoint($report);
            if ($endpoint === '') {
                continue;
            }

            $endpointHash = hash('sha256', $endpoint);
            $row = WebPushSubscription::query()->where('endpoint_hash', $endpointHash)->first();
            if (!$row) {
                continue;
            }

            if ($this->isReportSuccess($report)) {
                $sent++;
                $row->forceFill([
                    'fail_count' => 0,
                    'last_error' => null,
                    'last_seen_at' => now(),
                    'disabled_at' => null,
                ])->save();
                continue;
            }

            $failed++;
            $nextFailCount = (int) $row->fail_count + 1;
            $statusCode = $this->reportStatusCode($report);
            $disable = in_array($statusCode, [404, 410], true) || $nextFailCount >= $maxFailures;
            $row->forceFill([
                'fail_count' => $nextFailCount,
                'last_error' => $this->trimString($this->reportReason($report), 255),
                'last_seen_at' => now(),
                'disabled_at' => $disable ? now() : null,
            ])->save();
        }

        return ['sent' => $sent, 'failed' => $failed, 'skipped' => false];
    }

    public function isEnabled(): bool
    {
        if (!(bool) config('ccr_notifications.web_push_enabled', false)) {
            return false;
        }

        if (!class_exists('\\Minishlink\\WebPush\\WebPush') || !class_exists('\\Minishlink\\WebPush\\Subscription')) {
            return false;
        }

        $publicKey = trim((string) config('ccr_notifications.web_push_public_key', ''));
        $privateKey = trim((string) config('ccr_notifications.web_push_private_key', ''));
        $subject = trim((string) config('ccr_notifications.web_push_subject', ''));

        return $publicKey !== '' && $privateKey !== '' && $subject !== '';
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function normalizePayload(array $payload): array
    {
        $safeTitle = $this->trimString((string) ($payload['title'] ?? 'CCR Notification'), 110);
        $safeBody = $this->trimString((string) ($payload['body'] ?? ''), 240);
        $safeUrl = $this->safeUrl((string) ($payload['url'] ?? ''));

        return [
            'title' => $safeTitle !== '' ? $safeTitle : 'CCR Notification',
            'body' => $safeBody,
            'url' => $safeUrl !== '' ? $safeUrl : url('/inbox'),
            'tag' => $this->trimString((string) ($payload['tag'] ?? 'ccr-notification'), 64),
            'status' => $this->trimString((string) ($payload['status'] ?? 'info'), 32),
            'icon' => $this->safeAsset((string) config('ccr_notifications.web_push_icon', '/favicon-32.png')),
            'badge' => $this->safeAsset((string) config('ccr_notifications.web_push_badge', '/favicon-16.png')),
        ];
    }

    private function safeUrl(string $value): string
    {
        $text = trim($value);
        if ($text === '') {
            return '';
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

        return '';
    }

    private function safeAsset(string $value): string
    {
        $text = trim($value);
        if ($text === '') {
            return '';
        }
        if (str_starts_with($text, '/')) {
            return url($text);
        }
        if (filter_var($text, FILTER_VALIDATE_URL) !== false) {
            return $text;
        }
        return '';
    }

    private function trimString(string $value, int $max): string
    {
        $text = trim($value);
        if ($text === '') {
            return '';
        }

        if (mb_strlen($text) <= $max) {
            return $text;
        }

        return rtrim(mb_substr($text, 0, $max));
    }

    private function isReportSuccess(mixed $report): bool
    {
        try {
            if (method_exists($report, 'isSuccess')) {
                return (bool) $report->isSuccess();
            }
        } catch (\Throwable $e) {
            Log::debug('Web push report success check failed', ['error' => $e->getMessage()]);
        }

        return false;
    }

    private function reportEndpoint(mixed $report): string
    {
        try {
            if (method_exists($report, 'getEndpoint')) {
                $endpoint = (string) $report->getEndpoint();
                if ($endpoint !== '') {
                    return $endpoint;
                }
            }
            if (method_exists($report, 'getRequest')) {
                $request = $report->getRequest();
                if ($request && method_exists($request, 'getUri')) {
                    return (string) $request->getUri();
                }
            }
        } catch (\Throwable $e) {
            Log::debug('Web push report endpoint check failed', ['error' => $e->getMessage()]);
        }

        return '';
    }

    private function reportStatusCode(mixed $report): ?int
    {
        try {
            if (method_exists($report, 'getResponse')) {
                $response = $report->getResponse();
                if ($response && method_exists($response, 'getStatusCode')) {
                    return (int) $response->getStatusCode();
                }
            }
        } catch (\Throwable $e) {
            Log::debug('Web push report status check failed', ['error' => $e->getMessage()]);
        }

        return null;
    }

    private function reportReason(mixed $report): string
    {
        try {
            if (method_exists($report, 'getReason')) {
                return (string) $report->getReason();
            }
        } catch (\Throwable $e) {
            Log::debug('Web push report reason check failed', ['error' => $e->getMessage()]);
        }

        return 'web-push-failed';
    }
}
