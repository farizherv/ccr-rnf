<?php

namespace App\Http\Controllers;

use App\Models\CcrDraft;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;

class CcrDraftController extends Controller
{
    /**
     * GET /ccr/drafts?type=engine|seat
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['nullable', Rule::in(['engine', 'seat'])],
        ]);

        $query = CcrDraft::query()
            ->where('user_id', (int) auth()->id())
            ->orderByDesc('last_saved_at')
            ->orderByDesc('updated_at');

        $type = (string) ($validated['type'] ?? '');
        if ($type !== '') {
            $query->where('type', $type);
        }

        $drafts = $query->limit(50)->get()->map(function (CcrDraft $draft) {
            return [
                'id' => (string) $draft->id,
                'type' => (string) $draft->type,
                'client_key' => (string) ($draft->client_key ?? ''),
                'draft_name' => (string) ($draft->draft_name ?? ''),
                'last_saved_at' => optional($draft->last_saved_at)->toISOString(),
                'updated_at' => optional($draft->updated_at)->toISOString(),
                'has_ccr' => is_array($draft->ccr_payload) && !empty($draft->ccr_payload),
                'has_parts' => is_array($draft->parts_payload) && !empty($draft->parts_payload),
                'has_detail' => is_array($draft->detail_payload) && !empty($draft->detail_payload),
                'has_items' => is_array($draft->items_payload) && !empty($draft->items_payload),
            ];
        })->values();

        return response()->json([
            'ok' => true,
            'drafts' => $drafts,
        ]);
    }

    /**
     * POST /ccr/drafts/upsert
     */
    public function upsert(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'draft_id' => ['nullable', 'string', 'max:64'],
            'client_key' => ['nullable', 'string', 'max:120', 'regex:/^[A-Za-z0-9:_-]+$/'],
            'type' => ['required', Rule::in(['engine', 'seat'])],
            'section' => ['required', Rule::in(['ccr', 'parts', 'detail', 'items'])],
            'payload' => ['nullable', 'array'],
            'draft_name' => ['nullable', 'string', 'max:190'],
            'draft_name_auto' => ['nullable', 'string', 'max:190'],
        ]);

        $userId = (int) auth()->id();
        $type = (string) $validated['type'];
        $section = (string) $validated['section'];
        $sectionField = $this->sectionToColumn($section);
        $payload = (array) ($validated['payload'] ?? []);
        $clientKey = trim((string) ($validated['client_key'] ?? ''));

        $draftId = trim((string) ($validated['draft_id'] ?? ''));
        if ($clientKey !== '' && Cache::has($this->draftFinalizedCacheKey($type, $userId, $clientKey))) {
            CcrDraft::query()
                ->where('user_id', $userId)
                ->where('type', $type)
                ->where('client_key', $clientKey)
                ->delete();

            return response()->json([
                'ok' => true,
                'skipped' => true,
                'message' => 'Draft sudah difinalkan.',
            ]);
        }

        $draft = null;
        if ($draftId !== '') {
            $draft = CcrDraft::query()
                ->where('id', $draftId)
                ->where('user_id', $userId)
                ->where('type', $type)
                ->first();
        }

        if (!$draft && $clientKey !== '') {
            $draft = CcrDraft::query()
                ->where('user_id', $userId)
                ->where('type', $type)
                ->where('client_key', $clientKey)
                ->first();
        }

        if (!$draft && $draftId !== '' && $clientKey === '') {
            return response()->json([
                'ok' => true,
                'skipped' => true,
                'message' => 'Draft tidak lagi aktif.',
            ]);
        }

        if (!$draft) {
            if ($clientKey !== '') {
                $draft = $this->firstOrCreateByClientKey($userId, $type, $clientKey);
            } else {
                $draft = new CcrDraft();
                $draft->id = (string) Str::ulid();
                $draft->user_id = $userId;
                $draft->type = $type;
            }
        }

        if ($clientKey !== '' && trim((string) ($draft->client_key ?? '')) === '') {
            $draft->client_key = $clientKey;
        }

        // Tetap simpan payload kosong agar delete/reset section ikut tersinkron ke server.
        $draft->{$sectionField} = !empty($payload) ? $payload : null;

        $manualName = trim((string) ($validated['draft_name'] ?? ''));
        if ($manualName !== '') {
            $draft->draft_name = $manualName;
        } else {
            $autoName = trim((string) ($validated['draft_name_auto'] ?? ''));
            if ($autoName !== '') {
                $draft->draft_name = $autoName;
            } elseif (trim((string) ($draft->draft_name ?? '')) === '') {
                $draft->draft_name = strtoupper($type) . ' Draft';
            }
        }

        $draft->last_saved_at = now();
        $draft->save();

        return response()->json([
            'ok' => true,
            'draft' => [
                'id' => (string) $draft->id,
                'type' => (string) $draft->type,
                'client_key' => (string) ($draft->client_key ?? ''),
                'draft_name' => (string) ($draft->draft_name ?? ''),
                'last_saved_at' => optional($draft->last_saved_at)->toISOString(),
                'updated_at' => optional($draft->updated_at)->toISOString(),
            ],
        ]);
    }

    /**
     * DELETE /ccr/drafts/{id}
     */
    public function destroy(string $id): JsonResponse
    {
        $draft = CcrDraft::query()
            ->where('id', $id)
            ->where('user_id', (int) auth()->id())
            ->first();

        if (!$draft) {
            return response()->json([
                'ok' => false,
                'message' => 'Draft tidak ditemukan.',
            ], 404);
        }

        $draft->delete();

        return response()->json([
            'ok' => true,
            'message' => 'Draft dihapus.',
        ]);
    }

    private function sectionToColumn(string $section): string
    {
        return match ($section) {
            'ccr' => 'ccr_payload',
            'parts' => 'parts_payload',
            'detail' => 'detail_payload',
            'items' => 'items_payload',
            default => 'ccr_payload',
        };
    }

    private function firstOrCreateByClientKey(int $userId, string $type, string $clientKey): CcrDraft
    {
        $existing = CcrDraft::query()
            ->where('user_id', $userId)
            ->where('type', $type)
            ->where('client_key', $clientKey)
            ->first();
        if ($existing) return $existing;

        try {
            return CcrDraft::query()->create([
                'id' => (string) Str::ulid(),
                'user_id' => $userId,
                'type' => $type,
                'client_key' => $clientKey,
                'draft_name' => strtoupper($type) . ' Draft',
            ]);
        } catch (QueryException $e) {
            $retry = CcrDraft::query()
                ->where('user_id', $userId)
                ->where('type', $type)
                ->where('client_key', $clientKey)
                ->first();
            if ($retry) return $retry;
            throw $e;
        }
    }

    private function draftFinalizedCacheKey(string $type, int $userId, string $clientKey): string
    {
        return 'ccr:draft:finalized:' . $type . ':' . $userId . ':' . $clientKey;
    }
}
