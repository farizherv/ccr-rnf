@extends('layout')

@section('content')

<div class="engine-create-page">

    @php
        $draftSeed = (isset($draftSeed) && is_array($draftSeed)) ? $draftSeed : [];
        $draftSeedCcrPayload = (isset($draftSeed['ccr_payload']) && is_array($draftSeed['ccr_payload'])) ? $draftSeed['ccr_payload'] : [];
        $draftSeedCcrFields = (isset($draftSeedCcrPayload['fields']) && is_array($draftSeedCcrPayload['fields'])) ? $draftSeedCcrPayload['fields'] : [];
        $draftSeedCcrRows = (isset($draftSeedCcrPayload['rows']) && is_array($draftSeedCcrPayload['rows'])) ? $draftSeedCcrPayload['rows'] : [];
        $draftSeedPartsPayload = (isset($draftSeed['parts_payload']) && is_array($draftSeed['parts_payload'])) ? $draftSeed['parts_payload'] : [];
        $draftSeedDetailPayload = (isset($draftSeed['detail_payload']) && is_array($draftSeed['detail_payload'])) ? $draftSeed['detail_payload'] : [];
        $activeDraftId = trim((string) ($draftSeed['id'] ?? ''));
        $activeDraftClientKey = trim((string) ($draftSeed['client_key'] ?? ''));

        $defaultGroupFolder = old('group_folder', $draftSeedCcrFields['group_folder'] ?? 'Engine');
    @endphp

    {{-- =============== ERROR VALIDATION =============== --}}
    @if ($errors->any())
        <div class="error-box">
            <strong style="color:#b30000; font-size:15px;">⚠️ Gagal menyimpan CCR Engine:</strong>
            <ul style="margin:10px 0 0 22px; color:#800; font-size:14px;">
                @foreach ($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- =============== KEMBALI =============== --}}
    <a href="{{ route('ccr.manage.engine') }}" class="btn-back-enhanced">
        ← Kembali
    </a>

    {{-- =============== FORM UTAMA =============== --}}
    <form action="{{ route('engine.store') }}"
          method="POST"
          enctype="multipart/form-data"
          x-data="manageEngineCreate()"
          x-init="init()"
          @submit="prepareFinalSubmit($event)"
          @remove-engine-item.window="removeItem($event.detail)">

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
                    <h1 class="header-title-master">CREATE CCR – ENGINE</h1>
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
                CCR ENGINE
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
        </div>

        <div class="accent-line"></div>

        {{-- =========================================================
        TAB: CCR (INFO + ITEM)
        ========================================================== --}}
        <div x-show="tab === 'ccr'" x-cloak>
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
                                               placeholder="Contoh: Engine 3408 D9R">
                                    </td>
                                </tr>
                                <tr>
                                    <td class="doc-k">MAKE</td>
                                    <td class="doc-colon">:</td>
                                    <td class="doc-v">
                                        <input type="text"
                                               name="make"
                                               id="make_engine"
                                               class="doc-input"
                                               list="make_engine_list"
                                               value="{{ old('make', $draftSeedCcrFields['make'] ?? '') }}"
                                               placeholder="-- pilih make / ketik manual --"
                                               autocomplete="off">
                                        <datalist id="make_engine_list">
                                            @foreach ($brands as $b)
                                                <option value="{{ $b }}"></option>
                                            @endforeach
                                        </datalist>
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
                                    <td class="doc-k">S/N</td>
                                    <td class="doc-colon">:</td>
                                    <td class="doc-v">
                                        <input type="text" name="sn" class="doc-input" value="{{ old('sn', $draftSeedCcrFields['sn'] ?? '') }}">
                                    </td>
                                </tr>
                                <tr>
                                    <td class="doc-k">SMU</td>
                                    <td class="doc-colon">:</td>
                                    <td class="doc-v">
                                        <input type="text" name="smu" class="doc-input" value="{{ old('smu', $draftSeedCcrFields['smu'] ?? '') }}">
                                    </td>
                                </tr>
                                <tr>
                                    <td class="doc-k">CUSTOMER</td>
                                    <td class="doc-colon">:</td>
                                    <td class="doc-v">
                                        <input type="text"
                                               name="customer"
                                               id="customer_engine"
                                               class="doc-input"
                                               list="customer_engine_list"
                                               value="{{ old('customer', $draftSeedCcrFields['customer'] ?? '') }}"
                                               placeholder="-- pilih customer / ketik manual --"
                                               autocomplete="off">
                                        <datalist id="customer_engine_list">
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
                                               value="{{ old('inspection_date', $draftSeedCcrFields['inspection_date'] ?? '') }}"
                                               required>
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
                                                            @click="$dispatch('remove-engine-item', key)">
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
                                <label for="group_folder_engine_sidebar">Group Folder</label>
                                <input type="text"
                                       id="group_folder_engine_sidebar"
                                       name="group_folder"
                                       class="ccr-folder-picker-input"
                                       list="group_folder_engine_list"
                                       x-model="groupFolder"
                                       value="{{ $defaultGroupFolder }}"
                                       placeholder="-- pilih group folder --"
                                       autocomplete="off"
                                       required>
                                <datalist id="group_folder_engine_list">
                                    @foreach ($groupFolders as $g)
                                        <option value="{{ $g }}"></option>
                                    @endforeach
                                </datalist>
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
        @include('engine.partials.parts_worksheet', [
            'draftSeedPartsPayload' => $draftSeedPartsPayload,
            'skipLocalDraftLoad' => $activeDraftId !== '',
        ])

        {{-- =========================================================
        TAB: DETAIL
        ========================================================== --}}
        @include('engine.partials.detail_worksheet', [
            'draftSeedDetailPayload' => $draftSeedDetailPayload,
            'skipLocalDraftLoad' => $activeDraftId !== '',
        ])

        {{-- =============== SUBMIT (tampil di semua tab) =============== --}}
        <div class="engine-submit-wrap">
            <button type="submit" class="engine-submit-btn">
                Simpan CCR Engine
            </button>
        </div>

    </form>

</div>

<script>
function itemEditor(itemKey, namePrefix, el) {
    const MAX_PHOTOS = 10;
    let oldPhotos = [];

    try { oldPhotos = JSON.parse(el.dataset.photos || '[]'); }
    catch (e) { oldPhotos = []; }

    return {
        itemKey: String(itemKey ?? ''),
        namePrefix,
        existingPhotos: oldPhotos.map((p) => ({ id: p.id, url: p.url, deleted: false })),
        newPhotos: [],
        preview: { open: false, url: '', title: '' },

        init() {
            this.pullFilesFromParent();
            window.addEventListener('engine-create-ccr-item-photos-sync', () => {
                this.pullFilesFromParent();
            });
        },

        totalPhotos() {
            return this.existingPhotos.filter((p) => !p.deleted).length + this.newPhotos.length;
        },

        pullFilesFromParent() {
            if (!this.itemKey || typeof window.__engineCreateGetItemPhotos !== 'function') return;
            const files = window.__engineCreateGetItemPhotos(this.itemKey);
            if (!Array.isArray(files)) return;
            this.setFiles(files, false);
        },

        notifyParentPhotosChanged() {
            if (!this.itemKey || typeof window.__engineCreateSyncItemPhotos !== 'function') return;
            const files = this.newPhotos.map((p) => p.file).filter((f) => f instanceof File);
            window.__engineCreateSyncItemPhotos(this.itemKey, files);
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
                if (!(f.type || '').startsWith('image/')) continue;
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

        handleMixedDrop(event) {
            const dt = event.dataTransfer;
            if (!dt) return;

            const types = Array.from(dt.types || []);
            const hasStagingMarker = types.includes('application/x-ccr-engine-staging');
            const stagedId = hasStagingMarker && typeof window.__engineCreateGetDraggingStagingId === 'function'
                ? window.__engineCreateGetDraggingStagingId()
                : '';

            if (stagedId && typeof window.__engineCreateTakeStagingPhoto === 'function') {
                if (this.totalPhotos() >= MAX_PHOTOS) {
                    alert('Maksimal ' + MAX_PHOTOS + ' foto per item.');
                    return;
                }
                const staged = window.__engineCreateTakeStagingPhoto(stagedId);
                if (staged && staged.file) {
                    this.addFiles([staged.file]);
                }
            }

            const files = Array.from(dt.files || [])
                .filter((f) => (f.type || '').startsWith('image/'));
            if (files.length) this.addFiles(files);
        },

        addFiles(files) {
            for (const f of files) {
                if (!(f instanceof File)) continue;
                if (!(f.type || '').startsWith('image/')) continue;
                if (this.totalPhotos() >= MAX_PHOTOS) {
                    alert('Maksimal ' + MAX_PHOTOS + ' foto per item.');
                    break;
                }
                this.newPhotos.push({
                    file: f,
                    preview: URL.createObjectURL(f),
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
            this.newPhotos.forEach((p) => dt.items.add(p.file));
            this.$refs.fileInput.files = dt.files;
        }
    };
}

function manageEngineCreate() {

    let oldItems = @json(old('items'));
    const hasOldInput = @json(session()->hasOldInput());
    const seedRows = @json($draftSeedCcrRows);
    if (!Array.isArray(oldItems) || oldItems.length === 0) {
        if (Array.isArray(seedRows) && seedRows.length) {
            oldItems = seedRows.map((r) => ({ description: String((r && r.description) || '') }));
        } else {
            oldItems = [{ description: '' }];
        }
    }

    const keys = oldItems.map((_, idx) => idx);
    const descMap = {};
    oldItems.forEach((it, idx) => { descMap[idx] = (it && it.description) ? it.description : ''; });

    const draftUpsertUrl = @json(route('ccr.drafts.upsert'));
    const activeDraftId = @json($activeDraftId);
    const activeDraftClientKey = @json($activeDraftClientKey);
    const draftUserToken = @json((string) (auth()->id() ?? 'guest'));
    const ccrDraftKey = `engine:create:ccr:u:${draftUserToken}`;
    const draftClientKeyStorage = `ccr:create:server-key:u:${draftUserToken}:type:engine`;

    return {
        tab: @json(old('active_tab', 'ccr')),

        groupFolder: @json($defaultGroupFolder),

        newItems: keys.length ? keys : [0],
        counter: keys.length ? keys.length : 1,
        descriptions: descMap,

        sidebarOpen: true,
        ccrSaveState: 'saved',
        ccrSaveStatus: 'Auto-saved --:--:--',
        ccrSaveTimer: null,
        ccrLastSavedHash: '',
        ccrHasOldInput: !!hasOldInput,
        ccrDraftMaxAgeMs: 1000 * 60 * 60 * 24 * 45,

        stagingPhotos: [],
        stagingDragId: null,
        stagingPreview: { open:false, url:'', title:'' },
        stagingMaxPhotos: 400,
        itemPhotoFilesByKey: {},
        photoDraftVersion: 1,
        photoDraftStoreName: 'seatCreatePhotoDrafts',
        photoDraftDbName: 'seatCreateDraftDb',
        photoDraftRestoreDone: false,
        photoDraftMaxBytes: 120 * 1024 * 1024,
        photoDraftMaxFiles: 400,
        photoSaveTimer: null,
        photoLastSavedHash: '',
        ccrDraftKey,

        draftType: 'engine',
        draftUpsertUrl,
        serverDraftId: activeDraftId || '',
        serverDraftClientKey: activeDraftClientKey || '',
        draftClientKeyStorage,
        draftSectionTimers: {},
        draftSectionPending: {},
        draftEventBound: false,
        isFinalSubmitting: false,
        expectedUploadCount: 0,
        maxFileUploads: @js(max(1, (int) ini_get('max_file_uploads'))),

        init() {
            if (!this.tab) this.tab = 'ccr';

            this.bootstrapServerDraftClientKey();
            this.bindCcrAutosaveToServer();
            this.bindDraftSectionEvents();
            this.restorePhotoDraftIfEligible();

            window.__engineCreateTakeStagingPhoto = (id) => this.takeStagingPhoto(id);
            window.__engineCreateGetDraggingStagingId = () => this.stagingDragId;
            window.__engineCreateSyncItemPhotos = (itemKey, files) => this.syncItemPhotosFromEditor(itemKey, files);
            window.__engineCreateGetItemPhotos = (itemKey) => this.getItemPhotosForEditor(itemKey);

            window.addEventListener('beforeunload', () => {
                if (this.isSubmitInProgress()) return;
                this.queueServerDraftSection('ccr', this.buildCcrDraftPayload(), 0);
                this.flushPendingServerDraftSections(true);
                this.flushPhotoDraftSave(true);
                this.revokeStagingPreviews();
            });
            window.addEventListener('pagehide', () => {
                if (this.isSubmitInProgress()) return;
                this.queueServerDraftSection('ccr', this.buildCcrDraftPayload(), 0);
                this.flushPendingServerDraftSections(true);
                this.flushPhotoDraftSave(true);
                this.revokeStagingPreviews();
            });
            document.addEventListener('visibilitychange', () => {
                if (this.isSubmitInProgress()) return;
                if (document.visibilityState !== 'hidden') return;
                this.queueServerDraftSection('ccr', this.buildCcrDraftPayload(), 0);
                this.flushPendingServerDraftSections(true);
                this.flushPhotoDraftSave(true);
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

        flushWorksheetAutosave(force = false) {
            try {
                window.dispatchEvent(new CustomEvent('ccr:engine-force-save', {
                    detail: {
                        source: 'create-submit',
                        force: !!force,
                        ts: Date.now(),
                    },
                }));
            } catch (e) {}
        },

        applyWorksheetSnapshotToInput(inputName, payload) {
            if (!this.$el || !inputName || !payload || typeof payload !== 'object') return;
            const input = this.$el.querySelector(`input[name="${inputName}"]`);
            if (!input) return;
            try {
                input.value = JSON.stringify(payload);
            } catch (e) {}
        },

        collectWorksheetPayloadSnapshots() {
            const partsCollector = window.__engineCreateCollectPartsPayload;
            if (typeof partsCollector === 'function') {
                const payload = partsCollector();
                this.applyWorksheetSnapshotToInput('parts_payload', payload);
            }

            const detailCollector = window.__engineCreateCollectDetailPayload;
            if (typeof detailCollector === 'function') {
                const payload = detailCollector();
                this.applyWorksheetSnapshotToInput('detail_payload', payload);
            }
        },

        validateWorksheetPayloadInput(inputName, label) {
            if (!this.$el) return true;
            const input = this.$el.querySelector(`input[name="${inputName}"]`);
            if (!input) return true;

            const raw = String(input.value || '').trim();
            if (!raw) {
                alert(label + ' payload masih kosong. Silakan tunggu autosave selesai, lalu klik Simpan lagi.');
                return false;
            }

            try {
                const parsed = JSON.parse(raw);
                if (!parsed || typeof parsed !== 'object') {
                    alert(label + ' payload tidak valid. Silakan refresh halaman lalu simpan ulang.');
                    return false;
                }
            } catch (e) {
                alert(label + ' payload rusak/tidak lengkap. Silakan refresh halaman lalu simpan ulang.');
                return false;
            }

            return true;
        },

        prepareFinalSubmit(event) {
            if (event && typeof event.preventDefault === 'function') {
                event.preventDefault();
            }

            this.expectedUploadCount = this.countSelectedUploadFiles();
            if (!this.validateUploadCountBeforeSubmit()) {
                this.markSubmitInProgress(false);
                return;
            }

            this.markSubmitInProgress(true);
            this.collectWorksheetPayloadSnapshots();
            this.flushWorksheetAutosave(true);
            this.collectWorksheetPayloadSnapshots();

            if (!this.validateWorksheetPayloadInput('parts_payload', 'Parts Worksheet') ||
                !this.validateWorksheetPayloadInput('detail_payload', 'Detail Worksheet')) {
                this.markSubmitInProgress(false);
                return;
            }

            this.queueServerDraftSection('ccr', this.buildCcrDraftPayload(), 0);
            this.flushPendingServerDraftSections(true);
            this.flushPhotoDraftSave(true);

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

            if (this.$el && typeof HTMLFormElement !== 'undefined') {
                HTMLFormElement.prototype.submit.call(this.$el);
            }
        },

        countSelectedUploadFiles() {
            if (!this.$el) return 0;
            let total = 0;
            const inputs = this.$el.querySelectorAll('input[type="file"]');
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
                'Jumlah foto yang akan dikirim (' + total + ') melebihi batas server max_file_uploads (' + limit + ').\n\n' +
                'Silakan kurangi jumlah foto per sekali simpan, atau naikkan max_file_uploads di konfigurasi PHP server.'
            );
            return false;
        },

        setCcrSaveState(state, text) {
            this.ccrSaveState = state;
            this.ccrSaveStatus = text;
        },

        formatCcrTime(ts) {
            const d = ts instanceof Date ? ts : new Date(ts);
            if (Number.isNaN(d.getTime())) return '--:--:--';
            return d.toLocaleTimeString('id-ID', { hour12: false });
        },

        ccrPayloadHash(payload) {
            return JSON.stringify({
                fields: payload?.fields || {},
                rows: Array.isArray(payload?.rows) ? payload.rows : [],
            });
        },

        markCcrDirty() {},

        queueCcrAutosave(delay = 700, force = false) {
            if (this.ccrSaveTimer) clearTimeout(this.ccrSaveTimer);
            if (delay <= 0) {
                this.flushCcrAutosave(force);
                return;
            }
            this.ccrSaveTimer = setTimeout(() => this.flushCcrAutosave(force), delay);
        },

        flushCcrAutosave(force = false) {
            if (this.ccrSaveTimer) {
                clearTimeout(this.ccrSaveTimer);
                this.ccrSaveTimer = null;
            }

            const payload = this.buildCcrDraftPayload();
            const payloadHash = this.ccrPayloadHash(payload);
            if (!force && payloadHash === this.ccrLastSavedHash) return;

            if (this.isCcrPayloadEmpty(payload) && !this.serverDraftId) {
                this.ccrLastSavedHash = '';
                this.setCcrSaveState('saved', 'AutoSave ON');
                return;
            }

            this.ccrLastSavedHash = payloadHash;
            this.queueServerDraftSection('ccr', payload, force ? 0 : 350);
        },

        bindCcrAutosaveToServer() {
            if (!this.$el || this.__ccrDraftBound) return;
            this.__ccrDraftBound = true;

            const isTrackedField = (el) => {
                if (!el || typeof el.matches !== 'function') return false;
                if (el.classList?.contains('doc-textarea')) return true;
                return el.matches('[name="group_folder"], [name="component"], [name="make"], [name="model"], [name="sn"], [name="smu"], [name="customer"], [name="inspection_date"]');
            };

            const onFieldChange = (event) => {
                if (!isTrackedField(event.target)) return;
                if (event.target.name === 'group_folder') {
                    this.groupFolder = String(event.target.value || '');
                }
                this.queueCcrAutosave(750);
            };

            this.$el.addEventListener('input', onFieldChange);
            this.$el.addEventListener('change', onFieldChange);
        },

        bindDraftSectionEvents() {
            if (this.draftEventBound) return;
            this.draftEventBound = true;

            window.addEventListener('ccr:create-draft-section', (event) => {
                const detail = event && event.detail ? event.detail : {};
                if ((detail.type || '') !== this.draftType) return;
                const section = String(detail.section || '');
                const payload = (detail.payload && typeof detail.payload === 'object') ? detail.payload : {};
                if (!section || !payload || typeof payload !== 'object') return;
                this.queueServerDraftSection(section, payload, 500);
            });
        },

        addItem() {
            const key = this.counter++;
            this.newItems.push(key);
            this.descriptions[key] = '';
            this.queueCcrAutosave(300, true);
            this.queuePhotoDraftSave(450, true);
        },

        removeItem(key) {
            this.newItems = this.newItems.filter(k => k !== key);
            delete this.descriptions[key];
            delete this.itemPhotoFilesByKey[String(key)];
            if (this.newItems.length === 0) {
                const newKey = this.counter++;
                this.newItems = [newKey];
                this.descriptions[newKey] = '';
            }
            this.queueCcrAutosave(300, true);
            this.queuePhotoDraftSave(450, true);
        },

        canRemoveLastItem() {
            return this.newItems.length > 1;
        },

        removeLastItem() {
            if (!this.canRemoveLastItem()) return;
            const key = this.newItems[this.newItems.length - 1];
            this.removeItem(key);
        },

        duplicateLastItem() {
            let sourceDesc = '';
            if (this.newItems.length > 0) {
                const lastKey = this.newItems[this.newItems.length - 1];
                sourceDesc = this.descriptions[lastKey] || '';
            }
            const key = this.counter++;
            this.newItems.push(key);
            this.descriptions[key] = sourceDesc;
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
            event.target.value = '';
            this.addStagingFiles(files);
        },

        handleStagingDrop(event) {
            const files = Array.from(event.dataTransfer.files || []);
            this.addStagingFiles(files);
        },

        addStagingFiles(files) {
            let added = 0;
            for (const file of files) {
                if (!(file instanceof File)) continue;
                if (!(file.type || '').startsWith('image/')) continue;
                if (this.stagingPhotos.length >= this.stagingMaxPhotos) {
                    alert('Foto sementara maksimal ' + this.stagingMaxPhotos + ' file.');
                    break;
                }

                const duplicate = this.stagingPhotos.some((p) => {
                    if (!p || !p.file) return false;
                    return p.file.name === file.name && p.file.size === file.size && p.file.lastModified === file.lastModified;
                });
                if (duplicate) continue;

                const id = 'stg_' + Date.now().toString(36) + '_' + Math.random().toString(36).slice(2, 7);
                this.stagingPhotos.push({ id, file, name: file.name, preview: URL.createObjectURL(file) });
                added += 1;
            }
            if (added > 0) this.queuePhotoDraftSave(350, true);
        },

        startStagingDrag(id, event) {
            this.stagingDragId = String(id);
            if (!event || !event.dataTransfer) return;
            event.dataTransfer.effectAllowed = 'copyMove';
            event.dataTransfer.dropEffect = 'move';
            event.dataTransfer.setData('application/x-ccr-engine-staging', '1');
            event.dataTransfer.setData('text/plain', '');
        },

        endStagingDrag() {
            this.stagingDragId = null;
        },

        takeStagingPhoto(id) {
            const idx = this.stagingPhotos.findIndex((p) => String(p.id) === String(id));
            if (idx === -1) return null;
            const [picked] = this.stagingPhotos.splice(idx, 1);
            if (picked && picked.preview) {
                try { URL.revokeObjectURL(picked.preview); } catch (e) {}
            }
            this.stagingDragId = null;
            this.queuePhotoDraftSave(350, true);
            if (!picked) return null;
            return { id: picked.id, file: picked.file, name: picked.name };
        },

        removeStagingPhoto(id) {
            const idx = this.stagingPhotos.findIndex((p) => String(p.id) === String(id));
            if (idx === -1) return;
            const [removed] = this.stagingPhotos.splice(idx, 1);
            if (removed && removed.preview) {
                try { URL.revokeObjectURL(removed.preview); } catch (e) {}
            }
            if (String(this.stagingDragId || '') === String(id)) this.stagingDragId = null;
            this.queuePhotoDraftSave(350, true);
        },

        clearStagingPhotos() {
            this.stagingPhotos.forEach((p) => {
                if (p && p.preview) {
                    try { URL.revokeObjectURL(p.preview); } catch (e) {}
                }
            });
            this.stagingPhotos = [];
            this.stagingDragId = null;
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
                const req = window.indexedDB.open(this.photoDraftDbName);
                req.onupgradeneeded = () => {
                    const db = req.result;
                    if (!db.objectStoreNames.contains(this.photoDraftStoreName)) {
                        db.createObjectStore(this.photoDraftStoreName, { keyPath: 'id' });
                    }
                };
                req.onsuccess = () => {
                    const db = req.result;
                    if (db.objectStoreNames.contains(this.photoDraftStoreName)) {
                        resolve(db);
                        return;
                    }

                    const nextVersion = Number(db.version || 1) + 1;
                    try { db.close(); } catch (e) {}

                    const upgradeReq = window.indexedDB.open(this.photoDraftDbName, nextVersion);
                    upgradeReq.onupgradeneeded = () => {
                        const upDb = upgradeReq.result;
                        if (!upDb.objectStoreNames.contains(this.photoDraftStoreName)) {
                            upDb.createObjectStore(this.photoDraftStoreName, { keyPath: 'id' });
                        }
                    };
                    upgradeReq.onsuccess = () => resolve(upgradeReq.result);
                    upgradeReq.onerror = () => reject(upgradeReq.error || new Error('upgrade indexeddb failed'));
                };
                req.onerror = () => reject(req.error || new Error('open indexeddb failed'));
            });

            return this.__photoDraftDbPromise;
        },

        async readPhotoDraftRecord() {
            const db = await this.openPhotoDraftDb();
            if (!db || !this.ccrDraftKey) return null;

            return await new Promise((resolve, reject) => {
                let tx = null;
                try {
                    tx = db.transaction(this.photoDraftStoreName, 'readonly');
                } catch (e) {
                    reject(e);
                    return;
                }
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
                let tx = null;
                try {
                    tx = db.transaction(this.photoDraftStoreName, 'readwrite');
                } catch (e) {
                    reject(e);
                    return;
                }
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
                let tx = null;
                try {
                    tx = db.transaction(this.photoDraftStoreName, 'readwrite');
                } catch (e) {
                    reject(e);
                    return;
                }
                const store = tx.objectStore(this.photoDraftStoreName);
                const req = store.delete(this.ccrDraftKey);
                req.onsuccess = () => resolve(true);
                req.onerror = () => reject(req.error || new Error('delete draft failed'));
            });
        },

        queuePhotoDraftSave(delay = 1200, force = false) {
            if (!window.indexedDB || !this.ccrDraftKey) return;
            if (this.photoSaveTimer) clearTimeout(this.photoSaveTimer);
            this.photoSaveTimer = setTimeout(() => this.flushPhotoDraftSave(force), delay);
        },

        flushPhotoDraftSave(force = false) {
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
                this.setCcrSaveState('error', 'Draft foto terlalu besar, klik Simpan CCR Engine');
                return;
            }

            this.writePhotoDraftRecord(record)
                .then(() => {
                    this.photoLastSavedHash = hash;
                    this.setCcrSaveState('saved', 'Auto-saved ' + this.formatCcrTime(record.ts));
                })
                .catch((e) => {
                    console.warn('engine photo draft save failed', e);
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
                console.warn('engine photo draft read failed', e);
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
                window.dispatchEvent(new CustomEvent('engine-create-ccr-item-photos-sync'));
            });

            const totalItemFiles = Object.values(rebuilt).reduce((sum, arr) => {
                return sum + (Array.isArray(arr) ? arr.length : 0);
            }, 0);
            if (restoredStaging.length || totalItemFiles) {
                this.setCcrSaveState('saved', 'Auto-saved ' + this.formatCcrTime(ts || Date.now()));
            }
        },

        getFieldValue(name) {
            const el = this.$el ? this.$el.querySelector(`[name="${name}"]`) : null;
            return el ? String(el.value || '') : '';
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

        deriveDraftName(payload = null) {
            const p = payload && typeof payload === 'object' ? payload : this.buildCcrDraftPayload();
            const fields = p.fields && typeof p.fields === 'object' ? p.fields : {};
            const candidates = [fields.component, fields.smu, fields.sn];
            for (const c of candidates) {
                const text = String(c || '').trim();
                if (text) return text;
            }
            return 'ENGINE Draft';
        },

        buildCcrDraftPayload() {
            const fields = {
                group_folder: this.getFieldValue('group_folder'),
                component: this.getFieldValue('component'),
                make: this.getFieldValue('make'),
                model: this.getFieldValue('model'),
                sn: this.getFieldValue('sn'),
                smu: this.getFieldValue('smu'),
                customer: this.getFieldValue('customer'),
                inspection_date: this.getFieldValue('inspection_date'),
            };

            const rows = this.newItems.map((key) => ({
                description: String(this.descriptions[key] || ''),
            }));

            return { v: 1, ts: Date.now(), fields, rows };
        },

        isCcrPayloadEmpty(payload = null) {
            const p = payload && typeof payload === 'object' ? payload : this.buildCcrDraftPayload();
            const fields = (p.fields && typeof p.fields === 'object') ? p.fields : {};
            const rows = Array.isArray(p.rows) ? p.rows : [];
            const trackedFields = ['component', 'make', 'model', 'sn', 'smu', 'customer', 'inspection_date'];
            const hasField = trackedFields.some((k) => String(fields[k] || '').trim() !== '');
            const hasRow = rows.some((r) => String((r && r.description) || '').trim() !== '');
            return !hasField && !hasRow;
        },

        queueServerDraftSection(section, payload, delay = 700) {
            if (!this.draftUpsertUrl) return;
            if (!section || !payload || typeof payload !== 'object') return;

            const key = String(section);
            if (!this.serverDraftId && key !== 'ccr') return;
            if (key === 'ccr' && this.isCcrPayloadEmpty(payload) && !this.serverDraftId) return;
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
            const keys = new Set([
                ...Object.keys(this.draftSectionPending || {}),
                ...Object.keys(this.draftSectionTimers || {}),
            ]);
            keys.forEach((key) => this.flushServerDraftSection(key, null, !!force && key === 'ccr'));
        },

        async flushServerDraftSection(section, payload = null, keepalive = false) {
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

            try {
                const csrfMeta = document.querySelector('meta[name="csrf-token"]');
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
                if (key === 'ccr') {
                    this.setCcrSaveState('saved', 'Auto-saved ' + this.formatCcrTime(Date.now()));
                }
                delete this.draftSectionPending[key];
            } catch (e) {
                console.warn('engine create draft save failed', e);
            }
        },
    };
}
</script>

@include('engine.partials.create_engine_style')

@endsection
