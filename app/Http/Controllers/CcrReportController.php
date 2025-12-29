<?php

namespace App\Http\Controllers;

use App\Models\CcrReport;
use App\Models\CcrItem;
use App\Models\CcrPhoto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;


class CcrReportController extends Controller
{
    // =====================================================================
    // HALAMAN INDEX — MENU UTAMA
    // =====================================================================
    public function index()
    {
        $reports = CcrReport::latest()->get();
        return view('ccr.index', compact('reports'));
    }


    // =====================================================================
    // HALAMAN EDIT MENU (PILIH ENGINE ATAU SEAT)
    // =====================================================================
    public function editMenu()
    {
        return view('ccr.edit-menu');
    }

    // LIST CCR ENGINE UNTUK DI EDIT
    public function editEngineList()
    {
        $reports = CcrReport::where('group_folder', 'Engine')
            ->whereNull('deleted_at')
            ->orderBy('inspection_date', 'desc')
            ->get();

        return view('ccr.manage-engine', compact('reports'));
    }

    // LIST CCR OPERATOR SEAT UNTUK DI EDIT
    public function editSeatList()
    {
        $reports = CcrReport::where('group_folder', 'Operator Seat')
            ->whereNull('deleted_at')
            ->orderBy('inspection_date', 'desc')
            ->get();

        return view('ccr.manage-seat', compact('reports'));
    }


    // =====================================================================
    // HALAMAN CREATE (TIDAK DIPAKAI)
    // =====================================================================
    public function create()
    {
        $groupFolders = [
            'Engine',
            'Transmission',
            'Operator Seat',
            'Radiator',
            'After Cooler',
        ];

        return view('ccr.create', compact('groupFolders'));
    }


    // =====================================================================
    // STORE ORIGINAL (TIDAK DIPAKAI)
    // =====================================================================
    public function store(Request $request)
    {
        $data = $request->validate([
            'group_folder'         => 'required',
            'component'            => 'required',
            'unit'                 => 'required',
            'job_no'               => 'nullable',
            'sn'                   => 'nullable',
            'customer'             => 'nullable',
            'make'                 => 'nullable',
            'reff_wo'              => 'nullable',
            'inspection_date'      => 'nullable|date',
            'notes'                => 'nullable',

            'items'                => 'required|array',
            'items.*.description'  => 'nullable|string',
            'items.*.photos.*'     => 'nullable|image',
        ]);

        $report = CcrReport::create([
            'group_folder'    => $data['group_folder'],
            'component'       => $data['component'],
            'unit'            => $data['unit'],
            'job_no'          => $data['job_no'] ?? null,
            'sn'              => $data['sn'] ?? null,
            'customer'        => $data['customer'] ?? null,
            'make'            => $data['make'] ?? null,
            'reff_wo'         => $data['reff_wo'] ?? null,
            'inspection_date' => $data['inspection_date'] ?? null,
            'notes'           => $data['notes'] ?? null,
        ]);

        foreach ($request->items as $i => $itemData) {

            $desc   = $itemData['description'] ?? null;
            $photos = $request->file("items.$i.photos") ?? [];

            if (!$desc && empty($photos)) continue;

            $item = CcrItem::create([
                'ccr_report_id' => $report->id,
                'description'   => $desc,
            ]);

            foreach ($photos as $img) {
                $path = $img->store('ccr_photos', 'public');

                CcrPhoto::create([
                    'ccr_item_id' => $item->id,
                    'path'        => $path,
                ]);
            }
        }

        return redirect()
            ->route('ccr.show', $report->id)
            ->with('success', 'CCR berhasil disimpan!');
    }


    // =====================================================================
    // SHOW DETAIL CCR
    // =====================================================================
    public function show(CcrReport $report)
    {
        $report->load('items.photos');
        return view('ccr.show', compact('report'));
    }

    //CENTANGFILE
    public function deleteMultipleEngine(Request $request)
    {
    $ids = $request->ids ?? [];

    if (empty($ids)) {
        return redirect()->back()->with('error', 'Tidak ada laporan yang dipilih.');
    }

    foreach ($ids as $id) {
        $report = CcrReport::with('items.photos')->find($id);
        if (!$report) continue;

        // Hapus semua foto & item
        foreach ($report->items as $item) {
            foreach ($item->photos as $photo) {
                Storage::disk('public')->delete($photo->path);
                $photo->delete();
            }
            $item->delete();
        }

        // Hapus report
        $report->delete();
    }

    return redirect()->route('ccr.manage.engine')
        ->with('success', 'Laporan terpilih berhasil dihapus!');
    }

}
