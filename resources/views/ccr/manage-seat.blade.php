@extends('layout')

@section('content')

{{-- ======================= BACK BUTTON ======================= --}}
<a href="{{ route('ccr.manage.menu') }}" class="btn-back">← Kembali ke menu Edit CCR</a>

{{-- ======================= HEADER ======================= --}}
<div class="header-card">
    <div class="header-left">
        <img src="{{ asset('rnf-logo.png') }}" class="header-logo">
        <div>
            <h1 class="header-title">MANAGE CCR – OPERATOR SEAT</h1>
            <p class="header-subtitle">Pilih laporan CCR Operator Seat untuk dilihat atau diedit.</p>
        </div>
    </div>
</div>

<div class="accent-line"></div>


{{-- ======================= FILTER BOX ======================= --}}
<div class="box">

    <h3 style="margin-bottom:18px;">Daftar CCR Operator Seat</h3>

    @php
        $customers = $reports->pluck('customer')->filter()->unique()->values();
    @endphp

    <div class="filter-row">

        {{-- SEARCH --}}
        <div class="filter-group filter-large">
            <label for="searchInput">Cari</label>
            <input id="searchInput" type="text" class="input search-input"
                   placeholder="Cari component, customer, unit, model, make...">
        </div>

        {{-- FILTER CUSTOMER --}}
        <div class="filter-group filter-small">
            <label for="customerFilter">Filter Customer</label>
            <select id="customerFilter" class="input">
                <option value="">Semua customer</option>
                @foreach($customers as $c)
                    <option value="{{ $c }}">{{ $c }}</option>
                @endforeach
            </select>
        </div>

        {{-- SORT BY --}}
        <div class="filter-group filter-small">
            <label for="sortSelect">Sort By</label>
            <select id="sortSelect" class="input">
                <option value="newest">Newest</option>
                <option value="oldest">Oldest</option>
                <option value="updated">Recently Updated</option>
            </select>
        </div>

    </div>
</div>


{{-- ======================= BULK DELETE ======================= --}}
<div x-data="{ selectedReports: [] }">

    <form x-show="selectedReports.length > 0"
          action="{{ route('ccr.seat.deleteMultiple') }}"
          method="POST"
          style="margin-bottom: 18px;">
        @csrf @method('DELETE')

        <template x-for="id in selectedReports">
            <input type="hidden" name="ids[]" :value="id">
        </template>
    </form>
    <form x-show="selectedReports.length > 0"
      action="{{ route('ccr.seat.deleteMultiple') }}"
      method="POST"
      style="margin-bottom:18px;display:flex;gap:12px;align-items:center;">
    @csrf
    @method('DELETE')

    <template x-for="id in selectedReports" :key="id">
        <input type="hidden" name="ids[]" :value="id">
    </template>

    <button type="submit"
            class="btn-premium pdf-btn"
            onclick="return confirm('Yakin ingin menghapus laporan yang dipilih?')">
        🗑️ Hapus Terpilih (<span x-text="selectedReports.length"></span>)
    </button>
    </form>

    {{-- ======================= LIST ======================= --}}
    <div class="box" id="reportList">

        @forelse($reports as $r)

        <div class="report-card"
             data-search="{{ strtolower($r->component.' '.$r->customer.' '.$r->make.' '.$r->model.' '.$r->unit) }}"
             data-customer="{{ $r->customer }}"
             data-date="{{ $r->inspection_date }}"
             data-updated="{{ $r->updated_at }}">

            {{-- LEFT — checkbox + data --}}
            <div class="report-left">

                <input type="checkbox" class="select-checkbox"
                    @change="
                        const card = $event.target.closest('.report-card');
                        if ($event.target.checked) {
                            selectedReports.push({{ $r->id }});
                            card.classList.add('selected');
                        } else {
                            selectedReports = selectedReports.filter(x => x !== {{ $r->id }});
                            card.classList.remove('selected');
                        }
                    ">

                <div class="report-main">
                    <div class="report-title"><strong>{{ $r->component }}</strong></div>

                    <div class="report-meta">
                        <span>Customer: <b>{{ $r->customer ?? '-' }}</b></span>
                        <span>Make: <b>{{ $r->make ?? '-' }}</b></span>
                        <span>Model: <b>{{ $r->model ?? '-' }}</b></span>
                        <span>Unit: <b>{{ $r->unit ?? '-' }}</b></span>
                        <span>Tanggal: <b>{{ $r->inspection_date ? date('Y-m-d', strtotime($r->inspection_date)) : '-' }}</b></span>

                        @if($r->inspection_date)
                        <span class="time-pill">
                            <span class="time-text">
                                {{ \Carbon\Carbon::parse($r->inspection_date)->timezone('Asia/Makassar')->format('H:i') }}
                            </span>
                            <span class="time-wita">(WITA)</span>
                        </span>
                        @endif
                    </div>
                </div>
            </div>

            {{-- RIGHT BUTTONS --}}
            <div class="report-actions">
                {{-- LIHAT --}}
                <a href="{{ route('seat.preview', $r->id) }}" class="btn-premium lihat-btn">
                    👁️ Lihat
                </a>

                {{-- EDIT --}}
                <a href="{{ route('seat.edit', $r->id) }}" class="btn-premium edit-btn">
                    ✏️ Edit
                </a>

                {{-- WORD --}}
                <a href="{{ route('seat.export.word', $r->id) }}"
                   class="btn-premium word-btn">
                   <img src="/icons/word.svg" class="icon-btn">
                   Word
                </a>

            </div>

        </div>

        @empty

        <p>Belum ada data CCR Operator Seat.</p>

        @endforelse

    </div>
</div>


{{-- ======================= SCRIPT ======================= --}}
<script>
    document.addEventListener("DOMContentLoaded", () => {

        const searchInput    = document.getElementById("searchInput");
        const customerFilter = document.getElementById("customerFilter");
        const sortSelect     = document.getElementById("sortSelect");
        const list           = document.getElementById("reportList");
        let cards            = Array.from(document.querySelectorAll(".report-card"));

        function applyFilters() {
            const q = searchInput.value.toLowerCase();
            const c = customerFilter.value;

            cards.forEach(card => {
                let show = true;

                if (q && !card.dataset.search.includes(q)) show = false;
                if (c && card.dataset.customer !== c) show = false;

                card.style.display = show ? "flex" : "none";
            });
        }

        function applySort() {
            let sorted = [...cards];
            let mode = sortSelect.value;

            sorted.sort((a, b) => {
                if (mode === "newest")
                    return new Date(b.dataset.date) - new Date(a.dataset.date);
                if (mode === "oldest")
                    return new Date(a.dataset.date) - new Date(a.dataset.date);
                if (mode === "updated")
                    return new Date(b.dataset.updated) - new Date(a.dataset.updated);
            });

            sorted.forEach(card => list.appendChild(card));
            applyFilters();
        }

        searchInput.addEventListener("input", applyFilters);
        customerFilter.addEventListener("change", applyFilters);
        sortSelect.addEventListener("change", applySort);

        applySort();
    });
</script>


{{-- ======================= STYLE (SAMA ENGINE) ======================= --}}
<style>

    .btn-back{
        display:inline-block;color:white;padding:8px 18px;border-radius:8px;
        background:#5f656a;font-weight:600;font-size:14px;text-decoration:none;
        transition:.2s;box-shadow:0 3px 7px rgba(0,0,0,.15);margin-bottom:18px;
    }
    .btn-back:hover{background:#2b2d2f;transform:translateY(-2px)}

    .header-card{
        background:white;padding:22px;border-radius:14px;margin-bottom:20px;
        box-shadow:0 3px 10px rgba(0,0,0,.07);
    }
    .header-left{display:flex;align-items:center;gap:18px}
    .header-logo{width:80px;height:80px;object-fit:contain}
    .header-title{font-size:20px;font-weight:800;margin:0}
    .header-subtitle{font-size:14px;color:#555;margin-top:4px}
    .accent-line{height:4px;background:#0D6EFD;border-radius:20px;margin-bottom:18px}

    .box{
        background:white;padding:22px;border-radius:14px;margin-bottom:22px;
        box-shadow:0 3px 10px rgba(0,0,0,.07);
    }

    /* FILTER BAR — 3 KOLOM FIXED */
    .filter-row{
        display:flex;
        align-items:flex-end;
        gap:20px;
        flex-wrap:nowrap;
        width:100%;
    }

    /* Kolom besar (CARI) */
    .filter-large{
        flex:1;
        margin-right:30px;
    }

    /* Kolom kecil */
    .filter-small{
        flex:0 0 240px;
    }

    .input{
        width:100%;
        padding:12px 14px;
        border-radius:10px;
        border:1px solid #ccc;
        background:#fafafa;
        font-size:14px;
    }

    /* ============================================
    RESPONSIVE TABLET (max-width 1024px)
    ============================================ */
    @media (max-width: 1024px) {

        .filter-row {
            flex-wrap: wrap;
            gap: 18px;
        }

        .filter-large {
            flex: 1 0 100%;
            margin-right: 0;
            padding-right: 30px;
            box-sizing: border-box;
        }

        .filter-small {
            flex: 1;
        }
    }

    /* ============================================
    RESPONSIVE MOBILE (max-width 600px)
    ============================================ */
    @media (max-width: 600px) {

        .filter-row{
            flex-direction:column;
            align-items:flex-start;
            gap:16px;
            width:100%;
        }

        .filter-large{
            flex:1 0 100%;
            width:100%;
            margin-right:0;
            padding-right:30px;
            box-sizing:border-box;
        }

        .filter-small{
            flex:1 0 100%;
            width:100%;
        }

        .filter-group label{
            margin-left:4px;
        }

        .report-card {
            flex-direction: column;
            align-items: flex-start;
            gap: 14px;
            padding: 18px 12px;
        }

        .report-left {
            flex-direction: column;
            align-items: flex-start;
            gap: 10px;
            width: 100%;
        }

        .report-meta {
            flex-direction: column;
            flex-wrap: nowrap;
            gap: 6px;
            width: 100%;
        }

        .report-actions {
            border-left: none;
            padding-left: 0;
            width: 100%;
            justify-content: flex-start;
            flex-wrap: wrap;
            gap: 10px;
        }

        .report-actions a {
            width: 100%;
            justify-content: center;
        }

        .report-title {
            font-size: 18px;
        }
     }

    /* REPORT ROW */
    .report-card{
        display:flex;justify-content:space-between;align-items:center;
        padding:18px 14px;border-bottom:1px solid #eee;gap:18px;
    }
    .report-left{
        display:flex;align-items:center;gap:16px;flex:1;min-width:0;
    }
    .select-checkbox{
        width:20px;height:20px;accent-color:#C62828;cursor:pointer;
    }
    .report-main{display:flex;flex-direction:column;gap:6px;min-width:0}
    .report-title{font-size:16px;font-weight:700}
    .report-meta{
        font-size:13px;color:#555;display:flex;flex-wrap:wrap;gap:8px;
    }
    .report-meta span{
        background:#f5f5f5;padding:4px 8px;border-radius:999px;
    }
    .time-pill{
        background:#eee;padding:6px 14px;border-radius:999px;
    }
    .time-text,.time-wita{font-weight:700;color:#E40505}
    .time-wita{margin-left:-6px}

    /* BUTTONS */
    .report-actions{
        display:flex;gap:16px;align-items:center;border-left:2px solid #eee;
        padding-left:18px;flex-shrink:0;
    }
    .btn-premium{
        display:inline-flex;align-items:center;gap:8px;padding:10px 18px;
        border-radius:12px;font-size:14px;font-weight:600;color:white;
        text-decoration:none;transition:.25s;box-shadow:0 3px 6px rgba(0,0,0,.1)
    }
    .btn-premium:hover{
        transform:translateY(-2px);box-shadow:0 4px 10px rgba(0,0,0,.15)
    }
    .edit-btn{background:#6b7075}
    .word-btn{background:#185ABD}
    .pdf-btn{background:#C62828}
    .icon-btn{width:18px;height:18px}

    .lihat-btn {
        background: #F57C00;
    }
    .lihat-btn:hover {
        background: #d96b00;
    }

    .report-card.selected {
        background: #fff5f5;
        border: 2px solid rgba(228, 5, 5, 0.35);
        box-shadow: 0 0 14px rgba(228, 5, 5, 0.25);
        border-radius: 14px;
        transition: .25s ease;
    }

</style>

@endsection
