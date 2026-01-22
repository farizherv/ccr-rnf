<?php

namespace App\Http\Controllers;

use App\Models\CcrReport;
use App\Models\CcrItem;
use App\Models\CcrPhoto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;
use App\Support\Inbox;
use Illuminate\Support\Facades\Validator;
use App\Support\WorksheetTemplates\EngineTemplateRepo;
use App\Support\WorksheetTemplates\EngineTemplateService;

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

        return view('engine.create', compact('groupFolders', 'groupedCustomers', 'brands'));
    }

    // ===========================================================
    // STORE (HEADER + ITEM BARU)
    // ===========================================================
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'group_folder'    => 'required|string',
            'component'       => 'required|string',
            'make'            => 'nullable|string',
            'model'           => 'nullable|string',
            'sn'              => 'nullable|string',
            'smu'             => 'nullable|string',
            'customer'        => 'nullable|string',
            'inspection_date' => 'required|date',

            // ✅ payload JSON (boleh kosong)
            'parts_payload'   => ['nullable', 'string', function ($attr, $val, $fail) {
                if ($val === null || trim($val) === '') return;
                json_decode($val, true);
                if (json_last_error() !== JSON_ERROR_NONE) $fail('Parts payload JSON tidak valid.');
            }],
            'detail_payload'  => ['nullable', 'string', function ($attr, $val, $fail) {
                if ($val === null || trim($val) === '') return;
                json_decode($val, true);
                if (json_last_error() !== JSON_ERROR_NONE) $fail('Detail payload JSON tidak valid.');
            }],

            // ✅ minimal 1 item
            'items'               => 'required|array|min:1',
            'items.*.description' => 'nullable|string',
            'items.*.photos'      => 'nullable|array',
            'items.*.photos.*'    => 'nullable|image|max:8000',
        ], [
            'items.min' => 'Minimal 1 item kerusakan harus diisi.',
        ]);

        // ✅ tiap item wajib punya deskripsi ATAU foto
        $validator->after(function ($v) use ($request) {
            foreach ((array) $request->input('items', []) as $index => $row) {
                $desc = trim((string) ($row['description'] ?? ''));
                $hasPhoto = $request->hasFile("items.$index.photos");

                if ($desc === '' && !$hasPhoto) {
                    $v->errors()->add("items.$index.description", 'Isi deskripsi atau upload minimal 1 foto pada item ini.');
                }
            }
        });

        $data = $validator->validate();

        // tanggal + jam WITA
        $finalDate = Carbon::parse($data['inspection_date'])
            ->setTimeFromTimeString(now('Asia/Makassar')->format('H:i'));

        // decode + sanitize payload
        $partsPayload  = $this->sanitizePartsPayload($this->decodeJsonInput($request->input('parts_payload')));
        $detailPayload = $this->sanitizeGenericPayload($this->decodeJsonInput($request->input('detail_payload')));

        // ambil template meta dari payload (biar Template Kosong pun tersimpan)
        $tplKey = trim((string)($partsPayload['meta']['template_key'] ?? ''));
        $tplVerStr = trim((string)($partsPayload['meta']['template_version'] ?? ''));
        $tplVer = 1;
        if ($tplVerStr !== '') {
            $v = strtolower($tplVerStr);
            $v = ltrim($v, 'v');
            if (ctype_digit($v)) $tplVer = (int)$v;
        }
        if ($tplKey === '') $tplKey = null;

        // SIMPAN HEADER REPORT
        $report = CcrReport::create([
            'type'            => 'engine',
            'template_key'    => $tplKey,
            'template_version' => $tplVer,
            'group_folder'    => $data['group_folder'],
            'component'       => $data['component'],
            'make'            => $request->make,
            'model'           => $request->model,
            'sn'              => $request->sn,
            'smu'             => $request->smu,
            'customer'        => $request->customer,
            'inspection_date' => $finalDate,

            // ✅ simpan payload JSON
            'parts_payload'   => $partsPayload,
            'detail_payload'  => $detailPayload,
        ]);

        $report->refresh();

        // folder photos
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
        $report = CcrReport::with('items.photos')->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'group_folder'    => 'required|string',
            'component'       => 'required|string',
            'inspection_date' => 'required|date',

            // ✅ payload JSON (boleh kosong)
            'parts_payload'   => ['nullable', 'string', function ($attr, $val, $fail) {
                if ($val === null || trim($val) === '') return;
                json_decode($val, true);
                if (json_last_error() !== JSON_ERROR_NONE) $fail('Parts payload JSON tidak valid.');
            }],
            'detail_payload'  => ['nullable', 'string', function ($attr, $val, $fail) {
                if ($val === null || trim($val) === '') return;
                json_decode($val, true);
                if (json_last_error() !== JSON_ERROR_NONE) $fail('Detail payload JSON tidak valid.');
            }],

            'items'                 => 'nullable|array',
            'items.*.description'   => 'nullable|string',
            'items.*.photos'        => 'nullable|array',
            'items.*.photos.*'      => 'nullable|image|max:8000',
            'items.*.delete_photos' => 'nullable|array',

            'new_items'                 => 'nullable|array',
            'new_items.*.description'   => 'nullable|string',
            'new_items.*.photos'        => 'nullable|array',
            'new_items.*.photos.*'      => 'nullable|image|max:8000',
        ]);

        // validasi “desc atau foto” untuk ITEM LAMA
        $validator->after(function ($v) use ($request, $report) {
            foreach ((array) $request->input('items', []) as $itemId => $row) {
                $desc = trim((string) ($row['description'] ?? ''));
                $hasNewUpload = $request->hasFile("items.$itemId.photos");

                $itemModel = $report->items->firstWhere('id', (int) $itemId);
                if (!$itemModel) continue;

                $existingIds = $itemModel->photos->pluck('id')->map(fn($x) => (int) $x)->all();
                $toDelete = collect($row['delete_photos'] ?? [])->map(fn($x) => (int) $x)->all();

                $deletedCount = count(array_intersect($existingIds, $toDelete));
                $remainingOldPhotos = max(0, count($existingIds) - $deletedCount);

                if ($desc === '' && $remainingOldPhotos === 0 && !$hasNewUpload) {
                    $v->errors()->add("items.$itemId.description", 'Item ini wajib punya deskripsi atau minimal 1 foto.');
                }
            }
        });

        $data = $validator->validate();

        $changed = false;

        // tanggal + jam WITA
        $finalDate = Carbon::parse($data['inspection_date'])
            ->setTimeFromTimeString(now('Asia/Makassar')->format('H:i'));

        // ============================
        // Payload update (ANTI KEHAPUS):
        // - kalau request parts_payload kosong -> JANGAN overwrite DB
        // - overwrite hanya kalau ada JSON valid yang isinya meta/rows
        // ============================
        $payloadChanged = false;

        if ($request->filled('parts_payload')) {
            $newParts = $this->sanitizePartsPayload($this->decodeJsonInput($request->input('parts_payload')));
            if (!empty($newParts)) {
                $report->parts_payload = $newParts;
                $payloadChanged = true;

                // sync template meta ke kolom report
                $tplKey = trim((string)($newParts['meta']['template_key'] ?? ''));
                $tplVerStr = trim((string)($newParts['meta']['template_version'] ?? ''));
                $tplVer = 1;
                if ($tplVerStr !== '') {
                    $v = strtolower($tplVerStr);
                    $v = ltrim($v, 'v');
                    if (ctype_digit($v)) $tplVer = (int)$v;
                }
                if ($tplKey !== '') {
                    $report->template_key = $tplKey;
                    $report->template_version = $tplVer;
                }
            }
        }

        if ($request->filled('detail_payload')) {
            $newDetail = $this->sanitizeGenericPayload($this->decodeJsonInput($request->input('detail_payload')));
            if (!empty($newDetail)) {
                $report->detail_payload = $newDetail;
                $payloadChanged = true;
            }
        }

        // Update header report
        $report->fill([
            'group_folder'    => $data['group_folder'],
            'component'       => $data['component'],
            'make'            => $request->make,
            'model'           => $request->model,
            'sn'              => $request->sn,
            'smu'             => $request->smu,
            'customer'        => $request->customer,
            'inspection_date' => $finalDate,
        ]);

        if ($report->isDirty()) {
            $report->save();
            $changed = true;
        }

        if ($payloadChanged) {
            // kalau payload diset tapi kebetulan isDirty() false (misal cast bikin tricky),
            // kita paksa save sekali
            if (!$changed) {
                $report->save();
            }
            $changed = true;
        }

        // folder photos
        $date = Carbon::parse($report->inspection_date)->format('Y-m-d');
        $safe = substr(preg_replace('/[^A-Za-z0-9\- ]/', '', $report->component), 0, 30);
        $folder = "synology/CCR-{$report->group_folder}-{$date}-{$safe}";
        Storage::disk('public')->makeDirectory("$folder/photos");

        // ===== UPDATE OLD ITEMS =====
        if (!empty($data['items'])) {
            foreach ($data['items'] as $itemId => $input) {
                $item = CcrItem::with('photos')->find($itemId);
                if (!$item) continue;

                $item->fill([
                    'description' => $input['description'] ?? '',
                ]);

                if ($item->isDirty()) {
                    $item->save();
                    $changed = true;
                }

                // delete old photos
                if (!empty($input['delete_photos'])) {
                    foreach ($input['delete_photos'] as $photoId) {
                        $photo = CcrPhoto::find($photoId);
                        if ($photo) {
                            Storage::disk('public')->delete($photo->path);
                            $photo->delete();
                            $changed = true;
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
                        $changed = true;
                    }
                }
            }
        }

        // ===== CREATE NEW ITEMS =====
        if (!empty($data['new_items'])) {
            foreach ($data['new_items'] as $index => $input) {
                $hasDesc  = trim((string)($input['description'] ?? ''));
                $hasPhoto = $request->hasFile("new_items.$index.photos");

                if ($hasDesc === '' && !$hasPhoto) continue;

                $item = CcrItem::create([
                    'ccr_report_id' => $report->id,
                    'description'   => $hasDesc ?? '',
                ]);
                $changed = true;

                if ($hasPhoto) {
                    foreach ($request->file("new_items.$index.photos") as $photo) {
                        $path = $photo->store("$folder/photos", 'public');
                        CcrPhoto::create([
                            'ccr_item_id' => $item->id,
                            'path'        => $path,
                        ]);
                        $changed = true;
                    }
                }
            }
        }

        // invalidate docx kalau ada perubahan apapun
        if ($changed) {
            CcrReport::whereKey($report->id)->update([
                'docx_generated_at' => null,
                'updated_at'        => now(),
            ]);
        }

        return redirect()->route('ccr.manage.engine')
            ->with('success', 'Perubahan CCR Engine berhasil disimpan!');
    }

    // ===========================================================
    // DELETE ITEM
    // ===========================================================
    public function deleteItem(CcrItem $item)
    {
        $item->loadMissing(['report', 'photos']);

        $reportId = $item->ccr_report_id;

        foreach ($item->photos as $p) {
            Storage::disk('public')->delete($p->path);
            $p->delete();
        }

        $item->delete();

        CcrReport::whereKey($reportId)->update([
            'docx_generated_at' => null,
            'updated_at'        => now(),
        ]);

        return redirect()->route('engine.edit', $reportId)
            ->with('success', 'Item berhasil dihapus.');
    }

    // ===========================================================
    // DELETE PHOTO
    // ===========================================================
    public function deletePhoto(CcrPhoto $photo)
    {
        $photo->loadMissing('item');

        $reportId = $photo->item->ccr_report_id;

        Storage::disk('public')->delete($photo->path);
        $photo->delete();

        CcrReport::whereKey($reportId)->update([
            'docx_generated_at' => null,
            'updated_at'        => now(),
        ]);

        return redirect()->route('engine.edit', $reportId)
            ->with('success', 'Foto berhasil dihapus.');
    }

    // ===========================================================
    // DELETE MULTIPLE TERPILIH
    // ===========================================================
    public function deleteMultiple(Request $request)
    {
        $ids = $request->input('ids', []);

        CcrReport::whereIn('id', $ids)->get()->each(function ($r) {
            $r->purge_at = now()->addDays(7);
            $r->save();
            $r->delete();
        });

        return back()->with('success', 'Dipindahkan ke Sampah (7 hari).');
    }

    // PREVIEW LIHAT
    public function previewEngine($id)
    {
        $report = CcrReport::with('items.photos')->findOrFail($id);
        return view('engine.preview', compact('report'));
    }

    // SUBMIT TO DIREKTUR
    public function submit(Request $request, int $id)
    {
        $report = CcrReport::findOrFail($id);

        $resubmit = $request->boolean('resubmit');

        if (in_array($report->approval_status, ['waiting', 'in_review'])) {
            return back()->with('error', 'CCR ini sedang menunggu persetujuan Direktur.');
        }

        if ($report->approval_status === 'approved' && !$resubmit) {
            return back()->with('error', 'CCR ini sudah Approved. Gunakan tombol Re-submit jika ingin kirim ulang.');
        }

        $report->approval_status = 'waiting';
        $report->submitted_by    = auth()->id();
        $report->submitted_at    = now();

        if ($resubmit) {
            $report->director_note = null;
        }

        $report->save();

        $componentName = trim((string) ($report->component ?? ''));
        if ($componentName === '') $componentName = 'Engine';

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

    // ===========================================================
    // Helper: decode JSON string dari hidden input
    // ===========================================================
    private function decodeJsonInput(?string $raw): array
    {
        if ($raw === null) return [];
        $raw = trim($raw);
        if ($raw === '') return [];

        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }

    // ===========================================================
    // SANITIZE: Parts payload (buang _id, rapihin money digits)
    // Struktur minimal: { meta: { no_unit, footer_extended, footer_extended_mode }, rows: [] }
    // NOTE PATCH-1:
    // - Simpan total Extended Price footer ke DB via JSON (meta.footer_extended + mode)
    // - Anti overwrite kalau payload benar-benar kosong (rows kosong & meta penting kosong)
    // ===========================================================
    private function sanitizePartsPayload(array $payload): array
    {
        if (empty($payload)) return [];

        $meta   = $payload['meta'] ?? [];
        $rows   = $payload['rows'] ?? [];
        $styles = $payload['styles'] ?? [];
        $notes  = $payload['notes'] ?? [];

        if (!is_array($meta)) $meta = [];
        if (!is_array($rows)) $rows = [];
        if (!is_array($styles)) $styles = [];
        if (!is_array($notes)) $notes = [];

        // ===== META =====
        $noUnit = trim((string)($meta['no_unit'] ?? ''));

        // template meta (Opsi A)
        $templateKey     = trim((string)($meta['template_key'] ?? ''));
        $templateVersion = trim((string)($meta['template_version'] ?? ''));

        // footer totals (digits only)
        $footerTotal    = preg_replace('/[^\d]/', '', (string)($meta['footer_total'] ?? ''));
        $footerExtended = preg_replace('/[^\d]/', '', (string)($meta['footer_extended'] ?? ''));

        // mode: auto/manual (kalau ga valid -> auto)
        $ftModeRaw = strtolower(trim((string)($meta['footer_total_mode'] ?? 'auto')));
        $feModeRaw = strtolower(trim((string)($meta['footer_extended_mode'] ?? 'auto')));

        $footerTotalMode    = in_array($ftModeRaw, ['auto', 'manual'], true) ? $ftModeRaw : 'auto';
        $footerExtendedMode = in_array($feModeRaw, ['auto', 'manual'], true) ? $feModeRaw : 'auto';

        // ===== ROWS =====
        $cleanRows = [];
        foreach ($rows as $r) {
            if (!is_array($r)) continue;

            $qty  = preg_replace('/[^\d]/', '', (string)($r['qty'] ?? ''));
            $uom  = trim((string)($r['uom'] ?? ''));
            $pn   = trim((string)($r['part_number'] ?? ''));
            $pd   = trim((string)($r['part_description'] ?? ''));
            $ps   = trim((string)($r['part_section'] ?? ''));

            $pp   = preg_replace('/[^\d]/', '', (string)($r['purchase_price'] ?? ''));
            $tot  = preg_replace('/[^\d]/', '', (string)($r['total'] ?? ''));
            $sp   = preg_replace('/[^\d]/', '', (string)($r['sales_price'] ?? ''));
            $ext  = preg_replace('/[^\d]/', '', (string)($r['extended_price'] ?? ''));

            $tm = array_key_exists('total_manual', $r) ? (bool)$r['total_manual'] : false;
            $em = array_key_exists('extended_manual', $r) ? (bool)$r['extended_manual'] : false;

            // skip row kosong total
            $isEmpty = ($qty === '' && $uom === '' && $pn === '' && $pd === '' && $ps === '' && $pp === '' && $tot === '' && $sp === '' && $ext === '');
            if ($isEmpty) continue;

            $cleanRows[] = [
                'qty'              => $qty,
                'uom'              => $uom,
                'part_number'      => $pn,
                'part_description' => $pd,
                'part_section'     => $ps,

                'purchase_price'   => $pp,
                'total'            => $tot,
                'sales_price'      => $sp,
                'extended_price'   => $ext,

                'total_manual'     => $tm,
                'extended_manual'  => $em,
            ];
        }

        // ===== Anti overwrite kalau benar-benar kosong =====
        // - Kalau user submit kosong TANPA template_key, jangan timpa data lama.
        // - Tapi kalau template_key ada (termasuk Template Kosong), tetap simpan meskipun rows kosong.
        $hasMeaningfulMeta = ($noUnit !== '' || $templateKey !== '' || $templateVersion !== '' || $footerTotal !== '' || $footerExtended !== '');
        $hasMeaningfulExtra = (!empty($styles) || !empty($notes));

        if (!$hasMeaningfulMeta && !$hasMeaningfulExtra && count($cleanRows) === 0) {
            return [];
        }

        return [
            'meta' => [
                'no_unit'              => $noUnit,

                // template meta
                'template_key'         => $templateKey,
                'template_version'     => $templateVersion,

                // footer
                'footer_total'         => $footerTotal,
                'footer_extended'      => $footerExtended,
                'footer_total_mode'    => $footerTotalMode,    // auto/manual
                'footer_extended_mode' => $footerExtendedMode, // auto/manual
            ],
            'rows'   => $cleanRows,
            'styles' => $styles,
            'notes'  => $notes,
        ];
    }

    // ===========================================================
    // SANITIZE generic payload (detail nanti kamu isi belakangan)
    // ===========================================================
    private function sanitizeGenericPayload(array $payload): array
    {
        if (empty($payload)) return [];
        // minimal pastikan meta/rows array kalau ada
        if (isset($payload['meta']) && !is_array($payload['meta'])) $payload['meta'] = [];
        if (isset($payload['rows']) && !is_array($payload['rows'])) $payload['rows'] = [];
        return $payload;
    }

    
    // ===========================================================
    // Saat render halaman worksheet, panggil ensureInitialized + kirim list template
    // ===========================================================
    public function worksheet($id)
    {
    $report = CcrReport::findOrFail($id);

    // kalau template sudah ada dan payload kosong → seed defaults
    EngineTemplateService::ensureInitialized($report);

    // list template untuk popup
    $templates = EngineTemplateRepo::list();

    return view('engine.worksheet', compact('report','templates'));
    }

    // ===========================================================
    // Tambah method apply template
    // ===========================================================
    public function applyWorksheetTemplate(Request $request, $id)
    {
    $report = CcrReport::findOrFail($id);

    $data = $request->validate([
        'template_key' => 'required|string',
        'replace' => 'nullable|boolean',
    ]);

    $replace = (bool)($data['replace'] ?? false);

    $res = EngineTemplateService::applyTemplate($report, $data['template_key'], $replace);

    if ($request->wantsJson()) {
        return response()->json($res, $res['ok'] ? 200 : 422);
    }

    return back()->with($res['ok'] ? 'success' : 'error', $res['message'] ?? 'OK');
    }

}
