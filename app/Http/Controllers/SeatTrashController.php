<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\CcrReport;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;

class SeatTrashController extends Controller
{
    // ==========================================================
    // BULK TRASH (dari Manage Seat -> masuk ke trash)
    // ==========================================================
    public function trashMultiple(Request $request)
    {
        $ids = $request->input('ids', []);

        if (!is_array($ids) || count($ids) === 0) {
            return back()->with('error', 'Tidak ada laporan yang dipilih.');
        }

        $updated = CcrReport::whereIn('id', $ids)
            ->where('type', 'seat')
            ->whereNull('deleted_at')
            ->update([
                'deleted_at' => Carbon::now(),
                'purge_at'   => Carbon::now()->addDays(7),
                'updated_at' => Carbon::now(),
            ]);

        if ($updated === 0) {
            return back()->with('error', 'Tidak ada laporan SEAT yang berhasil dipindahkan (cek route/form/ids).');
        }

        return back()->with('success', "{$updated} laporan dipindahkan ke Sampah (7 hari).");
    }

    // ==========================================================
    // TRASH LIST (SEAT)
    // - data dikirim full
    // - filter/sort/search pakai JS (mirip Manage Seat)
    // ==========================================================
    public function index(Request $request)
    {
        $reports = CcrReport::onlyTrashed()
            ->where('type', 'seat')
            ->orderByDesc('deleted_at') // default: yang terakhir dihapus muncul dulu
            ->get();

        // dropdown customer: dari data trash (biar tidak kosong/aneh)
        $customers = $reports->pluck('customer')
            ->filter(fn($v) => $v !== null && trim($v) !== '')
            ->unique()
            ->values();

        return view('trash.seat', compact('reports', 'customers'));
    }

    // ==========================================================
    // RESTORE (single) -> balik ke MANAGE SEAT
    // ==========================================================
    public function restore($id)
    {
        $report = CcrReport::onlyTrashed()
            ->where('type', 'seat')
            ->findOrFail($id);

        $report->restore();

        return redirect()
            ->route('ccr.manage.seat')
            ->with('success', 'CCR Seat berhasil direstore.');
    }

    // ==========================================================
    // RESTORE (multiple) -> kalau kamu pakai bulk restore di trash
    // ROUTE: trash.seat.restoreMultiple
    // ==========================================================
    public function restoreMultiple(Request $request)
    {
        $ids = $request->input('ids', []);

        if (!is_array($ids) || count($ids) === 0) {
            return back()->with('error', 'Tidak ada laporan yang dipilih.');
        }

        $restored = CcrReport::onlyTrashed()
            ->where('type', 'seat')
            ->whereIn('id', $ids)
            ->restore(); // return jumlah yang direstore

        if ($restored === 0) {
            return back()->with('error', 'Tidak ada CCR Seat yang berhasil direstore.');
        }

        return redirect()
            ->route('ccr.manage.seat')
            ->with('success', "{$restored} CCR Seat berhasil direstore.");
    }

    // ==========================================================
    // FORCE DELETE (hapus permanen + foto + relasi)
    // ==========================================================
    public function forceDelete($id)
    {
        $report = CcrReport::onlyTrashed()
            ->where('type', 'seat')
            ->with(['items', 'photos'])
            ->findOrFail($id);

        // hapus file foto (kalau pakai disk public)
        foreach ($report->photos as $photo) {
            $path = $photo->path ?? null;
            if ($path && Storage::disk('public')->exists($path)) {
                Storage::disk('public')->delete($path);
            }
        }

        // hapus relasi DB
        $report->photos()->delete();
        $report->items()->delete();

        // hapus permanen report
        $report->forceDelete();

        return back()->with('success', 'CCR Seat dihapus permanen.');
    }

    public function forceMultiple(Request $request)
    {
    $ids = $request->input('ids', []);

    if (!is_array($ids) || count($ids) === 0) {
        return back()->with('error', 'Tidak ada laporan yang dipilih.');
    }

    $reports = CcrReport::onlyTrashed()
        ->where('type', 'seat')
        ->whereIn('id', $ids)
        ->with(['items', 'photos'])
        ->get();

    if ($reports->count() === 0) {
        return back()->with('error', 'Tidak ada CCR Seat yang ditemukan untuk dihapus permanen.');
    }

    foreach ($reports as $report) {
        foreach ($report->photos as $photo) {
            $path = $photo->path ?? null;
            if ($path && Storage::disk('public')->exists($path)) {
                Storage::disk('public')->delete($path);
            }
        }

        $report->photos()->delete();
        $report->items()->delete();
        $report->forceDelete();
    }

    return back()->with('success', $reports->count() . ' CCR Seat dihapus permanen.');
    }

}

