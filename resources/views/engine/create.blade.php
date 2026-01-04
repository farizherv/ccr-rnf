@extends('layout')

@section('content')

<div class="engine-create-page">

    {{-- ===================== ERROR VALIDATION BOX ===================== --}}
    @if ($errors->any())
        <div class="error-box">
            <strong style="color:#b30000; font-size:15px;">
                ⚠️ Gagal menyimpan CCR Engine:
            </strong>
            <ul style="margin:10px 0 0 22px; color:#800; font-size:14px;">
                @foreach ($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- ===================== BACK BUTTON ===================== --}}
    <a href="{{ route('ccr.index') }}" class="btn-back">
        ← Kembali
    </a>

    {{-- ===================== FORM START ===================== --}}
    <form method="POST"
          action="{{ route('engine.store') }}"
          enctype="multipart/form-data"
          x-data="engineForm()">

        @csrf

        {{-- ===================== HEADER CARD ===================== --}}
        <div class="header-card-master">
            <div class="header-content-master">

                <img src="{{ asset('rnf-logo.png') }}" class="header-logo-master" width="110" height="110" alt="RNF Logo">

                <div class="header-text">
                    <h1 class="header-title-master">CREATE CCR – ENGINE</h1>
                    <p class="header-subtitle-master">
                        Isi informasi komponen dan item kerusakan engine
                    </p>
                </div>

            </div>
        </div>

        <div class="accent-line"></div>

        {{-- ===================== COMPONENT INFORMATION – ENGINE ===================== --}}
        <div class="box">
            <h3 style="margin-bottom:16px;">Component Information – Engine</h3>

            <div class="info-grid-edit">

                {{-- Group Folder --}}
                <div>
                    <label>Group Folder</label>
                    <select name="group_folder" class="input">
                        @foreach ($groupFolders as $g)
                            <option value="{{ $g }}"
                                {{ old('group_folder') == $g ? 'selected' : '' }}>
                                {{ $g }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Component --}}
                <div>
                    <label>Component</label>
                    <input name="component"
                           class="input"
                           value="{{ old('component') }}">
                </div>

                {{-- Make --}}
                <div>
                    <label>Make</label>
                    <select id="make_engine_create" name="make" class="input">
                        <option value="">-- pilih make --</option>
                        @foreach ($brands as $b)
                            <option value="{{ $b }}" {{ old('make') == $b ? 'selected' : '' }}>
                                {{ $b }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Model --}}
                <div>
                    <label>Model</label>
                    <input name="model"
                           class="input"
                           value="{{ old('model') }}">
                </div>

                {{-- SN --}}
                <div>
                    <label>Serial Number (SN)</label>
                    <input name="sn"
                           class="input"
                           value="{{ old('sn') }}">
                </div>

                {{-- SMU --}}
                <div>
                    <label>SMU</label>
                    <input name="smu"
                           class="input"
                           value="{{ old('smu') }}">
                </div>

                {{-- Customer --}}
                <div>
                    <label>Customer</label>
                    <select id="customer_engine_create" name="customer" class="input">
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

                {{-- Inspection Date --}}
                <div>
                    <label>Inspection Date</label>
                    <input type="date"
                           name="inspection_date"
                           class="input"
                           value="{{ old('inspection_date') }}">
                </div>

            </div>
        </div>

        {{-- ====================== ITEM KERUSAKAN ====================== --}}
        <div class="box">
            <h3 style="margin-bottom:12px;">Item Kerusakan</h3>
            <p style="font-size:13px; color:#666; margin-bottom:12px;">
                Tambahkan deskripsi kerusakan dan dokumentasi foto untuk setiap item.
            </p>

            {{-- ITEM DINAMIS --}}
            <template x-for="(item, index) in items" :key="index">
                <div class="box" style="background:#fafafa; margin-bottom:14px;">

                    {{-- HAPUS ITEM --}}
                    <button type="button"
                            class="btn-modern btn-danger"
                            @click="removeItem(index)"
                            style="float:right;margin-top:-5px;">
                        Hapus Item
                    </button>

                    <div style="clear:both;"></div>

                    {{-- DESKRIPSI --}}
                    <div style="margin-top:10px;">
                        <label>Deskripsi Kerusakan</label>
                        <textarea class="input"
                                  rows="3"
                                  :name="'items['+index+'][description]'"
                                  x-model="item.desc"></textarea>
                    </div>

                    {{-- FOTO (DROPZONE) --}}
                    <div style="margin-top:10px;"
                         x-data="itemUploader(index)">

                        <label>Foto (klik / drag & drop)</label>

                        <div class="dropzone"
                             @dragover.prevent
                             @drop.prevent="handleDrop($event)"
                             @click="openFile()">

                            <p>Drop foto di sini atau klik untuk upload</p>

                            <div class="preview-container">
                                <template x-for="(file, i) in previews" :key="i">
                                    <div class="thumb">
                                        <img :src="file.url">
                                        <span class="remove-btn"
                                              @click.stop="removePhoto(i)">×</span>
                                    </div>
                                </template>
                            </div>

                            {{-- FILE INPUT SEBENARNYA (INI YANG DIKIRIM KE LARAVEL) --}}
                            <input type="file"
                                   class="hidden"
                                   multiple
                                   accept="image/*"
                                   :name="'items['+index+'][photos][]'"
                                   x-ref="fileInput"
                                   @change="handleSelect($event)">
                        </div>

                        <small style="font-size:11px; color:#777;">
                            Maksimal 10 foto per item, ukuran maks 8 MB per foto.
                        </small>
                    </div>

                </div>
            </template>

            {{-- TOMBOL TAMBAH ITEM --}}
            <button type="button"
                    class="btn-modern btn-primary"
                    @click="addItem()">
                + Tambah Item
            </button>
        </div>

        {{-- ====================== SUBMIT BUTTON ====================== --}}
        <button type="submit" class="btn-modern btn-success">
            Simpan CCR Engine
        </button>

    </form>

</div> {{-- /engine-create-page --}}

{{-- ======================== TOMSELECT ======================== --}}
<link href="https://cdn.jsdelivr.net/npm/tom-select/dist/css/tom-select.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/tom-select/dist/js/tom-select.complete.min.js"></script>

<script>
    document.addEventListener('alpine:init', () => {

        // ---------- FORM UTAMA ----------
        Alpine.data('engineForm', () => ({
            items: [
                { desc: '' }
            ],

            addItem() {
                this.items.push({ desc: '' });
            },

            removeItem(index) {
                this.items.splice(index, 1);

                // Minimal 1 item
                if (this.items.length === 0) {
                    this.items.push({ desc: '' });
                }
            },
        }));

        // ---------- UPLOADER PER ITEM ----------
        Alpine.data('itemUploader', (index) => ({
            MAX_PHOTOS: 10,
            previews: [],     // { url: 'blob:...' }

            openFile() {
                this.$refs.fileInput.click();
            },

            handleSelect(event) {
                const files = Array.from(event.target.files || []);
                this.setFiles(files);
            },

            handleDrop(event) {
                const droppedFiles = Array.from(event.dataTransfer.files || [])
                    .filter(f => f.type.startsWith('image/'));

                const files = droppedFiles.slice(0, this.MAX_PHOTOS);
                this.setFiles(files);
            },

            setFiles(files) {
                const limited = files.slice(0, this.MAX_PHOTOS);

                const dt = new DataTransfer();
                limited.forEach(f => dt.items.add(f));
                this.$refs.fileInput.files = dt.files;

                this.previews = limited.map(f => ({
                    file: f,
                    url: URL.createObjectURL(f),
                }));
            },

            removePhoto(i) {
                this.previews.splice(i, 1);

                const dt = new DataTransfer();
                this.previews.forEach(p => dt.items.add(p.file));
                this.$refs.fileInput.files = dt.files;
            }
        }));
    });

    // TomSelect init
    document.addEventListener('DOMContentLoaded', function () {
        new TomSelect('#customer_engine_create', {
            create: false,
            sortField: { field: "text", direction: "asc" },
            searchField: ['text'],
            maxOptions: 500,
        });

        new TomSelect('#make_engine_create', {
            create: false,
            sortField: { field: "text", direction: "asc" },
        });
    });
</script>

{{-- ======================== STYLE (SCOPED) ======================== --}}
<style>
/* ===========================================================
   SCOPED: hanya untuk halaman engine create
   Tujuan:
   1) CSS halaman ini tidak ganggu topbar/navigation (btn-modern, box, dll)
   2) Input "Component" tidak overflow keluar card (box-sizing)
=========================================================== */
.engine-create-page,
.engine-create-page * ,
.engine-create-page *::before,
.engine-create-page *::after{
    box-sizing: border-box;
}

/* BOX di halaman ini saja (topbar .box tidak kena) */
.engine-create-page .box{
    margin-bottom:20px;
    overflow:hidden; /* cegah input “nyelonong” keluar */
}

/* ================= BACK BUTTON ================= */
.engine-create-page .btn-back{
    display: inline-block;
    color: white;
    padding: 8px 18px;
    border-radius: 8px;
    font-weight: 600;
    font-size: 14px;
    text-decoration: none;
    background: #5f656a;
    transition: .2s;
    margin-bottom: 18px;
    box-shadow: 0 3px 7px rgba(0,0,0,0.15);
}
.engine-create-page .btn-back:hover{
    background:#2b2d2f;
    transform: translateY(-2px);
}

/* INPUT FIX (anti overflow) */
.engine-create-page .input{
    width:100%;
    max-width:100%;
}

/* TomSelect fix biar ga melebar */
.engine-create-page .ts-wrapper{
    width:100%;
    max-width:100%;
}
.engine-create-page .ts-control{
    max-width:100%;
}

/* === (STYLE LAINNYA TETAP SAMA) === */
.engine-create-page .dropzone{
    border: 2px dashed #999;
    padding: 20px;
    border-radius: 8px;
    cursor: pointer;
    text-align: center;
    background: #f8f8f8;
    margin-bottom: 10px;
}
.engine-create-page .dropzone:hover{ background: #f0f0f0; }

.engine-create-page .preview-container{
    display:flex;
    gap:10px;
    flex-wrap:wrap;
    margin-top:10px;
}

.engine-create-page .thumb{
    width:100px;
    height:100px;
    position:relative;
    border:1px solid #ccc;
    border-radius:6px;
    overflow:hidden;
}
.engine-create-page .thumb img{ width:100%; height:100%; object-fit:cover; }

.engine-create-page .remove-btn{
    position:absolute;
    top:3px;
    right:3px;
    background:#ff4444;
    color:white;
    padding:2px 6px;
    border-radius:50%;
    cursor:pointer;
    font-size:12px;
    font-weight:bold;
}

/* tombol (hanya di halaman ini) */
.engine-create-page .btn-modern{
    display:inline-block;
    padding:10px 18px;
    border-radius:8px;
    font-size:14px;
    font-weight:600;
    text-decoration:none;
    cursor:pointer;
    border:none;
    color:white !important;
    transition:0.2s ease;
    box-shadow:0 3px 7px rgba(0,0,0,0.15);
}

.engine-create-page .btn-primary{ background:#0d6efd; }
.engine-create-page .btn-success{ background:#198754; }
.engine-create-page .btn-danger { background:#dc3545; }

.engine-create-page .header-card-master{
    background:#ffffff;
    padding:28px 38px;
    border-radius:20px;
    box-shadow:0 4px 14px rgba(0,0,0,0.07);
    margin-bottom:24px;
    overflow:hidden; /* aman */
    max-width:100%;
}
.engine-create-page .header-content-master{
    display:flex;
    align-items:center;
    gap:26px;
    min-width:0;
}
.engine-create-page .header-text{ min-width:0; }

.engine-create-page .header-logo-master{
    width:95px;
    object-fit:contain;
    flex:0 0 auto;
}
.engine-create-page .header-title-master{
    margin:0;
    font-size:28px;
    font-weight:800;
}
.engine-create-page .header-subtitle-master{
    margin-top:6px;
    font-size:15px;
    color:#666;
}

.engine-create-page .accent-line{
    height:4px;
    background:#E40505;
    border-radius:20px;
    margin-bottom:20px;
}

.engine-create-page .info-grid-edit{
    display:flex;
    flex-direction:column;
    gap:16px;
}
.engine-create-page .info-grid-edit > div{
    min-width:0; /* penting untuk cegah overflow di flex item */
}

.engine-create-page .hidden{ display:none; }

.engine-create-page .error-box{
    background:#ffe5e5;
    padding:14px;
    border-radius:10px;
    margin-bottom:20px;
}

/* ================= MOBILE OPTIMIZATION (SCOPED) ================= */
@media (max-width: 600px){
    .engine-create-page .box{ padding:12px !important; }

    .engine-create-page label{
        font-size:14px;
        font-weight:600;
    }

    .engine-create-page .input{
        font-size:15px;
        padding:12px !important;
        border-radius:8px;
    }

    .engine-create-page .btn-modern{
        width:100%;
        text-align:center;
        font-size:16px;
        padding:14px !important;
        margin-top:8px;
    }

    .engine-create-page .dropzone{
        padding:30px !important;
        font-size:15px;
    }

    .engine-create-page .thumb{
        width:110px !important;
        height:110px !important;
    }

    .engine-create-page .ts-control{
        min-height:48px !important;
        font-size:15px !important;
    }

    .engine-create-page .ts-wrapper.single .ts-control{
        padding:10px !important;
    }

    .engine-create-page .header-content-master{
        flex-wrap:wrap;
    }
}

/* ================= TABLET OPTIMIZATION (SCOPED) ================= */
@media (min-width: 601px) and (max-width: 992px){
    .engine-create-page .box{ padding:18px !important; }

    .engine-create-page label{ font-size:15px; }

    .engine-create-page .input{
        font-size:16px;
        padding:14px !important;
    }

    .engine-create-page .btn-modern{
        width:100%;
        padding:16px !important;
        font-size:17px;
    }

    .engine-create-page .dropzone{
        padding:35px !important;
        font-size:16px;
    }

    .engine-create-page .thumb{
        width:130px !important;
        height:130px !important;
    }

    .engine-create-page .ts-control{
        min-height:50px !important;
        font-size:16px !important;
        padding:10px 12px !important;
    }
}
</style>

@endsection
