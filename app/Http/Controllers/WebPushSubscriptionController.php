<?php

namespace App\Http\Controllers;

use App\Models\WebPushSubscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WebPushSubscriptionController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        if (!$this->webPushEnabled()) {
            return response()->json([
                'ok' => false,
                'message' => 'Web Push belum aktif di server.',
            ], 202);
        }

        $validated = $request->validate([
            'subscription' => ['required', 'array'],
            'subscription.endpoint' => ['required', 'string', 'max:2048'],
            'subscription.keys' => ['required', 'array'],
            'subscription.keys.p256dh' => ['required', 'string', 'max:512'],
            'subscription.keys.auth' => ['required', 'string', 'max:512'],
            'subscription.contentEncoding' => ['nullable', 'string', 'max:32'],
            'user_agent' => ['nullable', 'string', 'max:512'],
        ]);

        $endpoint = trim((string) data_get($validated, 'subscription.endpoint', ''));
        if (!$this->isAllowedEndpoint($endpoint)) {
            return response()->json([
                'ok' => false,
                'message' => 'Endpoint push tidak valid.',
            ], 422);
        }

        $payload = [
            'user_id' => (int) $request->user()->id,
            'endpoint_hash' => hash('sha256', $endpoint),
            'endpoint' => $endpoint,
            'public_key' => trim((string) data_get($validated, 'subscription.keys.p256dh', '')),
            'auth_token' => trim((string) data_get($validated, 'subscription.keys.auth', '')),
            'content_encoding' => trim((string) data_get($validated, 'subscription.contentEncoding', 'aesgcm')) ?: 'aesgcm',
            'user_agent' => trim((string) ($validated['user_agent'] ?? $request->userAgent() ?? '')) ?: null,
            'fail_count' => 0,
            'last_error' => null,
            'last_seen_at' => now(),
            'disabled_at' => null,
        ];

        $subscription = WebPushSubscription::query()->updateOrCreate(
            ['endpoint_hash' => $payload['endpoint_hash']],
            $payload
        );

        return response()->json([
            'ok' => true,
            'id' => (int) $subscription->id,
        ]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'endpoint' => ['nullable', 'string', 'max:2048'],
            'endpoint_hash' => ['nullable', 'string', 'size:64'],
        ]);

        $endpointHash = trim((string) ($validated['endpoint_hash'] ?? ''));
        if ($endpointHash === '') {
            $endpoint = trim((string) ($validated['endpoint'] ?? ''));
            if ($endpoint !== '') {
                $endpointHash = hash('sha256', $endpoint);
            }
        }

        if ($endpointHash === '') {
            return response()->json([
                'ok' => false,
                'message' => 'Endpoint tidak ditemukan.',
            ], 422);
        }

        WebPushSubscription::query()
            ->where('user_id', (int) $request->user()->id)
            ->where('endpoint_hash', $endpointHash)
            ->update([
                'disabled_at' => now(),
                'updated_at' => now(),
            ]);

        return response()->json(['ok' => true]);
    }

    private function webPushEnabled(): bool
    {
        return (bool) config('ccr_notifications.web_push_enabled', false);
    }

    private function isAllowedEndpoint(string $endpoint): bool
    {
        if ($endpoint === '') {
            return false;
        }

        if (!str_starts_with($endpoint, 'https://')) {
            return false;
        }

        return filter_var($endpoint, FILTER_VALIDATE_URL) !== false;
    }
}

