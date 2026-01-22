@extends('layout')

@section('content')

<div class="engine-create-page">

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
    <a href="{{ route('ccr.index') }}" class="btn-back-enhanced">
        ← Kembali
    </a>

    {{-- =============== FORM UTAMA =============== --}}
    <form
        action="{{ route('engine.store') }}"
        method="POST"
        enctype="multipart/form-data"
        x-data="manageEngineCreate()"
        x-init="init()"
        @remove-item.window="removeItem($event.detail)">

        @csrf

        {{-- simpan tab terakhir (biar pas validation error balik ke tab yang sama) --}}
        <input type="hidden" name="active_tab" x-model="tab">

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

            {{-- =============== HEADER INFORMATION =============== --}}
            <div class="box">
                <h3 style="margin-bottom:18px;">Informasi Komponen</h3>

                <div class="info-grid-edit">

                    <div>
                        <label>Group Folder</label>
                        <select name="group_folder" id="group_folder_engine" class="input" required>
                            <option value="">-- pilih group folder --</option>
                            @foreach ($groupFolders as $g)
                                <option value="{{ $g }}"
                                    {{ old('group_folder', 'Engine') == $g ? 'selected' : '' }}>
                                    {{ $g }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label>Component</label>
                        <input type="text"
                               name="component"
                               class="input"
                               value="{{ old('component') }}"
                               placeholder="Contoh: Engine 3408 D9R">
                    </div>

                    <div>
                        <label>Make</label>
                        <select name="make" id="make_engine" class="input">
                            <option value="">-- pilih make --</option>
                            @foreach ($brands as $b)
                                <option value="{{ $b }}" {{ old('make') == $b ? 'selected' : '' }}>
                                    {{ $b }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label>Model</label>
                        <input type="text" name="model" class="input" value="{{ old('model') }}">
                    </div>

                    <div>
                        <label>Serial Number (SN)</label>
                        <input type="text" name="sn" class="input" value="{{ old('sn') }}">
                    </div>

                    <div>
                        <label>SMU</label>
                        <input type="text" name="smu" class="input" value="{{ old('smu') }}">
                    </div>

                    <div>
                        <label>Customer</label>
                        <select name="customer" id="customer_engine" class="input">
                            <option value="">-- pilih customer --</option>
                            @foreach ($groupedCustomers as $group => $list)
                                <optgroup label="{{ $group }}">
                                    @foreach ($list as $cust)
                                        <option value="{{ $cust }}" {{ old('customer') == $cust ? 'selected' : '' }}>
                                            {{ $cust }}
                                        </option>
                                    @endforeach
                                </optgroup>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label>Tanggal Inspeksi</label>
                        <input type="date" name="inspection_date" class="input" value="{{ old('inspection_date') }}">
                    </div>

                </div>
            </div>

            {{-- =============== ITEM KERUSAKAN =============== --}}
            <div class="box">
                <h3 style="margin-bottom:15px;">Item Kerusakan</h3>
                <p style="font-size:13px; color:#666; margin-bottom:12px;">
                    Tambahkan deskripsi kerusakan dan dokumentasi foto untuk setiap item.
                </p>

                <template x-for="key in newItems" :key="key">
                    <div class="box item-box"
                         style="background:#fafafa; margin-bottom:14px;"
                         x-data="itemEditor('items['+key+']', $el)"
                         data-photos="[]">

                        <button type="button"
                                class="btn-modern btn-delete full-width"
                                @click="$dispatch('remove-item', key)">
                            Hapus Item
                        </button>

                        <div style="margin-top:14px;">
                            <label>Deskripsi</label>
                            <textarea class="input"
                                      rows="3"
                                      :name="'items['+key+'][description]'"
                                      x-model="descriptions[key]"></textarea>
                        </div>

                        <div style="margin-top:14px;">
                            <label>Foto (klik / drag & drop)</label>

                            <div class="dropzone"
                                 @dragover.prevent
                                 @drop.prevent="handleDrop($event)"
                                 @click="openFile()">

                                <p>Drop foto di sini atau klik untuk upload</p>

                                <div class="preview-container">
                                    <template x-for="(file, i) in newPhotos" :key="'nw'+i">
                                        <div class="thumb">
                                            <img :src="file.preview">
                                            <span class="btn-delete-photo"
                                                  @click.stop="removeNewPhoto(i)">×</span>
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

                            <small style="font-size:11px; color:#777;">
                                Maksimal 10 foto per item, ukuran maks 5 MB per foto.
                            </small>
                        </div>

                    </div>
                </template>

                <button type="button"
                        class="btn-modern btn-primary full-width"
                        @click="addItem()">
                    + Tambah Item
                </button>
            </div>
        </div>

        {{-- =========================================================
        TAB: PARTS (dipindah ke partial)
        ========================================================== --}}
        @include('engine.partials.parts_worksheet')

        {{-- =========================================================
        TAB: DETAIL (dipindah ke partial)
        ========================================================== --}}
        @include('engine.partials.detail_worksheet')

        {{-- =============== SUBMIT (tampil di semua tab) =============== --}}
        <button type="submit"
                class="btn-modern btn-success full-width"
                style="margin-top:8px;">
            Simpan CCR Engine
        </button>

    </form>

</div>

{{-- ===========================
ALPINE JS
=========================== --}}
<script>
function itemEditor(namePrefix, el) {
    const MAX_PHOTOS = 10;
    let oldPhotos = [];

    try { oldPhotos = JSON.parse(el.dataset.photos || '[]'); } catch (e) { oldPhotos = []; }

    return {
        namePrefix,
        existingPhotos: oldPhotos.map(p => ({ id: p.id, url: p.url, deleted: false })),
        newPhotos: [],

        totalPhotos() {
            return this.existingPhotos.filter(p => !p.deleted).length + this.newPhotos.length;
        },

        openFile() { this.$refs.fileInput.click(); },

        handleFileSelect(event) {
            const files = Array.from(event.target.files || []);
            this.addFiles(files);
        },

        handleDrop(event) {
            const files = Array.from(event.dataTransfer.files || [])
                .filter(f => (f.type || '').startsWith('image/'));
            this.addFiles(files);
        },

        addFiles(files) {
            for (let f of files) {
                if (this.totalPhotos() >= MAX_PHOTOS) {
                    alert('Maksimal ' + MAX_PHOTOS + ' foto per item.');
                    break;
                }
                this.newPhotos.push({ file: f, preview: URL.createObjectURL(f) });
            }
            this.syncInputFiles();
        },

        removeNewPhoto(i) {
            this.newPhotos.splice(i, 1);
            this.syncInputFiles();
        },

        syncInputFiles() {
            const dt = new DataTransfer();
            this.newPhotos.forEach(p => dt.items.add(p.file));
            this.$refs.fileInput.files = dt.files;
        }
    };
}

function manageEngineCreate() {

    // ===== repopulate item kerusakan saat validation error =====
    let oldItems = @json(old('items'));
    if (!Array.isArray(oldItems) || oldItems.length === 0) oldItems = [{ description: '' }];

    const keys = oldItems.map((_, idx) => idx);
    const descMap = {};
    oldItems.forEach((it, idx) => { descMap[idx] = (it && it.description) ? it.description : ''; });

    // ===== repopulate parts & detail rows saat validation error =====
    const oldParts  = @json(old('parts_rows'));
    const oldDetail = @json(old('detail_rows'));

    const defaultPartsRow = {
        qty: '', uom: '', part_number: '', part_description: '',
        part_section: '',
        purchase_price: '', total: '', sales_price: '', extended_price: ''
    };

    const defaultDetailRow = {
        seg: '', code: '', component: '', description: '',
        work_order_no: '', hours: '', labour_charge: '', parts_charge: ''
    };

    return {
        tab: @json(old('active_tab', 'ccr')),

        // ===== ITEMS =====
        newItems: keys.length ? keys : [0],
        counter: keys.length ? keys.length : 1,
        descriptions: descMap,

        addItem() {
            const key = this.counter++;
            this.newItems.push(key);
            this.descriptions[key] = '';
        },

        removeItem(key) {
            this.newItems = this.newItems.filter(k => k !== key);
            delete this.descriptions[key];
            if (this.newItems.length === 0) {
                const newKey = this.counter++;
                this.newItems = [newKey];
                this.descriptions[newKey] = '';
            }
        },

        // ===== PARTS =====
        partsRows: (Array.isArray(oldParts) && oldParts.length) ? oldParts : [ { ...defaultPartsRow } ],

        addPartsRow() { this.partsRows.push({ ...defaultPartsRow }); },

        removePartsRow(i) {
            this.partsRows.splice(i, 1);
            if (this.partsRows.length === 0) this.addPartsRow();
        },

        // ===== DETAIL =====
        detailRows: (Array.isArray(oldDetail) && oldDetail.length) ? oldDetail : [ { ...defaultDetailRow } ],

        addDetailRow() { this.detailRows.push({ ...defaultDetailRow }); },

        removeDetailRow(i) {
            this.detailRows.splice(i, 1);
            if (this.detailRows.length === 0) this.addDetailRow();
        },

        // =========================
        // MONEY FORMAT (Rp di dalam input + auto titik)
        // =========================
        parseIDR(val) {
            const s = String(val ?? '');
            const digits = s.replace(/[^\d]/g, '');
            return digits ? parseInt(digits, 10) : 0;
        },

        formatIDR(num) {
            const n = Number(num ?? 0);
            if (!n) return '';
            return n.toLocaleString('id-ID'); // 1125000 -> 1.125.000
        },

        normalizeMoneyField(row, key) {
            // kalau sudah angka, biarkan. kalau string "1.125.000" -> 1125000
            row[key] = this.parseIDR(row[key]);
        },

        onMoneyInput(row, key, event) {
            const raw = this.parseIDR(event.target.value);
            row[key] = raw;
            event.target.value = this.formatIDR(raw);
        },

        // ===== INIT: normalize money + TomSelect =====
        init() {
            // normalize parts money fields
            this.partsRows.forEach(r => {
                ['purchase_price','total','sales_price','extended_price'].forEach(k => this.normalizeMoneyField(r, k));
            });

            // normalize detail money fields
            this.detailRows.forEach(r => {
                ['labour_charge','parts_charge'].forEach(k => this.normalizeMoneyField(r, k));
            });

            // init TomSelect saat tab CCR
            this.$watch('tab', (val) => {
                if (val === 'ccr') this.$nextTick(() => this.ensureTomSelect());
            });
            if (this.tab === 'ccr') this.$nextTick(() => this.ensureTomSelect());
        },

        ensureTomSelect() {
            if (!window.TomSelect) return;
            if (window.__engineCreateTSInited) return;
            window.__engineCreateTSInited = true;

            try {
                new TomSelect('#group_folder_engine', { create:false });
                new TomSelect('#make_engine', { create:false });
                new TomSelect('#customer_engine', { create:false, maxOptions:500 });
            } catch (e) {
                console.warn('TomSelect init skipped:', e);
            }
        },
    }
}
</script>

{{-- =============== TOMSELECT =============== --}}
<link href="https://cdn.jsdelivr.net/npm/tom-select/dist/css/tom-select.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/tom-select/dist/js/tom-select.complete.min.js"></script>

{{-- ===========================
STYLE (SCOPE)
=========================== --}}
<style>
    .engine-create-page,
    .engine-create-page * ,
    .engine-create-page *::before,
    .engine-create-page *::after{ box-sizing: border-box; }

    .engine-create-page [x-cloak]{ display:none !important; }

    /* =========================================
       ✅ ATUR LEBAR KOLOM PARTS DI SINI
       - Part Description mau dikurangi ~5cm -> kecilkan --pl-desc (±190px)
       - Kolom lain mau dipanjangin -> besarin variabelnya
    ========================================== */
    .engine-create-page{
        --pl-itemno: 70px;
        --pl-qty: 70px;
        --pl-uom: 85px;
        --pl-partno: 220px;
        --pl-desc: 520px;     /* ⬅️ INI yang kamu kecilkan/lebarkan (Part Description) */
        --pl-section: 220px;
        --pl-purchase: 210px;
        --pl-total: 200px;
        --pl-sales: 210px;
        --pl-extended: 230px;
        --pl-aksi: 90px;

        --dt-seg: 90px;
        --dt-code: 130px;
        --dt-comp: 260px;
        --dt-desc: 420px;
        --dt-wo: 180px;
        --dt-hours: 110px;
        --dt-lab: 210px;
        --dt-parts: 210px;
        --dt-aksi: 90px;
    }

    /* HEADER CARD */
    .engine-create-page .header-card-master {
        background: #ffffff;
        padding: 28px 38px;
        border-radius: 20px;
        box-shadow: 0 4px 14px rgba(0,0,0,0.07);
        margin-top: 10px;
        margin-bottom: 18px;
        overflow:hidden;
        max-width:100%;
    }
    .engine-create-page .header-content-master {
        display: flex;
        align-items: center;
        gap: 26px;
        min-width:0;
    }
    .engine-create-page .header-logo-master { width: 95px; object-fit:contain; flex:0 0 auto; }
    .engine-create-page .header-title-master { font-size: 28px; font-weight: 800; margin:0; }
    .engine-create-page .header-subtitle-master { font-size: 15px; color: #666; margin-top:6px; }

    /* TABS */
    .engine-create-page .tabbar{ display:flex; gap:10px; flex-wrap:wrap; margin: 0 0 12px; }
    .engine-create-page .tabbtn{
        border:1px solid #cfd3d7;
        background:#f6f7f8;
        padding:10px 14px;
        border-radius:10px;
        font-weight:800;
        cursor:pointer;
        transition:.2s;
    }
    .engine-create-page .tabbtn.active{ background:#0d6efd; border-color:#0d6efd; color:#fff; }
    .engine-create-page .tabbtn:hover{ transform: translateY(-1px); }

    .engine-create-page .accent-line {
        height: 4px;
        background: #E40505;
        border-radius: 20px;
        margin-bottom: 20px;
    }

    /* BOX & INPUT */
    .engine-create-page .box {
        background:white;
        padding:18px;
        border-radius:14px;
        margin-bottom:20px;
        box-shadow:0 3px 10px rgba(0,0,0,0.07);
        overflow:hidden;
    }
    .engine-create-page .full-width { width: 100%; }
    .engine-create-page .input {
        width: 100%;
        padding: 10px 12px;
        border: 1px solid #ccc;
        border-radius: 8px;
        max-width:100%;
    }
    .engine-create-page .input-sm{
        padding: 8px 10px;
        font-size: 13px;
        border-radius: 8px;
    }
    .engine-create-page .input-center{ text-align:center; }

    .engine-create-page .info-grid-edit{ display:flex; flex-direction:column; gap:16px; }
    .engine-create-page .info-grid-edit > div{ min-width:0; }

    /* TomSelect fix */
    .engine-create-page .ts-wrapper{ width:100%; max-width:100%; }
    .engine-create-page .ts-control{ max-width:100%; }

    /* TABLE WRAP */
    .engine-create-page .table-wrap{
        overflow:auto;
        border-radius:10px;
        border:1px solid #e5e7eb;
        background:#fff;
    }
    .engine-create-page .table-pl{
        width:100%;
        border-collapse: collapse;
        font-size:14px;
        min-width: 2100px; /* tabel parts lebar, biar kolom jelas */
    }
    .engine-create-page .table-pl th,
    .engine-create-page .table-pl td{
        border:1px solid #e5e7eb;
        padding:8px;
        background:#fff;
        vertical-align: middle; /* ✅ middle align */
    }
    .engine-create-page .table-pl th{
        background:#f3f4f6;
        font-weight:900;
        white-space:nowrap;
        text-align:center; /* ✅ center header sampai Aksi */
    }

    /* MONEY: Rp di dalam input */
    .engine-create-page .money{
        position:relative;
        width:100%;
    }
    .engine-create-page .money-prefix{
        position:absolute;
        left:10px;
        top:50%;
        transform:translateY(-50%);
        font-weight:900;
        color:#111827;
        pointer-events:none;
    }
    .engine-create-page .money .money-input{
        padding-left:40px;
    }

    /* textarea description di tabel */
    .engine-create-page .textarea-pl{
        resize: vertical;
        min-height: 42px;
    }

    .engine-create-page .btn-mini{
        padding:8px 10px;
        border-radius:8px;
        border:none;
        cursor:pointer;
        font-weight:800;
        color:#fff;
    }
    .engine-create-page .btn-danger{ background:#dc3545; }
    .engine-create-page .btn-danger:hover{ background:#bb2d3b; }

    /* DROPZONE */
    .engine-create-page .dropzone {
        border: 2px dashed #999;
        padding: 20px;
        background: #f8f8f8;
        border-radius: 10px;
        cursor:pointer;
        text-align:center;
        font-size:14px;
        color:#555;
    }
    .engine-create-page .preview-container {
        margin-top: 10px;
        display: flex;
        flex-wrap: wrap;
        gap:10px;
        justify-content:flex-start;
    }
    .engine-create-page .thumb {
        width: 100px;
        height: 100px;
        border-radius: 6px;
        border:1px solid #ccc;
        overflow:hidden;
        position:relative;
        background:#fff;
    }
    .engine-create-page .thumb img { width:100%; height:100%; object-fit:cover; }

    /* BUTTONS */
    .engine-create-page .btn-modern {
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
    .engine-create-page .btn-primary { background:#0d6efd; }
    .engine-create-page .btn-primary:hover { background:#0b5ed7; }
    .engine-create-page .btn-success { background:#198754; }
    .engine-create-page .btn-success:hover { background:#157347; }
    .engine-create-page .btn-delete { background:#dc3545; }
    .engine-create-page .btn-delete:hover { background:#bb2d3b; }

    .engine-create-page .btn-delete-photo {
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

    /* ERROR BOX */
    .engine-create-page .error-box {
        background:#ffe5e5;
        padding:14px;
        border-radius:10px;
        margin-bottom:20px;
    }

    /* BACK BUTTON */
    .engine-create-page .btn-back-enhanced {
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
    .engine-create-page .btn-back-enhanced:hover {
        background: #2b2d2f;
        transform: translateY(-2px);
    }

    .engine-create-page .hidden{ display:none; }

    @media (max-width: 600px) {
        .engine-create-page .btn-back-enhanced {
            font-size: 14px;
            padding: 9px 18px;
            margin-bottom: 22px;
        }
        .engine-create-page .thumb{ width:110px; height:110px; }
    }
</style>

@endsection
