<?php

namespace App\Support;

use App\Models\CcrDraft;
use App\Models\CcrItem;
use App\Models\CcrPhoto;
use App\Models\CcrReport;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * CcrReportService — Shared CRUD, submit, and file management logic.
 *
 * Extracted from CcrEngineController & CcrSeatController to eliminate duplication.
 */
class CcrReportService
{
    public function __construct(
        private readonly PayloadSanitizer $sanitizer,
        private readonly CcrWorksheetService $worksheet,
    ) {}

    // =====================================================================
    // SUBMIT TO DIRECTOR
    // =====================================================================

    /**
     * Submit or re-submit a report to the director.
     *
     * @param CcrReport $report
     * @param bool $resubmit
     * @param string $typeFallback  Fallback component name ('Engine' or 'Seat')
     * @param string $notificationType  Notification type key (e.g. 'engine_submitted')
     * @return array{ok: bool, message: string}
     */
    public function submitToDirector(CcrReport $report, bool $resubmit, string $typeFallback, string $notificationType): array
    {
        if (in_array($report->approval_status, ['waiting', 'in_review'])) {
            return ['ok' => false, 'message' => 'CCR ini sedang menunggu persetujuan Direktur.'];
        }

        if ($report->approval_status === 'approved' && !$resubmit) {
            return ['ok' => false, 'message' => 'CCR ini sudah Approved. Gunakan tombol Re-submit jika ingin kirim ulang.'];
        }

        $report->approval_status = 'waiting';
        $report->submitted_by    = auth()->id();
        $report->submitted_at    = now();

        if ($resubmit) {
            $report->director_note = null;
        }

        $report->save();

        $componentName = trim((string) ($report->component ?? ''));
        if ($componentName === '') $componentName = $typeFallback;

        $openUrl = route('director.monitoring', ['open' => $report->id], false) . '#r-' . $report->id;

        Inbox::toRoles(['director'], [
            'type'    => $notificationType,
            'title'   => $componentName,
            'message' => 'Disubmit oleh ' . (auth()->user()->name ?? 'User') . '.',
            'url'     => $openUrl,
        ], auth()->id());

        return [
            'ok' => true,
            'message' => $resubmit
                ? "CCR {$typeFallback} berhasil di Re-submit ke Direktur."
                : "CCR {$typeFallback} berhasil dikirim ke Direktur.",
        ];
    }

    // =====================================================================
    // DELETE ITEM with locking
    // =====================================================================

    /**
     * Delete an item with revision guard and photo cleanup.
     * Returns mutation result array.
     */
    public function deleteItemWithLocking(CcrItem $item, ?int $partsClientRev, ?int $detailClientRev): array
    {
        $reportId = (int) $item->ccr_report_id;

        return DB::transaction(function () use ($reportId, $item, $partsClientRev, $detailClientRev) {
            $report = CcrReport::query()->whereKey($reportId)->lockForUpdate()->firstOrFail();
            $staleSections = $this->worksheet->staleSectionsFromClientRevision($report, $partsClientRev, $detailClientRev);
            if (!empty($staleSections)) {
                return [
                    'ok' => false,
                    'stale' => true,
                    'sections' => $staleSections,
                    'parts_payload_rev' => (int) ($report->parts_payload_rev ?? 0),
                    'detail_payload_rev' => (int) ($report->detail_payload_rev ?? 0),
                ];
            }

            $lockedItem = CcrItem::query()
                ->where('ccr_report_id', $reportId)
                ->whereKey((int) $item->id)
                ->lockForUpdate()
                ->first();

            if (!$lockedItem) {
                return [
                    'ok' => false,
                    'missing' => true,
                    'parts_payload_rev' => (int) ($report->parts_payload_rev ?? 0),
                    'detail_payload_rev' => (int) ($report->detail_payload_rev ?? 0),
                ];
            }

            $lockedItem->loadMissing('photos');
            foreach ($lockedItem->photos as $p) {
                Storage::disk('public')->delete($p->path);
                $p->delete();
            }
            $lockedItem->delete();

            $report->docx_generated_at = null;
            $report->touch();

            return [
                'ok' => true,
                'deleted' => true,
                'item_id' => (int) $item->id,
                'parts_payload_rev' => (int) ($report->parts_payload_rev ?? 0),
                'detail_payload_rev' => (int) ($report->detail_payload_rev ?? 0),
            ];
        });
    }

    // =====================================================================
    // DELETE PHOTO with locking
    // =====================================================================

    /**
     * Delete a photo with revision guard.
     */
    public function deletePhotoWithLocking(CcrPhoto $photo, int $reportId, ?int $partsClientRev, ?int $detailClientRev): array
    {
        return DB::transaction(function () use ($reportId, $photo, $partsClientRev, $detailClientRev) {
            $report = CcrReport::query()->whereKey($reportId)->lockForUpdate()->firstOrFail();
            $staleSections = $this->worksheet->staleSectionsFromClientRevision($report, $partsClientRev, $detailClientRev);
            if (!empty($staleSections)) {
                return [
                    'ok' => false,
                    'stale' => true,
                    'sections' => $staleSections,
                    'parts_payload_rev' => (int) ($report->parts_payload_rev ?? 0),
                    'detail_payload_rev' => (int) ($report->detail_payload_rev ?? 0),
                ];
            }

            $lockedPhoto = CcrPhoto::query()
                ->whereKey((int) $photo->id)
                ->lockForUpdate()
                ->first();

            if (!$lockedPhoto) {
                return [
                    'ok' => false,
                    'missing' => true,
                    'parts_payload_rev' => (int) ($report->parts_payload_rev ?? 0),
                    'detail_payload_rev' => (int) ($report->detail_payload_rev ?? 0),
                ];
            }

            Storage::disk('public')->delete($lockedPhoto->path);
            $lockedPhoto->delete();

            $report->docx_generated_at = null;
            $report->touch();

            return [
                'ok' => true,
                'deleted' => true,
                'parts_payload_rev' => (int) ($report->parts_payload_rev ?? 0),
                'detail_payload_rev' => (int) ($report->detail_payload_rev ?? 0),
            ];
        });
    }

    // =====================================================================
    // DELETE MULTIPLE (bulk soft delete with photo cleanup)
    // =====================================================================

    /**
     * Bulk soft-delete reports of the given type, with storage cleanup.
     */
    public function deleteMultipleReports(array $ids, string $type): int
    {
        $ids = collect($ids)
            ->map(fn($id) => (int) $id)
            ->filter(fn($id) => $id > 0)
            ->unique()->values()->all();

        if (empty($ids)) return 0;

        $reports = CcrReport::with('items.photos')
            ->where('type', $type)
            ->whereIn('id', $ids)->get();

        foreach ($reports as $r) {
            foreach ($r->items as $item) {
                foreach ($item->photos as $photo) {
                    if (!empty($photo->path)) {
                        Storage::disk('public')->delete($photo->path);
                    }
                }
            }
            $r->purge_at = now()->addDays(7);
            $r->save();
            $r->delete();
        }

        return $reports->count();
    }

    // =====================================================================
    // DRAFT helpers
    // =====================================================================

    /**
     * Resolve a draft model by ID or client key.
     */
    public function resolveCreateDraftModel(string $type, int $userId, ?string $draftId, ?string $clientKey): ?CcrDraft
    {
        $draftId = trim((string) ($draftId ?? ''));
        $clientKey = trim((string) ($clientKey ?? ''));

        if ($draftId !== '') {
            $draft = CcrDraft::query()
                ->where('id', $draftId)
                ->where('user_id', $userId)
                ->where('type', $type)
                ->first();
            if ($draft) return $draft;
        }

        if ($clientKey !== '') {
            $draft = CcrDraft::query()
                ->where('user_id', $userId)
                ->where('type', $type)
                ->where('client_key', $clientKey)
                ->first();
            if ($draft) return $draft;
        }

        return null;
    }

    /**
     * Resolve draft seed data for the create page.
     *
     * If the URL has ?draft=<id>, load that specific draft.
     * Otherwise, auto-load the most recent draft for this user/type
     * so that a page refresh doesn't lose filled-in header fields.
     */
    public function resolveCreateDraftSeed(Request $request, string $type): array
    {
        $userId = (int) auth()->id();
        $draftId = trim((string) $request->query('draft', ''));

        $draft = null;

        if ($draftId !== '') {
            // Explicit draft ID from URL
            $draft = CcrDraft::query()
                ->where('id', $draftId)
                ->where('user_id', $userId)
                ->where('type', $type)
                ->first();
        } else {
            // Auto-detect: pick the most recently saved draft for this user/type
            $draft = CcrDraft::query()
                ->where('user_id', $userId)
                ->where('type', $type)
                ->orderByDesc('last_saved_at')
                ->orderByDesc('updated_at')
                ->first();
        }

        if (!$draft) return [];

        return [
            'id' => (string) $draft->id,
            'type' => (string) $draft->type,
            'client_key' => (string) ($draft->client_key ?? ''),
            'name' => (string) ($draft->draft_name ?? ''),
            'ccr_payload' => is_array($draft->ccr_payload) ? $draft->ccr_payload : [],
            'parts_payload' => is_array($draft->parts_payload) ? $draft->parts_payload : [],
            'detail_payload' => is_array($draft->detail_payload) ? $draft->detail_payload : [],
            'items_payload' => is_array($draft->items_payload) ? $draft->items_payload : [],
            'last_saved_at' => optional($draft->last_saved_at)->toISOString(),
        ];
    }

    /**
     * Generate cache key to mark draft as finalized.
     */
    public function draftFinalizedCacheKey(string $type, int $userId, string $clientKey): string
    {
        return "ccr_draft_finalized:{$type}:{$userId}:{$clientKey}";
    }

    // =====================================================================
    // File upload helpers
    // =====================================================================

    /**
     * Count total uploaded image files by request keys.
     */
    public function countUploadedFilesByKeys(Request $request, array $keys): int
    {
        $total = 0;
        foreach ($keys as $key) {
            $total += count($this->normalizeUploadedImageFiles($request->file($key)));
        }
        return $total;
    }

    /**
     * Normalize uploaded image files from nested request structure.
     *
     * @return array<int, UploadedFile>
     */
    public function normalizeUploadedImageFiles(mixed $value): array
    {
        $out = [];
        $queue = [];

        if (is_array($value)) {
            $queue = array_values($value);
        } elseif ($value instanceof UploadedFile) {
            $queue = [$value];
        }

        while (!empty($queue)) {
            $current = array_shift($queue);
            if ($current instanceof UploadedFile) {
                if ($current->isValid()) {
                    $out[] = $current;
                }
                continue;
            }
            if (is_array($current)) {
                foreach ($current as $nested) {
                    $queue[] = $nested;
                }
            }
        }

        return $out;
    }

    // =====================================================================
    // Role helpers
    // =====================================================================

    public function actorRole(): string
    {
        $role = optional(auth()->user())->role;
        return $role instanceof \App\Enums\UserRole ? $role->value : strtolower(trim((string) $role));
    }

    public function canEditWorksheet(): bool
    {
        $role = $this->actorRole();
        return in_array($role, ['admin', 'director'], true);
    }
}
