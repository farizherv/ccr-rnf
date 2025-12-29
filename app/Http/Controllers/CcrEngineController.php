<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use App\Models\CcrReport;
use App\Models\CcrItem;
use App\Models\CcrPhoto;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;
use App\Notifications\CcrSubmittedNotification;
use Illuminate\Support\Facades\Auth;
use App\Support\Inbox; 


class CcrEngineController extends Controller
{
    // ===========================================================
    // CREATE PAGE
    // ===========================================================
    public function create()
    {
        $groupFolders = ['Engine', 'Transmission', 'Radiator', 'Cabin', 'After Cooler'];

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

        // FIX VIEW NAME → create.blade.php
        return view('engine.create', compact('groupFolders', 'groupedCustomers', 'brands'));
    }

    // ===========================================================
    // STORE (HEADER + ITEM BARU)
    // ===========================================================
    public function store(Request $request)
    {
        // VALIDASI
        $data = $request->validate([
            'group_folder'         => 'required|string',
            'component'            => 'required|string',
            'make'                 => 'nullable|string',
            'model'                => 'nullable|string',
            'sn'                   => 'nullable|string',
            'smu'                  => 'nullable|string',
            'customer'             => 'nullable|string',
            'inspection_date'      => 'required|date',
            'items'                => 'required|array',
            'items.*.description'  => 'nullable|string',
            'items.*.photos.*'     => 'nullable|image|max:8000',
        ]);

        // ============================
        // Gabungkan TANGGAL + JAM WITA OTOMATIS
        // ============================
        $finalDate = Carbon::parse($data['inspection_date'])
            ->setTimeFromTimeString(now('Asia/Makassar')->format('H:i'));

        // ============================
        // SIMPAN HEADER REPORT
        // ============================
        $report = CcrReport::create([
            'type'            => 'engine',
            'group_folder'    => $data['group_folder'],
            'component'       => $data['component'],
            'make'            => $request->make,
            'model'           => $request->model,
            'sn'              => $request->sn,
            'smu'             => $request->smu,
            'customer'        => $request->customer,
            'inspection_date' => $finalDate, // PENTING
        ]);

        // ⭐ WAJIB SUPAYA DATA TERBARU TERAMBIL
        $report->refresh();

        // Generate folder pakai FINAL DATE (bukan input user)
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
            ->with('success', 'CCR Engine berhasil dibuat!');
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

        return view('engine.edit-engine', compact('report', 'brands', 'groupedCustomers'));
    }


    // ===========================================================
    // UPDATE HEADER + ITEM LAMA + ITEM BARU
    // ===========================================================
    public function updateHeader(Request $request, $id)
    {
        $data = $request->validate([
            'group_folder'    => 'required|string',
            'component'       => 'required|string',
            'inspection_date' => 'required|date',

            'items'                   => 'nullable|array',
            'items.*.description'     => 'nullable|string',
            'items.*.photos.*'        => 'nullable|image|max:8000',
            'items.*.delete_photos'   => 'nullable|array',

            'new_items'               => 'nullable|array',
            'new_items.*.description' => 'nullable|string',
            'new_items.*.photos.*'    => 'nullable|image|max:8000',
        ]);

        $report = CcrReport::with('items.photos')->findOrFail($id);

        // ============================================
        // Gabungkan TANGGAL + JAM WITA OTOMATIS SAAT EDIT
        // ============================================
        $finalDate = Carbon::parse($data['inspection_date'])
            ->setTimeFromTimeString(now('Asia/Makassar')->format('H:i'));

        // Update header
        $report->update([
            'group_folder'    => $data['group_folder'],
            'component'       => $data['component'],
            'make'            => $request->make,
            'model'           => $request->model,
            'sn'              => $request->sn,
            'smu'             => $request->smu,
            'customer'        => $request->customer,
            'inspection_date' => $finalDate,
        ]);

        // ⭐ jam & tanggal update
        $report->refresh();

        // generate folder
        $date = Carbon::parse($report->inspection_date)->format('Y-m-d');
        $safe = substr(preg_replace('/[^A-Za-z0-9\- ]/', '', $report->component), 0, 30);
        $folder = "synology/CCR-{$report->group_folder}-{$date}-{$safe}";
        Storage::disk('public')->makeDirectory("$folder/photos");


        // ===== UPDATE OLD ITEMS =====
        if (!empty($data['items'])) {
            foreach ($data['items'] as $itemId => $input) {

                $item = CcrItem::find($itemId);
                if (!$item) continue;

                $item->update([
                    'description' => $input['description'] ?? '',
                ]);

                // delete old photo
                if (!empty($input['delete_photos'])) {
                    foreach ($input['delete_photos'] as $photoId) {
                        $photo = CcrPhoto::find($photoId);
                        if ($photo) {
                            Storage::disk('public')->delete($photo->path);
                            $photo->delete();
                        }
                    }
                }

                // upload new photos
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

        // ===== CREATE NEW ITEMS =====
        if (!empty($data['new_items'])) {
            foreach ($data['new_items'] as $index => $input) {

                $hasDesc = $input['description'] ?? null;
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

        return redirect()->route('ccr.manage.engine')
            ->with('success', 'Perubahan CCR Engine berhasil disimpan!');
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

        return redirect()->route('engine.edit', $rid)
            ->with('success', 'Item berhasil dihapus.');
    }

    // ===========================================================
    // DELETE PHOTO
    // ===========================================================
    public function deletePhoto(CcrPhoto $photo)
    {
        $rid = $photo->item->ccr_report_id;

        Storage::disk('public')->delete($photo->path);
        $photo->delete();

        return redirect()->route('engine.edit', $rid)
            ->with('success', 'Foto berhasil dihapus.');
    }

    // ===========================================================
    //DELETE MULTIPLE TERPILIH
    // ===========================================================
    public function deleteMultiple(Request $request)
    {
        $ids = $request->input('ids', []);

        CcrReport::whereIn('id', $ids)->get()->each(function ($r) {
            $r->purge_at = now()->addDays(7);
            $r->save();
            $r->delete(); // masuk Trash (soft delete)
        });

        return back()->with('success', 'Dipindahkan ke Sampah (7 hari).');
    }


    //PREVIEW LIHAT
    public function previewEngine($id)
    {
        $report = CcrReport::with('items.photos')->findOrFail($id);  
        return view('engine.preview', compact('report'));
    }


    // SUBMIT TO DIREKTUR (Submit / Re-submit)
    public function submit(Request $request, int $id)
    {
        $report = CcrReport::findOrFail($id);

        $resubmit = $request->boolean('resubmit');

        // ✅ blok kalau masih antri / sedang direview
        if (in_array($report->approval_status, ['waiting', 'in_review'])) {
            return back()->with('error', 'CCR ini sedang menunggu persetujuan Direktur.');
        }

        // ✅ kalau sudah approved, hanya boleh submit lagi kalau resubmit=1
        if ($report->approval_status === 'approved' && !$resubmit) {
            return back()->with('error', 'CCR ini sudah Approved. Gunakan tombol Re-submit jika ingin kirim ulang.');
        }

        // ✅ submit/resubmit -> masuk monitoring direktur
        $report->approval_status = 'waiting';
        $report->submitted_by    = auth()->id();
        $report->submitted_at    = now();

        if ($resubmit) {
            $report->director_note = null;
        }

        $report->save();

        // ✅ nama component untuk title notif (hindari CCR #id)
        $componentName = trim((string) ($report->component ?? ''));
        if ($componentName === '') $componentName = 'Engine';

        // ✅ link aman untuk Director + highlight barisnya
        // pastikan route name ini memang ada di web.php
        $openUrl = route('director.monitoring', ['open' => $report->id], false) . '#r-' . $report->id;

        Inbox::toRoles(['director'], [
            'type'    => 'engine_submitted',
            'title'   => $componentName,
            'message' => 'Disubmit oleh ' . (auth()->user()->name ?? 'User') . '.',
            'url'     => $openUrl,
        ], auth()->id());

        return back()->with('success', $resubmit
            ? 'CCR Engine berhasil di Re-submit ke Direktur.'
            : 'CCR Engine berhasil dikirim ke Direktur.'
        );
    }

    
}
