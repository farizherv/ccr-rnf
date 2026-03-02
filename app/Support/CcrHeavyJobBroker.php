<?php

namespace App\Support;

use App\Jobs\BuildPartsLabourExportJob;
use App\Jobs\BuildPreviewPdfJob;
use App\Jobs\BuildWordExportJob;
use Illuminate\Support\Facades\Cache;
use Throwable;

class CcrHeavyJobBroker
{
    private const TYPES = ['engine', 'seat'];
    private const KINDS = ['word', 'preview', 'parts'];

    public function queueEnabled(): bool
    {
        $enabled = filter_var(env('CCR_HEAVY_QUEUE_ENABLED', true), FILTER_VALIDATE_BOOLEAN);
        if (!$enabled) {
            return false;
        }

        return (string) config('queue.default', 'sync') !== 'sync';
    }

    public function queueName(): string
    {
        $name = trim((string) env('CCR_HEAVY_QUEUE_NAME', 'ccr-heavy'));
        return $name !== '' ? $name : 'ccr-heavy';
    }

    public function pendingTtlSeconds(): int
    {
        $raw = env('CCR_HEAVY_PENDING_TTL_SECONDS');
        $value = is_numeric($raw) ? (int) $raw : 300;
        return max(30, min(3600, $value));
    }

    public function cooldownSeconds(): int
    {
        $raw = env('CCR_HEAVY_COOLDOWN_SECONDS');
        $value = is_numeric($raw) ? (int) $raw : 45;
        return max(10, min(900, $value));
    }

    public function executionLockSeconds(): int
    {
        $raw = env('CCR_HEAVY_LOCK_SECONDS');
        $value = is_numeric($raw) ? (int) $raw : 600;
        return max(60, min(3600, $value));
    }

    public function enqueue(string $kind, string $type, int $reportId): bool
    {
        $kind = $this->normalizeKind($kind);
        $type = $this->normalizeType($type);
        $reportId = max(1, $reportId);

        if (!$this->queueEnabled()) {
            return false;
        }

        if ($this->cooldownRemainingSeconds($kind, $type, $reportId) > 0) {
            return false;
        }

        if (!$this->markQueued($kind, $type, $reportId)) {
            return false;
        }

        $queue = $this->queueName();
        $job = match ($kind) {
            'word' => (new BuildWordExportJob($type, $reportId))->onQueue($queue),
            'preview' => (new BuildPreviewPdfJob($type, $reportId))->onQueue($queue),
            default => (new BuildPartsLabourExportJob($type, $reportId))->onQueue($queue),
        };

        try {
            dispatch($job);
            return true;
        } catch (Throwable $e) {
            $this->markFailure($kind, $type, $reportId, $e->getMessage());
            return false;
        }
    }

    public function markQueued(string $kind, string $type, int $reportId): bool
    {
        return Cache::add(
            $this->pendingKey($kind, $type, $reportId),
            (string) time(),
            now()->addSeconds($this->pendingTtlSeconds())
        );
    }

    public function markSuccess(string $kind, string $type, int $reportId): void
    {
        Cache::forget($this->pendingKey($kind, $type, $reportId));
        Cache::forget($this->cooldownKey($kind, $type, $reportId));
    }

    public function markFailure(string $kind, string $type, int $reportId, ?string $message = null): void
    {
        Cache::forget($this->pendingKey($kind, $type, $reportId));

        $cooldown = $this->cooldownSeconds();
        $until = time() + $cooldown;
        Cache::put(
            $this->cooldownKey($kind, $type, $reportId),
            [
                'until' => $until,
                'message' => $message ? substr($message, 0, 240) : null,
            ],
            now()->addSeconds($cooldown)
        );
    }

    public function isPending(string $kind, string $type, int $reportId): bool
    {
        return Cache::has($this->pendingKey($kind, $type, $reportId));
    }

    public function cooldownRemainingSeconds(string $kind, string $type, int $reportId): int
    {
        $raw = Cache::get($this->cooldownKey($kind, $type, $reportId));
        if (!is_array($raw)) {
            return 0;
        }

        $until = (int) ($raw['until'] ?? 0);
        if ($until <= 0) {
            return 0;
        }

        return max(0, $until - time());
    }

    public function retryAfterSeconds(string $kind, string $type, int $reportId): int
    {
        $pending = $this->isPending($kind, $type, $reportId) ? 3 : 0;
        $cooldown = $this->cooldownRemainingSeconds($kind, $type, $reportId);
        return max(3, $pending, $cooldown);
    }

    public function executionLockKey(string $kind, string $type, int $reportId): string
    {
        return sprintf(
            'ccr:heavy:lock:%s:%s:%d',
            $this->normalizeKind($kind),
            $this->normalizeType($type),
            max(1, $reportId)
        );
    }

    private function pendingKey(string $kind, string $type, int $reportId): string
    {
        return sprintf(
            'ccr:heavy:pending:%s:%s:%d',
            $this->normalizeKind($kind),
            $this->normalizeType($type),
            max(1, $reportId)
        );
    }

    private function cooldownKey(string $kind, string $type, int $reportId): string
    {
        return sprintf(
            'ccr:heavy:cooldown:%s:%s:%d',
            $this->normalizeKind($kind),
            $this->normalizeType($type),
            max(1, $reportId)
        );
    }

    private function normalizeType(string $type): string
    {
        $normalized = strtolower(trim($type));
        return in_array($normalized, self::TYPES, true) ? $normalized : 'engine';
    }

    private function normalizeKind(string $kind): string
    {
        $normalized = strtolower(trim($kind));
        return in_array($normalized, self::KINDS, true) ? $normalized : 'word';
    }
}
