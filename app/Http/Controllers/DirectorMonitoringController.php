<?php

namespace App\Http\Controllers;

use App\Models\CcrReport;
use App\Support\CcrApprovalStateMachine;
use App\Support\Inbox;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DirectorMonitoringController extends Controller
{
    /**
     * Sort options for monitoring page (server-side).
     */
    private const SORT_OPTIONS = [
        'submitted_newest',
        'submitted_oldest',
        'inspection_newest',
        'inspection_oldest',
    ];

    /**
     * Default page size for monitoring list.
     */
    private const DEFAULT_PER_PAGE = 25;

    public function index(Request $request)
    {
        $filters = $this->normalizeFilters(
            $request->validate([
                'q' => ['nullable', 'string', 'max:120'],
                'customer' => ['nullable', 'string', 'max:120'],
                'type' => ['nullable', Rule::in(['engine', 'seat'])],
                'sort' => ['nullable', Rule::in(self::SORT_OPTIONS)],
            ])
        );

        $baseScope = CcrReport::query()->whereIn('approval_status', ['waiting', 'in_review']);

        $pendingByType = (clone $baseScope)
            ->selectRaw('type, COUNT(*) as total')
            ->groupBy('type')
            ->pluck('total', 'type');

        $stats = [
            'pending_total' => (int) $pendingByType->sum(),
            'pending_engine' => (int) ($pendingByType['engine'] ?? 0),
            'pending_seat' => (int) ($pendingByType['seat'] ?? 0),
        ];

        $customers = (clone $baseScope)
            ->select('customer')
            ->whereNotNull('customer')
            ->where('customer', '<>', '')
            ->orderBy('customer')
            ->distinct()
            ->limit(300)
            ->pluck('customer')
            ->values();

        $reportQuery = (clone $baseScope)
            ->select([
                'id',
                'type',
                'component',
                'customer',
                'make',
                'model',
                'sn',
                'inspection_date',
                'submitted_at',
                'approval_status',
            ]);

        if ($filters['customer'] !== '') {
            $reportQuery->where('customer', $filters['customer']);
        }

        if ($filters['type'] !== '') {
            $reportQuery->where('type', $filters['type']);
        }

        if ($filters['q'] !== '') {
            $term = $this->escapeLike($filters['q']);
            $reportQuery->where(function (Builder $query) use ($term) {
                $query->orWhere('component', 'like', '%' . $term . '%')
                    ->orWhere('customer', 'like', '%' . $term . '%')
                    ->orWhere('make', 'like', '%' . $term . '%')
                    ->orWhere('model', 'like', '%' . $term . '%')
                    ->orWhere('sn', 'like', '%' . $term . '%');
            });
        }

        $this->applySort($reportQuery, $filters['sort']);

        $reports = $reportQuery
            ->simplePaginate(self::DEFAULT_PER_PAGE)
            ->withQueryString();

        $openId = (int) $request->query('open', 0); // contoh: /director/monitoring?open=18
        $openId = $openId > 0 ? $openId : null;

        return view('director.monitoring', compact('reports', 'openId', 'filters', 'stats', 'customers'));
    }

    public function approve(Request $request, $id)
    {
        $validated = $request->validate([
            'approve_note' => ['nullable', 'string', 'max:2000'],
            'idempotency_key' => ['nullable', 'string', 'max:120'],
        ]);

        $note = $this->sanitizeNote((string) ($validated['approve_note'] ?? ''));
        $idempotencyKey = app(CcrApprovalStateMachine::class)->resolveIdempotencyKey(
            (string) ($request->header('X-Idempotency-Key') ?: ($validated['idempotency_key'] ?? ''))
        );
        $result = app(CcrApprovalStateMachine::class)->decide(
            (int) $id,
            'approve',
            (int) auth()->id(),
            $note,
            $idempotencyKey
        );

        if (!empty($result['idempotent'])) {
            return back()->with('warning', 'Request approve duplikat terdeteksi, aksi sebelumnya dipakai.');
        }

        if (!empty($result['invalid_transition']) || !$result['updated']) {
            $status = (string) ($result['report']->approval_status ?? 'diproses');
            return back()->with('warning', 'CCR sudah berstatus ' . $status . ', tidak perlu approve ulang.');
        }

        /** @var CcrReport $r */
        $r = $result['report'];

        // ===== rapihin teks =====
        $actorName = auth()->user()->name ?? 'Director';
        $componentName = trim((string) ($r->component ?? ''));
        if ($componentName === '') $componentName = strtoupper($r->type ?? 'CCR');
        $notePreview = $this->notePreview($r->director_note);

        // URL notif untuk Admin/Planner -> arahkan ke halaman edit CCR yang bisa mereka akses
        $targetUrl = match ($r->type) {
            'engine' => route('engine.edit', $r->id),
            'seat'   => route('seat.edit', $r->id),
            default  => route('ccr.index'),
        };

        // ✅ notif ke Admin + Planner
        Inbox::toRoles(['admin','operator'], [
            'type'    => 'ccr_approved',
            'title'   => $componentName,
            'message' => 'Approved oleh ' . $actorName . '.'
                . ($notePreview !== '' ? (' Catatan: ' . $notePreview) : ''),
            'url'     => $targetUrl,
        ], auth()->id());

        return back()->with('success', 'CCR di-approve.');
    }

    public function reject(Request $request, $id)
    {
        $validated = $request->validate([
            'director_note' => ['required', 'string', 'max:2000'],
            'idempotency_key' => ['nullable', 'string', 'max:120'],
        ]);

        $note = $this->sanitizeNote((string) ($validated['director_note'] ?? ''));
        if ($note === '') {
            return back()
                ->withErrors(['director_note' => 'Catatan revisi wajib diisi.'])
                ->withInput();
        }

        $idempotencyKey = app(CcrApprovalStateMachine::class)->resolveIdempotencyKey(
            (string) ($request->header('X-Idempotency-Key') ?: ($validated['idempotency_key'] ?? ''))
        );
        $result = app(CcrApprovalStateMachine::class)->decide(
            (int) $id,
            'reject',
            (int) auth()->id(),
            $note,
            $idempotencyKey
        );

        if (!empty($result['idempotent'])) {
            return back()->with('warning', 'Request reject duplikat terdeteksi, aksi sebelumnya dipakai.');
        }

        if (!empty($result['invalid_transition']) || !$result['updated']) {
            $status = (string) ($result['report']->approval_status ?? 'diproses');
            return back()->with('warning', 'CCR sudah berstatus ' . $status . ', tidak bisa reject ulang.');
        }

        /** @var CcrReport $r */
        $r = $result['report'];

        // ===== rapihin teks =====
        $actorName = auth()->user()->name ?? 'Director';
        $componentName = trim((string) ($r->component ?? ''));
        if ($componentName === '') $componentName = strtoupper($r->type ?? 'CCR');
        $notePreview = $this->notePreview($r->director_note);

        // URL notif untuk Admin/Planner -> arahkan ke halaman edit CCR
        $targetUrl = match ($r->type) {
            'engine' => route('engine.edit', $r->id),
            'seat'   => route('seat.edit', $r->id),
            default  => route('ccr.index'),
        };

        // ✅ notif ke Admin + Planner
        Inbox::toRoles(['admin','operator'], [
            'type'    => 'ccr_rejected',
            'title'   => $componentName,
            'message' => 'Rejected oleh ' . $actorName . '. Catatan: ' . $notePreview,
            'url'     => $targetUrl,
        ], auth()->id());

        return back()->with('success', 'CCR di-reject + catatan tersimpan.');
    }

    /**
     * @param array<string, mixed> $validated
     * @return array{q:string,customer:string,type:string,sort:string}
     */
    private function normalizeFilters(array $validated): array
    {
        $sort = (string) ($validated['sort'] ?? 'submitted_newest');
        if (!in_array($sort, self::SORT_OPTIONS, true)) {
            $sort = 'submitted_newest';
        }

        return [
            'q' => trim((string) ($validated['q'] ?? '')),
            'customer' => trim((string) ($validated['customer'] ?? '')),
            'type' => trim((string) ($validated['type'] ?? '')),
            'sort' => $sort,
        ];
    }

    private function applySort(Builder $query, string $sort): void
    {
        if ($sort === 'submitted_oldest') {
            $query->orderBy('submitted_at')->orderBy('id');
            return;
        }

        if ($sort === 'inspection_newest') {
            $query->orderByDesc('inspection_date')->orderByDesc('id');
            return;
        }

        if ($sort === 'inspection_oldest') {
            $query->orderBy('inspection_date')->orderBy('id');
            return;
        }

        $query->orderByDesc('submitted_at')->orderByDesc('id');
    }

    private function escapeLike(string $value): string
    {
        return addcslashes($value, "\\%_");
    }

    private function sanitizeNote(string $value): string
    {
        $text = strip_tags($value);
        $text = preg_replace("/\r\n?/", "\n", $text) ?? '';
        $text = preg_replace("/[ \t]+/", " ", $text) ?? '';
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? '';
        $text = trim($text);

        if (mb_strlen($text) > 2000) {
            $text = mb_substr($text, 0, 2000);
        }

        return $text;
    }

    private function notePreview(?string $value): string
    {
        $text = trim((string) $value);
        if ($text === '') {
            return '';
        }
        if (mb_strlen($text) <= 280) {
            return $text;
        }

        return rtrim(mb_substr($text, 0, 280)) . '...';
    }
}
