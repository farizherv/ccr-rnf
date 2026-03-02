<?php

namespace App\Http\Controllers;

use App\Models\CcrReport;
use App\Models\User;
use App\Notifications\CcrReviewedNotification;
use App\Support\CcrApprovalStateMachine;
use App\Support\Inbox;
use Illuminate\Http\Request;

class CcrApprovalController extends Controller
{
    public function approve(Request $request, string $type, int $id)
    {
        $report = CcrReport::findOrFail($id);
        if (strtolower((string) ($report->type ?? '')) !== strtolower(trim($type))) {
            abort(404);
        }

        $note = trim(strip_tags((string) $request->input('note', '')));
        $idempotencyKey = app(CcrApprovalStateMachine::class)->resolveIdempotencyKey(
            (string) ($request->header('X-Idempotency-Key') ?: $request->input('idempotency_key', ''))
        );
        $result = app(CcrApprovalStateMachine::class)->decide(
            $report->id,
            'approve',
            (int) auth()->id(),
            $note,
            $idempotencyKey
        );

        if (!empty($result['idempotent'])) {
            return back()->with('warning', 'Request approve duplikat terdeteksi, aksi sebelumnya dipakai.');
        }
        if (!empty($result['invalid_transition']) || empty($result['updated'])) {
            $status = (string) ($result['report']->approval_status ?? 'diproses');
            return back()->with('warning', 'CCR sudah berstatus ' . $status . ', tidak perlu approve ulang.');
        }

        /** @var CcrReport $report */
        $report = $result['report'];

        // notify balik ke pembuat/submittter
        if ($report->submitted_by) {
            $maker = User::find($report->submitted_by);
            if ($maker) {
                $targetUrl = $report->type === 'seat'
                    ? route('seat.edit', $report->id)
                    : route('engine.edit', $report->id);
                Inbox::toUser((int) $maker->id, [
                    'from_user_id' => (int) auth()->id(),
                    'type' => 'ccr_approved',
                    'title' => trim((string) $report->component) !== '' ? (string) $report->component : strtoupper((string) $report->type),
                    'message' => 'Approved oleh ' . (string) (auth()->user()->name ?? auth()->user()->username ?? 'Director') . '.',
                    'url' => $targetUrl,
                ]);
                $maker->notify(new CcrReviewedNotification(
                    reportId: $report->id,
                    type: $type,
                    status: 'approved',
                    byUsername: auth()->user()->username
                ));
            }
        }

        return back()->with('success', 'CCR berhasil di-approve.');
    }

    public function reject(Request $request, string $type, int $id)
    {
        $report = CcrReport::findOrFail($id);
        if (strtolower((string) ($report->type ?? '')) !== strtolower(trim($type))) {
            abort(404);
        }

        $note = trim(strip_tags((string) $request->input('note', '')));
        if ($note === '') {
            return back()->withErrors(['note' => 'Catatan revisi wajib diisi.']);
        }

        $idempotencyKey = app(CcrApprovalStateMachine::class)->resolveIdempotencyKey(
            (string) ($request->header('X-Idempotency-Key') ?: $request->input('idempotency_key', ''))
        );
        $result = app(CcrApprovalStateMachine::class)->decide(
            $report->id,
            'reject',
            (int) auth()->id(),
            $note,
            $idempotencyKey
        );

        if (!empty($result['idempotent'])) {
            return back()->with('warning', 'Request reject duplikat terdeteksi, aksi sebelumnya dipakai.');
        }
        if (!empty($result['invalid_transition']) || empty($result['updated'])) {
            $status = (string) ($result['report']->approval_status ?? 'diproses');
            return back()->with('warning', 'CCR sudah berstatus ' . $status . ', tidak bisa reject ulang.');
        }

        /** @var CcrReport $report */
        $report = $result['report'];

        if ($report->submitted_by) {
            $maker = User::find($report->submitted_by);
            if ($maker) {
                $targetUrl = $report->type === 'seat'
                    ? route('seat.edit', $report->id)
                    : route('engine.edit', $report->id);
                Inbox::toUser((int) $maker->id, [
                    'from_user_id' => (int) auth()->id(),
                    'type' => 'ccr_rejected',
                    'title' => trim((string) $report->component) !== '' ? (string) $report->component : strtoupper((string) $report->type),
                    'message' => 'Rejected oleh ' . (string) (auth()->user()->name ?? auth()->user()->username ?? 'Director') . '. Catatan: ' . trim((string) $report->director_note),
                    'url' => $targetUrl,
                ]);
                $maker->notify(new CcrReviewedNotification(
                    reportId: $report->id,
                    type: $type,
                    status: 'rejected',
                    byUsername: auth()->user()->username
                ));
            }
        }

        return back()->with('success', 'CCR berhasil di-reject.');
    }
}
