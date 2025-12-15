@extends('layout')

@section('content')

{{-- ===================== BACK BUTTON ===================== --}}
<a href="{{ route('ccr.edit.seat') }}" class="btn-back">← Kembali ke daftar CCR Seat</a>

<form action="{{ route('seat.update.header', $report->id) }}"
      method="POST"
      enctype="multipart/form-data"
      x-data="manageSeat()">

    @csrf
    @method('PUT')

    {{-- ===================== HEADER CARD (SAMA SEPERTI ENGINE) ===================== --}}
    <div class="header-card-master">
        <div class="header-content-master">

            <img src="{{ asset('rnf-logo.png') }}" class="header-logo-master">

            <div>
                <h1 class="header-title-master">EDIT CCR – OPERATOR SEAT</h1>
                <p class="header-subtitle-master">
                    Edit informasi komponen & tambah item kerusakan baru
                </p>
            </div>

        </div>
    </div>

    <div class="accent-line"></div>



    {{-- ===================== EDIT HEADER INFORMATION ===================== --}}
    <div class="box">
        <h3 style="margin-bottom:18px;">Edit Informasi Komponen</h3>

        <div class="info-grid-edit"> {{-- Vertical Form --}}

            {{-- Group Folder --}}
            <div>
                <label>Group Folder</label>
                <input type="text" name="group_folder" class="input"
                       value="{{ $report->group_folder }}">
            </div>

            {{-- Component --}}
            <div>
                <label>Component</label>
                <input type="text" name="component" class="input"
                       value="{{ $report->component }}">
            </div>

            {{-- Make --}}
            <div>
                <label>Make</label>
                <select name="make" id="make" class="input">
                    <option value="">-- pilih make --</option>
                    @foreach ($brands as $brand)
                        <option value="{{ $brand }}" {{ $report->make == $brand ? 'selected' : '' }}>
                            {{ $brand }}
                        </option>
                    @endforeach
                </select>
            </div>

            {{-- Model --}}
            <div>
                <label>Model</label>
                <input type="text" name="model" class="input"
                       value="{{ $report->model }}">
            </div>

            {{-- Unit --}}
            <div>
                <label>Unit</label>
                <input type="text" name="unit" class="input"
                       value="{{ $report->unit }}">
            </div>

            {{-- Serial Number --}}
            <div>
                <label>Serial Number (SN)</label>
                <input type="text" name="sn" class="input"
                       value="{{ $report->sn }}">
            </div>

            {{-- SMU --}}
            <div>
                <label>SMU</label>
                <input type="text" name="smu" class="input"
                       value="{{ $report->smu }}">
            </div>

            {{-- Customer --}}
            <div>
                <label>Customer</label>
                <select name="customer" id="customer" class="input">
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

            {{-- Tanggal Inspeksi --}}
            <div>
                <label>Tanggal Inspeksi</label>
                <input type="date" name="inspection_date" class="input"
                       value="{{ $report->inspection_date }}">
            </div>

        </div>
    </div>



    {{-- ===================== ITEM BARU ===================== --}}
    <div class="box">
        <h3 style="margin-bottom:15px;">Tambah Item Kerusakan Baru</h3>

        <template x-for="(item, index) in newItems" :key="index">
            <div class="box" style="background:#fafafa; margin-bottom:14px;">

                <button type="button"
                        class="btn-modern btn-danger"
                        @click="removeNewItem(index)"
                        style="float:right;margin-top:-5px;">
                    Hapus Item
                </button>

                <div style="clear:both;"></div>

                <div>
                    <label>Deskripsi</label>
                    <textarea class="input"
                              rows="3"
                              :name="'new_items['+index+'][description]'"
                              x-model="item.desc"></textarea>
                </div>

                <div>
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
                                          @click="removePhoto(index, i)">×</span>
                                </div>
                            </template>
                        </div>

                        <input type="file"
                               multiple
                               :id="'fileInputNew'+index"
                               class="hidden"
                               :name="'new_items['+index+'][photos][]'"
                               @change="addSelectedFiles($event, index)">
                    </div>
                </div>

            </div>
        </template>

        <button type="button" class="btn-modern btn-primary" @click="addNewItem()">
            + Tambah Item
        </button>

    </div>



    {{-- ===================== SIMPAN ===================== --}}
    <button type="submit" class="btn-modern btn-success" style="margin-top:8px;">
        Simpan Perubahan
    </button>

</form>



{{-- ======================== ALPINE JS ======================== --}}
<script>
function manageSeat() {
    return {
        newItems: [
            { desc: '', photos: [] }
        ],

        addNewItem() {
            this.newItems.push({ desc: '', photos: [] });
        },

        removeNewItem(i) {
            this.newItems.splice(i, 1);
        },

        triggerFileInput(i) {
            document.getElementById('fileInputNew' + i).click();
        },

        addSelectedFiles(event, i) {
            let files = [...event.target.files];
            files.forEach(f => {
                this.newItems[i].photos.push({
                    file: f,
                    preview: URL.createObjectURL(f)
                });
            });
        },

        addDroppedFiles(event, i) {
            let dropped = [...event.dataTransfer.files];
            dropped.forEach(f => {
                this.newItems[i].photos.push({
                    file: f,
                    preview: URL.createObjectURL(f)
                });
            });

            let dt = new DataTransfer();
            this.newItems[i].photos.forEach(p => dt.items.add(p.file));
            document.getElementById('fileInputNew' + i).files = dt.files;
        },

        removePhoto(itemIndex, photoIndex) {
            this.newItems[itemIndex].photos.splice(photoIndex, 1);
        }
    }
}
</script>



{{-- ===================== TOMSELECT ===================== --}}
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
        maxOptions: 500
    });
</script>



{{-- ======================== STYLE (SAMA PERSIS ENGINE) ======================== --}}
<style>

/* ================= HEADER CARD (SAMA PERSIS EDIT-MENU) ================= */
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

/* LOGO SIZE EXACT MATCH (95px) */
.header-logo-master {
    width: 95px;
    height: auto;
    object-fit: contain;
}

/* TITLE EXACT MATCH */
.header-title-master {
    margin: 0;
    font-size: 28px;
    font-weight: 800;
    letter-spacing: .3px;
}

.header-subtitle-master {
    margin: 6px 0 0;
    font-size: 15px;
    color: #666;
}

/* Accent line setelah card */
.accent-line {
    height: 4px;
    width: 100%;
    background: #0D6EFD;
    border-radius: 20px;
    margin-bottom: 20px;
}

/* BOX GLOBAL */
.box { 
    margin-bottom:20px; 
    background:white;
    padding:18px;
    border-radius:14px;
    box-shadow:0 3px 10px rgba(0,0,0,0.07);
}

/* =============== FORM VERTIKAL 1 BARIS =============== */
/* ================= FORM HEADER FULL VERTICAL ================= */
.info-grid-edit {
    display: flex;
    flex-direction: column;
    gap: 18px;
}

.info-grid-edit label {
    font-weight: 600;
    font-size: 13px;
    margin-bottom: 6px;
    display: block;
}

.info-grid-edit input,
.info-grid-edit select {
    width: 100%;
}

/* INPUT */
.input {
    width: 100%;
    padding: 10px 12px;
    margin-top: 5px;
    margin-bottom: 10px;
    border: 1px solid #ccc;
    border-radius: 8px;
    font-size: 14px;
}
.input:focus {
    outline: none;
    border-color: #E40505;
    box-shadow: 0 0 6px rgba(228,5,5,0.25);
}

/* Dropzone */
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

.hidden { display: none; }


/* ================= BACK BUTTON GLOBAL STYLE ================= */
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


/* ================= BTN MODERN ================= */
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

.btn-primary { background: #0d6efd; }
.btn-primary:hover { background: #0a58ca; transform: translateY(-2px); }

.btn-success { background: #198754; }
.btn-success:hover { background: #157347; transform: translateY(-2px); }

.btn-danger { background: #dc3545; }
.btn-danger:hover { background: #bb2d3b; transform: translateY(-2px); }


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
        font-size: 16px;
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
