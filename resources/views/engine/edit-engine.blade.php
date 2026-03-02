@extends('layout')

@section('content')

{{-- ============ EDIT LOCK OVERLAY ============ --}}
@if(!empty($lockedBy))
<div id="editLockOverlay" style="position:fixed;inset:0;z-index:9999;background:rgba(15,23,42,.75);display:flex;align-items:center;justify-content:center">
    <div style="background:#fff;border-radius:16px;padding:32px 36px;max-width:420px;width:90%;text-align:center;box-shadow:0 20px 60px rgba(0,0,0,.25)">
        <div style="font-size:48px;margin-bottom:12px">🔒</div>
        <h2 style="margin:0 0 8px;font-size:20px;color:#0f172a">Sedang Diedit</h2>
        <p style="margin:0 0 20px;color:#64748b;font-size:14px;line-height:1.5">
            Laporan ini sedang diedit oleh <strong style="color:#0f172a">{{ $lockedBy }}</strong>.<br>
            Anda bisa melihat dalam mode read-only.
        </p>
        <div style="display:flex;gap:10px;justify-content:center">
            <a href="{{ url()->previous() }}" style="padding:10px 20px;border-radius:8px;background:#f1f5f9;color:#334155;font-weight:700;text-decoration:none;font-size:14px">← Kembali</a>
            <button onclick="document.getElementById('editLockOverlay').style.display='none';document.querySelectorAll('form').forEach(f=>{f.querySelectorAll('input,select,textarea,button').forEach(el=>{el.disabled=true})})" style="padding:10px 20px;border-radius:8px;background:#0f172a;color:#fff;font-weight:700;border:none;cursor:pointer;font-size:14px">Buka Read-Only</button>
        </div>
    </div>
</div>
@endif

<div class="engine-create-page">

    @php
        $existingItemCount = $report->items->count();
        $oldNewItems = old('new_items', []);
        if (!is_array($oldNewItems)) $oldNewItems = [];
        $groupFolderOptions = isset($groupFolders) && is_array($groupFolders) && count($groupFolders)
            ? $groupFolders
            : ['Engine', 'Transmission', 'Radiator', 'Cabin', 'After Cooler'];
        $defaultGroupFolder = old('group_folder', $report->group_folder);
    @endphp

    {{-- =============== ERROR VALIDATION =============== --}}
    @if ($errors->any())
        <div class="error-box">
            <strong style="color:#b30000; font-size:15px;">⚠️ Gagal menyimpan perubahan CCR Engine:</strong>
            <ul style="margin:10px 0 0 22px; color:#800; font-size:14px;">
                @foreach ($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- =============== KEMBALI =============== --}}
    <a href="{{ route('ccr.manage.engine') }}" class="btn-back-enhanced">
        ← Kembali ke Manage CCR Engine
    </a>


    {{-- =============== FORM UTAMA =============== --}}
    <form action="{{ route('engine.update.header', $report->id) }}"
          method="POST"
          enctype="multipart/form-data"
          x-data="manageEngineEdit()"
          x-init="init()"
          @submit="onFormSubmit($event)"
          @remove-engine-item.window="removeItem($event.detail)"
          @remove-old-engine-item.window="removeOldItem($event.detail)">

        @csrf
        @method('PUT')

        {{-- simpan tab terakhir (biar pas validation error balik ke tab yang sama) --}}
        <input type="hidden" name="active_tab" x-model="tab">
        <input type="hidden" name="expected_upload_count" :value="expectedUploadCount">

        {{-- =============== HEADER CARD =============== --}}
        <div class="header-card-master">
            <div class="header-content-master">
                <img src="{{ asset('rnf-logo.png') }}" class="header-logo-master" width="110" height="110" alt="RNF Logo">

                <div>
                    <h1 class="header-title-master">EDIT CCR – ENGINE</h1>
                    <p class="header-subtitle-master">
                        Edit informasi komponen & item kerusakan
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
                                               value="{{ old('component', $report->component) }}"
                                               placeholder="Engine">
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
                                               value="{{ old('make', $report->make) }}"
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
                                        <input type="text" name="model" class="doc-input" value="{{ old('model', $report->model) }}">
                                    </td>
                                </tr>
                                <tr>
                                    <td class="doc-k">S/N</td>
                                    <td class="doc-colon">:</td>
                                    <td class="doc-v">
                                        <input type="text" name="sn" class="doc-input" value="{{ old('sn', $report->sn) }}">
                                    </td>
                                </tr>
                                <tr>
                                    <td class="doc-k">SMU</td>
                                    <td class="doc-colon">:</td>
                                    <td class="doc-v">
                                        <input type="text" name="smu" class="doc-input" value="{{ old('smu', $report->smu) }}">
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
                                               value="{{ old('customer', $report->customer) }}"
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
                                               value="{{ old('inspection_date', $report->inspection_date ? \Carbon\Carbon::parse($report->inspection_date)->format('Y-m-d') : '') }}">
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

                            {{-- ITEM EXISTING --}}
                            @foreach($report->items as $item)
                                @php
                                    $existingPhotos = $item->photos->map(fn($p) => [
                                        'id' => $p->id,
                                        'url' => asset('storage/'.$p->path),
                                    ])->values();
                                @endphp

                                <div class="doc-item-block"
                                     x-data="itemEditor('old-{{ $item->id }}', 'items[{{ $item->id }}]', $el, true, {{ $item->id }})"
                                     x-show="!removed"
                                     @restore-old-engine-item.window="restoreOldItem($event.detail)"
                                     data-photos='@json($existingPhotos)'>
                                    <input type="hidden"
                                           name="deleted_items[]"
                                           value="{{ $item->id }}"
                                           disabled
                                           x-bind:disabled="!removed">
                                    <table class="doc-item-table">
                                        <tr>
                                            <td class="doc-item-left"
                                                @dragover.prevent.stop
                                                @drop.prevent.stop>
                                                <div class="doc-item-rowhead">
                                                    <span>Item #{{ $loop->iteration }}</span>
                                                    <button type="button"
                                                            class="doc-btn doc-btn--danger"
                                                            @click="removeOldItemInline()">
                                                        Hapus Item
                                                    </button>
                                                </div>
                                                <textarea class="doc-textarea"
                                                          rows="10"
                                                          name="items[{{ $item->id }}][description]"
                                                          @dragover.prevent.stop
                                                          @drop.prevent.stop
                                                          placeholder="- Tulis item temuan / deskripsi">{{ old("items.$item->id.description", $item->description) }}</textarea>
                                            </td>

                                            <td class="doc-item-right">
                                                <div class="doc-dropzone"
                                                     @dragover.prevent
                                                     @drop.prevent="handleMixedDrop($event)"
                                                     @click.self="openFile()">
                                                    <p class="doc-dropzone-hint">Drop foto di sini, klik upload, atau seret dari sidebar.</p>

                                                    <div class="preview-container si-thumbs">
                                                        <template x-for="(photo, i) in existingPhotos" :key="'od'+i">
                                                            <div class="si-thumb-wrap" x-show="!photo.deleted">
                                                                <button type="button"
                                                                        class="si-thumb"
                                                                        @click.stop.prevent="openPreview(photo.url, 'Photo')">
                                                                    <img :src="photo.url" alt="Preview foto item">
                                                                </button>
                                                                <button type="button"
                                                                        class="si-thumb-x"
                                                                        @click.stop="markExistingDeleted(i)">×</button>

                                                                <input type="hidden"
                                                                       name="items[{{ $item->id }}][delete_photos][]"
                                                                       :value="photo.id"
                                                                       disabled
                                                                       x-bind:disabled="!photo.deleted">
                                                            </div>
                                                        </template>

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
                                                           name="items[{{ $item->id }}][photos][]"
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
                            @endforeach

                            {{-- ITEM BARU --}}
                            <template x-for="(key, idx) in newItems" :key="key">
                                <div class="doc-item-block"
                                     x-data="itemEditor('new-'+key, 'new_items['+key+']', $el)"
                                     data-photos="[]">
                                    <table class="doc-item-table">
                                        <tr>
                                            <td class="doc-item-left"
                                                @dragover.prevent.stop
                                                @drop.prevent.stop>
                                                <div class="doc-item-rowhead">
                                                    <span>Item #<b x-text="existingItemCount + idx + 1"></b></span>
                                                    <button type="button"
                                                            class="doc-btn doc-btn--danger"
                                                            @click="$dispatch('remove-engine-item', key)">
                                                        Hapus Item
                                                    </button>
                                                </div>
                                                <textarea class="doc-textarea"
                                                          rows="10"
                                                          :name="'new_items['+key+'][description]'"
                                                          x-model="newDescriptions[key]"
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
                                                           :name="'new_items['+key+'][photos][]'"
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

                            <div class="ccr-side-actions" style="margin-top:10px; margin-bottom:10px;">
                                <button type="submit"
                                        name="preview_after_save"
                                        value="1"
                                        class="doc-btn doc-btn--ghost">
                                    👁️ Simpan &amp; Lihat Preview
                                </button>
                            </div>

                            <div class="ccr-folder-picker">
                                <label for="group_folder_engine_sidebar_edit">Group Folder</label>
                                <input type="text"
                                       id="group_folder_engine_sidebar_edit"
                                       name="group_folder"
                                       class="ccr-folder-picker-input"
                                       list="group_folder_engine_list"
                                       value="{{ $defaultGroupFolder }}"
                                       placeholder="-- pilih group folder --"
                                       autocomplete="off"
                                       required>
                                <datalist id="group_folder_engine_list">
                                    @foreach ($groupFolderOptions as $g)
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
                                        :disabled="existingItemCount + newItems.length < 1">
                                    Duplikat Item Terakhir
                                </button>
                            </div>

                            <div class="doc-count">Total Item: <b x-text="existingItemCount + newItems.length"></b></div>
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
        @include('engine.partials.parts_worksheet')

        {{-- =========================================================
        TAB: DETAIL
        ========================================================== --}}
        @include('engine.partials.detail_worksheet')

        {{-- =============== SUBMIT (tampil di semua tab) =============== --}}
        <div class="engine-submit-wrap">
            <button type="submit" class="engine-submit-btn">
                Simpan Perubahan CCR Engine
            </button>
        </div>

    </form>

</div>


<script>
function itemEditor(itemKey, namePrefix, el, isExisting = false, oldItemId = null) {

    const MAX_PHOTOS = 10;
    let oldPhotos = [];

    try { oldPhotos = JSON.parse(el.dataset.photos || '[]'); }
    catch (e) { oldPhotos = []; }

    return {
        itemKey: String(itemKey ?? ''),
        namePrefix,
        isExisting: !!isExisting,
        oldItemId: Number.isFinite(Number(oldItemId)) ? Number(oldItemId) : null,
        removed: false,
        existingPhotos: oldPhotos.map(p => ({ id: p.id, url: p.url, deleted: false })),
        newPhotos: [],
        preview: { open:false, url:'', title:'' },

        totalPhotos() {
            return this.existingPhotos.filter(p => !p.deleted).length + this.newPhotos.length;
        },

        openFile() {
            this.$refs.fileInput.click();
        },

        handleFileSelect(event) {
            this.addFiles(Array.from(event.target.files || []));
        },

        handleMixedDrop(event) {
            const dt = event.dataTransfer;
            if (!dt) return;

            const types = Array.from(dt.types || []);
            const hasStagingMarker = types.includes('application/x-ccr-engine-staging');
            const stagedId = hasStagingMarker && typeof window.__engineEditGetDraggingStagingId === 'function'
                ? window.__engineEditGetDraggingStagingId()
                : '';

            if (stagedId && typeof window.__engineEditTakeStagingPhoto === 'function') {
                if (this.totalPhotos() >= MAX_PHOTOS) {
                    alert('Maksimal ' + MAX_PHOTOS + ' foto per item.');
                    return;
                }
                const staged = window.__engineEditTakeStagingPhoto(stagedId);
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
                if (!(f instanceof File)) continue;
                if (!(f.type || '').startsWith('image/')) continue;
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
        },

        markExistingDeleted(i) {
            if (!this.existingPhotos[i]) return;
            this.existingPhotos[i].deleted = true;
        },

        removeOldItemInline() {
            if (!this.isExisting || !this.oldItemId || this.removed) return;
            this.newPhotos.forEach((p) => {
                if (p && p.preview) {
                    try { URL.revokeObjectURL(p.preview); } catch (e) {}
                }
            });
            this.newPhotos = [];
            this.syncInputFiles();
            this.removed = true;
            this.$dispatch('remove-old-engine-item', { id: this.oldItemId });
        },

        restoreOldItem(detail) {
            const rawId = (detail && typeof detail === 'object' && typeof detail.id !== 'undefined')
                ? detail.id
                : detail;
            const id = parseInt(rawId, 10);
            if (!Number.isFinite(id) || id <= 0) return;
            if (this.oldItemId !== id) return;
            this.removed = false;
        },

        removeNewPhoto(i) {
            const removed = this.newPhotos.splice(i, 1);
            const p = removed && removed[0] ? removed[0] : null;
            if (p && p.preview) {
                try { URL.revokeObjectURL(p.preview); } catch (e) {}
            }
            this.syncInputFiles();
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
            if (!this.$refs.fileInput) return;
            const dt = new DataTransfer();
            this.newPhotos.forEach((p) => {
                if (p && p.file instanceof File) dt.items.add(p.file);
            });
            this.$refs.fileInput.files = dt.files;
        }
    };
}

function manageEngineEdit() {
    const oldNewItems = @json($oldNewItems);
    const initialKeys = [];
    const initialDescriptions = {};

    if (Array.isArray(oldNewItems)) {
        oldNewItems.forEach((it, idx) => {
            const k = idx;
            initialKeys.push(k);
            initialDescriptions[k] = (it && typeof it === 'object') ? (it.description || '') : '';
        });
    } else if (oldNewItems && typeof oldNewItems === 'object') {
        Object.keys(oldNewItems).forEach((kRaw) => {
            const k = Number.isFinite(parseInt(kRaw, 10)) ? parseInt(kRaw, 10) : initialKeys.length;
            const it = oldNewItems[kRaw];
            initialKeys.push(k);
            initialDescriptions[k] = (it && typeof it === 'object') ? (it.description || '') : '';
        });
    }

    const maxKey = initialKeys.length ? Math.max(...initialKeys) : -1;

    return {
        tab: @json(old('active_tab', 'ccr')),
        existingItemCount: @json($existingItemCount),
        removedOldItems: [],

        newItems: initialKeys,
        newDescriptions: initialDescriptions,
        counter: maxKey + 1,

        sidebarOpen: true,
        isSubmitting: false,
        expectedUploadCount: 0,
        ccrSaveState: 'saved',
        ccrSaveStatus: 'Auto-saved (DB) ' + (new Date()).toLocaleTimeString('id-ID'),
        ccrSaveTimer: null,
        maxFileUploads: @js(max(1, (int) ini_get('max_file_uploads'))),
        csrfToken: @js(csrf_token()),
        deleteItemUrlTemplate: @js(route('engine.item.delete', ['item' => '__ITEM__'])),

        stagingPhotos: [],
        stagingDragId: null,
        stagingPreview: { open:false, url:'', title:'' },
        stagingMaxPhotos: 400,

        init(){
            if (!this.tab) this.tab = 'ccr';

            this.bindCcrStatusTracking();

            window.__engineEditTakeStagingPhoto = (id) => this.takeStagingPhoto(id);
            window.__engineEditGetDraggingStagingId = () => this.stagingDragId;

            window.addEventListener('beforeunload', () => {
                this.revokeStagingPreviews();
            });
        },

        markCcrDirty() {
            this.ccrSaveState = 'saved';
            this.ccrSaveStatus = 'Auto-saved (DB) ' + (new Date()).toLocaleTimeString('id-ID');
        },

        flushEditAutosave(force = false) {
            this.ccrSaveState = 'saved';
            this.ccrSaveStatus = 'Auto-saved (DB) ' + (new Date()).toLocaleTimeString('id-ID');
            try {
                window.dispatchEvent(new CustomEvent('ccr:engine-force-save', {
                    detail: {
                        source: 'edit-submit',
                        force: !!force,
                        ts: Date.now(),
                    },
                }));
            } catch (e) {}
        },

        onFormSubmit(event) {
            if (this.isSubmitting) {
                event.preventDefault();
                return;
            }
            this.expectedUploadCount = this.countSelectedUploadFiles();
            if (!this.validateUploadCountBeforeSubmit()) {
                event.preventDefault();
                this.isSubmitting = false;
                return;
            }
            this.isSubmitting = true;
            this.flushEditAutosave(true);
        },

        getSubmitFileInputs() {
            if (!this.$el) return [];
            const allowedPrefixes = ['items[', 'new_items['];
            const inputs = this.$el.querySelectorAll('input[type="file"][name]');
            return Array.from(inputs).filter((input) => {
                const name = String(input?.getAttribute('name') || '').trim();
                if (!name) return false;
                return allowedPrefixes.some((prefix) => name.startsWith(prefix));
            });
        },

        countSelectedUploadFiles() {
            let total = 0;
            const inputs = this.getSubmitFileInputs();
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

        currentPayloadRevision(field) {
            if (!this.$el) return 0;
            const input = this.$el.querySelector('input[name="' + field + '"]');
            const value = Number(input ? input.value : 0);
            if (!Number.isFinite(value) || value < 0) return 0;
            return Math.floor(value);
        },

        bindCcrStatusTracking() {
            const isTrackedField = (el) => {
                if (!el || typeof el.matches !== 'function') return false;
                if (el.classList?.contains('doc-textarea')) return true;
                return el.matches('[name="group_folder"], [name="component"], [name="make"], [name="model"], [name="sn"], [name="smu"], [name="customer"], [name="inspection_date"]');
            };

            const onFieldChange = (event) => {
                if (this.tab !== 'ccr') return;
                if (!isTrackedField(event.target)) return;
                this.markCcrDirty();
            };

            this.$el.addEventListener('input', onFieldChange);
            this.$el.addEventListener('change', onFieldChange);
        },

        addItem() {
            const key = this.counter++;
            this.newItems.push(key);
            this.newDescriptions[key] = '';
            this.markCcrDirty();
        },

        removeItem(key) {
            if (!this.newItems.includes(key)) return;
            this.newItems = this.newItems.filter((k) => k !== key);
            delete this.newDescriptions[key];
            this.markCcrDirty();
        },

        removeOldItem(detail) {
            const rawId = detail && typeof detail === 'object' ? detail.id : detail;
            const id = parseInt(rawId, 10);
            if (!Number.isFinite(id) || id <= 0) return;
            if (this.removedOldItems.includes(id)) return;
            this.removedOldItems.push(id);
            this.existingItemCount = Math.max(0, this.existingItemCount - 1);
            this.markCcrDirty();
            this.deleteOldItemDirect(id);
        },

        async deleteOldItemDirect(id) {
            if (!this.deleteItemUrlTemplate || !this.csrfToken) return;
            const url = this.deleteItemUrlTemplate.replace('__ITEM__', encodeURIComponent(String(id)));
            const body = new URLSearchParams();
            body.set('_token', this.csrfToken);
            body.set('_method', 'DELETE');
            body.set('parts_payload_rev', String(this.currentPayloadRevision('parts_payload_rev')));
            body.set('detail_payload_rev', String(this.currentPayloadRevision('detail_payload_rev')));

            try {
                const res = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                    body: body.toString(),
                });

                if (!res.ok) {
                    throw new Error('delete item http ' + res.status);
                }
            } catch (e) {
                this.removedOldItems = this.removedOldItems.filter((x) => x !== id);
                this.existingItemCount = this.existingItemCount + 1;
                window.dispatchEvent(new CustomEvent('restore-old-engine-item', { detail: { id } }));
                alert('Gagal hapus item. Coba lagi.');
            }
        },

        canRemoveLastItem() {
            return this.newItems.length > 0;
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
                sourceDesc = this.newDescriptions[lastKey] || '';
            } else {
                const list = this.$el.querySelectorAll('textarea[name^="items["][name$="[description]"]');
                if (list.length) {
                    sourceDesc = String(list[list.length - 1].value || '');
                }
            }
            const key = this.counter++;
            this.newItems.push(key);
            this.newDescriptions[key] = sourceDesc;
            this.markCcrDirty();
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
                this.stagingPhotos.push({
                    id,
                    file,
                    name: file.name,
                    preview: URL.createObjectURL(file),
                });
                added += 1;
            }
            if (added > 0) this.markCcrDirty();
        },

        startStagingDrag(id, event) {
            this.stagingDragId = String(id);
            if (!event || !event.dataTransfer) return;
            event.dataTransfer.effectAllowed = 'copyMove';
            event.dataTransfer.dropEffect = 'move';
            event.dataTransfer.setData('application/x-ccr-engine-staging', '1');
            event.dataTransfer.setData('text/plain', String(id));
        },

        endStagingDrag() {
            this.stagingDragId = null;
        },

        takeStagingPhoto(id) {
            const idx = this.stagingPhotos.findIndex((p) => String(p.id) === String(id));
            if (idx === -1) return null;
            const [picked] = this.stagingPhotos.splice(idx, 1);
            this.stagingDragId = null;
            return picked || null;
        },

        removeStagingPhoto(id) {
            const idx = this.stagingPhotos.findIndex((p) => String(p.id) === String(id));
            if (idx === -1) return;
            const [removed] = this.stagingPhotos.splice(idx, 1);
            if (removed && removed.preview) {
                try { URL.revokeObjectURL(removed.preview); } catch (e) {}
            }
            if (String(this.stagingDragId || '') === String(id)) this.stagingDragId = null;
            this.markCcrDirty();
        },

        clearStagingPhotos() {
            this.stagingPhotos.forEach((p) => {
                if (p && p.preview) {
                    try { URL.revokeObjectURL(p.preview); } catch (e) {}
                }
            });
            this.stagingPhotos = [];
            this.stagingDragId = null;
            this.markCcrDirty();
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
        }
    }
}
</script>

@include('engine.partials.create_engine_style')

{{-- ============ EDIT LOCK HEARTBEAT ============ --}}
@if(empty($lockedBy))
<script>
(function(){
    const reportId = @json($report->id);
    const hbUrl = @json(route('ccr.editlock.heartbeat', ['id' => $report->id]));
    const rlUrl = @json(route('ccr.editlock.release', ['id' => $report->id]));
    const csrf = @json(csrf_token());

    setInterval(()=>{
        fetch(hbUrl,{method:'POST',headers:{'X-CSRF-TOKEN':csrf,'Accept':'application/json'}}).catch(()=>{});
    }, 120000);

    window.addEventListener('beforeunload',()=>{
        navigator.sendBeacon(rlUrl+'?_token='+encodeURIComponent(csrf)+'&_method=DELETE');
    });
})();
</script>
@endif

@endsection
