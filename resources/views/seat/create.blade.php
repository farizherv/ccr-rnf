@extends('layout')

@section('content')

<div class="seat-create-page">

    {{-- ======================= BACK BUTTON (GLOBAL STYLE) ======================= --}}
    <a href="{{ url('/ccr') }}" class="btn-back">← Kembali</a>

    {{-- ===================== HEADER CARD ===================== --}}
    <div class="header-card-master">
        <div class="header-content-master">

            <img src="{{ asset('rnf-logo.png') }}" class="header-logo-master" width="110" height="110" alt="RNF Logo">

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
                           value="{{ old('component') }}"
                           placeholder="Contoh: Operator Seat CAT D9R">
                </div>

                <div>
                    <label>Make</label>
                    <select id="make" name="make" class="input">
                        <option value="">-- pilih make --</option>
                        @foreach ($brands as $b)
                            <option value="{{ $b }}" {{ old('make') == $b ? 'selected' : '' }}>{{ $b }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label>Unit</label>
                    <input type="text" name="unit" class="input" value="{{ old('unit') }}">
                </div>

                <div>
                    <label>Model</label>
                    <input type="text" name="model" class="input" value="{{ old('model') }}">
                </div>

                <div>
                    <label>WO / PR</label>
                    <input type="text" name="wo_pr" class="input" value="{{ old('wo_pr') }}">
                </div>

                <div>
                    <label>Customer</label>
                    <select id="customer" name="customer" class="input">
                        <option value="">-- pilih customer --</option>
                        @foreach ($groupedCustomers as $group => $list)
                            <optgroup label="{{ $group }}">
                                @foreach ($list as $cust)
                                    <option value="{{ $cust }}" {{ old('customer') == $cust ? 'selected' : '' }}>{{ $cust }}</option>
                                @endforeach
                            </optgroup>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label>Inspection Date</label>
                    <input type="date" name="inspection_date" class="input" value="{{ old('inspection_date') }}">
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
                <div class="box box-inner" style="background:#fafafa; margin-bottom:14px;">

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
        document.addEventListener('DOMContentLoaded', function () {
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
                    if (this.items.length === 0) this.items.push({ desc:'', photos:[] });
                },

                triggerFileInput(i) {
                    document.getElementById('fileInput'+i).click();
                },

                addSelectedFiles(e, i) {
                    let files = [...(e.target.files || [])];
                    files.forEach(f=>{
                        this.items[i].photos.push({
                            file:f,
                            preview:URL.createObjectURL(f)
                        });
                    });

                    // sync input filelist
                    let dt=new DataTransfer();
                    this.items[i].photos.forEach(p=>dt.items.add(p.file));
                    document.getElementById('fileInput'+i).files=dt.files;
                },

                addDroppedFiles(e, i){
                    let dropped=[...((e.dataTransfer && e.dataTransfer.files) || [])];
                    dropped.forEach(f=>{
                        if(!f.type || !f.type.startsWith('image/')) return;
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

    {{-- ======================== STYLE (SCOPED, BIAR NAV TOPBAR TIDAK KEUBAH) ======================== --}}
    <style>
        /* ✅ FIX OVERFLOW + supaya input ga keluar card */
        .seat-create-page,
        .seat-create-page *,
        .seat-create-page *::before,
        .seat-create-page *::after { box-sizing: border-box; }

        .seat-create-page .box { margin-bottom:20px; width:100%; max-width:100%; overflow:hidden; }
        .seat-create-page .box-inner{ overflow: visible; } /* inner box (item) boleh show */

        /* BACK */
        .seat-create-page .btn-back {
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
        .seat-create-page .btn-back:hover {
            background: #2b2d2f;
            transform: translateY(-2px);
        }

        /* INPUT */
        .seat-create-page .input{
            width:100%;
            max-width:100%;
            display:block;
        }

        /* Dropzone + preview */
        .seat-create-page .dropzone {
            border: 2px dashed #999;
            padding: 20px;
            border-radius: 8px;
            cursor: pointer;
            text-align: center;
            background: #f8f8f8;
            margin-bottom: 10px;
        }
        .seat-create-page .dropzone:hover { background: #f0f0f0; }

        .seat-create-page .preview-container {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 10px;
        }

        .seat-create-page .thumb {
            width: 100px;
            height: 100px;
            position: relative;
            border: 1px solid #ccc;
            border-radius: 6px;
            overflow: hidden;
        }

        .seat-create-page .thumb img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .seat-create-page .remove-btn {
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

        /* BUTTON */
        .seat-create-page .btn-modern {
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

        .seat-create-page .btn-primary { background:#0d6efd; }
        .seat-create-page .btn-success { background:#198754; }
        .seat-create-page .btn-danger  { background:#dc3545; }

        .seat-create-page .hidden { display:none; }

        /* HEADER CARD */
        .seat-create-page .header-card-master {
            background: #ffffff;
            padding: 28px 38px;
            border-radius: 20px;
            box-shadow: 0 4px 14px rgba(0,0,0,0.07);
            margin-bottom: 24px;

            width:100%;
            max-width:100%;
            overflow:hidden; /* ✅ cegah “nyelonong” */
        }

        .seat-create-page .header-content-master {
            display: flex;
            align-items: center;
            gap: 26px;
            min-width:0;
        }

        .seat-create-page .header-logo-master {
            width: 95px;
            object-fit: contain;
            flex: 0 0 auto;
        }

        .seat-create-page .header-title-master {
            margin: 0;
            font-size: 28px;
            font-weight: 800;
        }

        .seat-create-page .header-subtitle-master {
            margin-top: 6px;
            font-size: 15px;
            color: #666;
        }

        .seat-create-page .accent-line {
            height: 4px;
            background: #E40505;
            border-radius: 20px;
            margin-bottom: 20px;
        }

        .seat-create-page .info-grid-edit {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }
        .seat-create-page .info-grid-edit > div{ min-width:0; }

        /* TomSelect jangan overflow */
        .seat-create-page .ts-wrapper,
        .seat-create-page .ts-control { max-width:100%; }

        /* ================= MOBILE OPTIMIZATION ================= */
        @media (max-width: 600px) {
            .seat-create-page .box { padding: 12px !important; }

            .seat-create-page label {
                font-size: 14px;
                font-weight: 600;
            }

            .seat-create-page .input {
                font-size: 15px;
                padding: 12px !important;
                border-radius: 8px;
            }

            .seat-create-page .btn-modern {
                width: 100%;
                text-align: center;
                font-size:16px;
                padding: 14px !important;
                margin-top: 8px;
            }

            .seat-create-page .dropzone {
                padding: 30px !important;
                font-size: 15px;
            }

            .seat-create-page .thumb {
                width: 110px !important;
                height: 110px !important;
            }

            .seat-create-page .ts-control {
                min-height: 48px !important;
                font-size: 15px !important;
            }

            .seat-create-page .ts-wrapper.single .ts-control {
                padding: 10px !important;
            }
        }

        /* ================= TABLET OPTIMIZATION ================= */
        @media (min-width: 601px) and (max-width: 992px) {
            .seat-create-page .box { padding: 18px !important; }

            .seat-create-page label { font-size: 15px; }

            .seat-create-page .input {
                font-size: 16px;
                padding: 14px !important;
            }

            /* ✅ ini yang dulu nabrak topbar karena global — sekarang aman karena scoped */
            .seat-create-page .btn-modern {
                width: 100%;
                padding: 16px !important;
                font-size: 17px;
            }

            .seat-create-page .dropzone {
                padding: 35px !important;
                font-size: 16px;
            }

            .seat-create-page .thumb {
                width: 130px !important;
                height: 130px !important;
            }

            .seat-create-page .ts-control {
                min-height: 50px !important;
                font-size: 16px !important;
                padding: 10px 12px !important;
            }
        }
    </style>

</div>

@endsection
