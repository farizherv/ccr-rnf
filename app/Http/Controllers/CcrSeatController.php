<?php

namespace App\Http\Controllers;

use App\Models\CcrItem;
use App\Models\CcrPhoto;
use App\Models\CcrDraft;
use App\Models\CcrReport;
use App\Models\ItemMaster;
use App\Support\CcrReportService;
use App\Support\CcrWorksheetService;
use App\Support\Inbox;
use App\Support\PayloadSanitizer;
use App\Support\ActivityLogger;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CcrSeatController extends Controller
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
            if (str_starts_with($c, 'CV')) {
                $groupedCustomers['CV'][] = $c;
            } elseif (str_starts_with($c, 'PT')) {
                $groupedCustomers['PT'][] = $c;
            } else {
                $groupedCustomers['Other'][] = $c;
            }
        }

        // ✅ Seat parity: templates list (kalau SeatTemplateRepo/seat registry sudah ada)
        $templates = $this->getSeatTemplates();
        $seatItemsRows = $this->defaultSeatItemsRows();
        $draftSeed = $this->reportService->resolveCreateDraftSeed($request, 'seat');

        return view('seat.create', compact('groupFolders', 'groupedCustomers', 'brands', 'templates', 'seatItemsRows', 'draftSeed'));
    }

    // ===========================================================
    // STORE (HEADER + ITEM BARU) + SEED WORKSHEET PAYLOAD
    // ===========================================================
    public function store(Request $request)
    {
        $data = $request->validate([
            'group_folder'        => 'required|string',
            'component'           => 'required|string',
            'unit'                => 'nullable|string',
            'wo_pr'               => 'nullable|string',
            'customer'            => 'nullable|string',
            'make'                => 'nullable|string',
            'model'               => 'nullable|string',
            'sn'                  => 'nullable|string',
            'smu'                 => 'nullable|string',
            'inspection_date'     => 'required|date',

            // worksheet payload (seat parity)
            'template_key'        => 'nullable|string',
            'template_version'    => 'nullable|integer',
            'expected_upload_count' => 'nullable|integer|min:0',
            'draft_id'            => 'nullable|string|max:64',
            'draft_client_key'    => 'nullable|string|max:120',
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
            'seat_items_payload'  => ['nullable', 'string', function ($attr, $val, $fail) {
                if ($val === null || trim($val) === '') return;
                json_decode($val, true);
                if (json_last_error() !== JSON_ERROR_NONE) $fail('Seat items payload JSON tidak valid.');
            }],
            'seat_item_photos'      => 'nullable|array',
            'seat_item_photos.*'    => 'nullable|array',
            'seat_item_photos.*.*'  => 'nullable|image|mimes:jpeg,png,webp|max:8192',

            // items + photos
            'items'               => 'required|array|min:1',
            'items.*.description' => 'nullable|string',
            'items.*.photos'      => 'nullable|array',
            'items.*.photos.*'    => 'nullable|image|mimes:jpeg,png,webp|max:8192',
        ]);

        $expectedUploadCount = (int) ($data['expected_upload_count'] ?? 0);
        $actualUploadCount = $this->reportService->countUploadedFilesByKeys($request, ['items', 'seat_item_photos']);
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
        if ($request->has('seat_items_payload')) {
            $this->sanitizer->ensureSeatItemsPayloadWithinLimit($request->input('seat_items_payload'), 'seat_items_payload');
        }

        $itemsInput = $request->input('items');
        $itemFilesInput = $request->file('items', []);
        if (!is_array($itemsInput)) {
            $itemsInput = [];
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

        // tanggal + jam WITA (sesuai seat sebelumnya)
        $finalDate = Carbon::parse($data['inspection_date'])
            ->setTimeFromTimeString(now('Asia/Makassar')->format('H:i'));

        $templateKey = trim((string) ($data['template_key'] ?? ''));
        if ($templateKey === '') $templateKey = $this->defaultSeatTemplateKey();

        $templateVersionInt = (int) ($data['template_version'] ?? 1);
        if ($templateVersionInt < 1) $templateVersionInt = 1;

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
            'seat',
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

        [$payloadTemplateKey, $payloadVersionStr, $payloadVersionInt] = $this->resolveSeatTemplateFromPayload((array) $partsPayload);

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

        // fallback defaults jika payload kosong
        $needsPartsDefaults = $this->sanitizer->isLikelyIncompletePartsPayload((array) $partsPayload);
        $needsDetailDefaults = $this->sanitizer->isLikelyIncompleteDetailPayload((array) $detailPayload);
        if ($needsPartsDefaults || $needsDetailDefaults) {
            $defaults = $this->seatLoadDefaults($templateKey, $templateVersionInt);

            if ($needsPartsDefaults) {
                $partsPayload = $defaults['parts_defaults'] ?? ($defaults['parts'] ?? []);
            }
            if ($needsDetailDefaults) {
                $detailPayload = $defaults['detail_defaults'] ?? ($defaults['detail'] ?? []);
            }
        }

        // normalisasi detail tax
        if (!isset($detailPayload['totals']) || !is_array($detailPayload['totals'])) $detailPayload['totals'] = [];
        if (!isset($detailPayload['totals']['sales_tax_percent'])) {
            $detailPayload['totals']['sales_tax_percent'] = 11;
        }
        if (isset($detailPayload['totals']['tax_percent']) && !isset($detailPayload['totals']['sales_tax_percent'])) {
            $detailPayload['totals']['sales_tax_percent'] = $detailPayload['totals']['tax_percent'];
        }

        // sanitize + inject template meta (kalau template ada)
        $manifest = $this->seatManifest($templateKey);
        $versionStr = is_array($manifest) ? trim((string) ($manifest['version'] ?? '')) : '';
        if ($versionStr === '') $versionStr = 'v' . $templateVersionInt;

        $partsPayload  = $this->sanitizer->sanitizePartsPayload((array) $partsPayload, $templateKey, $versionStr, $manifest);
        $detailPayload = $this->sanitizer->sanitizeDetailPayload((array) $detailPayload, $templateKey, $versionStr, $manifest);

        $seatItemsRows = $this->sanitizeSeatItemsRows(
            $this->sanitizer->decodeJsonInput($data['seat_items_payload'] ?? null, 'seat_items_payload')
        );
        if (!empty($seatItemsRows) || $request->hasFile('seat_item_photos')) {
            $partsPayload = $this->mergeSeatItemsIntoPartsPayload((array) $partsPayload, $seatItemsRows);
        }

        $partsPayloadTs = $this->sanitizer->payloadTimestampFromArray((array) $partsPayload);
        $detailPayloadTs = $this->sanitizer->payloadTimestampFromArray((array) $detailPayload);
        $partsPayloadRev = $partsPayloadTs ?? (!empty($partsPayload) ? 1 : null);
        $detailPayloadRev = $detailPayloadTs ?? (!empty($detailPayload) ? 1 : null);

        $report = CcrReport::create([
            'type'            => 'seat',
            'created_by'      => auth()->id(),
            'group_folder'    => $data['group_folder'],
            'component'       => $data['component'],
            'unit'            => $data['unit'] ?? null,
            'wo_pr'           => $data['wo_pr'] ?? null,
            'make'            => $data['make'] ?? null,
            'model'           => $data['model'] ?? null,
            'sn'              => $data['sn'] ?? null,
            'smu'             => $data['smu'] ?? null,
            'customer'        => $data['customer'] ?? null,
            'inspection_date' => $finalDate,

            // template meta
            'template_key'     => $templateKey,
            'template_version' => $templateVersionInt,

            // worksheet payloads
            'parts_payload'    => $partsPayload,
            'detail_payload'   => $detailPayload,
            'parts_payload_rev' => $partsPayloadRev,
            'detail_payload_rev' => $detailPayloadRev,
        ]);

        // folder photos
        $date = Carbon::parse($report->inspection_date)->format('Y-m-d');
        $safe = substr(preg_replace('/[^A-Za-z0-9\- ]/', '', $report->component), 0, 30);
        $folder = "synology/CCR-{$report->group_folder}-{$date}-{$safe}";
        Storage::disk('public')->makeDirectory("$folder/photos");
        Storage::disk('public')->makeDirectory("$folder/seat_items");

        // create items + photos
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
                'description'   => $desc,
            ]);

            foreach ($uploadedPhotos as $photo) {
                if (!$photo instanceof UploadedFile || !$photo->isValid()) continue;
                $path = $photo->store("$folder/photos", 'public');
                CcrPhoto::create([
                    'ccr_item_id' => $item->id,
                    'path'        => $path,
                ]);
            }
        }

        if (!empty($seatItemsRows) || $request->hasFile('seat_item_photos')) {
            $seatItemsRows = $this->appendUploadedSeatItemsPhotos($request, $seatItemsRows, $folder);
            $newPartsPayload = $this->mergeSeatItemsIntoPartsPayload((array) ($report->parts_payload ?? []), $seatItemsRows);
            if ($newPartsPayload !== (array) ($report->parts_payload ?? [])) {
                $report->parts_payload = $newPartsPayload;
                $report->parts_payload_rev = $this->worksheetService->nextPayloadRevision(
                    (int) ($report->parts_payload_rev ?? 0),
                    $this->sanitizer->payloadTimestampFromArray((array) $newPartsPayload)
                );
                $report->save();
            }
        }

        $userId = (int) auth()->id();
        $draftClientKey = trim((string) ($data['draft_client_key'] ?? ''));

        // Cegah race: request autosave yang telat (in-flight) tidak boleh bikin draft baru lagi.
        if ($draftClientKey !== '') {
            Cache::put($this->reportService->draftFinalizedCacheKey('seat', $userId, $draftClientKey), true, now()->addSeconds(120));
        }

        // Setelah report final tersimpan, bersihkan draft seat user agar popup draft tidak muncul lagi.
        CcrDraft::query()
            ->where('user_id', $userId)
            ->where('type', 'seat')
            ->delete();

        ActivityLogger::log('create', $report, ['type' => 'seat', 'component' => $report->component]);

        return redirect()->route('ccr.manage.seat')
            ->with('success', 'CCR Seat berhasil dibuat.')
            ->with('clear_create_draft', 'seat');

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
        if (($report->type ?? null) !== 'seat') {
            abort(404);
        }

        // ✅ parity: kalau report punya template_key dan payload kosong → seed defaults
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

        $templates = $this->getSeatTemplates();
        $seatItemsRows = $this->resolveSeatItemsRowsForView($report);

        // Edit lock: check if someone else is editing
        $lockedBy = null;
        $lock = EditLockController::checkLock((int) $report->id);
        if ($lock && (int) ($lock['user_id'] ?? 0) !== (int) auth()->id()) {
            $lockedBy = $lock['user_name'] ?? 'User lain';
        } else {
            EditLockController::acquireLock((int) $report->id);
        }

        return view('seat.edit-seat', compact('report', 'brands', 'groupedCustomers', 'templates', 'seatItemsRows', 'lockedBy'));
    }

    // ===========================================================
    // UPDATE HEADER + ITEM LAMA + ITEM BARU + (OPTIONAL) PAYLOAD
    // ===========================================================
    public function updateHeader(Request $request, $id)
    {
        $report = CcrReport::with('items.photos')->findOrFail($id);
        if (($report->type ?? null) !== 'seat') {
            abort(404);
        }
        $this->authorize('update', $report);
        $oldSeatItemsRows = $this->extractSeatItemsRowsFromPartsPayload((array) ($report->parts_payload ?? []));

        $validator = Validator::make($request->all(), [
            'group_folder'    => 'required|string',
            'component'       => 'required|string',
            'inspection_date' => 'required|date',
            'expected_upload_count' => 'nullable|integer|min:0',
            'parts_payload_rev' => 'nullable|integer|min:0',
            'detail_payload_rev' => 'nullable|integer|min:0',

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
            'seat_items_payload' => ['nullable', 'string', function ($attr, $val, $fail) {
                if ($val === null || trim($val) === '') return;
                json_decode($val, true);
                if (json_last_error() !== JSON_ERROR_NONE) $fail('Seat items payload JSON tidak valid.');
            }],
            'seat_item_photos'      => 'nullable|array',
            'seat_item_photos.*'    => 'nullable|array',
            'seat_item_photos.*.*'  => 'nullable|image|mimes:jpeg,png,webp|max:8192',

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
        $actualUploadCount = $this->reportService->countUploadedFilesByKeys($request, ['items', 'new_items', 'seat_item_photos']);
        if ($expectedUploadCount > $actualUploadCount) {
            return back()->withInput()->withErrors([
                'photos' => 'Sebagian foto tidak diterima server (dipilih: ' . $expectedUploadCount . ', diterima: ' . $actualUploadCount . '). Kemungkinan batas max_file_uploads / post_max_size masih terlalu kecil.',
            ]);
        }

        try {

        $deletedItemIds = collect($data['deleted_items'] ?? [])
            ->map(fn ($itemId) => (int) $itemId)
            ->filter(fn ($itemId) => $itemId > 0)
            ->unique()
            ->values();
        $deletedItemIdList = $deletedItemIds->all();

        $changed = false;
        $payloadChanged = false;
        $staleSections = [];

        $finalDate = Carbon::parse($data['inspection_date'])
            ->setTimeFromTimeString(now('Asia/Makassar')->format('H:i'));

        $canEditWorksheet = $this->reportService->canEditWorksheet();
        $hasSeatItemsPayload = $request->has('seat_items_payload');
        $seatItemsRows = null;
        $seatItemsPayloadTs = null;

        $hasPartsPayload = $request->has('parts_payload');
        $hasDetailPayload = $request->has('detail_payload');
        $partsRawInput = $hasPartsPayload ? $request->input('parts_payload') : null;
        $detailRawInput = $hasDetailPayload ? $request->input('detail_payload') : null;

        if ($canEditWorksheet && (!$hasPartsPayload || !$hasDetailPayload)) {
            return back()->withInput()->withErrors([
                'worksheet' => 'Payload worksheet tidak lengkap. Simpan dibatalkan untuk mencegah data style/note/tools hilang. Coba refresh lalu simpan ulang.',
            ]);
        }

        if ($canEditWorksheet && $hasPartsPayload) {
            $this->sanitizer->ensurePayloadWithinLimit($partsRawInput, 'parts_payload');
        }
        if ($canEditWorksheet && $hasDetailPayload) {
            $this->sanitizer->ensurePayloadWithinLimit($detailRawInput, 'detail_payload');
        }
        if ($canEditWorksheet && $hasSeatItemsPayload) {
            $this->sanitizer->ensureSeatItemsPayloadWithinLimit($request->input('seat_items_payload'), 'seat_items_payload');
        }

        $rawParts = $canEditWorksheet ? $this->sanitizer->decodeJsonInput($partsRawInput, 'parts_payload') : [];
        $rawDetail = $canEditWorksheet ? $this->sanitizer->decodeJsonInput($detailRawInput, 'detail_payload') : [];

        if ($canEditWorksheet) {
            $serverSubmitTs = (int) floor(microtime(true) * 1000);
            if ($hasPartsPayload && !empty($rawParts) && $this->sanitizer->payloadTimestampFromArray($rawParts) === null) {
                $rawParts['ts'] = $serverSubmitTs;
            }
            if ($hasDetailPayload && !empty($rawDetail) && $this->sanitizer->payloadTimestampFromArray($rawDetail) === null) {
                $rawDetail['ts'] = $serverSubmitTs;
            }
        }

        [$templateKey, $templateVersionStr, $templateVersionInt, $manifest] = $this->resolveSeatTemplateFromPayload($rawParts);
        $partsClientRev = $this->sanitizer->parsePayloadRevision($request->input('parts_payload_rev'));
        $detailClientRev = $this->sanitizer->parsePayloadRevision($request->input('detail_payload_rev'));
        $seatItemsEnvelopeInput = ($canEditWorksheet && $hasSeatItemsPayload)
            ? $this->decodeSeatItemsPayloadEnvelope($request->input('seat_items_payload'))
            : ['rows' => [], 'ts' => null, 'parts_payload_rev' => null];
        $seatItemsClientRev = $this->sanitizer->parsePayloadRevision($seatItemsEnvelopeInput['parts_payload_rev'] ?? null);
        if ($seatItemsClientRev === null) {
            $seatItemsClientRev = $partsClientRev;
        }

        DB::transaction(function () use (
            $report,
            $data,
            $request,
            $finalDate,
            $canEditWorksheet,
            $hasPartsPayload,
            $hasDetailPayload,
            $rawParts,
            $rawDetail,
            $templateKey,
            $templateVersionStr,
            $templateVersionInt,
            $manifest,
            $partsClientRev,
            $detailClientRev,
            &$changed,
            &$payloadChanged,
            &$staleSections,
            &$seatItemsRows,
            &$seatItemsPayloadTs,
            $hasSeatItemsPayload,
            $seatItemsEnvelopeInput
        ) {
            $locked = CcrReport::query()->whereKey($report->id)->lockForUpdate()->firstOrFail();

            if ($canEditWorksheet && $hasPartsPayload) {
                $newParts = $this->sanitizer->sanitizePartsPayload($rawParts, $templateKey, $templateVersionStr, $manifest);
                if (!empty($newParts)) {
                    $partsState = $this->worksheetService->applyWorksheetPayloadSection($locked, 'parts', $newParts, $partsClientRev);
                    if ($partsState['stale']) $staleSections[] = 'parts';
                    if ($partsState['payload_changed']) $payloadChanged = true;
                }
            }

            if ($canEditWorksheet && $hasDetailPayload) {
                if (isset($rawDetail['totals']['tax_percent']) && !isset($rawDetail['totals']['sales_tax_percent'])) {
                    $rawDetail['totals']['sales_tax_percent'] = $rawDetail['totals']['tax_percent'];
                }
                if (!isset($rawDetail['totals']['sales_tax_percent'])) {
                    $rawDetail['totals']['sales_tax_percent'] = 11;
                }
                $newDetail = $this->sanitizer->sanitizeDetailPayload($rawDetail, $templateKey, $templateVersionStr, $manifest);
                if (!empty($newDetail)) {
                    $detailState = $this->worksheetService->applyWorksheetPayloadSection($locked, 'detail', $newDetail, $detailClientRev);
                    if ($detailState['stale']) $staleSections[] = 'detail';
                    if ($detailState['payload_changed']) $payloadChanged = true;
                }
            }

            if ($canEditWorksheet && $templateKey) {
                $locked->template_key = $templateKey;
                if ($templateVersionInt !== null) {
                    $locked->template_version = $templateVersionInt;
                }
            }

            if ($canEditWorksheet && $hasSeatItemsPayload) {
                $seatItemsRows = $this->sanitizeSeatItemsRows($seatItemsEnvelopeInput['rows'] ?? []);
                $seatItemsPayloadTs = $this->sanitizer->parsePayloadRevision($seatItemsEnvelopeInput['ts'] ?? null);
            }

            $locked->fill([
                'group_folder'    => $data['group_folder'],
                'component'       => $data['component'],
                'unit'            => $request->unit,
                'wo_pr'           => $request->wo_pr,
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

        if ($payloadChanged) {
            $changed = true;
        }

        $date = Carbon::parse($report->inspection_date)->format('Y-m-d');
        $safe = substr(preg_replace('/[^A-Za-z0-9\- ]/', '', $report->component), 0, 30);
        $folder = "synology/CCR-{$report->group_folder}-{$date}-{$safe}";
        Storage::disk('public')->makeDirectory("$folder/photos");
        Storage::disk('public')->makeDirectory("$folder/seat_items");
        $uploadedExistingItems = is_array($request->file('items')) ? $request->file('items') : [];
        $uploadedNewItems = is_array($request->file('new_items')) ? $request->file('new_items') : [];

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

        if (!empty($data['new_items'])) {
            foreach ($data['new_items'] as $index => $input) {
                $hasDesc = trim((string) ($input['description'] ?? ''));
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

        if ($canEditWorksheet && ($hasSeatItemsPayload || $request->hasFile('seat_item_photos'))) {
            if (!is_array($seatItemsRows)) {
                $seatItemsRows = $this->extractSeatItemsRowsFromPartsPayload((array) ($report->parts_payload ?? []));
            }
            $seatItemsRows = $this->sanitizeSeatItemsRows($seatItemsRows);
            $seatItemsRows = $this->appendUploadedSeatItemsPhotos($request, $seatItemsRows, $folder);

            $currentPartsPayload = (array) ($report->parts_payload ?? []);
            $newPartsPayload = $this->mergeSeatItemsIntoPartsPayload($currentPartsPayload, $seatItemsRows);

            if ($newPartsPayload !== $currentPartsPayload) {
                $manifestRow = $this->seatManifest((string) ($report->template_key ?? ''));
                $versionStr = is_array($manifestRow)
                    ? trim((string) ($manifestRow['version'] ?? ''))
                    : '';
                if ($versionStr === '') {
                    $versionStr = 'v' . (int) ($report->template_version ?: 1);
                }

                $newPartsPayload['ts'] = $seatItemsPayloadTs ?? (int) floor(microtime(true) * 1000);
                $newPartsPayload = $this->sanitizer->sanitizePartsPayload(
                    $newPartsPayload,
                    (string) ($report->template_key ?? ''),
                    $versionStr,
                    is_array($manifestRow) ? $manifestRow : null
                );
                $partsState = $this->worksheetService->applyWorksheetPayloadSection($report, 'parts', $newPartsPayload, $seatItemsClientRev);
                if (!empty($partsState['stale'])) {
                    $staleSections[] = 'parts';
                }
                if (!empty($partsState['payload_changed'])) {
                    $payloadChanged = true;
                }
                if (!empty($partsState['saved'])) {
                    $report->save();
                    $changed = true;
                }
            }

            $this->deleteRemovedSeatItemsPhotos($oldSeatItemsRows, $seatItemsRows);
        }

        if ($changed) {
            CcrReport::whereKey($report->id)->update([
                'docx_generated_at' => null,
                'updated_at'        => now(),
            ]);
        }

        if ($request->boolean('preview_after_save')) {
            return redirect()->route('seat.preview', $report->id);
        }

        ActivityLogger::log('update', $report, ['type' => 'seat', 'component' => $report->component]);

        $redirect = redirect()->route('ccr.manage.seat')
            ->with('success', 'Perubahan CCR Seat berhasil disimpan!');

        if (!empty($staleSections)) {
            $redirect->with('warning', 'Sebagian autosave lama diabaikan (stale): ' . implode(', ', array_values(array_unique($staleSections))) . '. Data terbaru tetap dipakai.');
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
    // Route (planned): seat.worksheet.template.defaults
    // ===========================================================
    public function templateDefaults(Request $request)
    {
        $data = $request->validate([
            'template_key' => ['required', 'string'],
        ]);

        $key = trim((string) $data['template_key']);
        if ($key === '') $key = $this->defaultSeatTemplateKey();

        $defaults = $this->seatLoadDefaults($key, 1);

        $manifest = is_array($defaults['manifest'] ?? null) ? $defaults['manifest'] : ($this->seatManifest($key) ?? []);
        if (!is_array($manifest)) $manifest = [];

        $versionStr = trim((string) ($manifest['version'] ?? ''));
        if ($versionStr === '') $versionStr = 'v1';
        $versionInt = $this->sanitizer->toTemplateVersionInt($versionStr) ?? 1;

        $detail = $defaults['detail'] ?? ($defaults['detail_defaults'] ?? []);
        if (isset($detail['totals']['tax_percent']) && !isset($detail['totals']['sales_tax_percent'])) {
            $detail['totals']['sales_tax_percent'] = $detail['totals']['tax_percent'];
        }
        if (!isset($detail['totals']['sales_tax_percent'])) {
            $detail['totals']['sales_tax_percent'] = 11;
        }

        $parts  = $defaults['parts'] ?? ($defaults['parts_defaults'] ?? []);
        $parts  = $this->sanitizer->sanitizePartsPayload((array) $parts, $key, $versionStr, $manifest);
        $detail = $this->sanitizer->sanitizeDetailPayload((array) $detail, $key, $versionStr, $manifest);

        $datalists = $this->seatDatalists($key);

        return response()->json([
            'ok'                   => true,
            'key'                  => $key,
            'template_version'     => $versionStr,
            'template_version_int' => $versionInt,
            'manifest'             => $manifest,
            'parts'                => $parts,
            'detail'               => $detail,
            'datalists'            => $datalists,
        ]);
    }

    // ===========================================================
    // TEMPLATE: apply ke report (opsional)
    // Route (planned): seat.worksheet.template.apply
    // ===========================================================
    public function applyWorksheetTemplate(Request $request, $id)
    {
        $report = CcrReport::findOrFail($id);
        if (($report->type ?? null) !== 'seat') {
            abort(404);
        }

        // role gate (operator/planner read-only)
        if (!$this->reportService->canEditWorksheet()) {
            return response()->json(['ok' => false, 'message' => 'Locked you cannot access this (403)'], 403);
        }

        $data = $request->validate([
            'template_key' => ['required', 'string'],
            'replace'      => ['nullable', 'boolean'],
        ]);

        $key = trim((string) $data['template_key']);
        if ($key === '') $key = $this->defaultSeatTemplateKey();

        $replace = (bool) ($data['replace'] ?? false);

        $defaults = $this->seatLoadDefaults($key, 1);
        $manifest = is_array($defaults['manifest'] ?? null) ? $defaults['manifest'] : ($this->seatManifest($key) ?? null);

        if (!is_array($manifest) || empty($manifest)) {
            $res = ['ok' => false, 'message' => 'Template tidak ditemukan.'];
            return $request->wantsJson()
                ? response()->json($res, 404)
                : back()->with('error', $res['message']);
        }

        $versionStr = trim((string) ($manifest['version'] ?? ''));
        if ($versionStr === '') $versionStr = 'v1';
        $versionInt = $this->sanitizer->toTemplateVersionInt($versionStr) ?? 1;

        DB::transaction(function () use ($report, $key, $versionInt, $versionStr, $manifest, $defaults, $replace) {
            $r = CcrReport::whereKey($report->id)->lockForUpdate()->first();

            $r->template_key = $key;
            $r->template_version = $versionInt;

            $partsEmpty  = empty($r->parts_payload) || $r->parts_payload === [];
            $detailEmpty = empty($r->detail_payload) || $r->detail_payload === [];

            $partsDefaults  = $defaults['parts'] ?? ($defaults['parts_defaults'] ?? []);
            $detailDefaults = $defaults['detail'] ?? ($defaults['detail_defaults'] ?? []);

            if ($replace || $partsEmpty) {
                $r->parts_payload = $this->sanitizer->sanitizePartsPayload((array) $partsDefaults, $key, $versionStr, $manifest);
            } else {
                $r->parts_payload = $this->sanitizer->sanitizePartsPayload((array) $r->parts_payload, $key, $versionStr, $manifest);
            }

            if ($replace || $detailEmpty) {
                if (isset($detailDefaults['totals']['tax_percent']) && !isset($detailDefaults['totals']['sales_tax_percent'])) {
                    $detailDefaults['totals']['sales_tax_percent'] = $detailDefaults['totals']['tax_percent'];
                }
                if (!isset($detailDefaults['totals']['sales_tax_percent'])) {
                    $detailDefaults['totals']['sales_tax_percent'] = 11;
                }

                $r->detail_payload = $this->sanitizer->sanitizeDetailPayload((array) $detailDefaults, $key, $versionStr, $manifest);
            } else {
                $r->detail_payload = $this->sanitizer->sanitizeDetailPayload((array) $r->detail_payload, $key, $versionStr, $manifest);
            }

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
    // Route (planned): seat.worksheet.autosave
    // ===========================================================
    public function autosaveWorksheet(Request $request, int $id)
    {
        $report = CcrReport::findOrFail($id);
        if (($report->type ?? null) !== 'seat') {
            abort(404);
        }

        // role gate (operator/planner read-only)
        if (!$this->reportService->canEditWorksheet()) {
            return response()->json(['ok' => false, 'message' => 'Locked you cannot access this (403)'], 403);
        }

        $hasParts  = $request->has('parts_payload');
        $hasDetail = $request->has('detail_payload');
        $hasSeatItems = $request->has('seat_items_payload');

        if (!$hasParts && !$hasDetail && !$hasSeatItems) {
            return response()->json([
                'ok' => false,
                'message' => 'Tidak ada payload untuk disimpan.',
            ], 422);
        }

        $partsRaw  = $hasParts  ? $request->input('parts_payload')  : null;
        $detailRaw = $hasDetail ? $request->input('detail_payload') : null;
        $seatItemsRaw = $hasSeatItems ? $request->input('seat_items_payload') : null;

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
        if ($hasSeatItems) {
            $this->sanitizer->ensureSeatItemsPayloadWithinLimit($seatItemsRaw, 'seat_items_payload');
            if ($this->sanitizer->isInvalidJsonInput($seatItemsRaw)) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Invalid seat_items_payload JSON',
                ], 422);
            }
        }

        $partsClientRev = $this->sanitizer->parsePayloadRevision($request->input('parts_payload_rev'));
        $detailClientRev = $this->sanitizer->parsePayloadRevision($request->input('detail_payload_rev'));
        $seatItemsEnvelope = $hasSeatItems
            ? $this->decodeSeatItemsPayloadEnvelope($seatItemsRaw)
            : ['rows' => [], 'ts' => null, 'parts_payload_rev' => null];
        $seatItemsClientRev = $this->sanitizer->parsePayloadRevision($seatItemsEnvelope['parts_payload_rev'] ?? null);
        if ($seatItemsClientRev === null) {
            $seatItemsClientRev = $partsClientRev;
        }

        $result = DB::transaction(function () use (
            $id,
            $hasParts,
            $hasDetail,
            $hasSeatItems,
            $partsRaw,
            $detailRaw,
            $seatItemsEnvelope,
            $partsClientRev,
            $detailClientRev,
            $seatItemsClientRev
        ) {
            $report = CcrReport::query()->whereKey($id)->lockForUpdate()->firstOrFail();

            $dirty = false;
            $payloadChanged = false;
            $stale = [
                'parts' => false,
                'detail' => false,
            ];

            $ctxKey = trim((string) ($report->template_key ?? ''));
            $ctxKey = ($ctxKey !== '') ? $ctxKey : null;

            $ctxManifest = $ctxKey ? $this->seatManifest($ctxKey) : null;

            $ctxVersionStr = '';
            if (is_array($ctxManifest)) {
                $ctxVersionStr = trim((string) ($ctxManifest['version'] ?? ''));
            }
            if ($ctxVersionStr === '') {
                $ctxVersionStr = 'v' . (int) ($report->template_version ?: 1);
            }

            $ctxVersionInt = (int) ($report->template_version ?: ($this->sanitizer->toTemplateVersionInt($ctxVersionStr) ?: 1));

            if ($hasParts) {
                $rawPartsArr = $this->sanitizer->decodeJsonInput($partsRaw, 'parts_payload');

                [$pKey, $pVerStr, $pVerInt, $pManifest] = $this->resolveSeatTemplateFromPayload($rawPartsArr);

                if ($pKey) {
                    $ctxKey = $pKey;
                    $ctxManifest = is_array($pManifest) ? $pManifest : ($ctxKey ? $this->seatManifest($ctxKey) : null);

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

            if ($hasDetail) {
                $rawDetailArr = $this->sanitizer->decodeJsonInput($detailRaw, 'detail_payload');

                if (isset($rawDetailArr['totals']['tax_percent']) && !isset($rawDetailArr['totals']['sales_tax_percent'])) {
                    $rawDetailArr['totals']['sales_tax_percent'] = $rawDetailArr['totals']['tax_percent'];
                }
                if (!isset($rawDetailArr['totals']['sales_tax_percent'])) {
                    $rawDetailArr['totals']['sales_tax_percent'] = 11;
                }

                $cleanDetail = $this->sanitizer->sanitizeDetailPayload($rawDetailArr, $ctxKey, $ctxVersionStr, $ctxManifest);

                if (!empty($cleanDetail)) {
                    $detailState = $this->worksheetService->applyWorksheetPayloadSection($report, 'detail', $cleanDetail, $detailClientRev);
                    $stale['detail'] = (bool) ($detailState['stale'] ?? false);
                    if (!empty($detailState['saved'])) $dirty = true;
                    if (!empty($detailState['payload_changed'])) $payloadChanged = true;
                }
            }

            if ($hasSeatItems) {
                $seatItemsRows = $this->sanitizeSeatItemsRows($seatItemsEnvelope['rows'] ?? []);
                $seatItemsTs = $this->sanitizer->parsePayloadRevision($seatItemsEnvelope['ts'] ?? null);

                $currentPartsPayload = $this->worksheetService->sectionPayloadArray($report, 'parts');
                $nextPartsPayload = $this->mergeSeatItemsIntoPartsPayload($currentPartsPayload, $seatItemsRows);
                if ($nextPartsPayload !== $currentPartsPayload) {
                    $nextPartsPayload['ts'] = $seatItemsTs ?? (int) floor(microtime(true) * 1000);
                    $nextPartsPayload = $this->sanitizer->sanitizePartsPayload($nextPartsPayload, $ctxKey, $ctxVersionStr, $ctxManifest);
                    $partsState = $this->worksheetService->applyWorksheetPayloadSection($report, 'parts', $nextPartsPayload, $seatItemsClientRev);
                    $stale['parts'] = $stale['parts'] || (bool) ($partsState['stale'] ?? false);
                    if (!empty($partsState['saved'])) $dirty = true;
                    if (!empty($partsState['payload_changed'])) $payloadChanged = true;
                }
            }

            if ($ctxKey && (
                (string) ($report->template_key ?? '') !== (string) $ctxKey
                || (int) ($report->template_version ?? 0) !== (int) $ctxVersionInt
            )) {
                $report->template_key = $ctxKey;
                $report->template_version = $ctxVersionInt;
                $dirty = true;
            }

            if ($dirty) {
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
        $item->loadMissing(['report', 'photos']);
        $report = CcrReport::find($item->ccr_report_id);
        if ($report) $this->authorize('delete', $report);

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

            return redirect()->route('seat.edit', $reportId);
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

        return redirect()->route('seat.edit', $reportId);
    }

    // ===========================================================
    // DELETE PHOTO
    // ===========================================================
    public function deletePhoto(Request $request, CcrPhoto $photo)
    {
        $photo->loadMissing('item');
        $report = CcrReport::find($photo->item->ccr_report_id ?? 0);
        if ($report) $this->authorize('delete', $report);

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

        return redirect()->route('seat.edit', $reportId)
            ->with('success', 'Foto berhasil dihapus.');
    }

    // ===========================================================
    // DELETE MULTIPLE
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
            ->where('type', 'seat')
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

        return back()->with('success', count($reports) . ' CCR Seat dipindahkan ke Sampah (7 hari).');
    }

    // ===========================================================
    // PREVIEW SEAT
    // ===========================================================
    public function preview($id)
    {
        $report = CcrReport::with('items.photos')->findOrFail($id);
        if (($report->type ?? null) !== 'seat') {
            abort(404);
        }
        return view('seat.preview', compact('report'));
    }

    // ===========================================================
    // SUBMIT TO DIREKTUR (Submit / Re-submit)
    // ===========================================================
    public function submit(Request $request, int $id)
    {
        $report = CcrReport::findOrFail($id);
        if (($report->type ?? null) !== 'seat') {
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

        ActivityLogger::log($resubmit ? 'resubmit' : 'submit', $report, ['type' => 'seat', 'component' => $report->component]);

        $componentName = trim((string) ($report->component ?? ''));
        if ($componentName === '') $componentName = 'Seat';

        $openUrl = route('director.monitoring', ['open' => $report->id], false) . '#r-' . $report->id;

        Inbox::toRoles(['director'], [
            'type'    => 'seat_submitted',
            'title'   => $componentName,
            'message' => 'Disubmit oleh ' . (auth()->user()->name ?? 'User') . '.',
            'url'     => $openUrl,
        ], auth()->id());

        return back()->with('success', $resubmit
            ? 'CCR Seat berhasil di Re-submit ke Direktur.'
            : 'CCR Seat berhasil dikirim ke Direktur.'
        );
    }

    private function decodeSeatItemsPayloadEnvelope($raw): array
    {
        $decoded = $this->sanitizer->decodeJsonInput($raw, 'seat_items_payload');

        if (isset($decoded['rows']) && is_array($decoded['rows'])) {
            return [
                'rows' => $decoded['rows'],
                'ts' => $decoded['ts'] ?? null,
                'parts_payload_rev' => $decoded['parts_payload_rev'] ?? null,
            ];
        }

        return [
            'rows' => is_array($decoded) ? $decoded : [],
            'ts' => null,
            'parts_payload_rev' => null,
        ];
    }


    // ===========================================================
    // Helper: resolve template meta dari payload
    // Return: [templateKey|null, versionStr, versionInt|null, manifest|null]
    // ===========================================================
    private function resolveSeatTemplateFromPayload(array $partsPayload): array
    {
        $meta = (isset($partsPayload['meta']) && is_array($partsPayload['meta'])) ? $partsPayload['meta'] : [];

        $key = trim((string) ($meta['template_key'] ?? ''));
        if ($key === '') $key = null;

        $versionStr = trim((string) ($meta['template_version'] ?? ''));
        $manifest = null;
        $versionInt = null;

        if ($key) {
            $manifest = $this->seatManifest($key);
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
        if ($key === '') $key = $this->defaultSeatTemplateKey();

        $partsEmpty  = empty($report->parts_payload) || $report->parts_payload === [];
        $detailEmpty = empty($report->detail_payload) || $report->detail_payload === [];

        if (!$partsEmpty && !$detailEmpty) return;

        $defaults = $this->seatLoadDefaults($key, (int) ($report->template_version ?: 1));
        $manifest = is_array($defaults['manifest'] ?? null) ? $defaults['manifest'] : ($this->seatManifest($key) ?? null);
        if (!is_array($manifest) || empty($manifest)) {
            $defaults = $this->seatBlankDefaults();
            $manifest = $defaults['manifest'];
        }

        $versionStr = trim((string) ($manifest['version'] ?? ''));
        $versionInt = $this->sanitizer->toTemplateVersionInt($versionStr) ?? ($report->template_version ?: 1);

        $partsDefaults  = $defaults['parts'] ?? ($defaults['parts_defaults'] ?? []);
        $detailDefaults = $defaults['detail'] ?? ($defaults['detail_defaults'] ?? []);

        DB::transaction(function () use ($report, $key, $versionInt, $versionStr, $manifest, $partsDefaults, $detailDefaults) {
            $r = CcrReport::whereKey($report->id)->lockForUpdate()->first();

            $partsEmpty  = empty($r->parts_payload) || $r->parts_payload === [];
            $detailEmpty = empty($r->detail_payload) || $r->detail_payload === [];

            $r->template_key = $key;
            $r->template_version = $versionInt;

            if ($partsEmpty) {
                $r->parts_payload = $this->sanitizer->sanitizePartsPayload((array) $partsDefaults, $key, $versionStr, $manifest);
            } else {
                $r->parts_payload = $this->sanitizer->sanitizePartsPayload((array) $r->parts_payload, $key, $versionStr, $manifest);
            }

            if ($detailEmpty) {
                if (isset($detailDefaults['totals']['tax_percent']) && !isset($detailDefaults['totals']['sales_tax_percent'])) {
                    $detailDefaults['totals']['sales_tax_percent'] = $detailDefaults['totals']['tax_percent'];
                }
                if (!isset($detailDefaults['totals']['sales_tax_percent'])) {
                    $detailDefaults['totals']['sales_tax_percent'] = 11;
                }
                $r->detail_payload = $this->sanitizer->sanitizeDetailPayload((array) $detailDefaults, $key, $versionStr, $manifest);
            } else {
                $r->detail_payload = $this->sanitizer->sanitizeDetailPayload((array) $r->detail_payload, $key, $versionStr, $manifest);
            }

            $r->save();
        });
    }


    // ===========================================================
    // Seat Template helpers (support SeatTemplateRepo jika sudah dibuat)
    // ===========================================================
    private function defaultSeatTemplateKey(): string
    {
        return 'seat_blank';
    }

    private function seatRepoClass(): string
    {
        // class ini akan kamu buat nanti: app/Support/WorksheetTemplates/SeatTemplateRepo.php
        return 'App\\Support\\WorksheetTemplates\\SeatTemplateRepo';
    }

    private function seatLoadDefaults(string $key, int $verInt = 1): array
    {
        $cls = $this->seatRepoClass();

        // 1) kalau SeatTemplateRepo sudah ada → gunakan
        if (class_exists($cls)) {
            try {
                if (method_exists($cls, 'loadDefaults')) {
                    $repo = app($cls);
                    $out = $repo->loadDefaults($key, $verInt);
                    return is_array($out) ? $out : [];
                }
                if (method_exists($cls, 'defaults')) {
                    $out = $cls::defaults($key);
                    return is_array($out) ? $out : [];
                }
            } catch (\Throwable $e) {
                // fallback ke filesystem
            }
        }

        // 2) filesystem seat templates (kalau folder sudah dibuat)
        $base = resource_path('worksheet_templates/seat');

        // kalau belum ada folder seat → fallback blank internal
        if (!is_dir($base)) {
            return $this->seatBlankDefaults();
        }

        // try manifest.php (di root template atau di vX folder)
        $manifest = $this->seatManifest($key);

        $versionStr = 'v' . max(1, $verInt);
        if (is_array($manifest) && trim((string) ($manifest['version'] ?? '')) !== '') {
            $versionStr = trim((string) $manifest['version']);
        }

        $partsPath  = $base . "/{$key}/{$versionStr}/defaults_parts.php";
        $detailPath = $base . "/{$key}/{$versionStr}/defaults_detail.php";

        $parts  = file_exists($partsPath) ? include $partsPath : [];
        $detail = file_exists($detailPath) ? include $detailPath : [];

        return [
            'manifest' => is_array($manifest) ? $manifest : [],
            'parts'    => is_array($parts) ? $parts : [],
            'detail'   => is_array($detail) ? $detail : [],
        ];
    }

    private function seatManifest(string $key): ?array
    {
        $cls = $this->seatRepoClass();
        if (class_exists($cls)) {
            try {
                if (method_exists($cls, 'manifest')) {
                    $m = $cls::manifest($key);
                    return is_array($m) ? $m : null;
                }
            } catch (\Throwable $e) {
                // ignore
            }
        }

        $base = resource_path('worksheet_templates/seat');

        $pathRoot = $base . "/{$key}/manifest.php";
        if (file_exists($pathRoot)) {
            $m = include $pathRoot;
            return is_array($m) ? $m : null;
        }

        $pathV1 = $base . "/{$key}/v1/manifest.php";
        if (file_exists($pathV1)) {
            $m = include $pathV1;
            return is_array($m) ? $m : null;
        }

        if ($key === $this->defaultSeatTemplateKey()) {
            return $this->seatBlankDefaults()['manifest'];
        }

        return null;
    }

    private function seatDatalists(string $key): array
    {
        $cls = $this->seatRepoClass();
        if (class_exists($cls)) {
            try {
                if (method_exists($cls, 'datalists')) {
                    $d = $cls::datalists($key);
                    return is_array($d) ? $d : [];
                }
            } catch (\Throwable $e) {
                // ignore
            }
        }

        $base = resource_path('worksheet_templates/seat');
        $path = $base . "/{$key}/datalists.php";
        if (file_exists($path)) {
            $d = include $path;
            return is_array($d) ? $d : [];
        }

        return [];
    }

    private function getSeatTemplates(): array
    {
        $list = [];

        $cls = $this->seatRepoClass();

        // 1) coba dari repo (kalau sudah ada)
        if (class_exists($cls) && method_exists($cls, 'list')) {
            try {
                $raw = $cls::list();
                $list = is_array($raw) ? $raw : [];
            } catch (\Throwable $e) {
                $list = [];
            }
        }

        // 2) fallback: registry.php
        if (empty($list)) {
            $path = resource_path('worksheet_templates/seat/registry.php');
            $raw = file_exists($path) ? include $path : [];
            $list = is_array($raw) ? $raw : [];
        }

        // 3) normalisasi assoc (key=>meta) → list
        $isAssoc = is_array($list) && array_keys($list) !== range(0, count($list) - 1);
        if ($isAssoc) {
            $tmp = [];
            foreach ($list as $k => $v) {
                if (!is_array($v)) continue;
                $key = $v['key'] ?? (is_string($k) ? $k : '');
                if ($key === '') continue;
                $tmp[] = [
                    'key'     => $key,
                    'name'    => $v['name'] ?? $v['title'] ?? $key,
                    'version' => $v['version'] ?? $v['ver'] ?? 'v1',
                    'notes'   => $v['notes'] ?? '',
                ];
            }
            $list = $tmp;
        } else {
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

        // 4) Pastikan minimal ada seat_blank
        $hasBlank = false;
        foreach ($list as $t) {
            if (($t['key'] ?? '') === $this->defaultSeatTemplateKey()) {
                $hasBlank = true;
                break;
            }
        }
        if (!$hasBlank) {
            $list[] = [
                'key'     => $this->defaultSeatTemplateKey(),
                'name'    => 'Template Kosong (Seat)',
                'version' => 'v1',
                'notes'   => 'Template kosong',
            ];
        }

        return $list;
    }

    private function resolveSeatItemsRowsForView(?CcrReport $report = null): array
    {
        // Items tab berfungsi sebagai master global (bukan snapshot report).
        return $this->defaultSeatItemsRows();
    }

    private function defaultSeatItemsRows(): array
    {
        $this->bootstrapSeatItemsMasterFromCode();

        $out = [];
        try {
            $all = ItemMaster::query()
                ->where('module', 'seat')
                ->orderByRaw('CASE WHEN no IS NULL THEN 1 ELSE 0 END')
                ->orderBy('no')
                ->orderBy('item')
                ->get(['id', 'no', 'category', 'pn', 'item', 'purchase_price', 'sales_price', 'photo_paths']);
        } catch (\Throwable $e) {
            try {
                $all = ItemMaster::query()
                    ->where('module', 'seat')
                    ->orderByRaw('CASE WHEN no IS NULL THEN 1 ELSE 0 END')
                    ->orderBy('no')
                    ->orderBy('item')
                    ->get(['id', 'no', 'category', 'pn', 'item', 'purchase_price', 'sales_price']);
            } catch (\Throwable $e2) {
                return [];
            }
        }

        foreach ($all as $i => $row) {
            $photoPaths = $this->sanitizeSeatItemsRows([
                [
                    'uid' => 'im_' . (int) $row->id,
                    'item' => (string) ($row->item ?? ''),
                    'photo_paths' => (array) ($row->photo_paths ?? []),
                ]
            ]);
            $photoPaths = $photoPaths[0]['photo_paths'] ?? [];

            $out[] = [
                'uid' => 'im_' . (int) $row->id,
                'no' => (string) ($row->no ?? ($i + 1)),
                'category' => (string) ($row->category ?? ''),
                'pn' => (string) ($row->pn ?? ''),
                'item' => (string) ($row->item ?? ''),
                'purchase_price' => preg_replace('/[^\d]/', '', (string) ($row->purchase_price ?? '')),
                'sales_price' => preg_replace('/[^\d]/', '', (string) ($row->sales_price ?? '')),
                'photo_paths' => $photoPaths,
            ];
        }

        return $out;
    }

    private function bootstrapSeatItemsMasterFromCode(): void
    {
        $seedRows = $this->loadSeatItemsSeedRowsFromCode();
        if (empty($seedRows)) return;

        $seedRows = $this->sanitizeSeatItemsRows($seedRows);
        if (empty($seedRows)) return;

        try {
            DB::transaction(function () use ($seedRows) {
                try {
                    $existing = ItemMaster::query()
                        ->where('module', 'seat')
                        ->get(['id', 'category', 'pn', 'item', 'purchase_price', 'sales_price', 'photo_paths']);
                } catch (\Throwable $e) {
                    $existing = ItemMaster::query()
                        ->where('module', 'seat')
                        ->get(['id', 'category', 'pn', 'item', 'purchase_price', 'sales_price']);
                }

                // Bersihkan row placeholder hasil input salah (contoh: item = "No")
                // agar seed baseline bisa masuk normal.
                foreach ($existing as $model) {
                    if (!$this->isSeatItemsPlaceholderRow($model)) continue;

                    $paths = is_array($model->photo_paths ?? null) ? $model->photo_paths : [];
                    foreach ($paths as $path) {
                        if (!is_string($path)) continue;
                        $path = trim($path);
                        if ($path === '' || preg_match('#^https?://#i', $path)) continue;
                        Storage::disk('public')->delete($path);
                    }

                    $model->delete();
                }

                $remaining = ItemMaster::query()->where('module', 'seat')->count();
                if ($remaining > 0) return;

                foreach ($seedRows as $i => $row) {
                    $payload = $this->buildSeatItemsMasterPayload($row, $i + 1);

                    try {
                        ItemMaster::query()->create($payload);
                    } catch (\Throwable $e) {
                        unset($payload['photo_paths']);
                        ItemMaster::query()->create($payload);
                    }
                }
            });
        } catch (\Throwable $e) {
            // silent: jangan ganggu create/edit halaman seat
        }
    }

    private function buildSeatItemsMasterPayload(array $row, int $fallbackNo): array
    {
        return [
            'module' => 'seat',
            'no' => ((int) ($row['no'] ?? $fallbackNo)) ?: null,
            'category' => (string) ($row['category'] ?? ''),
            'pn' => (string) ($row['pn'] ?? ''),
            'item' => (string) ($row['item'] ?? ''),
            'purchase_price' => ((string) ($row['purchase_price'] ?? '') !== '')
                ? (int) $row['purchase_price']
                : null,
            'sales_price' => ((string) ($row['sales_price'] ?? '') !== '')
                ? (int) $row['sales_price']
                : null,
            'photo_paths' => (array) ($row['photo_paths'] ?? []),
        ];
    }

    private function isSeatItemsPlaceholderRow(ItemMaster $row): bool
    {
        $item = strtolower(trim((string) ($row->item ?? '')));
        if (!in_array($item, ['no', 'no.'], true)) return false;

        $category = trim((string) ($row->category ?? ''));
        $pn = trim((string) ($row->pn ?? ''));
        if ($category !== '' || $pn !== '') return false;

        $purchase = (string) ($row->purchase_price ?? '');
        $sales = (string) ($row->sales_price ?? '');
        if ($purchase !== '' || $sales !== '') return false;

        $paths = $row->photo_paths ?? [];
        if (is_array($paths) && count(array_filter($paths, fn($p) => is_string($p) && trim($p) !== '')) > 0) {
            return false;
        }

        return true;
    }

    private function loadSeatItemsSeedRowsFromCode(): array
    {
        $paths = [
            resource_path('worksheet_templates/seat/items_master_seed.php'),
            resource_path('worksheet_templates/seat/part_list_operator_seat/v1/items_master_seed.php'),
            resource_path('worksheet_templates/seat/blank/v1/items_master_seed.php'),
        ];

        foreach ($paths as $path) {
            if (!is_file($path)) continue;
            $raw = include $path;
            if (!is_array($raw)) continue;

            $rows = $this->sanitizeSeatItemsRows($raw);
            if (!empty($rows)) {
                return $rows;
            }
        }

        // fallback: derive dari defaults_parts template part_list_operator_seat
        $partsPath = resource_path('worksheet_templates/seat/part_list_operator_seat/v1/defaults_parts.php');
        if (!is_file($partsPath)) return [];

        $payload = include $partsPath;
        if (!is_array($payload)) return [];

        $rowsRaw = $payload['rows'] ?? [];
        if (!is_array($rowsRaw)) return [];

        $seed = [];
        foreach ($rowsRaw as $i => $r) {
            if (!is_array($r)) continue;

            $item = trim((string) ($r['part_description'] ?? ''));
            $pn = trim((string) ($r['part_number'] ?? ''));
            $purchase = preg_replace('/[^\d]/', '', (string) ($r['purchase_price'] ?? ''));
            $sales = preg_replace('/[^\d]/', '', (string) ($r['sales_price'] ?? ''));

            if ($item === '' && $pn === '' && $purchase === '' && $sales === '') continue;

            $seed[] = [
                'uid' => 'seed_' . ($i + 1),
                'no' => (string) ($i + 1),
                'category' => '',
                'pn' => $pn,
                'item' => $item,
                'purchase_price' => $purchase,
                'sales_price' => $sales,
                'photo_paths' => [],
            ];
        }

        return $this->sanitizeSeatItemsRows($seed);
    }

    private function extractSeatItemsRowsFromPartsPayload(array $partsPayload): array
    {
        $rows = $partsPayload['items_master_rows'] ?? [];
        if (!is_array($rows)) $rows = [];
        return $this->sanitizeSeatItemsRows($rows);
    }

    private function sanitizeSeatItemsRows($rows): array
    {
        if (!is_array($rows)) return [];

        $out = [];
        $usedUid = [];

        foreach ($rows as $i => $row) {
            if (count($out) >= PayloadSanitizer::SEAT_ITEMS_MAX_ROWS) {
                break;
            }
            if (!is_array($row)) continue;

            $uid = trim((string) ($row['uid'] ?? ''));
            if ($uid === '') $uid = 'si_' . ($i + 1);
            $uid = preg_replace('/[^A-Za-z0-9_\-]/', '_', $uid);
            if ($uid === '') $uid = 'si_' . ($i + 1);

            if (isset($usedUid[$uid])) {
                $uid = $uid . '_' . ($i + 1);
            }
            $usedUid[$uid] = true;

            $photoPathsRaw = $row['photo_paths'] ?? ($row['photos'] ?? []);
            if (!is_array($photoPathsRaw)) $photoPathsRaw = [];

            $photoPaths = [];
            foreach ($photoPathsRaw as $p) {
                $path = '';
                if (is_string($p)) {
                    $path = trim($p);
                } elseif (is_array($p)) {
                    $path = trim((string) ($p['path'] ?? $p['url'] ?? ''));
                }

                if ($path === '') continue;
                if (strlen($path) > 1024) continue;
                if (!preg_match('#^https?://#i', $path)) {
                    $path = ltrim($path, '/');
                    if (str_starts_with($path, 'storage/')) $path = substr($path, 8);
                    if (str_starts_with($path, 'public/')) $path = substr($path, 7);
                    if (str_contains($path, '..')) continue;
                }

                $photoPaths[] = $path;
            }

            $photoPaths = array_values(array_unique(array_filter($photoPaths, fn($x) => $x !== '')));

            $clean = [
                'uid' => $uid,
                'no' => preg_replace('/[^\d]/', '', (string) ($row['no'] ?? ($i + 1))),
                'category' => $this->sanitizer->limitTextLength(trim((string) ($row['category'] ?? '')), PayloadSanitizer::SEAT_ITEMS_MAX_CATEGORY_CHARS),
                'pn' => $this->sanitizer->limitTextLength(trim((string) ($row['pn'] ?? '')), PayloadSanitizer::SEAT_ITEMS_MAX_PN_CHARS),
                'item' => $this->sanitizer->limitTextLength(trim((string) ($row['item'] ?? $row['items'] ?? '')), PayloadSanitizer::SEAT_ITEMS_MAX_ITEM_CHARS),
                'purchase_price' => preg_replace('/[^\d]/', '', (string) ($row['purchase_price'] ?? '')),
                'sales_price' => preg_replace('/[^\d]/', '', (string) ($row['sales_price'] ?? '')),
                'photo_paths' => array_slice($photoPaths, 0, PayloadSanitizer::SEAT_ITEMS_MAX_PHOTOS_PER_ROW),
            ];

            $hasNewUploads = filter_var($row['_has_new_photos'] ?? false, FILTER_VALIDATE_BOOLEAN);

            $hasValue = (
                $clean['category'] !== '' ||
                $clean['pn'] !== '' ||
                $clean['item'] !== '' ||
                $clean['purchase_price'] !== '' ||
                $clean['sales_price'] !== '' ||
                !empty($clean['photo_paths']) ||
                $hasNewUploads
            );
            if (!$hasValue) continue;

            if ($clean['no'] === '') $clean['no'] = (string) ($i + 1);
            $out[] = $clean;
        }

        return $out;
    }

    private function mergeSeatItemsIntoPartsPayload(array $partsPayload, array $seatItemsRows): array
    {
        if (!isset($partsPayload['meta']) || !is_array($partsPayload['meta'])) {
            $partsPayload['meta'] = [];
        }

        $rows = $this->sanitizeSeatItemsRows($seatItemsRows);
        $currentRows = $this->sanitizeSeatItemsRows($partsPayload['items_master_rows'] ?? []);
        $rowsChanged = ($currentRows !== $rows);

        $partsPayload['items_master_rows'] = $rows;
        $partsPayload['meta']['items_master_rows_count'] = count($rows);
        if ($rowsChanged || !isset($partsPayload['meta']['items_master_snapshot_at'])) {
            $partsPayload['meta']['items_master_snapshot_at'] = now()->toIso8601String();
        }

        return $partsPayload;
    }

    private function appendUploadedSeatItemsPhotos(Request $request, array $seatItemsRows, string $folder): array
    {
        $rows = $this->sanitizeSeatItemsRows($seatItemsRows);
        $uploads = $request->file('seat_item_photos', []);
        if (!is_array($uploads) || empty($uploads)) return $rows;

        $index = [];
        foreach ($rows as $i => $r) {
            $uid = trim((string) ($r['uid'] ?? ''));
            if ($uid !== '') $index[$uid] = $i;
        }

        foreach ($uploads as $uid => $files) {
            $uid = preg_replace('/[^A-Za-z0-9_\-]/', '_', (string) $uid);
            if ($uid === '') continue;

            if (!isset($index[$uid])) {
                if (count($rows) >= PayloadSanitizer::SEAT_ITEMS_MAX_ROWS) {
                    continue;
                }
                $rows[] = [
                    'uid' => $uid,
                    'no' => (string) (count($rows) + 1),
                    'category' => '',
                    'pn' => '',
                    'item' => '',
                    'purchase_price' => '',
                    'sales_price' => '',
                    'photo_paths' => [],
                ];
                $index[$uid] = count($rows) - 1;
            }

            $fileList = is_array($files) ? $files : [$files];
            $existingCount = count((array) ($rows[$index[$uid]]['photo_paths'] ?? []));
            $availableSlots = max(0, PayloadSanitizer::SEAT_ITEMS_MAX_PHOTOS_PER_ROW - $existingCount);
            foreach ($fileList as $file) {
                if ($availableSlots <= 0) {
                    break;
                }
                if (!$file || !method_exists($file, 'isValid') || !$file->isValid()) continue;
                $path = $file->store("$folder/seat_items", 'public');
                $rows[$index[$uid]]['photo_paths'][] = $path;
                $availableSlots--;
            }

            $rows[$index[$uid]]['photo_paths'] = array_values(array_unique(array_filter(
                $rows[$index[$uid]]['photo_paths'] ?? [],
                fn($x) => is_string($x) && trim($x) !== ''
            )));
        }

        return $this->sanitizeSeatItemsRows($rows);
    }

    private function deleteRemovedSeatItemsPhotos(array $oldRows, array $newRows): void
    {
        $oldPaths = [];
        foreach ($oldRows as $row) {
            foreach ((array) ($row['photo_paths'] ?? []) as $p) {
                $p = trim((string) $p);
                if ($p === '' || preg_match('#^https?://#i', $p)) continue;
                $oldPaths[$p] = true;
            }
        }

        if (empty($oldPaths)) return;

        $newPaths = [];
        foreach ($newRows as $row) {
            foreach ((array) ($row['photo_paths'] ?? []) as $p) {
                $p = trim((string) $p);
                if ($p === '' || preg_match('#^https?://#i', $p)) continue;
                $newPaths[$p] = true;
            }
        }

        foreach (array_keys($oldPaths) as $path) {
            if (isset($newPaths[$path])) continue;
            Storage::disk('public')->delete($path);
        }
    }


    private function seatBlankDefaults(int $rowsCount = 30): array
    {
        $rows = [];
        for ($i = 0; $i < $rowsCount; $i++) {
            $rows[] = [
                'qty'              => '',
                'uom'              => '',
                'part_number'      => '',
                'part_description' => '',
                'part_section'     => '',
                'sales_price'      => '',
                'extended_price'   => '',
            ];
        }

        return [
            'manifest' => [
                'key'     => $this->defaultSeatTemplateKey(),
                'name'    => 'Template Kosong (Seat)',
                'version' => 'v1',
            ],
            'parts' => [
                'meta' => [
                    'rows_count' => $rowsCount,
                    'footer_total_mode' => 'auto',
                    'footer_extended_mode' => 'auto',
                ],
                'rows' => $rows,
            ],
            'detail' => [
                'meta' => [],
                'totals' => [
                    'sales_tax_percent' => 11,
                ],
            ],
            'datalists' => [],
        ];
    }

}
