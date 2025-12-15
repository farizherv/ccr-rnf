<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\CcrReport;
use App\Models\CcrItem;
use App\Models\CcrPhoto;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class CcrSeatController extends Controller
{
    // ===========================================================
    // CREATE PAGE
    // ===========================================================
    public function create()
    {
        $groupFolders = ['Operator Seat'];

        $customers = file_exists(resource_path('data/customer_list.php'))
            ? include resource_path('data/customer_list.php')
            : [];

        $brands = file_exists(resource_path('data/brand_list.php'))
            ? include resource_path('data/brand_list.php')
            : [];

        // Group customer
        $groupedCustomers = ['CV' => [], 'PT' => [], 'Other' => []];
        foreach ($customers as $c) {
            if (str_starts_with($c, 'CV')) $groupedCustomers['CV'][] = $c;
            elseif (str_starts_with($c, 'PT')) $groupedCustomers['PT'][] = $c;
            else $groupedCustomers['Other'][] = $c;
        }

        return view('seat.create', compact('groupFolders', 'groupedCustomers', 'brands'));
    }

    // ===========================================================
    // STORE (HEADER + ITEM BARU)
    // ===========================================================
    public function store(Request $request)
    {
        $data = $request->validate([
            'group_folder'         => 'required|string',
            'component'            => 'required|string',
            'make'                 => 'nullable|string',
            'model'                => 'nullable|string',
            'unit'                 => 'nullable|string',
            'wo_pr'                => 'nullable|string',
            'customer'             => 'nullable|string',
            'inspection_date'      => 'required|date',

            'items'                => 'required|array',
            'items.*.description'  => 'nullable|string',
            'items.*.photos.*'     => 'nullable|image|max:8000',
        ]);

        // Tanggal + Jam WITA otomatis
        $finalDate = Carbon::parse($data['inspection_date'])
            ->setTimeFromTimeString(now('Asia/Makassar')->format('H:i'));

        // Simpan header report
        $report = CcrReport::create([
            'type'            => 'seat',
            'group_folder'    => $data['group_folder'],
            'component'       => $data['component'],
            'make'            => $request->make,
            'model'           => $request->model,
            'unit'            => $request->unit,
            'wo_pr'           => $request->wo_pr,
            'customer'        => $request->customer,
            'inspection_date' => $finalDate,
        ]);

        $report->refresh();

        // Generate folder Synology
        $date = Carbon::parse($report->inspection_date)->format('Y-m-d');
        $safe = substr(preg_replace('/[^A-Za-z0-9\- ]/', '', $report->component), 0, 30);
        $folder = "synology/CCR-{$report->group_folder}-{$date}-{$safe}";
        Storage::disk('public')->makeDirectory("$folder/photos");

        // Save Items
        foreach ($data['items'] as $index => $input) {

            $item = CcrItem::create([
                'ccr_report_id' => $report->id,
                'description'   => $input['description'] ?? '',
            ]);

            if ($request->hasFile("items.$index.photos")) {
                foreach ($request->file("items.$index.photos") as $photo) {
                    $path = $photo->store("$folder/photos", 'public');
                    CcrPhoto::create([
                        'ccr_item_id' => $item->id,
                        'path'        => $path,
                    ]);
                }
            }
        }

        return redirect()->route('ccr.index')
            ->with('success', 'CCR Operator Seat berhasil dibuat!');
    }

    // ===========================================================
    // EDIT PAGE
    // ===========================================================
    public function edit($id)
    {
        $report = CcrReport::with('items.photos')->findOrFail($id);

        $brands = file_exists(resource_path('data/brand_list.php'))
            ? include resource_path('data/brand_list.php')
            : [];

        $customers = file_exists(resource_path('data/customer_list.php'))
            ? include resource_path('data/customer_list.php')
            : [];

        $groupedCustomers = ['CV' => [], 'PT' => [], 'Other' => []];
        foreach ($customers as $c) {
            if (str_starts_with($c, 'CV')) $groupedCustomers['CV'][] = $c;
            elseif (str_starts_with($c, 'PT')) $groupedCustomers['PT'][] = $c;
            else $groupedCustomers['Other'][] = $c;
        }

        return view('seat.edit-seat', compact('report', 'brands', 'groupedCustomers'));
    }

    // ===========================================================
    // UPDATE HEADER + ITEM LAMA + ITEM BARU
    // ===========================================================
    public function updateHeader(Request $request, $id)
    {
        $data = $request->validate([
            'group_folder'    => 'required|string',
            'component'       => 'required|string',
            'make'            => 'nullable|string',
            'model'           => 'nullable|string',
            'unit'            => 'nullable|string',
            'wo_pr'           => 'nullable|string',
            'customer'        => 'nullable|string',
            'inspection_date' => 'required|date',

            'items'                    => 'nullable|array',
            'items.*.description'      => 'nullable|string',
            'items.*.photos.*'         => 'nullable|image|max:8000',
            'items.*.delete_photos'    => 'nullable|array',

            'new_items'                => 'nullable|array',
            'new_items.*.description'  => 'nullable|string',
            'new_items.*.photos.*'     => 'nullable|image|max:8000',
        ]);

        // Ambil report
        $report = CcrReport::with('items.photos')->findOrFail($id);

        // Tanggal + WITA
        $finalDate = Carbon::parse($data['inspection_date'])
            ->setTimeFromTimeString(now('Asia/Makassar')->format('H:i'));

        // Update header
        $report->update([
            'group_folder'    => $data['group_folder'],
            'component'       => $data['component'],
            'make'            => $request->make,
            'model'           => $request->model,
            'unit'            => $request->unit,
            'wo_pr'           => $request->wo_pr,
            'customer'        => $request->customer,
            'inspection_date' => $finalDate,
        ]);

        $report->refresh();

        // Folder Synology
        $date = Carbon::parse($report->inspection_date)->format('Y-m-d');
        $safe = substr(preg_replace('/[^A-Za-z0-9\- ]/', '', $report->component), 0, 30);
        $folder = "synology/CCR-{$report->group_folder}-{$date}-{$safe}";
        Storage::disk('public')->makeDirectory("$folder/photos");


        // ===========================================================
        // UPDATE ITEM LAMA
        // ===========================================================
        if (!empty($data['items'])) {
            foreach ($data['items'] as $itemId => $input) {

                $item = CcrItem::find($itemId);
                if (!$item) continue;

                $item->update([
                    'description' => $input['description'] ?? '',
                ]);

                // Hapus foto lama
                if (!empty($input['delete_photos'])) {
                    foreach ($input['delete_photos'] as $photoId) {
                        $photo = CcrPhoto::find($photoId);
                        if ($photo) {
                            Storage::disk('public')->delete($photo->path);
                            $photo->delete();
                        }
                    }
                }

                // Tambah foto baru
                if ($request->hasFile("items.$itemId.photos")) {
                    foreach ($request->file("items.$itemId.photos") as $photo) {
                        $path = $photo->store("$folder/photos", 'public');
                        CcrPhoto::create([
                            'ccr_item_id' => $itemId,
                            'path'        => $path,
                        ]);
                    }
                }
            }
        }

        // ===========================================================
        // CREATE ITEM BARU
        // ===========================================================
        if (!empty($data['new_items'])) {
            foreach ($data['new_items'] as $index => $input) {

                $hasDesc  = $input['description'] ?? null;
                $hasPhoto = $request->hasFile("new_items.$index.photos");

                if (!$hasDesc && !$hasPhoto) continue;

                $item = CcrItem::create([
                    'ccr_report_id' => $report->id,
                    'description'   => $hasDesc ?? '',
                ]);

                if ($hasPhoto) {
                    foreach ($request->file("new_items.$index.photos") as $photo) {
                        $path = $photo->store("$folder/photos", 'public');
                        CcrPhoto::create([
                            'ccr_item_id' => $item->id,
                            'path'        => $path,
                        ]);
                    }
                }
            }
        }

        return redirect()->route('ccr.manage.seat')
            ->with('success', 'Perubahan CCR Operator Seat berhasil disimpan!');
    }

    // ===========================================================
    // DELETE ITEM
    // ===========================================================
    public function deleteItem(CcrItem $item)
    {
        $rid = $item->ccr_report_id;

        foreach ($item->photos as $p) {
            Storage::disk('public')->delete($p->path);
            $p->delete();
        }

        $item->delete();

        return redirect()->route('seat.edit', $rid)
            ->with('success', 'Item berhasil dihapus.');
    }

    // ===========================================================
    // DELETE MULTIPLE
    // ===========================================================
    public function deleteMultiple(Request $request)
    {
        $ids = $request->ids;

        if (!$ids || !is_array($ids)) {
            return back()->with('error', 'Tidak ada file yang dipilih.');
        }

        $reports = CcrReport::whereIn('id', $ids)->get();

        foreach ($reports as $report) {
            foreach ($report->items as $item) {
                foreach ($item->photos as $photo) {
                    Storage::disk('public')->delete($photo->path);
                    $photo->delete();
                }
                $item->delete();
            }
            $report->delete();
        }

        return redirect()->route('ccr.manage.seat')
            ->with('success', 'CCR Operator Seat terpilih berhasil dihapus!');
    }

    public function preview($id)
    {
    $report = \App\Models\CcrReport::with('items.photos')->findOrFail($id);
    return view('seat.preview', compact('report'));
    }

}
