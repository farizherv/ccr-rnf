@extends('layout')

@section('content')

{{-- ======================= BACK BUTTON (GLOBAL STYLE) ======================= --}}
<a href="{{ url('/ccr') }}" class="btn-back">← Kembali</a>

{{-- ===================== HEADER CARD ===================== --}}
<div class="header-card-master">
    <div class="header-content-master">

        <img src="{{ asset('rnf-logo.png') }}" class="header-logo-master">

        <div>
            <h1 class="header-title-master">CREATE CCR – OPERATOR SEAT</h1>
            <p class="header-subtitle-master">
                Isi informasi komponen dan item kerusakan operator seat
            </p>
        </div>

    </div>
</div>

<div class="accent-line"></div>


{{-- ===================== FORM START ===================== --}}
<form method="POST"
      action="{{ route('seat.store') }}"
      enctype="multipart/form-data"
      x-data="seatForm()">

    @csrf


    {{-- ===================== COMPONENT INFORMATION – OPERATOR SEAT ===================== --}}
    <div class="box">
        <h3 style="margin-bottom:16px;">Component Information – Operator Seat</h3>

        <div class="info-grid-edit">

            <div>
                <label>Group Folder</label>
                <input type="text" name="group_folder" class="input"
                    value="Operator Seat" readonly>
            </div>

            <div>
                <label>Component</label>
                <input type="text" name="component" class="input"
                    placeholder=>
            </div>

            <div>
                <label>Make</label>
                <select id="make" name="make" class="input">
                    <option value="">-- pilih make --</option>
                    @foreach ($brands as $b)
                        <option value="{{ $b }}">{{ $b }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label>Unit</label>
                <input type="text" name="unit" class="input">
            </div>

            <div>
                <label>Model</label>
                <input type="text" name="model" class="input">
            </div>

            <div>
                <label>WO / PR</label>
                <input type="text" name="wo_pr" class="input">
            </div>

            <div>
                <label>Customer</label>
                <select id="customer" name="customer" class="input">
                    <option value="">-- pilih customer --</option>
                    @foreach ($groupedCustomers as $group => $list)
                        <optgroup label="{{ $group }}">
                            @foreach ($list as $cust)
                                <option value="{{ $cust }}">{{ $cust }}</option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
            </div>

            <div>
                <label>Inspection Date</label>
                <input type="date" name="inspection_date" class="input">
            </div>

        </div>
    </div>


    {{-- ====================== ITEM KERUSAKAN ====================== --}}
    <div class="box">
        <h3 style="margin-bottom:12px;">Item Kerusakan</h3>
        <p style="font-size:13px; color:#666; margin-bottom:12px;">
            Tambahkan deskripsi kerusakan dan dokumentasi foto untuk setiap item.
        </p>

        <template x-for="(item, index) in items" :key="index">
            <div class="box" style="background:#fafafa; margin-bottom:14px;">

                {{-- HAPUS ITEM (KANAN, KECIL) --}}
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

                {{-- FOTO --}}
                <div style="margin-top:10px;">
                    <label>Foto (klik / drag & drop)</label>

                    <div class="dropzone"
                        @dragover.prevent
                        @drop.prevent="addDroppedFiles($event, index)"
                        @click="triggerFileInput(index)">

                        <p>Drop foto di sini atau klik untuk upload</p>

                        <div class="preview-container">
                            <template x-for="(file, i) in item.photos" :key="i">
                                <div class="thumb">
                                    <img :src="file.preview">
                                    <span class="remove-btn"
                                        @click.stop="removePhoto(index, i)">×</span>
                                </div>
                            </template>
                        </div>

                        <input type="file"
                            multiple
                            class="hidden"
                            :id="'fileInput'+index"
                            :name="'items['+index+'][photos][]'"
                            @change="addSelectedFiles($event, index)">
                    </div>

                    <small style="font-size:11px; color:#777;">
                        Maksimal 10 foto per item, ukuran maks 8 MB per foto.
                    </small>
                </div>

            </div>
        </template>

        {{-- TAMBAH ITEM --}}
        <button type="button"
                class="btn-modern btn-primary"
                @click="addItem()">
            + Tambah Item
        </button>
    </div>

    {{-- ====================== SUBMIT BUTTON ====================== --}}
    <button type="submit" class="btn-modern btn-success">
        Simpan CCR Seat
    </button>

</form>


{{-- ======================== TOMSELECT ======================== --}}
<link href="https://cdn.jsdelivr.net/npm/tom-select/dist/css/tom-select.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/tom-select/dist/js/tom-select.complete.min.js"></script>

<script>
new TomSelect('#make', {
    create: false,
    sortField: { field: "text", direction: "asc" },
});

new TomSelect('#customer', {
    create: false,
    sortField: { field: "text", direction: "asc" },
    searchField: ['text'],
    maxOptions: 500
});
</script>


{{-- ======================== ALPINE JS ======================== --}}
<script>
function seatForm() {
    return {
        items: [{ desc: '', photos: [] }],

        addItem() {
            this.items.push({ desc:'', photos:[] });
        },

        removeItem(i) {
            this.items.splice(i, 1);
        },

        triggerFileInput(i) {
            document.getElementById('fileInput'+i).click();
        },

        addSelectedFiles(e, i) {
            let files = [...e.target.files];
            files.forEach(f=>{
                this.items[i].photos.push({
                    file:f,
                    preview:URL.createObjectURL(f)
                });
            });
        },

        addDroppedFiles(e, i){
            let dropped=[...e.dataTransfer.files];
            dropped.forEach(f=>{
                this.items[i].photos.push({
                    file:f,
                    preview:URL.createObjectURL(f)
                });
            });

            let dt=new DataTransfer();
            this.items[i].photos.forEach(p=>dt.items.add(p.file));
            document.getElementById('fileInput'+i).files=dt.files;
        },

        removePhoto(itemIdx, photoIdx){
            this.items[itemIdx].photos.splice(photoIdx,1);
            let dt=new DataTransfer();
            this.items[itemIdx].photos.forEach(p=>dt.items.add(p.file));
            document.getElementById('fileInput'+itemIdx).files=dt.files;
        }
    }
}
</script>



{{-- ======================== STYLE (INCLUDING GLOBAL BACK BUTTON) ======================== --}}
<style>

.box { margin-bottom:20px; }

/* ================= BACK BUTTON GLOBAL STYLE (OPTION A) ================= */
.btn-back {
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
.btn-back:hover {
    background: #2b2d2f;
    transform: translateY(-2px);
}



/* === (LANJUT STYLE LAINNYA TETAP SAMA — TIDAK DIUBAH) === */
.dropzone {
    border: 2px dashed #999;
    padding: 20px;
    border-radius: 8px;
    cursor: pointer;
    text-align: center;
    background: #f8f8f8;
    margin-bottom: 10px;
}
.dropzone:hover { background: #f0f0f0; }

.preview-container {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    margin-top: 10px;
}

.thumb {
    width: 100px;
    height: 100px;
    position: relative;
    border: 1px solid #ccc;
    border-radius: 6px;
    overflow: hidden;
}

.thumb img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.remove-btn {
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


.btn-modern {
    display: inline-block;
    padding: 10px 18px;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 600;
    text-decoration: none;
    cursor: pointer;
    border: none;
    color: white !important;
    transition: 0.2s ease;
    box-shadow: 0 3px 7px rgba(0,0,0,0.15);
}

.header-card-master {
    background: #ffffff;
    padding: 28px 38px;
    border-radius: 20px;
    box-shadow: 0 4px 14px rgba(0,0,0,0.07);
    margin-bottom: 24px;
}

.header-content-master {
    display: flex;
    align-items: center;
    gap: 26px;
}

.header-logo-master {
    width: 95px;
    object-fit: contain;
}

.header-title-master {
    margin: 0;
    font-size: 28px;
    font-weight: 800;
}

.header-subtitle-master {
    margin-top: 6px;
    font-size: 15px;
    color: #666;
}

.accent-line {
    height: 4px;
    background: #E40505;
    border-radius: 20px;
    margin-bottom: 20px;
}

.info-grid-edit {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.btn-primary { background:#0d6efd; }
.btn-success { background:#198754; }
.btn-danger  { background:#dc3545; }

.hidden { display:none; }

/* ERROR BOX */
.error-box {
    background:#ffe5e5;
    padding:14px;
    border-radius:10px;
    margin-bottom:20px;
}

/* ================= MOBILE OPTIMIZATION ================= */
@media (max-width: 600px) {

    body {
        padding: 10px !important;
        font-size: 16px;
    }

    .box {
        padding: 12px !important;
    }

    label {
        font-size: 14px;
        font-weight: 600;
    }

    .input {
        font-size: 15px;
        padding: 12px !important;
        border-radius: 8px;
    }

    .btn-modern {
        width: 100%;
        text-align: center;
        font-size:16px;
        padding: 14px !important;
        margin-top: 8px;
    }

    .dropzone {
        padding: 30px !important;
        font-size: 15px;
    }

    .thumb {
        width: 110px !important;
        height: 110px !important;
    }

    .box > div {
        margin-bottom: 16px;
    }

    .ts-control {
        min-height: 48px !important;
        font-size: 15px !important;
    }

    .ts-wrapper.single .ts-control {
        padding: 10px !important;
    }
}


/* ================= TABLET OPTIMIZATION ================= */
@media (min-width: 601px) and (max-width: 992px) {

    body {
        padding: 20px !important;
        font-size: 17px;
    }

    .box {
        padding: 18px !important;
    }

    label {
        font-size: 15px;
    }

    .input {
        font-size: 16px;
        padding: 14px !important;
    }

    .btn-modern {
        width: 100%;
        padding: 16px !important;
        font-size: 17px;
    }

    .dropzone {
        padding: 35px !important;
        font-size: 16px;
    }

    .thumb {
        width: 130px !important;
        height: 130px !important;
    }

    .ts-control {
        min-height: 50px !important;
        font-size: 16px !important;
        padding: 10px 12px !important;
    }
}

</style>

@endsection
