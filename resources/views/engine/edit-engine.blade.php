@extends('layout')

@section('content')

{{-- =============== ERROR VALIDATION =============== --}}
@if ($errors->any())
    <div class="error-box">
        <strong style="color:#b30000; font-size:15px;">⚠️ Gagal menyimpan perubahan:</strong>
        <ul style="margin:10px 0 0 22px; color:#800; font-size:14px;">
            @foreach ($errors->all() as $err)
                <li>{{ $err }}</li>
            @endforeach
        </ul>
    </div>
@endif


{{-- =============== KEMBALI =============== --}}
<a href="{{ route('ccr.manage.engine') }}" class="btn-back-enhanced">
    ← Kembali ke Manage CCR
</a>


{{-- =============== FORM UTAMA =============== --}}
<form
    action="{{ route('engine.update.header', $report->id) }}"
    method="POST"
    enctype="multipart/form-data"
    x-data="manageEngine()"
    @remove-item.window="removeNewItem($event.detail)">

    @csrf
    @method('PUT')

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

    <div class="accent-line"></div>



    {{-- =============== HEADER INFORMATION =============== --}}
    <div class="box">
        <h3 style="margin-bottom:18px;">Informasi Komponen</h3>

        <div class="info-grid-edit">

            <div>
                <label>Group Folder</label>
                <input type="text" name="group_folder" class="input" value="{{ $report->group_folder }}">
            </div>

            <div>
                <label>Component</label>
                <input type="text" name="component" class="input" value="{{ $report->component }}">
            </div>

            <div>
                <label>Make</label>
                <select name="make" id="make_engine" class="input">
                    <option value="">-- pilih make --</option>
                    @foreach ($brands as $brand)
                        <option value="{{ $brand }}" {{ $report->make == $brand ? 'selected' : '' }}>
                            {{ $brand }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label>Model</label>
                <input type="text" name="model" class="input" value="{{ $report->model }}">
            </div>

            <div>
                <label>Serial Number (SN)</label>
                <input type="text" name="sn" class="input" value="{{ $report->sn }}">
            </div>

            <div>
                <label>SMU</label>
                <input type="text" name="smu" class="input" value="{{ $report->smu }}">
            </div>

            <div>
                <label>Customer</label>
                <select name="customer" id="customer_engine" class="input">
                    <option value="">-- pilih customer --</option>
                    @foreach ($groupedCustomers as $group => $list)
                        <optgroup label="{{ $group }}">
                            @foreach ($list as $cust)
                                <option value="{{ $cust }}" {{ $report->customer == $cust ? 'selected' : '' }}>
                                    {{ $cust }}
                                </option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
            </div>

            <div>
                <label>Tanggal Inspeksi</label>
                <input type="date"
                       name="inspection_date"
                       class="input"
                       value="{{ $report->inspection_date ? \Carbon\Carbon::parse($report->inspection_date)->format('Y-m-d') : '' }}">
            </div>

        </div>
    </div>



    {{-- =============== ITEM KERUSAKAN =============== --}}
    <div class="box">
        <h3 style="margin-bottom:15px;">Item Kerusakan</h3>

        {{-- ===== ITEM LAMA ===== --}}
        @foreach($report->items as $item)

            @php
                $existingPhotos = $item->photos->map(fn($p) => [
                    'id'  => $p->id,
                    'url' => asset('storage/'.$p->path),
                ]);
            @endphp

            <div class="box item-box"
                 style="background:#fafafa; margin-bottom:14px;"
                 x-data="itemEditor('items[{{ $item->id }}]', $el)"
                 data-photos='@json($existingPhotos)'>

                <button type="button"
                        class="btn-modern btn-delete full-width"
                        @click="if(confirm('Hapus item ini?')) document.getElementById('delItem{{ $item->id }}').submit()">
                    Hapus Item
                </button>

                <div style="margin-top:14px;">
                    <label>Deskripsi</label>
                    <textarea class="input"
                              name="items[{{ $item->id }}][description]"
                              rows="3">{{ $item->description }}</textarea>
                </div>

                {{-- DROPZONE ITEM LAMA --}}
                <div style="margin-top:14px;">
                    <label>Foto (klik / drag & drop)</label>

                    <div class="dropzone"
                         @dragover.prevent
                         @drop.prevent="handleDrop($event)"
                         @click="openFile()">

                        <p>Drop foto di sini atau klik untuk upload</p>

                        <div class="preview-container">

                            {{-- FOTO LAMA --}}
                            <template x-for="(photo, i) in existingPhotos" :key="'old'+i">
                                <div class="thumb" x-show="!photo.deleted">
                                    <img :src="photo.url">

                                    {{-- hidden input delete --}}
                                    <input type="hidden"
                                           :name="namePrefix + '[delete_photos][]'"
                                           :value="photo.id"
                                           x-bind:disabled="!photo.deleted">

                                    <span class="btn-delete-photo"
                                          @click="photo.deleted = true">×</span>
                                </div>
                            </template>

                            {{-- FOTO BARU --}}
                            <template x-for="(file, i) in newPhotos" :key="'new'+i">
                                <div class="thumb">
                                    <img :src="file.preview">
                                    <span class="btn-delete-photo"
                                          @click="removeNewPhoto(i)">×</span>
                                </div>
                            </template>
                        </div>

                        <input type="file"
                               class="hidden"
                               multiple
                               x-ref="fileInput"
                               :name="namePrefix + '[photos][]'"
                               @change="handleFileSelect($event)">
                    </div>
                </div>
            </div>
        @endforeach

        {{-- ===== ITEM BARU ===== --}}
        <template x-for="key in newItems" :key="key">
            <div class="box item-box"
                 style="background:#fafafa; margin-bottom:14px;"
                 x-data="itemEditor('new_items['+key+']', $el)"
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
                              :name="'new_items['+key+'][description]'"></textarea>
                </div>

                <div style="margin-top:14px;">
                    <label>Foto (klik / drag & drop)</label>

                    <div class="dropzone"
                         @dragover.prevent
                         @drop.prevent="handleDrop($event)"
                         @click="openFile()">

                        <p>Drop foto di sini atau klik untuk upload</p>

                        <div class="preview-container">
                            {{-- hanya foto baru, karena foto lama = 0 --}}
                            <template x-for="(file, i) in newPhotos" :key="'nw'+i">
                                <div class="thumb">
                                    <img :src="file.preview">
                                    <span class="btn-delete-photo"
                                          @click="removeNewPhoto(i)">×</span>
                                </div>
                            </template>
                        </div>

                        <input type="file"
                               class="hidden"
                               multiple
                               x-ref="fileInput"
                               :name="'new_items['+key+'][photos][]'"
                               @change="handleFileSelect($event)">
                    </div>
                </div>

            </div>
        </template>


        {{-- TOMBOL TAMBAH ITEM BARU --}}
        <button type="button"
                class="btn-modern btn-primary full-width"
                @click="addNewItem()">
            + Tambah Item
        </button>

    </div> {{-- END BOX ITEM KERUSAKAN --}}



    {{-- =============== SUBMIT =============== --}}
    <button type="submit"
            class="btn-modern btn-success full-width"
            style="margin-top:8px;">
        Simpan Perubahan
    </button>

</form>



{{-- =============== FORM DELETE ITEM (DI LUAR FORM UTAMA) =============== --}}
@foreach($report->items as $item)
    <form id="delItem{{ $item->id }}"
          method="POST"
          action="{{ route('engine.item.delete', $item->id) }}"
          style="display:none;">
        @csrf
        @method('DELETE')
    </form>
@endforeach



{{-- =============== ALPINE JS =============== --}}
<script>
function itemEditor(namePrefix, el) {

    const MAX_PHOTOS = 10;
    let oldPhotos = [];

    try {
        oldPhotos = JSON.parse(el.dataset.photos || '[]');
    } catch (e) {
        oldPhotos = [];
    }

    return {
        namePrefix,
        existingPhotos: oldPhotos.map(p => ({
            id: p.id,
            url: p.url,
            deleted: false
        })),
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
            const files = Array.from(event.dataTransfer.files || []);
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

function manageEngine() {
    return {
        newItems: [],   // hanya menyimpan key angka
        counter: 0,

        addNewItem() {
            this.newItems.push(this.counter++);
        },

        removeNewItem(key) {
            this.newItems = this.newItems.filter(k => k !== key);
        }
    }
}
</script>



{{-- =============== TOMSELECT =============== --}}
<link href="https://cdn.jsdelivr.net/npm/tom-select/dist/css/tom-select.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/tom-select/dist/js/tom-select.complete.min.js"></script>

<script>
    new TomSelect('#make_engine', { create:false });
    new TomSelect('#customer_engine', { create:false, maxOptions:500 });
</script>



{{-- =============== STYLE (TIDAK DIUBAH SECARA VISUAL) =============== --}}
<style>
    /* ===========================
    HEADER CARD
    =========================== */
    .header-card-master {
        background: #ffffff;
        padding: 28px 38px;
        border-radius: 20px;
        box-shadow: 0 4px 14px rgba(0,0,0,0.07);
        margin-top: 10px;    /* ⭐ tambahan agar tidak mepet tombol back */
        margin-bottom: 24px;
    }

    .header-content-master {
        display: flex;
        align-items: center;
        gap: 26px;
    }
    .header-logo-master {
        width: 95px;
    }
    .header-title-master {
        font-size: 28px;
        font-weight: 800;
    }
    .header-subtitle-master {
        font-size: 15px;
        color: #666;
    }

    .accent-line {
        height: 4px;
        background: #0D6EFD;
        border-radius: 20px;
        margin-bottom: 20px;
    }

    /* ===========================
    BOX & INPUT
    =========================== */
    .box {
        background:white;
        padding:18px;
        border-radius:14px;
        margin-bottom:20px;
        box-shadow:0 3px 10px rgba(0,0,0,0.07);
    }

    .full-width { width: 100%; }

    .input {
        width: 100%;
        padding: 10px 12px;
        border: 1px solid #ccc;
        border-radius: 8px;
    }

    /* ===========================
    DROPZONE
    =========================== */
    .dropzone {
        border: 2px dashed #999;
        padding: 20px;
        background: #f8f8f8;
        border-radius: 10px;
        cursor:pointer;
        text-align:center;
        font-size:14px;
        color:#555;
    }

    .preview-container {
        margin-top: 10px;
        display: flex;
        flex-wrap: wrap;
        gap:10px;
    }

    .thumb {
        width: 100px;
        height: 100px;
        border-radius: 6px;
        border:1px solid #ccc;
        overflow:hidden;
        position:relative;
    }
    .thumb img {
        width:100%;
        height:100%;
        object-fit:cover;
    }

    /* ===========================
    BUTTONS
    =========================== */
    .btn-modern {
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
    }

    .btn-primary { background:#0d6efd; }
    .btn-primary:hover { background:#0b5ed7; }

    .btn-success { background:#198754; }
    .btn-success:hover { background:#157347; }

    .btn-delete { background:#dc3545; }
    .btn-delete:hover { background:#bb2d3b; }

    .btn-delete-photo {
        position:absolute;
        top:4px; right:4px;
        background:#dc3545;
        color:white;
        border-radius:50%;
        padding:2px 6px;
        cursor:pointer;
        font-size:12px;
    }

    /* ===========================
    ERROR BOX
    =========================== */
    .error-box {
        background:#ffe5e5;
        padding:14px;
        border-radius:10px;
        margin-bottom:20px;
    }

    /* ===========================
    ⭐ NEW BACK BUTTON STYLE
    =========================== */
    .btn-back-enhanced {
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
        margin-bottom: 14px;   /* jarak ideal */
    }
    .btn-back-enhanced:hover {
        background: #2b2d2f;
        transform: translateY(-2px);
    }

    @media (max-width: 600px) {
        .btn-back-enhanced {
            font-size: 14px;
            padding: 9px 18px;
            margin-bottom: 22px;
        }
    }
</style>

@endsection
