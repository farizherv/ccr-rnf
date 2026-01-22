@extends('layout')

@section('content')

<div class="seat-create-page">

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
    <a href="{{ route('ccr.index') }}" class="btn-back-enhanced">
        ← Kembali
    </a>

    {{-- =============== FORM UTAMA =============== --}}
    <form action="{{ route('seat.store') }}"
          method="POST"
          enctype="multipart/form-data"
          x-data="manageSeatCreate()"
          @remove-seat-item.window="removeItem($event.detail)">

        @csrf

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

        <div class="accent-line"></div>

        {{-- =============== HEADER INFORMATION =============== --}}
        <div class="box">
            <h3 style="margin-bottom:18px;">Informasi Komponen</h3>

            <div class="info-grid-edit">

                <div>
                    <label>Group Folder</label>
                    <input type="text" class="input" value="Operator Seat" readonly>
                    <input type="hidden" name="group_folder" value="Operator Seat">
                </div>

                <div>
                    <label>Component</label>
                    <input type="text"
                           name="component"
                           class="input"
                           value="{{ old('component') }}"
                           placeholder="Contoh: Operator Seat D9R">
                </div>

                <div>
                    <label>Make</label>
                    <select name="make" id="make_seat" class="input">
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
                    <label>Unit</label>
                    <input type="text" name="unit" class="input" value="{{ old('unit') }}">
                </div>

                <div>
                    <label>WO / PR</label>
                    <input type="text" name="wo_pr" class="input" value="{{ old('wo_pr') }}">
                </div>

                <div>
                    <label>Customer</label>
                    <select name="customer" id="customer_seat" class="input">
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

            {{-- ===== ITEM BARU (SEMUA ITEM DI CREATE) ===== --}}
            <template x-for="key in newItems" :key="key">
                <div class="box item-box"
                     style="background:#fafafa; margin-bottom:14px;"
                     x-data="itemEditor('items['+key+']', $el)"
                     data-photos="[]">

                    <button type="button"
                            class="btn-modern btn-delete full-width"
                            @click="$dispatch('remove-seat-item', key)">
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

            {{-- TOMBOL TAMBAH ITEM --}}
            <button type="button"
                    class="btn-modern btn-primary full-width"
                    @click="addItem()">
                + Tambah Item
            </button>

        </div>

        {{-- =============== SUBMIT =============== --}}
        <button type="submit"
                class="btn-modern btn-success full-width"
                style="margin-top:8px;">
            Simpan CCR Seat
        </button>

    </form>

</div>


{{-- =============== ALPINE JS (STYLE LOGIC SAMA DENGAN EDIT-SEAT) =============== --}}
<script>
function itemEditor(namePrefix, el) {

    const MAX_PHOTOS = 10;
    let oldPhotos = [];

    try { oldPhotos = JSON.parse(el.dataset.photos || '[]'); }
    catch (e) { oldPhotos = []; }

    return {
        namePrefix,
        existingPhotos: oldPhotos.map(p => ({ id:p.id, url:p.url, deleted:false })),
        newPhotos: [],

        totalPhotos() {
            return this.existingPhotos.filter(p => !p.deleted).length + this.newPhotos.length;
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

function manageSeatCreate() {

    // repopulate deskripsi saat validation error (foto tidak bisa balik)
    const oldItems = @json(old('items', [['description' => '']]));

    const keys = oldItems.map((_, idx) => idx);

    const descMap = {};
    oldItems.forEach((it, idx) => {
        descMap[idx] = (it && it.description) ? it.description : '';
    });

    return {
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

            // minimal 1 item
            if (this.newItems.length === 0) {
                const newKey = this.counter++;
                this.newItems = [newKey];
                this.descriptions[newKey] = '';
            }
        }
    }
}
</script>


{{-- =============== TOMSELECT =============== --}}
<link href="https://cdn.jsdelivr.net/npm/tom-select/dist/css/tom-select.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/tom-select/dist/js/tom-select.complete.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    new TomSelect('#make_seat', { create:false });
    new TomSelect('#customer_seat', { create:false, maxOptions:500 });
});
</script>


{{-- =============== STYLE (SAMA DENGAN EDIT-SEAT, DI-SCOPE BIAR NAV AMAN) =============== --}}
<style>
    .seat-create-page,
    .seat-create-page * ,
    .seat-create-page *::before,
    .seat-create-page *::after{
        box-sizing: border-box;
    }

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

    @media (max-width: 600px) {
        .seat-create-page .btn-back-enhanced {
            font-size: 14px;
            padding: 9px 18px;
            margin-bottom: 22px;
        }
        .seat-create-page .thumb{ width:110px; height:110px; }
    }
</style>

@endsection
