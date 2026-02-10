<?php

namespace App\Http\Controllers;

use App\Models\CcrItem;
use App\Models\CcrPhoto;
use App\Models\CcrReport;
use App\Support\Inbox;
use App\Support\WorksheetTemplates\EngineTemplateRepo;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

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
            if (str_starts_with($c, 'CV')) {
                $groupedCustomers['CV'][] = $c;
            } elseif (str_starts_with($c, 'PT')) {
                $groupedCustomers['PT'][] = $c;
            } else {
                $groupedCustomers['Other'][] = $c;
            }
        }

        $templates = $this->getEngineTemplates();

        return view('engine.create', compact('groupFolders', 'groupedCustomers', 'brands', 'templates'));
    } 

    // ===========================================================
    // STORE (HEADER + ITEM BARU)
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
            'inspection_date' => 'required|string',

            'template_key' => 'nullable|string',
            'template_version' => 'nullable|integer',
            'parts_payload' => 'nullable|string',
            'detail_payload' => 'nullable|string',
        ]);

        $finalDate = Carbon::parse($data['inspection_date'])->format('Y-m-d');

        $templateKey = $data['template_key'] ?? 'engine_blank';
        $templateVersionInt = (int)($data['template_version'] ?? 1);

        $partsPayload = [];
        $detailPayload = [];

        if (!empty($data['parts_payload'])) {
            $decoded = json_decode($data['parts_payload'], true);
            $partsPayload = is_array($decoded) ? $decoded : [];
        }

        if (!empty($data['detail_payload'])) {
            $decoded = json_decode($data['detail_payload'], true);
            $detailPayload = is_array($decoded) ? $decoded : [];
        }

        // fallback defaults
        if (empty($partsPayload) || empty($detailPayload)) {
            // ✅ pakai repo yang kamu import: App\Support\WorksheetTemplates\EngineTemplateRepo
            if (method_exists(EngineTemplateRepo::class, 'loadDefaults')) {
                $repo = app(EngineTemplateRepo::class);
                $defaults = $repo->loadDefaults($templateKey, $templateVersionInt);
                if (empty($partsPayload) && !empty($defaults['parts_defaults'])) $partsPayload = $defaults['parts_defaults'];
                if (empty($detailPayload) && !empty($defaults['detail_defaults'])) $detailPayload = $defaults['detail_defaults'];
            } else {
                $defaults = EngineTemplateRepo::defaults($templateKey);
                if (empty($partsPayload) && !empty($defaults['parts'])) $partsPayload = $defaults['parts'];
                if (empty($detailPayload) && !empty($defaults['detail'])) $detailPayload = $defaults['detail'];
            }
        }

        if (!isset($detailPayload['totals'])) $detailPayload['totals'] = [];
        if (!isset($detailPayload['totals']['sales_tax_percent'])) {
            $detailPayload['totals']['sales_tax_percent'] = 11;
        }

        $report = CcrReport::create([
            'type' => 'engine',
            'group_folder' => $data['group_folder'],
            'component' => $data['component'],
            'make' => $data['make'] ?? null,
            'model' => $data['model'] ?? null,
            'sn' => $data['sn'] ?? null,
            'smu' => $data['smu'] ?? null,
            'customer' => $data['customer'] ?? null,
            'inspection_date' => $finalDate,

            'template_key' => $templateKey,
            'template_version' => $templateVersionInt,

            'parts_payload' => $partsPayload,
            'detail_payload' => $detailPayload,
        ]);

        return redirect()->route('engine.edit', $report->id)
            ->with('success', 'CCR Engine berhasil dibuat.');
    }

    // ===========================================================
    // EDIT PAGE
    // ===========================================================
    public function edit($id)
    {
        $report = CcrReport::with('items.photos')->findOrFail($id);

        // ✅ Opsi A: kalau report sudah punya template_key dan payload masih kosong → isi default template
        $this->ensureWorksheetInitialized($report);
        $report->refresh();

        $brands = file_exists(resource_path('data/brand_list.php'))
            ? include resource_path('data/brand_list.php')
            : [];

        $customers = file_exists(resource_path('data/customer_list.php'))
            ? include resource_path('data/customer_list.php')
            : [];

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

        $templates = $this->getEngineTemplates();

        return view('engine.edit-engine', compact('report', 'brands', 'groupedCustomers', 'templates'));
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

            // payload JSON (boleh kosong)
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

            'new_items'               => 'nullable|array',
            'new_items.*.description' => 'nullable|string',
            'new_items.*.photos'      => 'nullable|array',
            'new_items.*.photos.*'    => 'nullable|image|max:8000',
        ]);

        // validasi “desc atau foto” untuk ITEM LAMA
        $validator->after(function ($v) use ($request, $report) {
            foreach ((array) $request->input('items', []) as $itemId => $row) {
                $desc = trim((string) ($row['description'] ?? ''));
                $hasNewUpload = $request->hasFile("items.$itemId.photos");

                $itemModel = $report->items->firstWhere('id', (int) $itemId);
                if (!$itemModel) continue;

                $existingIds = $itemModel->photos->pluck('id')->map(fn ($x) => (int) $x)->all();
                $toDelete = collect($row['delete_photos'] ?? [])->map(fn ($x) => (int) $x)->all();

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
        // - kalau request payload kosong -> JANGAN overwrite DB
        // - overwrite hanya kalau ada JSON valid dan bukan truly-empty
        // ============================
        $payloadChanged = false;

        // decode dulu untuk ambil template meta (kalau ada)
        $rawParts  = $this->decodeJsonInput($request->input('parts_payload'));
        $rawDetail = $this->decodeJsonInput($request->input('detail_payload'));
        [$templateKey, $templateVersionStr, $templateVersionInt, $manifest] = $this->resolveTemplateFromPayload($rawParts);

        if ($request->filled('parts_payload')) {
            $newParts = $this->sanitizePartsPayload($rawParts, $templateKey, $templateVersionStr, $manifest);
            if (!empty($newParts)) {
                $report->parts_payload = $newParts;
                $payloadChanged = true;
            }
        }

        if ($request->filled('detail_payload')) {
            $newDetail = $this->sanitizeDetailPayload($rawDetail, $templateKey, $templateVersionStr, $manifest);
            if (!empty($newDetail)) {
                $report->detail_payload = $newDetail;
                $payloadChanged = true;
            }
        }

        // update template columns kalau payload mengandung template meta
        if ($templateKey) {
            $report->template_key = $templateKey;
            if ($templateVersionInt !== null) {
                $report->template_version = $templateVersionInt;
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
            // kalau payload diset tapi kebetulan isDirty() false,
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
                $hasDesc  = trim((string) ($input['description'] ?? ''));
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
    // TEMPLATE: AJAX defaults (tanpa report id)
    // Route: engine.worksheet.template.defaults
    // ===========================================================
    public function templateDefaults(Request $request)
    {
        $data = $request->validate([
            'template_key' => ['required', 'string'],
        ]);

        $key = trim((string) $data['template_key']);

        $defaults = EngineTemplateRepo::defaults($key);
        if (!is_array($defaults) || empty($defaults)) {
            return response()->json([
                'ok'      => false,
                'message' => 'Template tidak ditemukan',
            ], 404);
        }

        $manifest = is_array($defaults['manifest'] ?? null) ? $defaults['manifest'] : [];
        $versionStr = (string) ($manifest['version'] ?? '');
        $versionInt = $this->toTemplateVersionInt($versionStr);
        $detail = $defaults['detail'] ?? [];
        if (isset($detail['totals']['tax_percent']) && !isset($detail['totals']['sales_tax_percent'])) {
            $detail['totals']['sales_tax_percent'] = $detail['totals']['tax_percent'];
        }

        // pastikan payload punya meta template juga (biar UI langsung sinkron)
        $parts  = $this->sanitizePartsPayload($defaults['parts'] ?? [], $key, $versionStr, $manifest);
        $detail = $this->sanitizeDetailPayload($defaults['detail'] ?? [], $key, $versionStr, $manifest);

        // datalists (per template) + fallback global
        $datalists = \App\Support\WorksheetTemplates\EngineTemplateRepo::datalists($key);

        return response()->json([
            'ok'                 => true,
            'key'                => $key,
            'template_version'   => $versionStr,
            'template_version_int' => $versionInt,
            'manifest'           => $manifest,
            'parts'              => $parts,
            'detail'             => $detail,
            'datalists'          => $datalists,
        ]);
    }

    // ===========================================================
    // TEMPLATE: apply ke report (opsional)
    // Route: engine.worksheet.template.apply
    // ===========================================================
    public function applyWorksheetTemplate(Request $request, $id)
    {
        $report = CcrReport::findOrFail($id);

        $data = $request->validate([
            'template_key' => ['required', 'string'],
            'replace'      => ['nullable', 'boolean'],
        ]);

        $key = trim((string) $data['template_key']);
        $replace = (bool) ($data['replace'] ?? false);

        $defaults = EngineTemplateRepo::defaults($key);
        if (empty($defaults['manifest'])) {
            $res = ['ok' => false, 'message' => 'Template tidak ditemukan.'];
            return $request->wantsJson()
                ? response()->json($res, 404)
                : back()->with('error', $res['message']);
        }

        $manifest = $defaults['manifest'];
        $versionStr = (string) ($manifest['version'] ?? '');
        $versionInt = $this->toTemplateVersionInt($versionStr) ?? 1;

        DB::transaction(function () use ($report, $key, $versionInt, $versionStr, $manifest, $defaults, $replace) {
            $r = CcrReport::whereKey($report->id)->lockForUpdate()->first();

            $r->template_key = $key;
            $r->template_version = $versionInt;

            $partsEmpty  = empty($r->parts_payload) || $r->parts_payload === [];
            $detailEmpty = empty($r->detail_payload) || $r->detail_payload === [];

            if ($replace || $partsEmpty) {
                $r->parts_payload = $this->sanitizePartsPayload($defaults['parts'] ?? [], $key, $versionStr, $manifest);
            } else {
                $r->parts_payload = $this->sanitizePartsPayload((array) $r->parts_payload, $key, $versionStr, $manifest);
            }

            if ($replace || $detailEmpty) {
                $r->detail_payload = $this->sanitizeDetailPayload($defaults['detail'] ?? [], $key, $versionStr, $manifest);
            } else {
                $r->detail_payload = $this->sanitizeDetailPayload((array) $r->detail_payload, $key, $versionStr, $manifest);
            }

            // invalidate docx
            $r->docx_generated_at = null;
            $r->save();
        });

        $res = ['ok' => true, 'message' => 'Template berhasil diterapkan.'];
        return $request->wantsJson()
            ? response()->json($res)
            : back()->with('success', $res['message']);
    }

// ===========================================================
// CONTROLLER: endpoint autosave worksheet (Parts + Detail)
// Route name: engine.worksheet.autosave
// Payload support: JSON object (request application/json) atau string JSON
// ===========================================================
public function autosaveWorksheet(Request $request, int $id)
{
    $report = CcrReport::findOrFail($id);

    $hasParts  = $request->has('parts_payload');
    $hasDetail = $request->has('detail_payload');

    if (!$hasParts && !$hasDetail) {
        return response()->json([
            'ok' => false,
            'message' => 'Tidak ada payload untuk disimpan.',
        ], 422);
    }

    // Ambil raw payload (bisa array dari JSON request, bisa string JSON dari hidden input)
    $partsRaw  = $hasParts  ? $request->input('parts_payload')  : null;
    $detailRaw = $hasDetail ? $request->input('detail_payload') : null;

    $dirty = false;

    // ============================
    // Context template dari report (fallback)
    // ============================
    $ctxKey = trim((string) ($report->template_key ?? ''));
    $ctxKey = ($ctxKey !== '') ? $ctxKey : null;

    $ctxManifest = $ctxKey ? EngineTemplateRepo::manifest($ctxKey) : null;

    $ctxVersionStr = '';
    if (is_array($ctxManifest)) {
        $ctxVersionStr = trim((string) ($ctxManifest['version'] ?? ''));
    }
    if ($ctxVersionStr === '') {
        $ctxVersionStr = 'v' . (int) ($report->template_version ?: 1);
    }

    $ctxVersionInt = (int) ($report->template_version ?: ($this->toTemplateVersionInt($ctxVersionStr) ?: 1));

    // ============================
    // PARTS autosave
    // - resolve template meta dari parts payload (kalau ada)
    // - sanitize + anti overwrite kosong
    // ============================
    if ($hasParts) {
        $rawPartsArr = $this->decodeJsonInput($partsRaw);

        // coba ambil template meta dari payload parts (kalau user baru pilih template)
        [$pKey, $pVerStr, $pVerInt, $pManifest] = $this->resolveTemplateFromPayload($rawPartsArr);

        if ($pKey) {
            $ctxKey = $pKey;
            $ctxManifest = is_array($pManifest) ? $pManifest : ($ctxKey ? EngineTemplateRepo::manifest($ctxKey) : null);

            if (trim((string) $pVerStr) !== '') $ctxVersionStr = trim((string) $pVerStr);
            else if (is_array($ctxManifest) && trim((string) ($ctxManifest['version'] ?? '')) !== '') {
                $ctxVersionStr = trim((string) $ctxManifest['version']);
            }

            $ctxVersionInt = (int) (($pVerInt !== null) ? $pVerInt : ($this->toTemplateVersionInt($ctxVersionStr) ?: $ctxVersionInt));
        }

        $cleanParts = $this->sanitizePartsPayload($rawPartsArr, $ctxKey, $ctxVersionStr, $ctxManifest);

        // anti overwrite: kalau benar-benar kosong, jangan timpa DB
        if (!empty($cleanParts)) {
            $report->parts_payload = $cleanParts;
            $dirty = true;
        }
    }

        // ============================
        // DETAIL autosave
        // - normalize legacy tax_percent -> sales_tax_percent
        // - sanitize + inject template meta dari context (kalau ada)
        // ============================
        if ($hasDetail) {
            $rawDetailArr = $this->decodeJsonInput($detailRaw);

            if (isset($rawDetailArr['totals']['tax_percent']) && !isset($rawDetailArr['totals']['sales_tax_percent'])) {
                $rawDetailArr['totals']['sales_tax_percent'] = $rawDetailArr['totals']['tax_percent'];
            }

            $cleanDetail = $this->sanitizeDetailPayload($rawDetailArr, $ctxKey, $ctxVersionStr, $ctxManifest);

            // anti overwrite: kalau benar-benar kosong, jangan timpa DB
            if (!empty($cleanDetail)) {
                $report->detail_payload = $cleanDetail;
                $dirty = true;
            }
        }

        // ============================
        // Update kolom template_key/template_version jika kita punya context
        // ============================
        if ($ctxKey) {
            $report->template_key = $ctxKey;
            $report->template_version = $ctxVersionInt;
        }

        if ($dirty) {
            // export Word harus regenerasi kalau payload berubah
            $report->docx_generated_at = null;
            $report->save();
        }

        return response()->json([
            'ok' => true,
            'saved' => $dirty,
            'saved_at' => now()->toIso8601String(),
            'template_key' => $ctxKey,
            'template_version' => $ctxVersionInt,
        ]);
    }


        // ===========================================================
        // applyWorksheetTemplate
        // ===========================================================
        public function worksheetAutosave(Request $request, int $id)
    {
        $report = CcrReport::findOrFail($id);

        $partsJson  = $request->input('parts_payload');
        $detailJson = $request->input('detail_payload');

        if ($partsJson === null && $detailJson === null) {
            return response()->json(['ok' => false, 'message' => 'No payload provided'], 422);
        }

        $dirty = false;

        if (is_string($partsJson)) {
            $decoded = json_decode($partsJson, true);
            if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
                return response()->json(['ok' => false, 'message' => 'Invalid parts_payload JSON'], 422);
            }

            $report->parts_payload = $this->sanitizePartsPayload($decoded);
            $dirty = true;

            // optional: sync template_key dari meta
            $meta = $report->parts_payload['meta'] ?? [];
            if (is_array($meta)) {
                if (!empty($meta['template_key'])) {
                    $report->template_key = $meta['template_key'];
                }
                if (!empty($meta['template_version'])) {
                    $report->template_version = $this->parseTemplateVersion($meta['template_version']);
                }
            }
        }

        if (is_string($detailJson)) {
            $decoded = json_decode($detailJson, true);
            if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
                return response()->json(['ok' => false, 'message' => 'Invalid detail_payload JSON'], 422);
            }

            // normalize legacy key tax_percent → sales_tax_percent
            if (isset($decoded['totals']['tax_percent']) && !isset($decoded['totals']['sales_tax_percent'])) {
                $decoded['totals']['sales_tax_percent'] = $decoded['totals']['tax_percent'];
            }

            $report->detail_payload = $this->sanitizeGenericPayload($decoded);
            $dirty = true;
        }

        if ($dirty) {
            // export Word harus regenerasi kalau payload berubah
            $report->docx_generated_at = null;
            $report->save();
        }

        return response()->json([
            'ok' => true,
            'saved_at' => now()->toIso8601String(),
        ]);
    }

        private function parseTemplateVersion($v): ?int
    {
        if ($v === null) return null;
        if (is_int($v)) return $v;
        $s = trim((string)$v);
        if ($s === '') return null;

        // "v1" → 1
        if (preg_match('/v(\d+)/i', $s, $m)) return (int)$m[1];

        // "1" → 1
        if (ctype_digit($s)) return (int)$s;

        return null;
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
    // Helper: decode JSON string / array dari hidden input atau request JSON
    // NOTE: $field hanya untuk kompatibilitas pemanggilan lama (boleh diabaikan)
    // ===========================================================
    private function decodeJsonInput($raw, ?string $field = null): array
    {
        if ($raw === null) return [];
        if (is_array($raw)) return $raw;

        $raw = trim((string) $raw);
        if ($raw === '') return [];

        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }

    // ===========================================================
    // Helper: resolve template meta dari payload
    // Return: [templateKey|null, versionStr, versionInt|null, manifest|null]
    // ===========================================================
    private function resolveTemplateFromPayload(array $partsPayload): array
    {
        $meta = (isset($partsPayload['meta']) && is_array($partsPayload['meta'])) ? $partsPayload['meta'] : [];

        $key = trim((string) ($meta['template_key'] ?? ''));
        if ($key === '') $key = null;

        $versionStr = trim((string) ($meta['template_version'] ?? ''));
        $manifest = null;
        $versionInt = null;

        if ($key) {
            $manifest = EngineTemplateRepo::manifest($key);
            if (is_array($manifest)) {
                $mv = trim((string) ($manifest['version'] ?? ''));
                if ($versionStr === '' && $mv !== '') $versionStr = $mv;
            }
            if ($versionStr !== '') {
                $versionInt = $this->toTemplateVersionInt($versionStr);
            }
        }

        return [$key, $versionStr, $versionInt, $manifest];
    }

    private function toTemplateVersionInt(?string $versionStr): ?int
    {
        $versionStr = trim((string) $versionStr);
        if ($versionStr === '') return null;

        // menerima '1', 'v1', 'V2', 'version 3', dll
        if (preg_match('/(\d+)/', $versionStr, $m)) {
            return max(1, (int) $m[1]);
        }

        return null;
    }

    // ===========================================================
    // ensureInitialized: kalau report punya template_key dan payload kosong → seed defaults
    // ===========================================================
    private function ensureWorksheetInitialized(CcrReport $report): void
    {
        $key = trim((string) ($report->template_key ?? ''));
        if ($key === '') return;

        $partsEmpty  = empty($report->parts_payload) || $report->parts_payload === [];
        $detailEmpty = empty($report->detail_payload) || $report->detail_payload === [];

        if (!$partsEmpty && !$detailEmpty) return;

        $defaults = EngineTemplateRepo::defaults($key);
        if (empty($defaults['manifest'])) return;

        $manifest = $defaults['manifest'];
        $versionStr = trim((string) ($manifest['version'] ?? ''));
        $versionInt = $this->toTemplateVersionInt($versionStr) ?? ($report->template_version ?: 1);

        DB::transaction(function () use ($report, $key, $versionInt, $versionStr, $manifest, $defaults) {
            $r = CcrReport::whereKey($report->id)->lockForUpdate()->first();

            $partsEmpty  = empty($r->parts_payload) || $r->parts_payload === [];
            $detailEmpty = empty($r->detail_payload) || $r->detail_payload === [];

            $r->template_key = $key;
            $r->template_version = $versionInt;

            if ($partsEmpty) {
                $r->parts_payload = $this->sanitizePartsPayload($defaults['parts'] ?? [], $key, $versionStr, $manifest);
            } else {
                $r->parts_payload = $this->sanitizePartsPayload((array) $r->parts_payload, $key, $versionStr, $manifest);
            }

            if ($detailEmpty) {
                $r->detail_payload = $this->sanitizeDetailPayload($defaults['detail'] ?? [], $key, $versionStr, $manifest);
            } else {
                $r->detail_payload = $this->sanitizeDetailPayload((array) $r->detail_payload, $key, $versionStr, $manifest);
            }

            $r->save();
        });
    }

    // ===========================================================
    // SANITIZE: Parts payload (jangan buang field lain)
    // - preserve: meta, rows, styles, notes, dll
    // - clean: angka money jadi digits-only
    // - inject: meta.template_key + meta.template_version (+ optional meta.template)
    // ===========================================================
    private function sanitizePartsPayload(array $payload, ?string $templateKey = null, ?string $templateVersion = null, ?array $manifest = null): array
    {
        if (empty($payload)) return [];

        // preserve root keys
        $clean = $payload;

        // meta
        if (!isset($clean['meta']) || !is_array($clean['meta'])) {
            $clean['meta'] = [];
        }

        $meta = $clean['meta'];

        // normalisasi string
        foreach ($meta as $k => $v) {
            if (is_string($v)) $meta[$k] = trim($v);
        }

        // inject template meta (biar UI & DB sinkron)
        if ($templateKey) {
            $meta['template_key'] = $templateKey;
            if ($templateVersion !== null) {
                $meta['template_version'] = trim((string) $templateVersion);
            }
            // optional meta.template
            if ($manifest && is_array($manifest)) {
                $meta['template'] = [
                    'key'     => (string) ($manifest['key'] ?? $templateKey),
                    'version' => (string) ($manifest['version'] ?? $templateVersion ?? ''),
                    'name'    => (string) ($manifest['name'] ?? ''),
                ];
            }
        }

        // clean meta angka
        $metaMoneyKeys = [
            'footer_total',
            'footer_extended',
        ];
        foreach ($metaMoneyKeys as $mk) {
            if (isset($meta[$mk])) {
                $meta[$mk] = preg_replace('/[^\d]/', '', (string) $meta[$mk]);
            }
        }

        // default mode kalau kosong
        if (isset($meta['footer_total_mode'])) {
            $m = strtolower(trim((string) $meta['footer_total_mode']));
            $meta['footer_total_mode'] = in_array($m, ['auto', 'manual'], true) ? $m : 'auto';
        }
        if (isset($meta['footer_extended_mode'])) {
            $m = strtolower(trim((string) $meta['footer_extended_mode']));
            $meta['footer_extended_mode'] = in_array($m, ['auto', 'manual'], true) ? $m : 'auto';
        }

        $clean['meta'] = $meta;

        // rows
        if (!isset($clean['rows']) || !is_array($clean['rows'])) {
            $clean['rows'] = [];
        }

        $moneyKeys = [
            'purchase_price',
            'sales_price',
            'total',
            'extended_price',
            'unit_price',
            'amount',
            'cost',
            'price',
        ];

        $cleanRows = [];
        foreach ($clean['rows'] as $row) {
            if (!is_array($row)) {
                $cleanRows[] = [];
                continue;
            }

            // buang _id dan key private
            foreach (array_keys($row) as $k) {
                if ($k === '_id' || str_starts_with((string) $k, '_')) {
                    unset($row[$k]);
                }
            }

            foreach ($row as $k => $v) {
                // trim string
                if (is_string($v)) $v = trim($v);

                // qty digits-only
                if ($k === 'qty' || $k === 'quantity') {
                    $row[$k] = preg_replace('/[^\d]/', '', (string) $v);
                    continue;
                }

                // money digits-only
                if (in_array($k, $moneyKeys, true)) {
                    $row[$k] = preg_replace('/[^\d]/', '', (string) $v);
                    continue;
                }

                // percent keep digits + dot
                if (str_contains((string) $k, 'percent') || str_contains((string) $k, 'pct')) {
                    $row[$k] = preg_replace('/[^\d.]/', '', (string) $v);
                    continue;
                }

                $row[$k] = $v;
            }

            $cleanRows[] = $row;
        }

        $clean['rows'] = $cleanRows;

        // sanity: styles/notes harus array
        if (isset($clean['styles']) && !is_array($clean['styles'])) $clean['styles'] = [];
        if (isset($clean['notes']) && !is_array($clean['notes'])) $clean['notes'] = [];

        // anti overwrite: kalau benar-benar kosong (meta kosong + rows kosong + tidak ada keys lain)
        $hasAnyRows = is_array($clean['rows']) && count($clean['rows']) > 0;
        $hasAnyMeta = is_array($clean['meta']) && count(array_filter($clean['meta'], fn ($v) => $v !== '' && $v !== null && $v !== [])) > 0;
        $hasOther   = count(array_diff(array_keys($clean), ['meta', 'rows', 'styles', 'notes'])) > 0;

        if (!$hasAnyRows && !$hasAnyMeta && !$hasOther) {
            return [];
        }

        return $clean;
    }

    // ===========================================================
    // SANITIZE: Detail payload (preserve fields; inject template meta)
    // ===========================================================
    private function sanitizeDetailPayload(array $payload, ?string $templateKey = null, ?string $templateVersion = null, ?array $manifest = null): array
    {
        if (empty($payload)) return [];

        $clean = $payload;

        if (!isset($clean['meta']) || !is_array($clean['meta'])) {
            $clean['meta'] = [];
        }

        $meta = $clean['meta'];
        foreach ($meta as $k => $v) {
            if (is_string($v)) $meta[$k] = trim($v);
        }

        if ($templateKey) {
            $meta['template_key'] = $templateKey;
            if ($templateVersion !== null) {
                $meta['template_version'] = trim((string) $templateVersion);
            }
            if ($manifest && is_array($manifest)) {
                $meta['template'] = [
                    'key'     => (string) ($manifest['key'] ?? $templateKey),
                    'version' => (string) ($manifest['version'] ?? $templateVersion ?? ''),
                    'name'    => (string) ($manifest['name'] ?? ''),
                ];
            }
        }

        $clean['meta'] = $meta;

        return $clean;
    }


    // ===========================================================
    // Tambahin Helper di CcrEngineController
    // ===========================================================
    private function getEngineTemplates(): array
    {
    $list = [];

    // 1) Coba ambil dari Repo kalau ada method list()
    if (method_exists(\App\Support\WorksheetTemplates\EngineTemplateRepo::class, 'list')) {
        try {
            $raw = \App\Support\WorksheetTemplates\EngineTemplateRepo::list();
            $list = is_array($raw) ? $raw : [];
        } catch (\Throwable $e) {
            $list = [];
        }
    }

    // 2) Fallback: registry.php
    if (empty($list)) {
        $path = resource_path('worksheet_templates/engine/registry.php');
        $raw = file_exists($path) ? include $path : [];
        $list = is_array($raw) ? $raw : [];
    }

    // 3) Normalisasi: assoc (key=>meta) → list
    $isAssoc = is_array($list) && array_keys($list) !== range(0, count($list) - 1);
    if ($isAssoc) {
        $tmp = [];
        foreach ($list as $k => $v) {
            if (is_array($v)) {
                $key = $v['key'] ?? (is_string($k) ? $k : '');
                if ($key === '') continue;
                $tmp[] = [
                    'key'     => $key,
                    'name'    => $v['name'] ?? $v['title'] ?? $key,
                    'version' => $v['version'] ?? $v['ver'] ?? 'v1',
                    'notes'   => $v['notes'] ?? '',
                ];
            }
        }
        $list = $tmp;
    } else {
        // kalau sudah list, pastikan shape minimal
        $tmp = [];
        foreach ($list as $v) {
            if (!is_array($v)) continue;
            $key = $v['key'] ?? '';
            if ($key === '') continue;
            $tmp[] = [
                'key'     => $key,
                'name'    => $v['name'] ?? $v['title'] ?? $key,
                'version' => $v['version'] ?? $v['ver'] ?? 'v1',
                'notes'   => $v['notes'] ?? '',
            ];
        }
        $list = $tmp;
    }

    // 4) Pastikan minimal ada engine_blank
    $hasBlank = false;
    foreach ($list as $t) {
        if (($t['key'] ?? '') === 'engine_blank') { $hasBlank = true; break; }
    }
    if (!$hasBlank) {
        $list[] = ['key' => 'engine_blank', 'name' => 'Engine Blank', 'version' => 'v1', 'notes' => 'Template kosong'];
    }

    return $list;
    }



}
