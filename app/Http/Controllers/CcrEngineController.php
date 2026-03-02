<?php

namespace App\Http\Controllers;

use App\Models\CcrItem;
use App\Models\CcrPhoto;
use App\Models\CcrDraft;
use App\Models\CcrReport;
use App\Support\CcrReportService;
use App\Support\CcrWorksheetService;
use App\Support\Inbox;
use App\Support\PayloadSanitizer;
use App\Support\ActivityLogger;
use App\Support\WorksheetTemplates\EngineTemplateRepo;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CcrEngineController extends Controller
{
    public function __construct(
        private readonly PayloadSanitizer $sanitizer,
        private readonly CcrWorksheetService $worksheetService,
        private readonly CcrReportService $reportService,
    ) {}

    // ===========================================================
    // CREATE PAGE
    // ===========================================================
    public function create(Request $request)
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
        $draftSeed = $this->reportService->resolveCreateDraftSeed($request, 'engine');

        return view('engine.create', compact('groupFolders', 'groupedCustomers', 'brands', 'templates', 'draftSeed'));
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
            'inspection_date' => 'required|date',
            'draft_id' => 'nullable|string|max:64',
            'draft_client_key' => 'nullable|string|max:120',

            'template_key' => 'nullable|string',
            'template_version' => 'nullable|integer',
            'expected_upload_count' => 'nullable|integer|min:0',
            'parts_payload' => ['nullable', 'string', function ($attr, $val, $fail) {
                if ($val === null || trim((string) $val) === '') return;
                json_decode((string) $val, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $fail('Parts payload JSON tidak valid.');
                }
            }],
            'detail_payload' => ['nullable', 'string', function ($attr, $val, $fail) {
                if ($val === null || trim((string) $val) === '') return;
                json_decode((string) $val, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $fail('Detail payload JSON tidak valid.');
                }
            }],

            // items + photos (create flow)
            'items' => 'nullable|array',
            'items.*.description' => 'nullable|string',
            'items.*.photos' => 'nullable|array',
            'items.*.photos.*' => 'nullable|image|mimes:jpeg,png,webp|max:8192',
            'new_items' => 'nullable|array',
            'new_items.*.description' => 'nullable|string',
            'new_items.*.photos' => 'nullable|array',
            'new_items.*.photos.*' => 'nullable|image|mimes:jpeg,png,webp|max:8192',
        ]);

        $expectedUploadCount = (int) ($data['expected_upload_count'] ?? 0);
        $actualUploadCount = $this->reportService->countUploadedFilesByKeys($request, ['items', 'new_items']);
        if ($expectedUploadCount > $actualUploadCount) {
            return back()->withInput()->withErrors([
                'photos' => 'Sebagian foto tidak diterima server (dipilih: ' . $expectedUploadCount . ', diterima: ' . $actualUploadCount . '). Kemungkinan batas max_file_uploads / post_max_size masih terlalu kecil.',
            ]);
        }

        try {

        if ($request->has('parts_payload')) {
            $this->sanitizer->ensurePayloadWithinLimit($request->input('parts_payload'), 'parts_payload');
        }
        if ($request->has('detail_payload')) {
            $this->sanitizer->ensurePayloadWithinLimit($request->input('detail_payload'), 'detail_payload');
        }

        // Robust parsing: create bisa kirim `items[...]`; fallback ke `new_items[...]` jika flow lama/tercampur.
        $itemsInput = $request->input('items');
        $itemFilesInput = $request->file('items', []);
        if (!is_array($itemsInput) || count($itemsInput) === 0) {
            $fallbackItems = $request->input('new_items');
            if (is_array($fallbackItems) && count($fallbackItems) > 0) {
                $itemsInput = $fallbackItems;
                $itemFilesInput = $request->file('new_items', []);
            } else {
                $itemsInput = [];
            }
        }

        $hasAnyMeaningfulItem = false;
        foreach ($itemsInput as $index => $itemData) {
            $desc = trim((string) data_get($itemData, 'description', ''));
            $uploadedPhotos = $this->reportService->normalizeUploadedImageFiles(
                data_get($itemFilesInput, $index . '.photos')
            );
            if ($desc !== '' || !empty($uploadedPhotos)) {
                $hasAnyMeaningfulItem = true;
                break;
            }
        }
        if (!$hasAnyMeaningfulItem) {
            return back()->withInput()->withErrors([
                'items' => 'Minimal 1 item temuan (deskripsi atau foto) wajib diisi.',
            ]);
        }

        $finalDate = Carbon::parse($data['inspection_date'])->format('Y-m-d');

        $templateKey = trim((string) ($data['template_key'] ?? 'engine_blank'));
        if ($templateKey === '') {
            $templateKey = 'engine_blank';
        }
        $templateVersionInt = (int) ($data['template_version'] ?? 1);
        if ($templateVersionInt < 1) {
            $templateVersionInt = 1;
        }

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

        $draftModel = $this->reportService->resolveCreateDraftModel(
            'engine',
            (int) auth()->id(),
            $data['draft_id'] ?? null,
            $data['draft_client_key'] ?? null
        );

        $draftParts = $draftModel && is_array($draftModel->parts_payload) ? $draftModel->parts_payload : [];
        $draftDetail = $draftModel && is_array($draftModel->detail_payload) ? $draftModel->detail_payload : [];

        if (!empty($draftParts)) {
            $partsPayload = $this->sanitizer->mergeCreatePartsPayload((array) $partsPayload, (array) $draftParts);
        }
        if (!empty($draftDetail)) {
            $detailPayload = $this->sanitizer->mergeCreateDetailPayload((array) $detailPayload, (array) $draftDetail);
        }

        if ($this->sanitizer->isLikelyIncompletePartsPayload((array) $partsPayload) && !$this->sanitizer->isLikelyIncompletePartsPayload((array) $draftParts)) {
            $partsPayload = (array) $draftParts;
        }
        if ($this->sanitizer->isLikelyIncompleteDetailPayload((array) $detailPayload) && !$this->sanitizer->isLikelyIncompleteDetailPayload((array) $draftDetail)) {
            $detailPayload = (array) $draftDetail;
        }

        [$payloadTemplateKey, $payloadVersionStr, $payloadVersionInt] = $this->resolveTemplateFromPayload((array) $partsPayload);

        // fallback defaults
        if ($payloadTemplateKey) {
            $templateKey = $payloadTemplateKey;
            if ($payloadVersionInt !== null) {
                $templateVersionInt = max(1, (int) $payloadVersionInt);
            } elseif (trim((string) $payloadVersionStr) !== '') {
                $parsed = $this->sanitizer->toTemplateVersionInt($payloadVersionStr);
                if ($parsed !== null) {
                    $templateVersionInt = max(1, (int) $parsed);
                }
            }
        }

        $needsPartsDefaults = $this->sanitizer->isLikelyIncompletePartsPayload((array) $partsPayload);
        $needsDetailDefaults = $this->sanitizer->isLikelyIncompleteDetailPayload((array) $detailPayload);
        if ($needsPartsDefaults || $needsDetailDefaults) {
            // ✅ pakai repo yang kamu import: App\Support\WorksheetTemplates\EngineTemplateRepo
            if (method_exists(EngineTemplateRepo::class, 'loadDefaults')) {
                $repo = app(EngineTemplateRepo::class);
                $defaults = $repo->loadDefaults($templateKey, $templateVersionInt);
                if ($needsPartsDefaults && !empty($defaults['parts_defaults'])) $partsPayload = $defaults['parts_defaults'];
                if ($needsDetailDefaults && !empty($defaults['detail_defaults'])) $detailPayload = $defaults['detail_defaults'];
            } else {
                $defaults = EngineTemplateRepo::defaults($templateKey);
                if ($needsPartsDefaults && !empty($defaults['parts'])) $partsPayload = $defaults['parts'];
                if ($needsDetailDefaults && !empty($defaults['detail'])) $detailPayload = $defaults['detail'];
            }
        }

        if (!isset($detailPayload['totals'])) $detailPayload['totals'] = [];
        if (!isset($detailPayload['totals']['sales_tax_percent'])) {
            $detailPayload['totals']['sales_tax_percent'] = 11;
        }
        if (isset($detailPayload['totals']['tax_percent']) && !isset($detailPayload['totals']['sales_tax_percent'])) {
            $detailPayload['totals']['sales_tax_percent'] = $detailPayload['totals']['tax_percent'];
        }

        $manifest = EngineTemplateRepo::manifest($templateKey);
        $versionStr = is_array($manifest) ? trim((string) ($manifest['version'] ?? '')) : '';
        if ($versionStr === '') {
            $versionStr = 'v' . $templateVersionInt;
        }

        $partsPayload = $this->sanitizer->sanitizePartsPayload((array) $partsPayload, $templateKey, $versionStr, $manifest);
        $detailPayload = $this->sanitizer->sanitizeDetailPayload((array) $detailPayload, $templateKey, $versionStr, $manifest);

        $partsPayloadTs = $this->sanitizer->payloadTimestampFromArray((array) $partsPayload);
        $detailPayloadTs = $this->sanitizer->payloadTimestampFromArray((array) $detailPayload);
        $partsPayloadRev = $partsPayloadTs ?? (!empty($partsPayload) ? 1 : null);
        $detailPayloadRev = $detailPayloadTs ?? (!empty($detailPayload) ? 1 : null);

        $report = CcrReport::create([
            'type' => 'engine',
            'created_by' => auth()->id(),
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
            'parts_payload_rev' => $partsPayloadRev,
            'detail_payload_rev' => $detailPayloadRev,
        ]);

        // Simpan item + foto dari create (parity dengan flow seat).
        $date = Carbon::parse($report->inspection_date)->format('Y-m-d');
        $safe = substr(preg_replace('/[^A-Za-z0-9\- ]/', '', (string) $report->component), 0, 30);
        $folder = "synology/CCR-{$report->group_folder}-{$date}-{$safe}";
        Storage::disk('public')->makeDirectory("$folder/photos");

        foreach ($itemsInput as $index => $itemData) {
            $desc = trim((string) data_get($itemData, 'description', ''));
            $uploadedPhotos = $this->reportService->normalizeUploadedImageFiles(
                data_get($itemFilesInput, $index . '.photos')
            );
            if ($desc === '' && empty($uploadedPhotos)) {
                continue;
            }

            $item = CcrItem::create([
                'ccr_report_id' => $report->id,
                'description' => $desc,
            ]);

            foreach ($uploadedPhotos as $photo) {
                if (!$photo instanceof UploadedFile || !$photo->isValid()) continue;
                $path = $photo->store("$folder/photos", 'public');
                CcrPhoto::create([
                    'ccr_item_id' => $item->id,
                    'path' => $path,
                ]);
            }
        }

        $userId = (int) auth()->id();
        $draftClientKey = trim((string) ($data['draft_client_key'] ?? ''));

        // Cegah race: request autosave yang telat (in-flight) tidak boleh bikin draft baru lagi.
        if ($draftClientKey !== '') {
            Cache::put($this->reportService->draftFinalizedCacheKey('engine', $userId, $draftClientKey), true, now()->addSeconds(120));
        }

        // Setelah report final tersimpan, bersihkan draft engine user agar popup draft tidak muncul lagi.
        CcrDraft::query()
            ->where('user_id', $userId)
            ->where('type', 'engine')
            ->delete();

        ActivityLogger::log('create', $report, ['type' => 'engine', 'component' => $report->component]);

        return redirect()->route('ccr.manage.engine')
            ->with('success', 'CCR Engine berhasil dibuat.')
            ->with('clear_create_draft', 'engine');

        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            report($e);
            return back()->withInput()->with('error', 'Terjadi kesalahan saat menyimpan laporan. Silakan coba lagi.');
        }
    }

    // ===========================================================
    // EDIT PAGE
    // ===========================================================
    public function edit($id)
    {
        $report = CcrReport::with('items.photos')->findOrFail($id);
        $groupFolders = ['Engine', 'Transmission', 'Radiator', 'Cabin', 'After Cooler'];

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

        // Edit lock: check if someone else is editing
        $lockedBy = null;
        $lock = EditLockController::checkLock((int) $report->id);
        if ($lock && (int) ($lock['user_id'] ?? 0) !== (int) auth()->id()) {
            $lockedBy = $lock['user_name'] ?? 'User lain';
        } else {
            EditLockController::acquireLock((int) $report->id);
        }

        return view('engine.edit-engine', compact('report', 'brands', 'groupedCustomers', 'templates', 'groupFolders', 'lockedBy'));
    }

    // ===========================================================
    // UPDATE HEADER + ITEM LAMA + ITEM BARU
    // ===========================================================
    public function updateHeader(Request $request, $id)
    {
        $report = CcrReport::with('items.photos')->findOrFail($id);
        $this->authorize('update', $report);

        $validator = Validator::make($request->all(), [
            'group_folder'    => 'required|string',
            'component'       => 'required|string',
            'inspection_date' => 'required|date',
            'expected_upload_count' => 'nullable|integer|min:0',
            'parts_payload_rev' => 'nullable|integer|min:0',
            'detail_payload_rev' => 'nullable|integer|min:0',

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
            'items.*.photos.*'      => 'nullable|image|mimes:jpeg,png,webp|max:8192',
            'items.*.delete_photos' => 'nullable|array',
            'deleted_items'         => 'nullable|array',
            'deleted_items.*'       => 'nullable|integer',

            'new_items'               => 'nullable|array',
            'new_items.*.description' => 'nullable|string',
            'new_items.*.photos'      => 'nullable|array',
            'new_items.*.photos.*'    => 'nullable|image|mimes:jpeg,png,webp|max:8192',
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
        $expectedUploadCount = (int) ($data['expected_upload_count'] ?? 0);
        $actualUploadCount = $this->reportService->countUploadedFilesByKeys($request, ['items', 'new_items']);
        if ($expectedUploadCount > $actualUploadCount) {
            return back()->withInput()->withErrors([
                'photos' => 'Sebagian foto tidak diterima server (dipilih: ' . $expectedUploadCount . ', diterima: ' . $actualUploadCount . '). Kemungkinan batas max_file_uploads / post_max_size masih terlalu kecil.',
            ]);
        }

        try {

        $deletedItemIds = collect($data['deleted_items'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();
        $deletedItemIdList = $deletedItemIds->all();

        $changed = false;
        $payloadChanged = false;
        $staleSections = [];

        // tanggal + jam WITA
        $finalDate = Carbon::parse($data['inspection_date'])
            ->setTimeFromTimeString(now('Asia/Makassar')->format('H:i'));

        $hasPartsPayload = $request->has('parts_payload');
        $hasDetailPayload = $request->has('detail_payload');
        $partsRawInput = $hasPartsPayload ? $request->input('parts_payload') : null;
        $detailRawInput = $hasDetailPayload ? $request->input('detail_payload') : null;

        if (!$hasPartsPayload || !$hasDetailPayload) {
            return back()->withInput()->withErrors([
                'worksheet' => 'Payload worksheet tidak lengkap. Simpan dibatalkan untuk mencegah data style/note/tools hilang. Coba refresh lalu simpan ulang.',
            ]);
        }

        if ($hasPartsPayload) {
            $this->sanitizer->ensurePayloadWithinLimit($partsRawInput, 'parts_payload');
        }
        if ($hasDetailPayload) {
            $this->sanitizer->ensurePayloadWithinLimit($detailRawInput, 'detail_payload');
        }

        $rawParts  = $this->sanitizer->decodeJsonInput($partsRawInput);
        $rawDetail = $this->sanitizer->decodeJsonInput($detailRawInput);

        // Final submit dari form edit harus authoritative:
        // kalau payload tidak membawa ts, inject ts server agar tidak ter-drop karena rev stale.
        $serverSubmitTs = (int) floor(microtime(true) * 1000);
        if ($hasPartsPayload && !empty($rawParts) && $this->sanitizer->payloadTimestampFromArray($rawParts) === null) {
            $rawParts['ts'] = $serverSubmitTs;
        }
        if ($hasDetailPayload && !empty($rawDetail) && $this->sanitizer->payloadTimestampFromArray($rawDetail) === null) {
            $rawDetail['ts'] = $serverSubmitTs;
        }

        [$templateKey, $templateVersionStr, $templateVersionInt, $manifest] = $this->resolveTemplateFromPayload($rawParts);

        $partsClientRev = $this->sanitizer->parsePayloadRevision($request->input('parts_payload_rev'));
        $detailClientRev = $this->sanitizer->parsePayloadRevision($request->input('detail_payload_rev'));

        DB::transaction(function () use (
            $report,
            $data,
            $request,
            $finalDate,
            $templateKey,
            $templateVersionInt,
            $templateVersionStr,
            $manifest,
            $rawParts,
            $rawDetail,
            $hasPartsPayload,
            $hasDetailPayload,
            $partsClientRev,
            $detailClientRev,
            &$changed,
            &$payloadChanged,
            &$staleSections
        ) {
            $locked = CcrReport::query()->whereKey($report->id)->lockForUpdate()->firstOrFail();

            if ($hasPartsPayload) {
                $newParts = $this->sanitizer->sanitizePartsPayload($rawParts, $templateKey, $templateVersionStr, $manifest);
                if (!empty($newParts)) {
                    $partsState = $this->worksheetService->applyWorksheetPayloadSection($locked, 'parts', $newParts, $partsClientRev);
                    if ($partsState['stale']) $staleSections[] = 'parts';
                    if ($partsState['payload_changed']) $payloadChanged = true;
                }
            }

            if ($hasDetailPayload) {
                $newDetail = $this->sanitizer->sanitizeDetailPayload($rawDetail, $templateKey, $templateVersionStr, $manifest);
                if (!empty($newDetail)) {
                    $detailState = $this->worksheetService->applyWorksheetPayloadSection($locked, 'detail', $newDetail, $detailClientRev);
                    if ($detailState['stale']) $staleSections[] = 'detail';
                    if ($detailState['payload_changed']) $payloadChanged = true;
                }
            }

            // update template columns kalau payload mengandung template meta
            if ($templateKey) {
                $locked->template_key = $templateKey;
                if ($templateVersionInt !== null) {
                    $locked->template_version = $templateVersionInt;
                }
            }

            // Update header report
            $locked->fill([
                'group_folder'    => $data['group_folder'],
                'component'       => $data['component'],
                'make'            => $request->make,
                'model'           => $request->model,
                'sn'              => $request->sn,
                'smu'             => $request->smu,
                'customer'        => $request->customer,
                'inspection_date' => $finalDate,
            ]);

            if ($locked->isDirty()) {
                $locked->save();
                $changed = true;
            }
        });

        $staleSections = array_values(array_unique($staleSections));
        $report = CcrReport::with('items.photos')->findOrFail($id);

        // folder photos
        $date = Carbon::parse($report->inspection_date)->format('Y-m-d');
        $safe = substr(preg_replace('/[^A-Za-z0-9\- ]/', '', $report->component), 0, 30);
        $folder = "synology/CCR-{$report->group_folder}-{$date}-{$safe}";
        Storage::disk('public')->makeDirectory("$folder/photos");
        $uploadedExistingItems = is_array($request->file('items')) ? $request->file('items') : [];
        $uploadedNewItems = is_array($request->file('new_items')) ? $request->file('new_items') : [];

        // ===== DELETE OLD ITEMS (inline delete dari form edit, tanpa refresh) =====
        if (!empty($deletedItemIdList)) {
            $itemsToDelete = CcrItem::with('photos')
                ->where('ccr_report_id', $report->id)
                ->whereIn('id', $deletedItemIdList)
                ->get();

            foreach ($itemsToDelete as $itemToDelete) {
                foreach ($itemToDelete->photos as $photo) {
                    if (!empty($photo->path)) {
                        Storage::disk('public')->delete($photo->path);
                    }
                }
                $itemToDelete->delete();
                $changed = true;
            }
        }

        // ===== UPDATE OLD ITEMS =====
        if (!empty($data['items'])) {
            foreach ($data['items'] as $itemId => $input) {
                if (in_array((int) $itemId, $deletedItemIdList, true)) {
                    continue;
                }
                $item = CcrItem::with('photos')
                    ->where('ccr_report_id', $report->id)
                    ->whereKey((int) $itemId)
                    ->first();
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
                        $photo = CcrPhoto::query()
                            ->where('ccr_item_id', $item->id)
                            ->whereKey((int) $photoId)
                            ->first();
                        if ($photo) {
                            Storage::disk('public')->delete($photo->path);
                            $photo->delete();
                            $changed = true;
                        }
                    }
                }

                // upload new photos
                $uploadedPhotos = $this->reportService->normalizeUploadedImageFiles(
                    data_get($uploadedExistingItems, $itemId . '.photos')
                );
                foreach ($uploadedPhotos as $photo) {
                    $path = $photo->store("$folder/photos", 'public');
                    CcrPhoto::create([
                        'ccr_item_id' => $item->id,
                        'path'        => $path,
                    ]);
                    $changed = true;
                }
            }
        }

        // ===== CREATE NEW ITEMS =====
        if (!empty($data['new_items'])) {
            foreach ($data['new_items'] as $index => $input) {
                $hasDesc  = trim((string) ($input['description'] ?? ''));
                $newItemPhotos = $this->reportService->normalizeUploadedImageFiles(
                    data_get($uploadedNewItems, $index . '.photos')
                );
                $hasPhoto = !empty($newItemPhotos);

                if ($hasDesc === '' && !$hasPhoto) continue;

                $item = CcrItem::create([
                    'ccr_report_id' => $report->id,
                    'description'   => $hasDesc ?? '',
                ]);
                $changed = true;

                foreach ($newItemPhotos as $photo) {
                    $path = $photo->store("$folder/photos", 'public');
                    CcrPhoto::create([
                        'ccr_item_id' => $item->id,
                        'path'        => $path,
                    ]);
                    $changed = true;
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

        if ($request->boolean('preview_after_save')) {
            return redirect()->route('engine.preview', $report->id);
        }

        ActivityLogger::log('update', $report, ['type' => 'engine', 'component' => $report->component]);

        $redirect = redirect()->route('ccr.manage.engine')
            ->with('success', 'Perubahan CCR Engine berhasil disimpan!');

        if (!empty($staleSections)) {
            $redirect->with('warning', 'Sebagian autosave lama diabaikan (stale): ' . implode(', ', $staleSections) . '. Data terbaru tetap dipakai.');
        }

        return $redirect;

        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            report($e);
            return back()->withInput()->with('error', 'Terjadi kesalahan saat menyimpan perubahan. Silakan coba lagi.');
        }
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
        $versionInt = $this->sanitizer->toTemplateVersionInt($versionStr);
        $detail = $defaults['detail'] ?? [];
        if (isset($detail['totals']['tax_percent']) && !isset($detail['totals']['sales_tax_percent'])) {
            $detail['totals']['sales_tax_percent'] = $detail['totals']['tax_percent'];
        }

        // pastikan payload punya meta template juga (biar UI langsung sinkron)
        $parts  = $this->sanitizer->sanitizePartsPayload($defaults['parts'] ?? [], $key, $versionStr, $manifest);
        $detail = $this->sanitizer->sanitizeDetailPayload($defaults['detail'] ?? [], $key, $versionStr, $manifest);

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
        $versionInt = $this->sanitizer->toTemplateVersionInt($versionStr) ?? 1;

        DB::transaction(function () use ($report, $key, $versionInt, $versionStr, $manifest, $defaults, $replace) {
            $r = CcrReport::whereKey($report->id)->lockForUpdate()->first();

            $r->template_key = $key;
            $r->template_version = $versionInt;

            $partsEmpty  = empty($r->parts_payload) || $r->parts_payload === [];
            $detailEmpty = empty($r->detail_payload) || $r->detail_payload === [];

            if ($replace || $partsEmpty) {
                $r->parts_payload = $this->sanitizer->sanitizePartsPayload($defaults['parts'] ?? [], $key, $versionStr, $manifest);
            } else {
                $r->parts_payload = $this->sanitizer->sanitizePartsPayload((array) $r->parts_payload, $key, $versionStr, $manifest);
            }

            if ($replace || $detailEmpty) {
                $r->detail_payload = $this->sanitizer->sanitizeDetailPayload($defaults['detail'] ?? [], $key, $versionStr, $manifest);
            } else {
                $r->detail_payload = $this->sanitizer->sanitizeDetailPayload((array) $r->detail_payload, $key, $versionStr, $manifest);
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

    if ($hasParts) {
        $this->sanitizer->ensurePayloadWithinLimit($partsRaw, 'parts_payload');
        if ($this->sanitizer->isInvalidJsonInput($partsRaw)) {
            return response()->json([
                'ok' => false,
                'message' => 'Invalid parts_payload JSON',
            ], 422);
        }
    }

    if ($hasDetail) {
        $this->sanitizer->ensurePayloadWithinLimit($detailRaw, 'detail_payload');
        if ($this->sanitizer->isInvalidJsonInput($detailRaw)) {
            return response()->json([
                'ok' => false,
                'message' => 'Invalid detail_payload JSON',
            ], 422);
        }
    }

    $partsClientRev = $this->sanitizer->parsePayloadRevision($request->input('parts_payload_rev'));
    $detailClientRev = $this->sanitizer->parsePayloadRevision($request->input('detail_payload_rev'));

    $result = DB::transaction(function () use (
        $id,
        $hasParts,
        $hasDetail,
        $partsRaw,
        $detailRaw,
        $partsClientRev,
        $detailClientRev
    ) {
        $report = CcrReport::query()->whereKey($id)->lockForUpdate()->firstOrFail();

        $dirty = false;
        $payloadChanged = false;
        $stale = [
            'parts' => false,
            'detail' => false,
        ];

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

        $ctxVersionInt = (int) ($report->template_version ?: ($this->sanitizer->toTemplateVersionInt($ctxVersionStr) ?: 1));

        // ============================
        // PARTS autosave
        // ============================
        if ($hasParts) {
            $rawPartsArr = $this->sanitizer->decodeJsonInput($partsRaw);

            // coba ambil template meta dari payload parts (kalau user baru pilih template)
            [$pKey, $pVerStr, $pVerInt, $pManifest] = $this->resolveTemplateFromPayload($rawPartsArr);

            if ($pKey) {
                $ctxKey = $pKey;
                $ctxManifest = is_array($pManifest) ? $pManifest : ($ctxKey ? EngineTemplateRepo::manifest($ctxKey) : null);

                if (trim((string) $pVerStr) !== '') {
                    $ctxVersionStr = trim((string) $pVerStr);
                } elseif (is_array($ctxManifest) && trim((string) ($ctxManifest['version'] ?? '')) !== '') {
                    $ctxVersionStr = trim((string) $ctxManifest['version']);
                }

                $ctxVersionInt = (int) (($pVerInt !== null) ? $pVerInt : ($this->sanitizer->toTemplateVersionInt($ctxVersionStr) ?: $ctxVersionInt));
            }

            $cleanParts = $this->sanitizer->sanitizePartsPayload($rawPartsArr, $ctxKey, $ctxVersionStr, $ctxManifest);

            if (!empty($cleanParts)) {
                $partsState = $this->worksheetService->applyWorksheetPayloadSection($report, 'parts', $cleanParts, $partsClientRev);
                $stale['parts'] = (bool) ($partsState['stale'] ?? false);
                if (!empty($partsState['saved'])) $dirty = true;
                if (!empty($partsState['payload_changed'])) $payloadChanged = true;
            }
        }

        // ============================
        // DETAIL autosave
        // ============================
        if ($hasDetail) {
            $rawDetailArr = $this->sanitizer->decodeJsonInput($detailRaw);

            if (isset($rawDetailArr['totals']['tax_percent']) && !isset($rawDetailArr['totals']['sales_tax_percent'])) {
                $rawDetailArr['totals']['sales_tax_percent'] = $rawDetailArr['totals']['tax_percent'];
            }

            $cleanDetail = $this->sanitizer->sanitizeDetailPayload($rawDetailArr, $ctxKey, $ctxVersionStr, $ctxManifest);

            if (!empty($cleanDetail)) {
                $detailState = $this->worksheetService->applyWorksheetPayloadSection($report, 'detail', $cleanDetail, $detailClientRev);
                $stale['detail'] = (bool) ($detailState['stale'] ?? false);
                if (!empty($detailState['saved'])) $dirty = true;
                if (!empty($detailState['payload_changed'])) $payloadChanged = true;
            }
        }

        // ============================
        // Update kolom template_key/template_version jika kita punya context
        // ============================
        if ($ctxKey && (
            (string) ($report->template_key ?? '') !== (string) $ctxKey
            || (int) ($report->template_version ?? 0) !== (int) $ctxVersionInt
        )) {
            $report->template_key = $ctxKey;
            $report->template_version = $ctxVersionInt;
            $dirty = true;
        }

        if ($dirty) {
            // export Word harus regenerasi kalau payload berubah
            if ($payloadChanged) {
                $report->docx_generated_at = null;
            }
            $report->save();
        }

        return [
            'ok' => true,
            'saved' => $dirty,
            'payload_changed' => $payloadChanged,
            'stale' => $stale,
            'saved_at' => now()->toIso8601String(),
            'template_key' => $ctxKey,
            'template_version' => $ctxVersionInt,
            'parts_payload_rev' => (int) ($report->parts_payload_rev ?? 0),
            'detail_payload_rev' => (int) ($report->detail_payload_rev ?? 0),
        ];
    });

    return response()->json($result);
}

    // ===========================================================
    // DELETE ITEM
    // ===========================================================
    public function deleteItem(Request $request, CcrItem $item)
    {
        $report = CcrReport::find($item->ccr_report_id);
        if ($report) $this->authorize('delete', $report);

        $item->loadMissing(['report', 'photos']);

        $reportId = (int) $item->ccr_report_id;
        $partsClientRev = $this->sanitizer->parsePayloadRevision($request->input('parts_payload_rev'));
        $detailClientRev = $this->sanitizer->parsePayloadRevision($request->input('detail_payload_rev'));
        if (($request->expectsJson() || $request->ajax()) && ($partsClientRev === null || $detailClientRev === null)) {
            return response()->json([
                'ok' => false,
                'message' => 'Payload revision wajib dikirim (parts/detail) untuk aksi hapus item.',
            ], 422);
        }

        $mutation = DB::transaction(function () use ($reportId, $item, $partsClientRev, $detailClientRev) {
            $report = CcrReport::query()->whereKey($reportId)->lockForUpdate()->firstOrFail();
            $staleSections = $this->worksheetService->staleSectionsFromClientRevision($report, $partsClientRev, $detailClientRev);
            if (!empty($staleSections)) {
                return [
                    'ok' => false,
                    'stale' => true,
                    'sections' => $staleSections,
                    'parts_payload_rev' => (int) ($report->parts_payload_rev ?? 0),
                    'detail_payload_rev' => (int) ($report->detail_payload_rev ?? 0),
                ];
            }

            $lockedItem = CcrItem::query()
                ->where('ccr_report_id', $reportId)
                ->whereKey((int) $item->id)
                ->lockForUpdate()
                ->first();

            if (!$lockedItem) {
                return [
                    'ok' => false,
                    'missing' => true,
                    'parts_payload_rev' => (int) ($report->parts_payload_rev ?? 0),
                    'detail_payload_rev' => (int) ($report->detail_payload_rev ?? 0),
                ];
            }

            $lockedItem->loadMissing('photos');
            foreach ($lockedItem->photos as $p) {
                Storage::disk('public')->delete($p->path);
                $p->delete();
            }
            $lockedItem->delete();

            $report->docx_generated_at = null;
            $report->touch();

            return [
                'ok' => true,
                'deleted' => true,
                'item_id' => (int) $item->id,
                'parts_payload_rev' => (int) ($report->parts_payload_rev ?? 0),
                'detail_payload_rev' => (int) ($report->detail_payload_rev ?? 0),
            ];
        });

        if (!empty($mutation['stale'])) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Data kamu sudah stale. Refresh halaman sebelum hapus item.',
                    'stale' => true,
                    'sections' => $mutation['sections'] ?? [],
                    'parts_payload_rev' => (int) ($mutation['parts_payload_rev'] ?? 0),
                    'detail_payload_rev' => (int) ($mutation['detail_payload_rev'] ?? 0),
                ], 409);
            }

            return back()->with('warning', 'Data sudah berubah oleh sesi lain. Refresh dulu sebelum hapus item.');
        }

        if (!empty($mutation['missing'])) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'ok' => true,
                    'deleted' => true,
                    'item_id' => (int) $item->id,
                    'parts_payload_rev' => (int) ($mutation['parts_payload_rev'] ?? 0),
                    'detail_payload_rev' => (int) ($mutation['detail_payload_rev'] ?? 0),
                ]);
            }

            return redirect()->route('engine.edit', $reportId);
        }

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'ok' => true,
                'deleted' => true,
                'item_id' => (int) ($mutation['item_id'] ?? $item->id),
                'parts_payload_rev' => (int) ($mutation['parts_payload_rev'] ?? 0),
                'detail_payload_rev' => (int) ($mutation['detail_payload_rev'] ?? 0),
            ]);
        }

        return redirect()->route('engine.edit', $reportId);
    }

    // ===========================================================
    // DELETE PHOTO
    // ===========================================================
    public function deletePhoto(Request $request, CcrPhoto $photo)
    {
        $report = $photo->ccrItem->ccrReport ?? null;
        if ($report) $this->authorize('delete', $report);

        $photo->loadMissing('item');

        $reportId = (int) $photo->item->ccr_report_id;
        $partsClientRev = $this->sanitizer->parsePayloadRevision($request->input('parts_payload_rev'));
        $detailClientRev = $this->sanitizer->parsePayloadRevision($request->input('detail_payload_rev'));
        if (($request->expectsJson() || $request->ajax()) && ($partsClientRev === null || $detailClientRev === null)) {
            return response()->json([
                'ok' => false,
                'message' => 'Payload revision wajib dikirim (parts/detail) untuk aksi hapus foto.',
            ], 422);
        }

        $mutation = DB::transaction(function () use ($reportId, $photo, $partsClientRev, $detailClientRev) {
            $report = CcrReport::query()->whereKey($reportId)->lockForUpdate()->firstOrFail();
            $staleSections = $this->worksheetService->staleSectionsFromClientRevision($report, $partsClientRev, $detailClientRev);
            if (!empty($staleSections)) {
                return [
                    'ok' => false,
                    'stale' => true,
                    'sections' => $staleSections,
                    'parts_payload_rev' => (int) ($report->parts_payload_rev ?? 0),
                    'detail_payload_rev' => (int) ($report->detail_payload_rev ?? 0),
                ];
            }

            $lockedPhoto = CcrPhoto::query()
                ->whereKey((int) $photo->id)
                ->lockForUpdate()
                ->first();

            if (!$lockedPhoto) {
                return [
                    'ok' => false,
                    'missing' => true,
                    'parts_payload_rev' => (int) ($report->parts_payload_rev ?? 0),
                    'detail_payload_rev' => (int) ($report->detail_payload_rev ?? 0),
                ];
            }

            Storage::disk('public')->delete($lockedPhoto->path);
            $lockedPhoto->delete();

            $report->docx_generated_at = null;
            $report->touch();

            return [
                'ok' => true,
                'deleted' => true,
                'parts_payload_rev' => (int) ($report->parts_payload_rev ?? 0),
                'detail_payload_rev' => (int) ($report->detail_payload_rev ?? 0),
            ];
        });

        if (!empty($mutation['stale'])) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Data kamu sudah stale. Refresh halaman sebelum hapus foto.',
                    'stale' => true,
                    'sections' => $mutation['sections'] ?? [],
                    'parts_payload_rev' => (int) ($mutation['parts_payload_rev'] ?? 0),
                    'detail_payload_rev' => (int) ($mutation['detail_payload_rev'] ?? 0),
                ], 409);
            }

            return back()->with('warning', 'Data sudah berubah oleh sesi lain. Refresh dulu sebelum hapus foto.');
        }

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'ok' => true,
                'deleted' => true,
                'parts_payload_rev' => (int) ($mutation['parts_payload_rev'] ?? 0),
                'detail_payload_rev' => (int) ($mutation['detail_payload_rev'] ?? 0),
            ]);
        }

        return redirect()->route('engine.edit', $reportId)
            ->with('success', 'Foto berhasil dihapus.');
    }

    // ===========================================================
    // DELETE MULTIPLE TERPILIH
    // ===========================================================
    public function deleteMultiple(Request $request)
    {
        $ids = collect($request->input('ids', []))
            ->map(fn($id) => (int) $id)
            ->filter(fn($id) => $id > 0)
            ->unique()->values()->all();

        if (empty($ids)) {
            return back()->with('warning', 'Tidak ada item yang dipilih.');
        }

        $reports = CcrReport::with('items.photos')
            ->where('type', 'engine')
            ->whereIn('id', $ids)->get();

        foreach ($reports as $r) {
            // Hapus file foto dari storage sebelum soft delete
            foreach ($r->items as $item) {
                foreach ($item->photos as $photo) {
                    if (!empty($photo->path)) {
                        Storage::disk('public')->delete($photo->path);
                    }
                }
            }
            $r->purge_at = now()->addDays(7);
            $r->save();
            $r->delete();
        }

        return back()->with('success', count($reports) . ' CCR Engine dipindahkan ke Sampah (7 hari).');
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
        if (($report->type ?? null) !== 'engine') {
            abort(404);
        }
        $this->authorize('submit', $report);

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

        ActivityLogger::log($resubmit ? 'resubmit' : 'submit', $report, ['type' => 'engine', 'component' => $report->component]);

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
                $versionInt = $this->sanitizer->toTemplateVersionInt($versionStr);
            }
        }

        return [$key, $versionStr, $versionInt, $manifest];
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
        $versionInt = $this->sanitizer->toTemplateVersionInt($versionStr) ?? ($report->template_version ?: 1);

        DB::transaction(function () use ($report, $key, $versionInt, $versionStr, $manifest, $defaults) {
            $r = CcrReport::whereKey($report->id)->lockForUpdate()->first();

            $partsEmpty  = empty($r->parts_payload) || $r->parts_payload === [];
            $detailEmpty = empty($r->detail_payload) || $r->detail_payload === [];

            $r->template_key = $key;
            $r->template_version = $versionInt;

            if ($partsEmpty) {
                $r->parts_payload = $this->sanitizer->sanitizePartsPayload($defaults['parts'] ?? [], $key, $versionStr, $manifest);
            } else {
                $r->parts_payload = $this->sanitizer->sanitizePartsPayload((array) $r->parts_payload, $key, $versionStr, $manifest);
            }

            if ($detailEmpty) {
                $r->detail_payload = $this->sanitizer->sanitizeDetailPayload($defaults['detail'] ?? [], $key, $versionStr, $manifest);
            } else {
                $r->detail_payload = $this->sanitizer->sanitizeDetailPayload((array) $r->detail_payload, $key, $versionStr, $manifest);
            }

            $r->save();
        });
    }

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
