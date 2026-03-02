@extends('layout')

@section('content')

<div class="seat-create-page">

    @php
        $draftSeed = (isset($draftSeed) && is_array($draftSeed)) ? $draftSeed : [];
        $draftSeedCcrPayload = (isset($draftSeed['ccr_payload']) && is_array($draftSeed['ccr_payload'])) ? $draftSeed['ccr_payload'] : [];
        $draftSeedCcrFields = (isset($draftSeedCcrPayload['fields']) && is_array($draftSeedCcrPayload['fields'])) ? $draftSeedCcrPayload['fields'] : [];
        $draftSeedCcrRows = (isset($draftSeedCcrPayload['rows']) && is_array($draftSeedCcrPayload['rows'])) ? $draftSeedCcrPayload['rows'] : [];
        $draftSeedPartsPayload = (isset($draftSeed['parts_payload']) && is_array($draftSeed['parts_payload'])) ? $draftSeed['parts_payload'] : [];
        $draftSeedDetailPayload = (isset($draftSeed['detail_payload']) && is_array($draftSeed['detail_payload'])) ? $draftSeed['detail_payload'] : [];
        $draftSeedItemsPayload = (isset($draftSeed['items_payload']) && is_array($draftSeed['items_payload'])) ? $draftSeed['items_payload'] : [];
        $activeDraftId = trim((string) ($draftSeed['id'] ?? ''));
        $activeDraftClientKey = trim((string) ($draftSeed['client_key'] ?? ''));
    @endphp

    {{-- =============== ERROR VALIDATION =============== --}}
    @if ($errors->any())
        <div class="error-box">
            <strong style="color:#b30000; font-size:15px;">⚠️ Gagal menyimpan CCR Seat:</strong>
            <ul style="margin:10px 0 0 22px; color:#800; font-size:14px;">
                @foreach ($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- =============== KEMBALI =============== --}}
    <a href="{{ route('ccr.manage.seat') }}" class="btn-back-enhanced">
        ← Kembali
    </a>

    {{-- =============== FORM UTAMA =============== --}}
    <form action="{{ route('seat.store') }}"
          method="POST"
          enctype="multipart/form-data"
          x-data="manageSeatCreate()"
          x-init="init()"
          @submit="prepareFinalSubmit($event)"
          @remove-seat-item.window="removeItem($event.detail)">

        @csrf

        {{-- simpan tab terakhir (biar pas validation error balik ke tab yang sama) --}}
        <input type="hidden" name="active_tab" x-model="tab">
        <input type="hidden" name="expected_upload_count" :value="expectedUploadCount">
        <input type="hidden" name="draft_id" :value="serverDraftId || ''">
        <input type="hidden" name="draft_client_key" :value="serverDraftClientKey || ''">

        {{-- =============== HEADER CARD =============== --}}
        <div class="header-card-master">
            <div class="header-content-master">
                <img src="{{ asset('rnf-logo.png') }}" class="header-logo-master" width="110" height="110" alt="RNF Logo">

                <div>
                    <h1 class="header-title-master">CREATE CCR – OPERATOR SEAT</h1>
                    <p class="header-subtitle-master">
                        Isi informasi komponen & item kerusakan
                    </p>
                </div>
            </div>
        </div>

        {{-- =============== TABS =============== --}}
        <div class="tabbar">
            <button type="button"
                    class="tabbtn"
                    :class="{ 'active': tab === 'ccr' }"
                    @click="tab='ccr'">
                CCR SEAT
            </button>

            <button type="button"
                    class="tabbtn"
                    :class="{ 'active': tab === 'parts' }"
                    @click="tab='parts'">
                Parts &amp; Labour Worksheet
            </button>

            <button type="button"
                    class="tabbtn"
                    :class="{ 'active': tab === 'detail' }"
                    @click="tab='detail'">
                Detail
            </button>

            <button type="button"
                    class="tabbtn"
                    :class="{ 'active': tab === 'items' }"
                    @click="tab='items'">
                Items
            </button>
        </div>

        <div class="accent-line"></div>

        {{-- =========================================================
        TAB: CCR (INFO + ITEM)
        ========================================================== --}}
        <div x-show="tab === 'ccr'" x-cloak>
            <input type="hidden" name="group_folder" value="Operator Seat">

            <div class="ccr-workspace" :class="sidebarOpen ? 'is-sidebar-open' : 'is-sidebar-closed'">
                <div class="ccr-main-pane">
                    <div class="doc-a4-wrap">
                        <div class="doc-a4">
                    <div class="doc-header-rnf">
                        <div class="doc-header-rnf__row">
                            <div>
                                <img src="{{ asset('ccrrnf.png') }}" alt="RNF">
                            </div>

                            <div class="doc-header-rnf__center">
                                <div class="doc-company">PT. REZEKI NADH FATHAN</div>
                                <div class="doc-company-sub">COMPONENTS REBUILD AND GENERAL SUPPLIER</div>
                                <div class="doc-company-address">
                                    JL. Sangga Buana RT. 35 No 54-B Graha Indah Balikpapan Kalimantan Timur 76126
                                    <br>
                                    Telp : 0542-4563163 email : sales@rnadhfathan.com
                                </div>
                            </div>

                            <div>
                                <img src="{{ asset('engine.jpg') }}" alt="Engine">
                            </div>
                        </div>

                        <div class="doc-header-rnf__line"></div>
                    </div>

                    <table class="doc-info-table">
                        <tr>
                            <td colspan="3" class="doc-title">COMPONENT CONDITION REPORT</td>
                        </tr>
                        <tr>
                            <td colspan="3" class="doc-info-head">COMPONENT INFORMATION:</td>
                        </tr>
                        <tr>
                            <td class="doc-k">COMPONENT</td>
                            <td class="doc-colon">:</td>
                            <td class="doc-v">
                                <input type="text"
                                       name="component"
                                       class="doc-input"
                                       value="{{ old('component', $draftSeedCcrFields['component'] ?? '') }}"
                                       placeholder="Operator Seat">
                            </td>
                        </tr>
                        <tr>
                            <td class="doc-k">MAKE</td>
                            <td class="doc-colon">:</td>
                            <td class="doc-v">
                                <input type="text"
                                       name="make"
                                       id="make_seat"
                                       class="doc-input"
                                       list="make_seat_list"
                                       value="{{ old('make', $draftSeedCcrFields['make'] ?? '') }}"
                                       placeholder="-- pilih make / ketik manual --"
                                       autocomplete="off">
                                <datalist id="make_seat_list">
                                    @foreach ($brands as $b)
                                        <option value="{{ $b }}"></option>
                                    @endforeach
                                </datalist>
                            </td>
                        </tr>
                        <tr>
                            <td class="doc-k">UNIT</td>
                            <td class="doc-colon">:</td>
                            <td class="doc-v">
                                <input type="text" name="unit" class="doc-input" value="{{ old('unit', $draftSeedCcrFields['unit'] ?? '') }}">
                            </td>
                        </tr>
                        <tr>
                            <td class="doc-k">MODEL</td>
                            <td class="doc-colon">:</td>
                            <td class="doc-v">
                                <input type="text" name="model" class="doc-input" value="{{ old('model', $draftSeedCcrFields['model'] ?? '') }}">
                            </td>
                        </tr>
                        <tr>
                            <td class="doc-k">WO / PR</td>
                            <td class="doc-colon">:</td>
                            <td class="doc-v">
                                <input type="text" name="wo_pr" class="doc-input" value="{{ old('wo_pr', $draftSeedCcrFields['wo_pr'] ?? '') }}">
                            </td>
                        </tr>
                        <tr>
                            <td class="doc-k">CUSTOMER</td>
                            <td class="doc-colon">:</td>
                            <td class="doc-v">
                                <input type="text"
                                       name="customer"
                                       id="customer_seat"
                                       class="doc-input"
                                       list="customer_seat_list"
                                       value="{{ old('customer', $draftSeedCcrFields['customer'] ?? '') }}"
                                       placeholder="-- pilih customer / ketik manual --"
                                       autocomplete="off">
                                <datalist id="customer_seat_list">
                                    @foreach ($groupedCustomers as $group => $list)
                                        @foreach ($list as $cust)
                                            <option value="{{ $cust }}"></option>
                                        @endforeach
                                    @endforeach
                                </datalist>
                            </td>
                        </tr>
                        <tr>
                            <td class="doc-k">INSPECTION DATE</td>
                            <td class="doc-colon">:</td>
                            <td class="doc-v">
                                <input type="date"
                                       name="inspection_date"
                                       class="doc-input"
                                       value="{{ old('inspection_date', $draftSeedCcrFields['inspection_date'] ?? '') }}">
                            </td>
                        </tr>
                    </table>

                    <table class="doc-main-head">
                        <tr>
                            <td class="doc-main-title">
                                Part Number -Name – Modifier Code
                                <br>
                                (RH/LH/Upper/Lower etc)
                                <br>
                                – Notes Identify Type, Amount, Location of Wear,
                                <br>
                                Deposits, Damage, Fractures etc.
                            </td>
                            <td class="doc-main-title">
                                OBSERVATION
                                <br>
                                To Help Explain Progression/Stage of Failure,
                                <br>
                                Use – Photos/Parts Book Illustration.
                            </td>
                        </tr>
                    </table>

                    <template x-for="(key, idx) in newItems" :key="key">
                        <div class="doc-item-block"
                             x-data="itemEditor(key, 'items['+key+']', $el)"
                             data-photos="[]">
                            <table class="doc-item-table">
                                <tr>
                                    <td class="doc-item-left"
                                        @dragover.prevent.stop
                                        @drop.prevent.stop>
                                        <div class="doc-item-rowhead">
                                            <span>Item #<b x-text="idx + 1"></b></span>
                                            <button type="button"
                                                    class="doc-btn doc-btn--danger"
                                                    @click="$dispatch('remove-seat-item', key)">
                                                Hapus Item
                                            </button>
                                        </div>
                                        <textarea class="doc-textarea"
                                                  rows="10"
                                                  :name="'items['+key+'][description]'"
                                                  x-model="descriptions[key]"
                                                  @dragover.prevent.stop
                                                  @drop.prevent.stop
                                                  placeholder="- Tulis item temuan / deskripsi"></textarea>
                                    </td>

                                <td class="doc-item-right">
                                        <div class="doc-dropzone"
                                             @dragover.prevent
                                             @drop.prevent="handleMixedDrop($event)"
                                             @click.self="openFile()">
                                            <p class="doc-dropzone-hint">Drop foto di sini, klik upload, atau seret dari sidebar.</p>

                                            <div class="preview-container si-thumbs">
                                                <template x-for="(file, i) in newPhotos" :key="'nw'+i">
                                                    <div class="si-thumb-wrap">
                                                        <button type="button"
                                                                class="si-thumb"
                                                                @click.stop.prevent="openPreview(file.preview, 'Photo')">
                                                            <img :src="file.preview" alt="Preview foto item">
                                                        </button>
                                                        <button type="button"
                                                                class="si-thumb-x"
                                                                @click.stop="removeNewPhoto(i)">×</button>
                                                    </div>
                                                </template>
                                            </div>

                                            <input type="file"
                                                   class="hidden"
                                                   multiple
                                                   accept="image/*"
                                                   x-ref="fileInput"
                                                   :name="'items['+key+'][photos][]'"
                                                   @change="handleFileSelect($event)">
                                        </div>

                                        <small class="doc-photo-help">
                                            Maksimal 10 foto per item, ukuran maks 5 MB per foto.
                                        </small>
                                    </td>
                                </tr>
                            </table>

                            <div class="si-modal"
                                 x-show="preview.open"
                                 x-transition
                                 @keydown.escape.window="closePreview()"
                                 x-cloak>
                                <div class="si-modal__backdrop" @click="closePreview()"></div>
                                <button type="button" class="si-modal__x" @click="closePreview()">×</button>
                                <img class="si-modal__img" :src="preview.url" :alt="preview.title || 'Preview'">
                            </div>
                        </div>
                    </template>

                    <div class="doc-bottom-action">
                        <button type="button" class="doc-btn doc-btn--primary" @click="addItem()">+ Tambah Item</button>
                    </div>
                </div>
                    </div>
                </div>

                <aside class="ccr-side-pane">
                    <button type="button"
                            class="ccr-side-toggle"
                            :class="sidebarOpen ? 'is-open' : 'is-closed'"
                            @click="toggleSidebar()"
                            :aria-label="sidebarOpen ? 'Tutup sidebar' : 'Buka sidebar'">
                        <span x-text="sidebarOpen ? '→' : '←'"></span>
                    </button>

                    <div class="ccr-side-content" x-show="sidebarOpen" x-transition.opacity>
                        <div class="ccr-side-card">
                            <div class="ccr-autosave-row">
                                <span class="ccr-autosave-pill"
                                      :class="'is-' + ccrSaveState"
                                      x-text="ccrSaveStatus"></span>
                                <span class="ccr-autosave-label">AutoSave ON</span>
                            </div>

                            <div class="ccr-folder-picker">
                                <label for="group_folder_seat_sidebar">Group Folder</label>
                                <input type="text"
                                       id="group_folder_seat_sidebar"
                                       class="ccr-folder-picker-input"
                                       value="Operator Seat"
                                       readonly>
                            </div>

                            <div class="ccr-side-actions">
                                <button type="button" class="doc-btn doc-btn--primary" @click="addItem()">+ Tambah Item</button>
                                <button type="button"
                                        class="doc-btn doc-btn--danger"
                                        :disabled="!canRemoveLastItem()"
                                        @click="removeLastItem()">
                                    Hapus Item Terakhir
                                </button>
                                <button type="button"
                                        class="doc-btn doc-btn--ghost"
                                        @click="duplicateLastItem()"
                                        :disabled="newItems.length < 1">
                                    Duplikat Item Terakhir
                                </button>
                            </div>

                            <div class="doc-count">Total Item: <b x-text="newItems.length"></b></div>
                        </div>

                        <div class="ccr-side-card">
                            <div class="ccr-side-head">
                                <div class="ccr-side-title">Foto Sementara</div>
                                <span class="ccr-side-counter" x-text="stagingPhotos.length + ' / ' + stagingMaxPhotos"></span>
                            </div>
                            <p class="ccr-side-subtitle">Simpan dulu di sini, lalu drag ke area OBSERVATION item.</p>

                            <div class="ccr-staging-drop"
                                 @dragover.prevent
                                 @drop.prevent="handleStagingDrop($event)"
                                 @click="openStagingPicker()">
                                <div class="ccr-staging-note">Drop foto di sini atau klik upload</div>

                                <div class="si-thumbs">
                                    <template x-for="p in stagingPhotos" :key="p.id">
                                        <div class="si-thumb-wrap">
                                            <button type="button"
                                                    class="si-thumb ccr-staging-thumb"
                                                    draggable="true"
                                                    @dragstart="startStagingDrag(p.id, $event)"
                                                    @dragend="endStagingDrag()"
                                                    @click.stop.prevent="openStagingPreview(p.preview, p.name || 'Photo')">
                                                <img :src="p.preview" :alt="p.name || 'Photo'">
                                            </button>
                                            <button type="button" class="si-thumb-x" @click.stop="removeStagingPhoto(p.id)">×</button>
                                        </div>
                                    </template>
                                </div>

                                <input type="file"
                                       class="hidden"
                                       x-ref="stagingInput"
                                       multiple
                                       accept="image/*"
                                       @change="handleStagingInput($event)">
                            </div>

                            <button type="button"
                                    class="doc-btn doc-btn--ghost ccr-side-clear-btn"
                                    :disabled="stagingPhotos.length === 0"
                                    @click="clearStagingPhotos()">
                                Hapus Semua Foto Sementara
                            </button>

                            <p class="ccr-side-footnote">Hanya file gambar yang didukung untuk area ini.</p>
                        </div>
                    </div>
                </aside>
            </div>

            <div class="si-modal" x-show="stagingPreview.open" x-transition x-cloak>
                <div class="si-modal__backdrop" @click="closeStagingPreview()"></div>
                <button type="button" class="si-modal__x" @click="closeStagingPreview()">×</button>
                <img class="si-modal__img" :src="stagingPreview.url" :alt="stagingPreview.title || 'Preview'">
            </div>
        </div> {{-- end tab: ccr --}}

        {{-- =========================================================
        TAB: PARTS
        ========================================================== --}}
        @include('seat.partials.parts_worksheet', [
            'draftSeedPartsPayload' => $draftSeedPartsPayload,
            'skipLocalDraftLoad' => $activeDraftId !== '',
        ])

        {{-- =========================================================
        TAB: DETAIL
        ========================================================== --}}
        @include('seat.partials.detail_worksheet', [
            'draftSeedDetailPayload' => $draftSeedDetailPayload,
            'skipLocalDraftLoad' => $activeDraftId !== '',
        ])

        {{-- =========================================================
        TAB: ITEMS MASTER (SEAT)
        ========================================================== --}}
        @include('seat.partials.items_worksheet', [
            'draftSeedItemsPayload' => $draftSeedItemsPayload,
            'skipLocalDraftLoad' => $activeDraftId !== '',
        ])

        {{-- =============== SUBMIT (tampil di semua tab) =============== --}}
        <div class="seat-submit-wrap">
            <button type="submit" class="seat-submit-btn">
                Simpan CCR Seat
            </button>
        </div>

    </form>

</div>


{{-- =============== ALPINE JS (STYLE LOGIC SAMA DENGAN EDIT-SEAT) =============== --}}
<script>
function itemEditor(itemKey, namePrefix, el) {

    const MAX_PHOTOS = 10;
    let oldPhotos = [];

    try { oldPhotos = JSON.parse(el.dataset.photos || '[]'); }
    catch (e) { oldPhotos = []; }

    return {
        itemKey: String(itemKey ?? ''),
        namePrefix,
        existingPhotos: oldPhotos.map(p => ({ id:p.id, url:p.url, deleted:false })),
        newPhotos: [],
        preview: { open:false, url:'', title:'' },

        init() {
            this.pullFilesFromParent();
            window.addEventListener('seat-create-ccr-item-photos-sync', () => {
                this.pullFilesFromParent();
            });
        },

        totalPhotos() {
            return this.existingPhotos.filter(p => !p.deleted).length + this.newPhotos.length;
        },

        pullFilesFromParent() {
            if (!this.itemKey || typeof window.__seatCreateGetItemPhotos !== 'function') return;
            const files = window.__seatCreateGetItemPhotos(this.itemKey);
            if (!Array.isArray(files)) return;
            this.setFiles(files, false);
        },

        notifyParentPhotosChanged() {
            if (!this.itemKey || typeof window.__seatCreateSyncItemPhotos !== 'function') return;
            const files = this.newPhotos.map((p) => p.file).filter((f) => f instanceof File);
            window.__seatCreateSyncItemPhotos(this.itemKey, files);
        },

        setFiles(files, notifyParent = true) {
            this.newPhotos.forEach((p) => {
                if (p && p.preview) {
                    try { URL.revokeObjectURL(p.preview); } catch (e) {}
                }
            });

            const next = [];
            for (const f of (Array.isArray(files) ? files : [])) {
                if (!(f instanceof File)) continue;
                if (next.length >= MAX_PHOTOS) break;
                next.push({ file: f, preview: URL.createObjectURL(f) });
            }

            this.newPhotos = next;
            this.syncInputFiles();
            if (notifyParent) this.notifyParentPhotosChanged();
        },

        openFile() {
            this.$refs.fileInput.click();
        },

        handleFileSelect(event) {
            const files = Array.from(event.target.files || []);
            this.addFiles(files);
        },

        handleDrop(event) {
            const files = Array.from(event.dataTransfer.files || [])
                .filter(f => (f.type || '').startsWith('image/'));
            this.addFiles(files);
        },

        handleMixedDrop(event) {
            const dt = event.dataTransfer;
            if (!dt) return;

            const types = Array.from(dt.types || []);
            const hasStagingMarker = types.includes('application/x-ccr-seat-staging');
            const stagedId = hasStagingMarker && typeof window.__seatCreateGetDraggingStagingId === 'function'
                ? window.__seatCreateGetDraggingStagingId()
                : '';

            if (stagedId && typeof window.__seatCreateTakeStagingPhoto === 'function') {
                if (this.totalPhotos() >= MAX_PHOTOS) {
                    alert('Maksimal ' + MAX_PHOTOS + ' foto per item.');
                    return;
                }
                const staged = window.__seatCreateTakeStagingPhoto(stagedId);
                if (staged && staged.file) {
                    this.addFiles([staged.file]);
                }
            }

            const files = Array.from(dt.files || [])
                .filter(f => (f.type || '').startsWith('image/'));
            if (files.length) this.addFiles(files);
        },

        addFiles(files) {
            for (let f of files) {
                if (this.totalPhotos() >= MAX_PHOTOS) {
                    alert('Maksimal ' + MAX_PHOTOS + ' foto per item.');
                    break;
                }
                this.newPhotos.push({
                    file: f,
                    preview: URL.createObjectURL(f)
                });
            }
            this.syncInputFiles();
            this.notifyParentPhotosChanged();
        },

        removeNewPhoto(i) {
            const removed = this.newPhotos.splice(i, 1);
            const p = removed && removed[0] ? removed[0] : null;
            if (p && p.preview) {
                try { URL.revokeObjectURL(p.preview); } catch (e) {}
            }
            this.syncInputFiles();
            this.notifyParentPhotosChanged();
        },

        openPreview(url, title = '') {
            if (!url) return;
            this.preview.url = url;
            this.preview.title = title || 'Photo';
            this.preview.open = true;
        },

        closePreview() {
            this.preview.open = false;
            this.preview.url = '';
            this.preview.title = '';
        },

        syncInputFiles() {
            const dt = new DataTransfer();
            this.newPhotos.forEach(p => dt.items.add(p.file));
            this.$refs.fileInput.files = dt.files;
        }
    };
}

function manageSeatCreate() {

    // repopulate deskripsi saat validation error (foto tidak bisa balik)
    let oldItems = @json(old('items'));
    const seedRows = @json($draftSeedCcrRows);
    if (!Array.isArray(oldItems) || oldItems.length === 0) {
        if (Array.isArray(seedRows) && seedRows.length) {
            oldItems = seedRows.map((r) => ({ description: String((r && r.description) || '') }));
        } else {
            oldItems = [{ description: '' }];
        }
    }
    const hasOldInput = @json(session()->hasOldInput());
    const draftUserId = @json((string) (auth()->id() ?? 'guest'));
    const ccrDraftKey = `seat:create:ccr:u:${draftUserId}`;
    const draftClientKeyStorage = `ccr:create:server-key:u:${draftUserId}:type:seat`;
    const draftUpsertUrl = @json(route('ccr.drafts.upsert'));
    const activeDraftId = @json($activeDraftId);
    const activeDraftClientKey = @json($activeDraftClientKey);

    const keys = oldItems.map((_, idx) => idx);

    const descMap = {};
    oldItems.forEach((it, idx) => {
        descMap[idx] = (it && it.description) ? it.description : '';
    });

    return {
        tab: @json(old('active_tab', 'ccr')),

        newItems: keys.length ? keys : [0],
        counter: keys.length ? keys.length : 1,
        descriptions: descMap,
        sidebarOpen: true,
        stagingPhotos: [],
        stagingDragId: null,
        stagingPreview: { open:false, url:'', title:'' },
        stagingMaxPhotos: 400,
        ccrDraftKey,
        ccrDraftVersion: 1,
        ccrDraftMaxAgeMs: 1000 * 60 * 60 * 24 * 45,
        ccrDraftChecked: false,
        ccrHasOldInput: !!hasOldInput,
        ccrSaveTimer: null,
        ccrSaveState: 'saved',
        ccrSaveStatus: 'Auto-saved --:--:--',
        ccrLastSavedHash: '',
        itemPhotoFilesByKey: {},
        photoDraftVersion: 1,
        photoDraftStoreName: 'seatCreatePhotoDrafts',
        photoDraftDbName: 'seatCreateDraftDb',
        photoDraftEnabled: false,
        photoDraftRestoreDone: false,
        photoDraftMaxBytes: 120 * 1024 * 1024,
        photoDraftMaxFiles: 400,
        photoSaveTimer: null,
        photoLastSavedHash: '',
        draftType: 'seat',
        draftUpsertUrl,
        serverDraftId: activeDraftId || '',
        serverDraftClientKey: activeDraftClientKey || '',
        draftClientKeyStorage,
        draftSectionTimers: {},
        draftSectionPending: {},
        draftSectionEventsBound: false,
        isFinalSubmitting: false,
        expectedUploadCount: 0,
        maxFileUploads: @js(max(1, (int) ini_get('max_file_uploads'))),

        init(){
            if (!this.tab) this.tab = 'ccr';

            this.$watch('tab', (val) => {
                if (val === 'ccr') {
                    this.$nextTick(() => {
                        this.restoreCcrDraftIfEligible();
                    });
                    return;
                }
                this.flushCcrAutosave(true);
            });
            if (this.tab === 'ccr') {
                this.$nextTick(() => {
                    this.restoreCcrDraftIfEligible();
                });
            }
            this.bootstrapServerDraftClientKey();
            if (this.serverDraftId) {
                this.clearLocalCcrDraft();
            }
            this.bindCcrAutosave();
            this.bindServerDraftSectionEvents();

            window.__seatCreateTakeStagingPhoto = (id) => this.takeStagingPhoto(id);
            window.__seatCreateGetDraggingStagingId = () => this.stagingDragId;
            window.__seatCreateSyncItemPhotos = (itemKey, files) => this.syncItemPhotosFromEditor(itemKey, files);
            window.__seatCreateGetItemPhotos = (itemKey) => this.getItemPhotosForEditor(itemKey);
            window.addEventListener('beforeunload', () => {
                if (this.isSubmitInProgress()) return;
                this.flushCcrAutosave(true);
                this.flushPendingServerDraftSections(true);
                this.flushPhotoDraftSave(true);
                this.revokeStagingPreviews();
            });
            window.addEventListener('pagehide', () => {
                if (this.isSubmitInProgress()) return;
                this.flushCcrAutosave(true);
                this.flushPendingServerDraftSections(true);
                this.flushPhotoDraftSave(true);
            });
            document.addEventListener('visibilitychange', () => {
                if (this.isSubmitInProgress()) return;
                if (document.visibilityState === 'hidden') {
                    this.flushCcrAutosave(true);
                    this.flushPendingServerDraftSections(true);
                    this.flushPhotoDraftSave(true);
                }
            });
        },

        isSubmitInProgress() {
            const map = (window.__ccrCreateSubmitInProgress && typeof window.__ccrCreateSubmitInProgress === 'object')
                ? window.__ccrCreateSubmitInProgress
                : {};
            return !!(this.isFinalSubmitting || map[this.draftType]);
        },

        markSubmitInProgress(flag = true) {
            this.isFinalSubmitting = !!flag;
            if (!window.__ccrCreateSubmitInProgress || typeof window.__ccrCreateSubmitInProgress !== 'object') {
                window.__ccrCreateSubmitInProgress = {};
            }
            window.__ccrCreateSubmitInProgress[this.draftType] = !!flag;
        },

        prepareFinalSubmit(event) {
            this.expectedUploadCount = this.countSelectedUploadFiles();
            if (!this.validateUploadCountBeforeSubmit()) {
                if (event && typeof event.preventDefault === 'function') {
                    event.preventDefault();
                }
                this.markSubmitInProgress(false);
                return;
            }

            this.markSubmitInProgress(true);
            if (this.ccrSaveTimer) {
                clearTimeout(this.ccrSaveTimer);
                this.ccrSaveTimer = null;
            }
            if (this.photoSaveTimer) {
                clearTimeout(this.photoSaveTimer);
                this.photoSaveTimer = null;
            }
            Object.values(this.draftSectionTimers || {}).forEach((timerId) => {
                if (timerId) clearTimeout(timerId);
            });
            this.draftSectionTimers = {};
            this.draftSectionPending = {};
        },

        countSelectedUploadFiles() {
            if (!this.$el) return 0;
            let total = 0;
            const inputs = this.$el.querySelectorAll('input[type=\"file\"]');
            inputs.forEach((input) => {
                if (!input || !input.files) return;
                total += input.files.length || 0;
            });
            return total;
        },

        validateUploadCountBeforeSubmit() {
            const limit = Number(this.maxFileUploads || 0);
            if (!Number.isFinite(limit) || limit <= 0) return true;

            const total = this.countSelectedUploadFiles();
            if (total <= limit) return true;

            alert(
                'Jumlah foto yang akan dikirim (' + total + ') melebihi batas server max_file_uploads (' + limit + ').\\n\\n' +
                'Silakan kurangi jumlah foto per sekali simpan, atau naikkan max_file_uploads di konfigurasi PHP server.'
            );
            return false;
        },

        bindCcrAutosave() {
            if (this.__ccrAutosaveBound) return;
            this.__ccrAutosaveBound = true;

            const isTrackedField = (el) => {
                if (!el || typeof el.matches !== 'function') return false;
                if (el.classList?.contains('doc-textarea')) return true;
                return el.matches('[name="component"], [name="make"], [name="unit"], [name="model"], [name="wo_pr"], [name="customer"], [name="inspection_date"]');
            };

            const onFieldChange = (event) => {
                if (this.tab !== 'ccr') return;
                if (!isTrackedField(event.target)) return;
                this.queueCcrAutosave();
            };

            this.$el.addEventListener('input', onFieldChange);
            this.$el.addEventListener('change', onFieldChange);
        },

        bindServerDraftSectionEvents() {
            if (this.draftSectionEventsBound) return;
            this.draftSectionEventsBound = true;

            window.addEventListener('ccr:create-draft-section', (event) => {
                if (this.isSubmitInProgress()) return;
                const detail = event && event.detail ? event.detail : {};
                if ((detail.type || '') !== this.draftType) return;
                const section = String(detail.section || '');
                const payload = (detail.payload && typeof detail.payload === 'object') ? detail.payload : {};
                if (!section || !payload || typeof payload !== 'object') return;
                this.queueServerDraftSection(section, payload, 500);
            });
        },

        generateServerDraftClientKey() {
            return 'ck_' + Date.now().toString(36) + '_' + Math.random().toString(36).slice(2, 10);
        },

        persistServerDraftClientKey() {
            if (!this.draftClientKeyStorage || !this.serverDraftClientKey) return;
            try {
                localStorage.setItem(this.draftClientKeyStorage, String(this.serverDraftClientKey));
            } catch (e) {}
        },

        bootstrapServerDraftClientKey() {
            let key = String(this.serverDraftClientKey || '').trim();
            if (!key && this.draftClientKeyStorage) {
                try {
                    key = String(localStorage.getItem(this.draftClientKeyStorage) || '').trim();
                } catch (e) {
                    key = '';
                }
            }
            if (!key) key = this.generateServerDraftClientKey();
            this.serverDraftClientKey = key;
            this.persistServerDraftClientKey();
        },

        syncItemPhotosFromEditor(itemKey, files) {
            const key = String(itemKey ?? '');
            if (!key) return;

            const safeFiles = (Array.isArray(files) ? files : [])
                .filter((f) => f instanceof File);

            if (safeFiles.length) {
                this.itemPhotoFilesByKey[key] = safeFiles;
            } else {
                delete this.itemPhotoFilesByKey[key];
            }

            this.queuePhotoDraftSave(450, true);
        },

        getItemPhotosForEditor(itemKey) {
            const key = String(itemKey ?? '');
            if (!key) return [];
            const files = this.itemPhotoFilesByKey[key];
            return Array.isArray(files) ? files.slice() : [];
        },

        photoSignatureOfFile(file) {
            if (!(file instanceof File)) return '';
            return [
                String(file.name || ''),
                Number(file.size || 0),
                Number(file.lastModified || 0),
                String(file.type || ''),
            ].join(':');
        },

        buildPhotoDraftHash() {
            const stagingPart = this.stagingPhotos
                .map((p) => this.photoSignatureOfFile(p?.file))
                .filter(Boolean)
                .join('|');

            const activeKeys = this.newItems.map((k) => String(k));
            const itemPart = activeKeys.map((key) => {
                const files = Array.isArray(this.itemPhotoFilesByKey[key]) ? this.itemPhotoFilesByKey[key] : [];
                const sig = files.map((f) => this.photoSignatureOfFile(f)).filter(Boolean).join(',');
                return key + '=' + sig;
            }).join('|');

            return stagingPart + '##' + itemPart;
        },

        photoFileToDraftEntry(file) {
            return {
                name: String(file.name || 'photo'),
                type: String(file.type || 'application/octet-stream'),
                size: Number(file.size || 0),
                lastModified: Number(file.lastModified || Date.now()),
                blob: file,
            };
        },

        photoDraftEntryToFile(entry) {
            if (!entry || !(entry.blob instanceof Blob)) return null;
            const name = String(entry.name || 'photo');
            const type = String(entry.type || entry.blob.type || 'application/octet-stream');
            const lastModified = Number(entry.lastModified || Date.now());
            try {
                return new File([entry.blob], name, { type, lastModified });
            } catch (e) {
                const fallback = entry.blob;
                fallback.name = name;
                fallback.lastModified = lastModified;
                fallback.type = type;
                return fallback;
            }
        },

        buildPhotoDraftRecord() {
            const staging = [];
            const items = {};
            let totalBytes = 0;
            let totalFiles = 0;

            const collectFile = (file, bucket) => {
                if (!(file instanceof File)) return;
                const size = Number(file.size || 0);
                totalFiles += 1;
                totalBytes += Math.max(size, 0);
                bucket.push(this.photoFileToDraftEntry(file));
            };

            for (const p of this.stagingPhotos) {
                collectFile(p?.file, staging);
            }

            const activeKeys = this.newItems.map((k) => String(k));
            for (const key of activeKeys) {
                const list = Array.isArray(this.itemPhotoFilesByKey[key]) ? this.itemPhotoFilesByKey[key] : [];
                const bucket = [];
                for (const f of list) collectFile(f, bucket);
                if (bucket.length) items[key] = bucket;
            }

            return {
                id: this.ccrDraftKey,
                v: this.photoDraftVersion,
                ts: Date.now(),
                total_bytes: totalBytes,
                total_files: totalFiles,
                staging,
                items,
            };
        },

        openPhotoDraftDb() {
            if (this.__photoDraftDbPromise) return this.__photoDraftDbPromise;
            if (!window.indexedDB) return Promise.resolve(null);

            this.__photoDraftDbPromise = new Promise((resolve, reject) => {
                const req = window.indexedDB.open(this.photoDraftDbName, 1);
                req.onupgradeneeded = () => {
                    const db = req.result;
                    if (!db.objectStoreNames.contains(this.photoDraftStoreName)) {
                        db.createObjectStore(this.photoDraftStoreName, { keyPath: 'id' });
                    }
                };
                req.onsuccess = () => resolve(req.result);
                req.onerror = () => reject(req.error || new Error('open indexeddb failed'));
            });

            return this.__photoDraftDbPromise;
        },

        async readPhotoDraftRecord() {
            const db = await this.openPhotoDraftDb();
            if (!db || !this.ccrDraftKey) return null;

            return await new Promise((resolve, reject) => {
                const tx = db.transaction(this.photoDraftStoreName, 'readonly');
                const store = tx.objectStore(this.photoDraftStoreName);
                const req = store.get(this.ccrDraftKey);
                req.onsuccess = () => resolve(req.result || null);
                req.onerror = () => reject(req.error || new Error('read draft failed'));
            });
        },

        async writePhotoDraftRecord(record) {
            const db = await this.openPhotoDraftDb();
            if (!db || !this.ccrDraftKey) return;

            return await new Promise((resolve, reject) => {
                const tx = db.transaction(this.photoDraftStoreName, 'readwrite');
                const store = tx.objectStore(this.photoDraftStoreName);
                const req = store.put(record);
                req.onsuccess = () => resolve(true);
                req.onerror = () => reject(req.error || new Error('write draft failed'));
            });
        },

        async deletePhotoDraftRecord() {
            const db = await this.openPhotoDraftDb();
            if (!db || !this.ccrDraftKey) return;

            return await new Promise((resolve, reject) => {
                const tx = db.transaction(this.photoDraftStoreName, 'readwrite');
                const store = tx.objectStore(this.photoDraftStoreName);
                const req = store.delete(this.ccrDraftKey);
                req.onsuccess = () => resolve(true);
                req.onerror = () => reject(req.error || new Error('delete draft failed'));
            });
        },

        queuePhotoDraftSave(delay = 1200, force = false) {
            if (this.isSubmitInProgress()) return;
            if (!window.indexedDB || !this.ccrDraftKey) return;
            if (this.photoSaveTimer) clearTimeout(this.photoSaveTimer);
            this.photoSaveTimer = setTimeout(() => this.flushPhotoDraftSave(force), delay);
        },

        flushPhotoDraftSave(force = false) {
            if (this.isSubmitInProgress()) return;
            if (!window.indexedDB || !this.ccrDraftKey) return;
            if (this.photoSaveTimer) {
                clearTimeout(this.photoSaveTimer);
                this.photoSaveTimer = null;
            }

            const hash = this.buildPhotoDraftHash();
            if (!force && hash === this.photoLastSavedHash) return;

            const record = this.buildPhotoDraftRecord();
            if (!record.total_files) {
                this.photoLastSavedHash = '';
                this.deletePhotoDraftRecord().catch(() => {});
                return;
            }

            if (record.total_files > this.photoDraftMaxFiles || record.total_bytes > this.photoDraftMaxBytes) {
                this.setCcrSaveState('error', 'Draft foto terlalu besar, klik Simpan CCR Seat');
                return;
            }

            this.writePhotoDraftRecord(record)
                .then(() => {
                    this.photoLastSavedHash = hash;
                    this.setCcrSaveState('saved', 'Auto-saved ' + this.formatCcrTime(record.ts));
                })
                .catch((e) => {
                    console.warn('seat photo draft save failed', e);
                    this.setCcrSaveState('error', 'Autosave foto gagal');
                });
        },

        async restorePhotoDraftIfEligible() {
            if (this.photoDraftRestoreDone) return;
            this.photoDraftRestoreDone = true;

            if (!window.indexedDB || !this.ccrDraftKey) return;

            let record = null;
            try {
                record = await this.readPhotoDraftRecord();
            } catch (e) {
                console.warn('seat photo draft read failed', e);
                return;
            }

            if (!record || typeof record !== 'object') return;

            const ts = Number(record.ts || 0);
            if (ts > 0 && (Date.now() - ts) > this.ccrDraftMaxAgeMs) {
                try { await this.deletePhotoDraftRecord(); } catch (e) {}
                return;
            }

            this.revokeStagingPreviews();

            const restoredStaging = [];
            for (const item of (Array.isArray(record.staging) ? record.staging : [])) {
                const file = this.photoDraftEntryToFile(item);
                if (!(file instanceof File)) continue;
                restoredStaging.push({
                    id: 'stg_' + Date.now().toString(36) + '_' + Math.random().toString(36).slice(2, 8),
                    file,
                    name: String(file.name || ''),
                    preview: URL.createObjectURL(file),
                });
            }
            this.stagingPhotos = restoredStaging;

            const rebuilt = {};
            const rawItems = (record.items && typeof record.items === 'object') ? record.items : {};
            for (const [key, list] of Object.entries(rawItems)) {
                const files = [];
                for (const entry of (Array.isArray(list) ? list : [])) {
                    const file = this.photoDraftEntryToFile(entry);
                    if (file instanceof File) files.push(file);
                }
                if (files.length) rebuilt[String(key)] = files;
            }
            this.itemPhotoFilesByKey = rebuilt;
            this.photoLastSavedHash = this.buildPhotoDraftHash();

            this.$nextTick(() => {
                window.dispatchEvent(new CustomEvent('seat-create-ccr-item-photos-sync'));
            });

            const totalItemFiles = Object.values(rebuilt).reduce((sum, arr) => {
                return sum + (Array.isArray(arr) ? arr.length : 0);
            }, 0);
            if (restoredStaging.length || totalItemFiles) {
                this.setCcrSaveState('saved', 'Auto-saved ' + this.formatCcrTime(ts || Date.now()));
            }
        },

        getCcrFieldEl(name) {
            if (!this.$el) return null;
            return this.$el.querySelector(`[name="${name}"]`);
        },

        getCcrFieldValue(name) {
            const el = this.getCcrFieldEl(name);
            if (!el) return '';
            return String(el.value ?? '');
        },

        setCcrFieldValue(name, value) {
            const el = this.getCcrFieldEl(name);
            if (!el) return;
            const nextValue = value == null ? '' : String(value);
            if (el.tomselect) {
                el.tomselect.setValue(nextValue, true);
                return;
            }
            el.value = nextValue;
        },

        isCcrSectionEmpty() {
            const trackedFields = ['component', 'make', 'unit', 'model', 'wo_pr', 'customer', 'inspection_date'];
            const hasAnyField = trackedFields.some((name) => this.getCcrFieldValue(name).trim() !== '');
            const hasAnyDescription = Object.values(this.descriptions || {})
                .some((text) => String(text || '').trim() !== '');

            return !hasAnyField && !hasAnyDescription && this.newItems.length <= 1;
        },

        buildCcrDraftPayload() {
            const fields = {
                component: this.getCcrFieldValue('component'),
                make: this.getCcrFieldValue('make'),
                unit: this.getCcrFieldValue('unit'),
                model: this.getCcrFieldValue('model'),
                wo_pr: this.getCcrFieldValue('wo_pr'),
                customer: this.getCcrFieldValue('customer'),
                inspection_date: this.getCcrFieldValue('inspection_date'),
            };

            const rows = this.newItems.map((key) => ({
                description: String(this.descriptions[key] ?? ''),
            }));

            return {
                v: this.ccrDraftVersion,
                ts: Date.now(),
                fields,
                rows,
            };
        },

        deriveDraftName(payload = null) {
            const p = (payload && typeof payload === 'object') ? payload : this.buildCcrDraftPayload();
            const fields = (p.fields && typeof p.fields === 'object') ? p.fields : {};
            const candidates = [fields.component, fields.unit, fields.wo_pr, fields.customer];
            for (const c of candidates) {
                const text = String(c || '').trim();
                if (text) return text;
            }
            return 'SEAT Draft';
        },

        queueServerDraftSection(section, payload, delay = 700) {
            if (this.isSubmitInProgress()) return;
            if (!this.draftUpsertUrl) return;
            if (!section || !payload || typeof payload !== 'object') return;

            const key = String(section);
            if (!this.serverDraftId && key !== 'ccr') return;
            if (key === 'ccr' && this.isCcrSectionEmpty() && !this.serverDraftId) return;
            if (!this.serverDraftClientKey) this.bootstrapServerDraftClientKey();

            this.draftSectionPending[key] = payload;
            if (this.draftSectionTimers[key]) clearTimeout(this.draftSectionTimers[key]);
            if (delay <= 0) {
                this.flushServerDraftSection(key, null, key === 'ccr');
                return;
            }
            this.draftSectionTimers[key] = setTimeout(() => {
                this.flushServerDraftSection(key);
            }, delay);
        },

        flushPendingServerDraftSections(force = false) {
            if (this.isSubmitInProgress()) return;
            const keys = new Set([
                ...Object.keys(this.draftSectionPending || {}),
                ...Object.keys(this.draftSectionTimers || {}),
            ]);
            keys.forEach((key) => this.flushServerDraftSection(key, null, !!force && key === 'ccr'));
        },

        async flushServerDraftSection(section, payload = null, keepalive = false) {
            if (this.isSubmitInProgress()) return;
            if (!this.draftUpsertUrl) return;
            const key = String(section || '');
            if (!key) return;

            if (payload && typeof payload === 'object') {
                this.draftSectionPending[key] = payload;
            }
            const finalPayload = this.draftSectionPending[key];
            if (!finalPayload || typeof finalPayload !== 'object') return;

            if (this.draftSectionTimers[key]) {
                clearTimeout(this.draftSectionTimers[key]);
                this.draftSectionTimers[key] = null;
            }

            const csrfMeta = document.querySelector('meta[name="csrf-token"]');
            try {
                const res = await fetch(this.draftUpsertUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfMeta ? String(csrfMeta.content || '') : '',
                    },
                    keepalive: !!keepalive,
                    body: JSON.stringify({
                        draft_id: this.serverDraftId || null,
                        client_key: this.serverDraftClientKey || null,
                        type: this.draftType,
                        section: key,
                        payload: finalPayload,
                        draft_name_auto: this.deriveDraftName(finalPayload),
                    }),
                });

                if (!res.ok) return;
                const json = await res.json().catch(() => ({}));
                if (json && json.ok && json.draft && json.draft.id) {
                    this.serverDraftId = String(json.draft.id);
                }
                if (json && json.ok && json.draft && json.draft.client_key) {
                    this.serverDraftClientKey = String(json.draft.client_key);
                    this.persistServerDraftClientKey();
                }
                if (key === 'ccr' && this.serverDraftId) {
                    this.clearLocalCcrDraft();
                }
                delete this.draftSectionPending[key];
            } catch (e) {
                console.warn('seat create draft save failed', e);
            }
        },

        ccrPayloadHash(payload) {
            return JSON.stringify({
                fields: payload?.fields || {},
                rows: Array.isArray(payload?.rows) ? payload.rows : [],
            });
        },

        formatCcrTime(ts) {
            const d = ts instanceof Date ? ts : new Date(ts);
            if (Number.isNaN(d.getTime())) return '--:--:--';
            return d.toLocaleTimeString('id-ID', { hour12: false });
        },

        setCcrSaveState(state, text) {
            this.ccrSaveState = state;
            this.ccrSaveStatus = text;
        },

        clearLocalCcrDraft() {
            if (!this.ccrDraftKey) return;
            try { localStorage.removeItem(this.ccrDraftKey); } catch (e) {}
        },

        queueCcrAutosave(delay = 900, force = false) {
            if (this.isSubmitInProgress()) return;
            if (!this.ccrDraftKey) return;
            if (this.ccrSaveTimer) clearTimeout(this.ccrSaveTimer);

            this.ccrSaveTimer = setTimeout(() => {
                this.flushCcrAutosave(force);
            }, delay);
        },

        flushCcrAutosave(force = false) {
            if (this.isSubmitInProgress()) return;
            if (!this.ccrDraftKey) return;
            if (this.ccrSaveTimer) {
                clearTimeout(this.ccrSaveTimer);
                this.ccrSaveTimer = null;
            }

            if (this.isCcrSectionEmpty() && !this.serverDraftId) {
                this.clearLocalCcrDraft();
                this.ccrLastSavedHash = '';
                this.setCcrSaveState('saved', 'AutoSave ON');
                return;
            }

            const payload = this.buildCcrDraftPayload();
            const payloadHash = this.ccrPayloadHash(payload);

            if (!force && payloadHash === this.ccrLastSavedHash) return;

            if (this.serverDraftId) {
                this.ccrLastSavedHash = payloadHash;
                this.clearLocalCcrDraft();
                this.setCcrSaveState('saved', 'Auto-saved ' + this.formatCcrTime(payload.ts));
                this.queueServerDraftSection('ccr', payload, force ? 0 : 350);
                return;
            }

            this.setCcrSaveState('saving', 'Saving...');
            try {
                localStorage.setItem(this.ccrDraftKey, JSON.stringify(payload));
                this.ccrLastSavedHash = payloadHash;
                this.setCcrSaveState('saved', 'Auto-saved ' + this.formatCcrTime(payload.ts));
                this.queueServerDraftSection('ccr', payload, force ? 0 : 350);
            } catch (e) {
                console.warn('seat ccr autosave failed', e);
                this.setCcrSaveState('error', 'Autosave gagal (storage penuh)');
            }
        },

        loadCcrDraft() {
            if (!this.ccrDraftKey) return null;
            let raw = null;
            try {
                raw = localStorage.getItem(this.ccrDraftKey);
            } catch (e) {
                console.warn('seat ccr draft read failed', e);
                return null;
            }
            if (!raw) return null;

            try {
                const parsed = JSON.parse(raw);
                if (!parsed || typeof parsed !== 'object') return null;

                const ts = Number(parsed.ts || 0);
                if (ts > 0) {
                    const age = Date.now() - ts;
                    if (age > this.ccrDraftMaxAgeMs) {
                        try { localStorage.removeItem(this.ccrDraftKey); } catch (e) {}
                        return null;
                    }
                }

                if (!Array.isArray(parsed.rows)) parsed.rows = [];
                if (!parsed.fields || typeof parsed.fields !== 'object') parsed.fields = {};
                return parsed;
            } catch (e) {
                console.warn('seat ccr draft parse failed', e);
                return null;
            }
        },

        applyCcrDraft(payload) {
            const fields = payload?.fields || {};
            this.setCcrFieldValue('component', fields.component ?? '');
            this.setCcrFieldValue('make', fields.make ?? '');
            this.setCcrFieldValue('unit', fields.unit ?? '');
            this.setCcrFieldValue('model', fields.model ?? '');
            this.setCcrFieldValue('wo_pr', fields.wo_pr ?? '');
            this.setCcrFieldValue('customer', fields.customer ?? '');
            this.setCcrFieldValue('inspection_date', fields.inspection_date ?? '');

            const rows = Array.isArray(payload?.rows) ? payload.rows : [];
            const safeRows = rows.length ? rows : [{ description: '' }];

            this.newItems = [];
            this.descriptions = {};
            this.counter = 0;

            safeRows.forEach((row) => {
                const key = this.counter++;
                this.newItems.push(key);
                this.descriptions[key] = String(row?.description ?? '');
            });

            if (!this.newItems.length) {
                const key = this.counter++;
                this.newItems = [key];
                this.descriptions[key] = '';
            }
        },

        restoreCcrDraftIfEligible() {
            if (this.ccrDraftChecked) {
                this.restorePhotoDraftIfEligible();
                return;
            }
            this.ccrDraftChecked = true;

            const draft = this.loadCcrDraft();
            if (!draft) {
                this.restorePhotoDraftIfEligible();
                return;
            }

            if (this.ccrHasOldInput) {
                this.ccrLastSavedHash = this.ccrPayloadHash(draft);
                this.setCcrSaveState('saved', 'Draft lokal tersedia');
                this.restorePhotoDraftIfEligible();
                return;
            }

            if (!this.isCcrSectionEmpty()) {
                this.ccrLastSavedHash = this.ccrPayloadHash(draft);
                this.setCcrSaveState('saved', 'Draft lokal tersedia');
                this.restorePhotoDraftIfEligible();
                return;
            }

            this.applyCcrDraft(draft);
            this.ccrLastSavedHash = this.ccrPayloadHash(draft);
            this.setCcrSaveState('saved', 'Auto-saved ' + this.formatCcrTime(draft.ts));
            this.restorePhotoDraftIfEligible();
        },

        addItem() {
            const key = this.counter++;
            this.newItems.push(key);
            this.descriptions[key] = '';
            this.queueCcrAutosave(300, true);
            this.queuePhotoDraftSave(450, true);
        },

        canRemoveLastItem() {
            return this.newItems.length > 1;
        },

        removeLastItem() {
            if (!this.canRemoveLastItem()) return;
            const lastKey = this.newItems[this.newItems.length - 1];
            this.removeItem(lastKey);
        },

        duplicateLastItem() {
            if (!this.newItems.length) return;
            const srcKey = this.newItems[this.newItems.length - 1];
            const key = this.counter++;
            this.newItems.push(key);
            this.descriptions[key] = this.descriptions[srcKey] || '';
            this.queueCcrAutosave(300, true);
            this.queuePhotoDraftSave(450, true);
        },

        toggleSidebar() {
            this.sidebarOpen = !this.sidebarOpen;
        },

        openStagingPicker() {
            this.$refs.stagingInput?.click();
        },

        handleStagingInput(event) {
            const files = Array.from(event.target.files || []);
            this.addStagingFiles(files);
            event.target.value = '';
        },

        handleStagingDrop(event) {
            const files = Array.from(event.dataTransfer.files || []);
            this.addStagingFiles(files);
        },

        addStagingFiles(files) {
            let hitLimit = false;
            let invalidType = false;
            let changed = false;
            for (const f of files) {
                const type = String(f.type || '');
                const name = String(f.name || '');

                if (type.startsWith('image/')) {
                    if (this.stagingPhotos.length >= this.stagingMaxPhotos) {
                        hitLimit = true;
                        continue;
                    }

                    const duplicate = this.stagingPhotos.some((p) => {
                        const pf = p && p.file ? p.file : null;
                        return !!pf
                            && String(p.name || '') === name
                            && Number(pf.size || 0) === Number(f.size || 0)
                            && Number(pf.lastModified || 0) === Number(f.lastModified || 0);
                    });
                    if (duplicate) continue;

                    this.stagingPhotos.push({
                        id: 'stg_' + Date.now().toString(36) + '_' + Math.random().toString(36).slice(2, 8),
                        file: f,
                        name,
                        preview: URL.createObjectURL(f),
                    });
                    changed = true;
                    continue;
                }
                invalidType = true;
            }

            if (invalidType) {
                alert('Hanya file gambar yang bisa diupload.');
            }
            if (hitLimit) {
                alert('Foto sementara maksimal ' + this.stagingMaxPhotos + ' file.');
            }
            if (changed) {
                this.queuePhotoDraftSave(350, true);
            }
        },

        startStagingDrag(id, event) {
            this.stagingDragId = String(id);
            if (!event || !event.dataTransfer) return;
            event.dataTransfer.effectAllowed = 'copyMove';
            event.dataTransfer.setData('application/x-ccr-seat-staging', '1');
            event.dataTransfer.setData('text/plain', '');
        },

        endStagingDrag() {
            this.stagingDragId = null;
        },

        takeStagingPhoto(id) {
            const idx = this.stagingPhotos.findIndex(p => String(p.id) === String(id));
            if (idx === -1) return null;
            const [picked] = this.stagingPhotos.splice(idx, 1);
            if (!picked) return null;
            this.stagingDragId = null;
            try { URL.revokeObjectURL(picked.preview); } catch (e) {}
            this.queuePhotoDraftSave(350, true);
            return { id: picked.id, file: picked.file, name: picked.name };
        },

        removeStagingPhoto(id) {
            const idx = this.stagingPhotos.findIndex(p => String(p.id) === String(id));
            if (idx === -1) return;
            const [removed] = this.stagingPhotos.splice(idx, 1);
            if (String(this.stagingDragId || '') === String(id)) {
                this.stagingDragId = null;
            }
            if (removed && removed.preview) {
                try { URL.revokeObjectURL(removed.preview); } catch (e) {}
            }
            this.queuePhotoDraftSave(350, true);
        },

        openStagingPreview(url, title = '') {
            if (!url) return;
            this.stagingPreview.url = url;
            this.stagingPreview.title = title || 'Photo';
            this.stagingPreview.open = true;
        },

        closeStagingPreview() {
            this.stagingPreview.open = false;
            this.stagingPreview.url = '';
            this.stagingPreview.title = '';
        },

        revokeStagingPreviews() {
            this.stagingPhotos.forEach((p) => {
                if (p && p.preview) {
                    try { URL.revokeObjectURL(p.preview); } catch (e) {}
                }
            });
        },

        clearStagingPhotos() {
            this.revokeStagingPreviews();
            this.stagingPhotos = [];
            this.closeStagingPreview();
            this.queuePhotoDraftSave(350, true);
        },

        removeItem(key) {
            this.newItems = this.newItems.filter(k => k !== key);
            delete this.descriptions[key];
            delete this.itemPhotoFilesByKey[String(key)];

            // minimal 1 item
            if (this.newItems.length === 0) {
                const newKey = this.counter++;
                this.newItems = [newKey];
                this.descriptions[newKey] = '';
            }

            this.queueCcrAutosave(300, true);
            this.queuePhotoDraftSave(350, true);
        }
    }
}
</script>


{{-- =============== TOMSELECT =============== --}}
{{-- =============== STYLE (SAMA DENGAN EDIT-SEAT, DI-SCOPE BIAR NAV AMAN) =============== --}}
<style>
    .seat-create-page,
    .seat-create-page * ,
    .seat-create-page *::before,
    .seat-create-page *::after{
        box-sizing: border-box;
    }

    .seat-create-page [x-cloak]{ display:none !important; }
    .seat-create-page{
        --ccr-sidebar-width: 390px;
        --ccr-workspace-gap: 20px;
    }

    .seat-create-page .tabbar{ display:flex; gap:10px; flex-wrap:wrap; margin: 0 0 12px; }
    .seat-create-page .tabbtn{
        border:1px solid #cfd3d7;
        background:#f6f7f8;
        padding:10px 14px;
        border-radius:10px;
        font-weight:800;
        cursor:pointer;
        transition:.2s;
        color:#0f172a;
    }
    .seat-create-page .tabbtn.active{ background:#111827; border-color:#111827; color:#fff; }
    .seat-create-page .tabbtn:hover{ transform: translateY(-1px); }


    .seat-create-page .header-card-master {
        background: #ffffff;
        padding: 28px 38px;
        border-radius: 20px;
        box-shadow: 0 4px 14px rgba(0,0,0,0.07);
        margin-top: 10px;
        margin-bottom: 24px;
        overflow:hidden;
        max-width:100%;
    }

    .seat-create-page .header-content-master {
        display: flex;
        align-items: center;
        gap: 26px;
        min-width:0;
    }
    .seat-create-page .header-logo-master { width: 95px; object-fit:contain; flex:0 0 auto; }
    .seat-create-page .header-title-master { font-size: 28px; font-weight: 800; margin:0; }
    .seat-create-page .header-subtitle-master { font-size: 15px; color: #666; margin-top:6px; }

    .seat-create-page .accent-line {
        height: 4px;
        background: #E40505;
        border-radius: 20px;
        margin-bottom: 20px;
    }

    .seat-create-page .ccr-workspace{
        display:grid;
        grid-template-columns:minmax(0, 820px) auto;
        align-items:start;
        justify-content:center;
        gap:var(--ccr-workspace-gap);
        max-width:calc(820px + var(--ccr-sidebar-width) + var(--ccr-workspace-gap));
        margin:0 auto;
    }
    .seat-create-page .ccr-main-pane{
        min-width:0;
        transition:all .22s ease;
    }
    .seat-create-page .ccr-side-pane{
        position:sticky;
        top:82px;
        z-index:15;
        align-self:start;
    }
    .seat-create-page .ccr-side-toggle{
        width:40px;
        height:40px;
        border:none;
        border-radius:999px;
        background:#0f172a;
        color:#fff;
        font-size:17px;
        font-weight:900;
        cursor:pointer;
        box-shadow:0 10px 20px rgba(2,6,23,.28);
        margin-left:auto;
        display:flex;
        align-items:center;
        justify-content:center;
    }
    .seat-create-page .ccr-side-toggle.is-open{
        margin-bottom:10px;
    }
    .seat-create-page .ccr-side-content{
        width:var(--ccr-sidebar-width);
        max-height:calc(100vh - 140px);
        overflow:auto;
        background:#fff;
        border:1px solid #d1d5db;
        border-radius:14px;
        padding:12px;
        box-shadow:0 10px 24px rgba(2,6,23,.10);
    }
    .seat-create-page .ccr-workspace.is-sidebar-closed .ccr-side-content{
        display:none !important;
    }
    .seat-create-page .ccr-workspace.is-sidebar-open .doc-a4-wrap{
        justify-content:flex-start;
    }
    .seat-create-page .ccr-side-card{
        border:1px solid #e2e8f0;
        border-radius:12px;
        background:#f8fafc;
        padding:12px;
        margin-bottom:12px;
    }
    .seat-create-page .ccr-autosave-row{
        display:flex;
        align-items:center;
        gap:8px;
        margin-bottom:10px;
    }
    .seat-create-page .ccr-autosave-pill{
        flex:1 1 auto;
        min-width:0;
        font-size:12px;
        font-weight:900;
        border-radius:999px;
        border:1px solid #d0d7e2;
        padding:7px 12px;
        line-height:1.2;
        white-space:nowrap;
        overflow:hidden;
        text-overflow:ellipsis;
        color:#334155;
        background:#e2e8f0;
    }
    .seat-create-page .ccr-autosave-pill.is-saving{
        background:#ffedd5;
        border-color:#fdba74;
        color:#9a3412;
    }
    .seat-create-page .ccr-autosave-pill.is-saved{
        background:#dbeafe;
        border-color:#bfdbfe;
        color:#1e3a8a;
    }
    .seat-create-page .ccr-autosave-pill.is-error{
        background:#fee2e2;
        border-color:#fecaca;
        color:#991b1b;
    }
    .seat-create-page .ccr-autosave-label{
        font-size:12px;
        font-weight:900;
        color:#475569;
        white-space:nowrap;
    }
    .seat-create-page .ccr-side-head{
        display:flex;
        align-items:center;
        justify-content:space-between;
        gap:8px;
    }
    .seat-create-page .ccr-side-card:last-child{
        margin-bottom:0;
    }
    .seat-create-page .ccr-side-title{
        font-size:13px;
        font-weight:900;
        color:#0f172a;
        margin-bottom:4px;
    }
    .seat-create-page .ccr-side-counter{
        font-size:11px;
        font-weight:900;
        color:#334155;
        background:#eef2ff;
        border:1px solid #c7d2fe;
        border-radius:999px;
        padding:3px 8px;
        white-space:nowrap;
    }
    .seat-create-page .ccr-side-subtitle{
        margin:0 0 9px;
        font-size:11px;
        color:#475569;
        line-height:1.3;
        font-weight:700;
    }
    .seat-create-page .ccr-side-actions{
        margin-top:10px;
        margin-bottom:12px;
        display:grid;
        gap:10px;
    }
    .seat-create-page .ccr-side-actions .doc-btn{
        width:100%;
    }
    .seat-create-page .ccr-side-actions .doc-btn--danger{
        height:36px;
        font-size:12px;
    }
    .seat-create-page .ccr-staging-drop{
        border:1px dashed #94a3b8;
        border-radius:10px;
        background:#fff;
        min-height:170px;
        padding:9px;
        cursor:pointer;
    }
    .seat-create-page .ccr-staging-note{
        font-size:11px;
        font-weight:800;
        color:#334155;
        margin-bottom:8px;
    }
    .seat-create-page .ccr-staging-thumb{
        cursor:grab;
    }
    .seat-create-page .ccr-staging-thumb:active{
        cursor:grabbing;
    }
    .seat-create-page .ccr-side-footnote{
        margin:8px 0 0;
        font-size:11px;
        color:#475569;
        line-height:1.32;
        font-weight:700;
    }
    .seat-create-page .ccr-side-clear-btn{
        width:100%;
        margin-top:8px;
    }

    .seat-create-page .ccr-folder-picker{
        display:flex;
        align-items:center;
        gap:8px;
        border:1px solid #d1d5db;
        background:#fff;
        border-radius:12px;
        padding:8px 12px;
        margin-bottom:2px;
    }
    .seat-create-page .ccr-folder-picker label{
        font-size:12px;
        font-weight:900;
        color:#334155;
        white-space:nowrap;
        margin:0;
        line-height:1;
    }
    .seat-create-page .ccr-folder-picker-input{
        flex:1 1 auto;
        min-width:0;
        height:28px;
        border:1px solid #d1d5db;
        border-radius:8px;
        padding:0 10px;
        font-size:12px;
        font-weight:800;
        color:#111827;
        background:#fff;
    }
    .seat-create-page .doc-count{
        font-size:12px;
        font-weight:800;
        color:#334155;
        background:#fff;
        border:1px solid #d1d5db;
        border-radius:999px;
        padding:7px 11px;
        margin-top:2px;
    }

    .seat-create-page .doc-btn{
        height:36px;
        border-radius:10px;
        border:1px solid #d1d5db;
        background:#fff;
        color:#111827;
        font-size:12px;
        font-weight:800;
        padding:0 14px;
        cursor:pointer;
        display:inline-flex;
        align-items:center;
        justify-content:center;
        text-decoration:none;
    }
    .seat-create-page .doc-btn:disabled{
        opacity:.55;
        cursor:not-allowed;
    }
    .seat-create-page .doc-btn--primary{
        background:#2563eb;
        border-color:#2563eb;
        color:#fff;
    }
    .seat-create-page .doc-btn--primary:hover:not(:disabled){ background:#1d4ed8; }
    .seat-create-page .doc-btn--ghost:hover:not(:disabled){ background:#f1f5f9; }
    .seat-create-page .doc-btn--danger{
        background:#dc2626;
        border-color:#dc2626;
        color:#fff;
        height:30px;
        padding:0 10px;
        font-size:11px;
    }
    .seat-create-page .doc-btn--danger:hover:not(:disabled){ background:#b91c1c; }

    .seat-create-page .doc-a4-wrap{
        width:100%;
        overflow-x:auto;
        display:flex;
        justify-content:center;
        padding:0 0 16px;
    }
    .seat-create-page .doc-a4{
        width:793px;
        min-width:793px;
        background:#fff;
        box-shadow:0 0 16px rgba(0,0,0,.16);
        padding:14px 0 26px;
        border:1px solid #d7d7d7;
    }
    .seat-create-page .doc-a4 table{
        border-collapse:collapse;
        width:720px;
        margin:0 auto;
    }

    .seat-create-page .doc-header-rnf{
        width:720px;
        margin:0 auto;
    }
    .seat-create-page .doc-header-rnf__row{
        display:flex;
        align-items:center;
        justify-content:space-between;
        gap:12px;
    }
    .seat-create-page .doc-header-rnf__row img{
        width:2.3cm;
        height:2.9cm;
        object-fit:contain;
    }
    .seat-create-page .doc-header-rnf__center{
        flex:1;
        text-align:center;
        color:#1f2937;
    }
    .seat-create-page .doc-company{
        font-size:15px;
        font-weight:900;
        letter-spacing:.3px;
    }
    .seat-create-page .doc-company-sub{
        font-size:10px;
        margin-top:1px;
        font-weight:700;
    }
    .seat-create-page .doc-company-address{
        font-size:9px;
        margin-top:2px;
        line-height:1.2;
    }
    .seat-create-page .doc-header-rnf__line{
        margin:7px 0 10px;
        border-bottom:1px solid #555;
        box-shadow:0 2px 0 0 #555;
        height:3px;
    }

    .seat-create-page .doc-info-table{
        border:1px solid #111;
        margin-top:4px;
    }
    .seat-create-page .doc-info-table td{
        border:none !important;
        padding:2px 6px;
        font-size:11px;
        color:#111;
        vertical-align:middle;
    }
    .seat-create-page .doc-title{
        text-align:center;
        font-size:16px !important;
        font-weight:900;
        text-decoration:underline;
        padding:8px 4px !important;
    }
    .seat-create-page .doc-info-head{
        font-size:12px !important;
        font-weight:900;
        text-decoration:underline;
        padding:7px 6px !important;
    }
    .seat-create-page .doc-k{
        width:150px;
        font-weight:700;
    }
    .seat-create-page .doc-colon{
        width:14px;
        text-align:center;
        font-weight:700;
    }
    .seat-create-page .doc-input{
        width:100%;
        border:1px solid transparent;
        border-bottom:1px dotted #6b7280;
        border-radius:0;
        height:24px;
        background:transparent;
        padding:0 2px;
        font-size:11px;
        color:#111;
    }
    .seat-create-page .doc-input:focus{
        outline:none;
        border-bottom:1px solid #2563eb;
        box-shadow:none;
        background:#f8fbff;
    }
    .seat-create-page .doc-info-table .ts-wrapper{
        border:none;
        padding:0;
    }
    .seat-create-page .doc-info-table .ts-control{
        min-height:24px;
        height:24px;
        border:1px solid transparent;
        border-bottom:1px dotted #6b7280;
        border-radius:0;
        box-shadow:none;
        padding:0 2px;
        background:transparent;
        font-size:11px;
    }
    .seat-create-page .doc-info-table .ts-control input{
        font-size:11px;
    }

    .seat-create-page .doc-main-head{
        border:1px solid #111;
        border-top:none;
        margin-top:0;
    }
    .seat-create-page .doc-main-head td{
        border:1px solid #111 !important;
        padding:4px 6px;
    }
    .seat-create-page .doc-main-title{
        width:50%;
        font-size:11px;
        font-weight:900;
        text-align:center;
        line-height:1.2;
        vertical-align:middle;
    }

    .seat-create-page .doc-item-table{
        border:1px solid #111;
        border-top:none;
        margin-top:0;
    }
    .seat-create-page .doc-item-table td{
        border:1px solid #111 !important;
        vertical-align:top;
        padding:8px;
    }
    .seat-create-page .doc-item-left,
    .seat-create-page .doc-item-right{
        width:50%;
        min-height:280px;
    }
    .seat-create-page .doc-item-rowhead{
        display:flex;
        align-items:center;
        justify-content:space-between;
        gap:8px;
        margin-bottom:6px;
        font-size:11px;
        font-weight:700;
    }
    .seat-create-page .doc-textarea{
        width:100%;
        min-height:250px;
        resize:vertical;
        border:none;
        outline:none;
        padding:0;
        font-size:11px;
        line-height:1.3;
        background:transparent;
    }
    .seat-create-page .doc-dropzone{
        border:1px dashed #8a8a8a;
        border-radius:0;
        background:#fff;
        min-height:248px;
        padding:8px;
    }
    .seat-create-page .doc-thumb-open{
        display:block;
        width:100%;
        height:100%;
        border:none;
        background:transparent;
        padding:0;
        cursor:pointer;
    }
    .seat-create-page .doc-dropzone-hint{
        margin:0 0 7px;
        font-size:10px;
        color:#4b5563;
        font-weight:700;
        text-align:left;
    }
    .seat-create-page .doc-photo-help{
        display:block;
        margin-top:6px;
        font-size:10px;
        color:#4b5563;
    }
    .seat-create-page .doc-photo-modal{
        position:fixed;
        inset:0;
        z-index:98000;
        display:flex;
        align-items:center;
        justify-content:center;
        padding:24px;
    }
    .seat-create-page .doc-photo-modal__backdrop{
        position:absolute;
        inset:0;
        background:rgba(2,6,23,.72);
    }
    .seat-create-page .doc-photo-modal__x{
        position:absolute;
        top:14px;
        right:14px;
        z-index:2;
        width:42px;
        height:42px;
        border:none;
        border-radius:999px;
        background:#0f172a;
        color:#fff;
        font-size:30px;
        line-height:42px;
        padding:0;
        cursor:pointer;
        box-shadow:0 10px 24px rgba(0,0,0,.35);
    }
    .seat-create-page .doc-photo-modal__img{
        position:relative;
        z-index:1;
        max-width:min(95vw, 1320px);
        max-height:92vh;
        object-fit:contain;
        border-radius:6px;
        box-shadow:0 16px 48px rgba(0,0,0,.42);
        background:transparent;
    }
    .seat-create-page .doc-bottom-action{
        width:720px;
        margin:12px auto 0;
        text-align:right;
    }

    .seat-create-page .box {
        background:white;
        padding:18px;
        border-radius:14px;
        margin-bottom:20px;
        box-shadow:0 3px 10px rgba(0,0,0,0.07);
        overflow:hidden;
    }

    .seat-create-page .full-width { width: 100%; }

    .seat-create-page .input {
        width: 100%;
        padding: 10px 12px;
        border: 1px solid #ccc;
        border-radius: 8px;
        max-width:100%;
    }

    .seat-create-page .info-grid-edit{
        display:flex;
        flex-direction:column;
        gap:16px;
    }
    .seat-create-page .info-grid-edit > div{ min-width:0; }

    .seat-create-page .ts-wrapper,
    .seat-create-page .ts-control{ max-width:100%; }

    .seat-create-page .dropzone {
        border: 2px dashed #999;
        padding: 20px;
        background: #f8f8f8;
        border-radius: 10px;
        cursor:pointer;
        text-align:center;
        font-size:14px;
        color:#555;
    }

    .seat-create-page .preview-container {
        margin-top: 10px;
        display: flex;
        flex-wrap: wrap;
        gap:10px;
    }

    .seat-create-page .thumb {
        width: 100px;
        height: 100px;
        border-radius: 6px;
        border:1px solid #ccc;
        overflow:hidden;
        position:relative;
        background:#fff;
    }

    .seat-create-page .thumb img {
        width:100%;
        height:100%;
        object-fit:cover;
    }

    .seat-create-page .btn-modern {
        padding: 10px 18px;
        border-radius: 8px;
        border:none;
        cursor:pointer;
        color:white !important;
        font-weight:600;
        display:flex;
        justify-content:center;
        align-items:center;
        gap:6px;
        box-shadow:0 3px 7px rgba(0,0,0,0.15);
        transition:0.25s ease;
        text-decoration:none;
    }

    .seat-create-page .btn-primary { background:#0d6efd; }
    .seat-create-page .btn-primary:hover { background:#0b5ed7; }

    .seat-create-page .btn-success { background:#198754; }
    .seat-create-page .btn-success:hover { background:#157347; }

    .seat-create-page .btn-delete { background:#dc3545; }
    .seat-create-page .btn-delete:hover { background:#bb2d3b; }
    .seat-create-page .btn-ghost{
        background:#ffffff;
        color:#0f172a !important;
        border:1px solid #d1d5db;
        box-shadow:none;
    }
    .seat-create-page .btn-ghost:hover{
        background:#f1f5f9;
        transform:none;
    }
    .seat-create-page .btn-ghost:disabled{
        opacity:.5;
        cursor:not-allowed;
    }

    .seat-create-page .btn-delete-photo {
        position:absolute;
        top:4px; right:4px;
        background:#dc3545;
        color:white;
        border-radius:50%;
        padding:2px 6px;
        cursor:pointer;
        font-size:12px;
        line-height:1;
        user-select:none;
    }

    .seat-create-page .seat-submit-wrap{
        margin-top:10px;
        display:flex;
        justify-content:center;
        padding:8px 6px 8px;
        border:1px solid #d8e3f2;
        border-radius:16px;
        background:rgba(255,255,255,.95);
        box-shadow:0 10px 24px rgba(0,0,0,.08);
        width:fit-content;
        max-width:100%;
        margin-left:auto;
        margin-right:auto;
    }
    .seat-create-page .seat-submit-btn{
        width:700px;
        max-width:100%;
        min-height:58px;
        border-radius:12px;
        border:1px solid #d5dfef;
        background:#f4f8ff;
        color:#0f172a;
        font-size:16px;
        font-weight:900;
        letter-spacing:.1px;
        display:inline-flex;
        align-items:center;
        justify-content:center;
        gap:8px;
        box-shadow:none;
        transition:background-color .18s ease, border-color .18s ease, transform .18s ease, box-shadow .18s ease;
        cursor:pointer;
    }
    .seat-create-page .seat-submit-btn:hover{
        background:#ffffff;
        border-color:#111827;
        color:#111827;
        transform:translateY(-1px);
        box-shadow:
            0 0 0 2px rgba(17,24,39,.15),
            0 0 16px rgba(17,24,39,.22),
            0 8px 16px rgba(0,0,0,.08);
    }
    .seat-create-page .seat-submit-btn:active{
        transform:translateY(0);
    }
    .seat-create-page .seat-submit-btn:focus-visible{
        outline:none;
        border-color:#111827;
        box-shadow:
            0 0 0 2px rgba(17,24,39,.18),
            0 0 14px rgba(17,24,39,.20);
    }

    .seat-create-page .error-box {
        background:#ffe5e5;
        padding:14px;
        border-radius:10px;
        margin-bottom:20px;
    }

    .seat-create-page .btn-back-enhanced {
        display: inline-flex;
        align-items: center;
        padding: 10px 22px;
        background: #5f656a;
        color: white !important;
        font-weight: 600;
        font-size: 15px;
        border-radius: 10px;
        text-decoration: none;
        box-shadow: 0 3px 8px rgba(0,0,0,0.18);
        transition: 0.25s ease;
        margin-bottom: 14px;
    }
    .seat-create-page .btn-back-enhanced:hover {
        background: #2b2d2f;
        transform: translateY(-2px);
    }

    .seat-create-page .hidden{ display:none; }

    @media (max-width: 768px){
        .seat-create-page .seat-submit-wrap{
            padding:6px;
            border-radius:18px;
            width:100%;
        }
        .seat-create-page .seat-submit-btn{
            width:100%;
            min-height:50px;
            border-radius:14px;
            font-size:14px;
        }
    }

    @media (max-width: 600px) {
        .seat-create-page .btn-back-enhanced {
            font-size: 14px;
            padding: 9px 18px;
            margin-bottom: 22px;
        }
        .seat-create-page .thumb{ width:110px; height:110px; }
    }

    @media (max-width: 1200px){
        .seat-create-page .ccr-workspace{
            grid-template-columns:minmax(0, 1fr);
            gap:10px;
            max-width:100%;
        }
        .seat-create-page .ccr-side-pane{
            position:static;
            order:-1;
        }
        .seat-create-page .ccr-side-toggle{
            display:none;
        }
        .seat-create-page .ccr-side-content{
            width:100%;
            max-height:none;
        }
        .seat-create-page .ccr-workspace.is-sidebar-open .doc-a4-wrap,
        .seat-create-page .ccr-workspace.is-sidebar-closed .doc-a4-wrap{
            justify-content:center;
        }
    }

    @media (max-width: 920px){
        .seat-create-page .doc-a4-wrap{
            justify-content:flex-start;
        }
        .seat-create-page .doc-a4{
            transform:scale(.84);
            transform-origin:top left;
            margin-right:-125px;
        }
    }

    @media (max-width: 640px){
        .seat-create-page .doc-a4{
            transform:scale(.62);
            transform-origin:top left;
            margin-right:-290px;
        }
    }
</style>

@endsection
