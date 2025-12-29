<?php

namespace App\Http\Controllers;

use App\Models\CcrReport;
use Illuminate\Http\Request;

class CcrTrashController extends Controller
{
    // Halaman Trash (semua yang sudah dihapus)
    public function index(Request $request)
    {
        $type = $request->get('type'); // optional: engine/seat

        $q = CcrReport::onlyTrashed()->orderByDesc('deleted_at');

        if ($type) {
            $q->where('type', $type); // kalau kamu pakai kolom type
        }

        $trashReports = $q->get();

        return view('trash.index', compact('trashReports', 'type'));
    }

    // Restore (balikin lagi ke Manage)
    public function restore(Request $request)
    {
        $ids = $request->input('ids', []);

        CcrReport::onlyTrashed()
            ->whereIn('id', $ids)
            ->update(['purge_at' => null]); // optional: reset countdown

        CcrReport::onlyTrashed()
            ->whereIn('id', $ids)
            ->restore();

        return back()->with('success', 'Laporan berhasil direstore.');
    }
}
