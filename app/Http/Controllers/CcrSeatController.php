<?php

namespace App\Http\Controllers;

use App\Models\CcrItem;
use App\Models\CcrPhoto;
use App\Models\CcrReport;
use App\Support\Inbox;
use App\Support\WorksheetTemplates\SeatTemplateRepo;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

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

        // Group customer (CV, PT, Other)
        $groupedCustomers = ['CV' => [], 'PT' => [], 'Other' => []];
        foreach ($customers as $c) {
            if (str_starts_with($c, 'CV')) {
                $groupedCustomers['CV'][] = $c;
            } elseif (str_starts_with($c, 'PT')) {
                $groupedCustomers['PT'][] = $c;
            } else {
                $groupedCustomers['Other'][] = $c;
            }
        }

        // Ambil list template khusus Seat
        $templates = $this->getSeatTemplates();

        return view('seat.create', compact('groupFolders', 'groupedCustomers', 'brands', 'templates'));
    }

    // ===========================================================
    // STORE (HEADER + PAYLOAD TEMPLATE)
    // ===========================================================
    public function store(Request $request)
    {
        $data = $request->validate([
            'group_folder' => 'required|string',
            'component' => 'required|string',
            'customer' => 'nullable|string',
            'make' => 'nullable|string',
            'model' => 'nullable|string',
            'sn' => 'nullable|string',
            'smu' => 'nullable|string',
            'unit' => 'nullable|string', // Tambahan field unit untuk Seat
            'wo_pr' => 'nullable|string', // Tambahan field WO untuk Seat
            'inspection_date' => 'required|string',

            'template_key' => 'nullable|string',
            'template_version' => 'nullable|integer',
            'parts_payload' => 'nullable|string',
            'detail_payload' => 'nullable|string',
        ]);

        $finalDate = Carbon::parse($data['inspection_date'])->format('Y-m-d');

        $templateKey = $data['template_key'] ?? 'seat_blank';
        $templateVersionInt = (int)($data['template_version'] ?? 1);

        $partsPayload = $this->decodeJsonInput($data['parts_payload'] ?? null);
        $detailPayload = $this->decodeJsonInput($data['detail_payload'] ?? null);

        // Fallback ke defaults jika payload kosong (User tidak pilih template tapi sistem butuh data awal)
        if (empty($partsPayload) || empty($detailPayload)) {
            $repo = app(SeatTemplateRepo::class);
            $defaults = $repo->loadDefaults($templateKey, $templateVersionInt);
            
            if (empty($partsPayload) && !empty($defaults['parts_defaults'])) $partsPayload = $defaults['parts_defaults'];
            if (empty($detailPayload) && !empty($defaults['detail_defaults'])) $detailPayload = $defaults['detail_defaults'];
        }

        // Default Sales Tax 11% untuk Seat
        if (!isset($detailPayload['totals'])) $detailPayload['totals'] = [];
        if (!isset($detailPayload['totals']['sales_tax_percent'])) {
            $detailPayload['totals']['sales_tax_percent'] = 11;
        }

        $report = CcrReport::create([
            'type' => 'seat',
            'group_folder' => $data['group_folder'],
            'component' => $data['component'],
            'make' => $data['make'] ?? null,
            'model' => $data['model'] ?? null,
            'sn' => $data['sn'] ?? null,
            'smu' => $data['smu'] ?? null,
            'unit' => $data['unit'] ?? null,
            'wo_pr' => $data['wo_pr'] ?? null,
            'customer' => $data['customer'] ?? null,
            'inspection_date' => $finalDate,

            'template_key' => $templateKey,
            'template_version' => $templateVersionInt,

            'parts_payload' => $partsPayload,
            'detail_payload' => $detailPayload,
        ]);

        return redirect()->route('seat.edit', $report->id)
            ->with('success', 'CCR Operator Seat berhasil dibuat.');
    }

    // ===========================================================
    // EDIT PAGE
    // ===========================================================
    public function edit($id)
    {
        $report = CcrReport::with('items.photos')->findOrFail($id);

        // Inisialisasi worksheet jika DB masih kosong (Syncing logic)
        $this->ensureWorksheetInitialized($report);
        $report->refresh();

        $brands = file_exists(resource_path('data/brand_list.php')) ? include resource_path('data/brand_list.php') : [];
        $customers = file_exists(resource_path('data/customer_list.php')) ? include resource_path('data/customer_list.php') : [];

        $groupedCustomers = ['CV' => [], 'PT' => [], 'Other' => []];
        foreach ($customers as $c) {
            if (str_starts_with($c, 'CV')) $groupedCustomers['CV'][] = $c;
            elseif (str_starts_with($c, 'PT')) $groupedCustomers['PT'][] = $c;
            else $groupedCustomers['Other'][] = $c;
        }

        $templates = $this->getSeatTemplates();

        return view('seat.edit-seat', compact('report', 'brands', 'groupedCustomers', 'templates'));
    }

    // ===========================================================
    // UPDATE HEADER + PAYLOAD + ITEMS
    // ===========================================================
    public function updateHeader(Request $request, $id)
    {
        $report = CcrReport::with('items.photos')->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'group_folder'    => 'required|string',
            'component'       => 'required|string',
            'inspection_date' => 'required|date',

            'parts_payload'   => 'nullable|string',
            'detail_payload'  => 'nullable|string',

            'items'                 => 'nullable|array',
            'items.*.description'   => 'nullable|string',
            'items.*.photos'        => 'nullable|array',
            'items.*.photos.*'      => 'nullable|image|max:8000',
            'items.*.delete_photos' => 'nullable|array',

            'new_items'               => 'nullable|array',
            'new_items.*.description' => 'nullable|string',
        ]);

        // Logic validasi sisa foto lama (Anti-blank item)
        $validator->after(function ($v) use ($request, $report) {
            foreach ((array) $request->input('items', []) as $itemId => $row) {
                $desc = trim((string) ($row['description'] ?? ''));
                $hasNewUpload = $request->hasFile("items.$itemId.photos");
                $itemModel = $report->items->firstWhere('id', (int) $itemId);
                if (!$itemModel) continue;

                $existingIds = $itemModel->photos->pluck('id')->map(fn ($x) => (int) $x)->all();
                $toDelete = collect($row['delete_photos'] ?? [])->map(fn ($x) => (int) $x)->all();
                $remaining = count($existingIds) - count(array_intersect($existingIds, $toDelete));

                if ($desc === '' && $remaining === 0 && !$hasNewUpload) {
                    $v->errors()->add("items.$itemId.description", 'Item Seat wajib memiliki deskripsi atau minimal 1 foto.');
                }
            }
        });

        $data = $validator->validate();
        $changed = false;

        // Jam WITA
        $finalDate = Carbon::parse($data['inspection_date'])->setTimeFromTimeString(now('Asia/Makassar')->format('H:i'));

        // Update Payload (Anti-Overwrite)
        if ($request->filled('parts_payload')) {
            $rawParts = $this->decodeJsonInput($request->parts_payload);
            $report->parts_payload = $this->sanitizePartsPayload($rawParts);
            $changed = true;
        }
        if ($request->filled('detail_payload')) {
            $rawDetail = $this->decodeJsonInput($request->detail_payload);
            $report->detail_payload = $this->sanitizeDetailPayload($rawDetail);
            $changed = true;
        }

        // Update Header
        $report->fill([
            'group_folder'    => $data['group_folder'],
            'component'       => $data['component'],
            'make'            => $request->make,
            'model'           => $request->model,
            'sn'              => $request->sn,
            'smu'             => $request->smu,
            'unit'            => $request->unit,
            'wo_pr'           => $request->wo_pr,
            'customer'        => $request->customer,
            'inspection_date' => $finalDate,
        ]);

        if ($report->isDirty()) {
            $report->save();
            $changed = true;
        }

        // Sync Foto Folder
        $folderDate = Carbon::parse($report->inspection_date)->format('Y-m-d');
        $safeComp = substr(preg_replace('/[^A-Za-z0-9\- ]/', '', $report->component), 0, 30);
        $folderPath = "synology/CCR-Seat-{$folderDate}-{$safeComp}";
        Storage::disk('public')->makeDirectory("$folderPath/photos");

        // Update Old Items & Photos
        if (!empty($data['items'])) {
            foreach ($data['items'] as $itemId => $input) {
                $item = CcrItem::find($itemId);
                if ($item) {
                    $item->update(['description' => $input['description'] ?? '']);
                    if (!empty($input['delete_photos'])) {
                        foreach ($input['delete_photos'] as $pId) {
                            $p = CcrPhoto::find($pId);
                            if ($p) { Storage::disk('public')->delete($p->path); $p->delete(); }
                        }
                    }
                    if ($request->hasFile("items.$itemId.photos")) {
                        foreach ($request->file("items.$itemId.photos") as $file) {
                            CcrPhoto::create(['ccr_item_id' => $itemId, 'path' => $file->store("$folderPath/photos", 'public')]);
                        }
                    }
                }
            }
        }

        // New Items
        if (!empty($data['new_items'])) {
            foreach ($data['new_items'] as $index => $input) {
                if (empty($input['description']) && !$request->hasFile("new_items.$index.photos")) continue;
                $item = CcrItem::create(['ccr_report_id' => $report->id, 'description' => $input['description'] ?? '']);
                if ($request->hasFile("new_items.$index.photos")) {
                    foreach ($request->file("new_items.$index.photos") as $file) {
                        CcrPhoto::create(['ccr_item_id' => $item->id, 'path' => $file->store("$folderPath/photos", 'public')]);
                    }
                }
            }
        }

        if ($changed) {
            $report->update(['docx_generated_at' => null, 'updated_at' => now()]);
        }

        return redirect()->route('ccr.manage.seat')->with('success', 'CCR Operator Seat berhasil diperbarui!');
    }

    // ===========================================================
    // AUTOSAVE (AJAX)
    // ===========================================================
    public function autosaveWorksheet(Request $request, $id)
    {
        $report = CcrReport::findOrFail($id);
        
        $hasParts = $request->has('parts_payload');
        $hasDetail = $request->has('detail_payload');

        $report->parts_payload = $hasParts ? $this->sanitizePartsPayload($this->decodeJsonInput($request->parts_payload)) : $report->parts_payload;
        $report->detail_payload = $hasDetail ? $this->sanitizeDetailPayload($this->decodeJsonInput($request->detail_payload)) : $report->detail_payload;

        if ($report->isDirty()) {
            $report->docx_generated_at = null;
            $report->save();
        }

        return response()->json(['ok' => true, 'saved_at' => now()->toIso8601String()]);
    }

    // ===========================================================
    // TEMPLATE AJAX DEFAULTS
    // ===========================================================
    public function templateDefaults(Request $request)
    {
        $key = $request->input('template_key', 'seat_blank');
        $repo = app(SeatTemplateRepo::class);
        $defaults = $repo->loadDefaults($key);

        return response()->json([
            'ok' => true,
            'parts' => $defaults['parts_defaults'] ?? [],
            'detail' => $defaults['detail_defaults'] ?? [],
            'datalists' => SeatTemplateRepo::datalists($key)
        ]);
    }

    // ===========================================================
    // HELPERS & SANITIZATION
    // ===========================================================
    private function decodeJsonInput($raw): array
    {
        if (is_array($raw)) return $raw;
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function sanitizePartsPayload(array $payload): array
    {
        // Logic pembersihan angka/money (sama dengan Engine)
        if (isset($payload['rows'])) {
            foreach ($payload['rows'] as &$row) {
                foreach (['sales_price', 'extended_price', 'qty'] as $key) {
                    if (isset($row[$key])) $row[$key] = preg_replace('/[^\d]/', '', (string)$row[$key]);
                }
            }
        }
        return $payload;
    }

    private function sanitizeDetailPayload(array $payload): array
    {
        if (isset($payload['totals']['tax_percent'])) {
            $payload['totals']['sales_tax_percent'] = $payload['totals']['tax_percent'];
        }
        return $payload;
    }

    private function getSeatTemplates(): array
    {
        return app(SeatTemplateRepo::class)->list();
    }

    private function ensureWorksheetInitialized($report)
    {
        if (empty($report->parts_payload) && $report->template_key) {
            $defaults = app(SeatTemplateRepo::class)->loadDefaults($report->template_key);
            $report->update([
                'parts_payload' => $defaults['parts_defaults'],
                'detail_payload' => $defaults['detail_defaults']
            ]);
        }
    }
}