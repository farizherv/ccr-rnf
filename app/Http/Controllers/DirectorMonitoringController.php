<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\CcrReport;
use App\Support\Inbox;

class DirectorMonitoringController extends Controller
{
    public function index(Request $request)
    {
        $reports = CcrReport::whereIn('approval_status', ['waiting', 'in_review'])
            ->orderByDesc('submitted_at')
            ->get();

        $openId = $request->query('open'); // contoh: /director/monitoring?open=18

        return view('director.monitoring', compact('reports', 'openId'));
    }

    public function approve(Request $request, $id)
    {
        $r = CcrReport::findOrFail($id);

        $r->approval_status = 'approved';

        $note = trim((string) $request->input('approve_note', ''));
        $r->director_note = $note !== '' ? $note : null;

        $r->save();

        // ===== rapihin teks =====
        $actorName = auth()->user()->name ?? 'Director';
        $componentName = trim((string) ($r->component ?? ''));
        if ($componentName === '') $componentName = strtoupper($r->type ?? 'CCR');

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
                . ($r->director_note ? (' Catatan: ' . $r->director_note) : ''),
            'url'     => $targetUrl,
        ], auth()->id());

        return back()->with('success', 'CCR di-approve.');
    }

    public function reject(Request $request, $id)
    {
        $request->validate([
            'director_note' => 'required|string|min:1',
        ]);

        $r = CcrReport::findOrFail($id);
        $r->approval_status = 'rejected';
        $r->director_note = trim((string) $request->director_note);
        $r->save();

        // ===== rapihin teks =====
        $actorName = auth()->user()->name ?? 'Director';
        $componentName = trim((string) ($r->component ?? ''));
        if ($componentName === '') $componentName = strtoupper($r->type ?? 'CCR');

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
            'message' => 'Rejected oleh ' . $actorName . '. Catatan: ' . $r->director_note,
            'url'     => $targetUrl,
        ], auth()->id());

        return back()->with('success', 'CCR di-reject + catatan tersimpan.');
    }
}
