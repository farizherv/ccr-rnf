@extends('layout')

@section('content')


{{-- ======================= BACK BUTTON ======================= --}}
<a href="{{ route('ccr.index') }}" class="btn-back">← Kembali</a>

{{-- ======================= HEADER ======================= --}}
<div class="header-card">
    <div class="header-left">
        <img src="{{ asset('rnf-logo.png') }}" class="header-logo" alt="RNF">
        <div>
            <h1 class="header-title">MONITORING DIREKTUR</h1>
            <p class="header-subtitle">Daftar CCR yang menunggu persetujuan (Approve / Reject).</p>
        </div>
    </div>
</div>

<div class="accent-line"></div>

<div data-page="director-monitoring">

    {{-- ======================= FILTER BOX ======================= --}}
    <div class="box">
        <h3 style="margin-bottom:16px;">Daftar CCR Engine & Seat</h3>

        @php
            $customers = $reports->pluck('customer')->filter()->unique()->values();
        @endphp

        <div class="mon-filter-grid">

            {{-- SEARCH --}}
            <div class="mon-filter-group mon-filter-search">
                <label for="searchInputMon">Cari</label>
                <input id="searchInputMon" type="text" class="input"
                    placeholder="Cari component, customer, make, model, SN...">
            </div>

            {{-- FILTER CUSTOMER --}}
            <div class="mon-filter-group">
                <label for="customerFilterMon">Filter Customer</label>
                <select id="customerFilterMon" class="input">
                    <option value="">Semua customer</option>
                    @foreach($customers as $c)
                        <option value="{{ $c }}">{{ $c }}</option>
                    @endforeach
                </select>
            </div>

            {{-- FILTER TYPE --}}
            <div class="mon-filter-group">
                <label for="typeFilterMon">Filter Type</label>
                <select id="typeFilterMon" class="input">
                    <option value="">Semua</option>
                    <option value="engine">Engine</option>
                    <option value="seat">Seat</option>
                </select>
            </div>

            {{-- SORT --}}
            <div class="mon-filter-group">
                <label for="sortSelectMon">Sort By</label>
                <select id="sortSelectMon" class="input">
                    <option value="submitted_newest">Newest (Submitted)</option>
                    <option value="submitted_oldest">Oldest (Submitted)</option>
                    <option value="inspection_newest">Newest (Inspection)</option>
                    <option value="inspection_oldest">Oldest (Inspection)</option>
                </select>
            </div>

        </div>

    </div>

    {{-- ======================= LIST ======================= --}}
    <div class="box" id="reportListMon">

        @forelse($reports as $r)
            @php
                $isEngine = ($r->type === 'engine');

                $previewRoute = $isEngine
                    ? (Illuminate\Support\Facades\Route::has('engine.preview') ? route('engine.preview', $r->id) : '#')
                    : (Illuminate\Support\Facades\Route::has('seat.preview') ? route('seat.preview', $r->id) : '#');

                $editRoute = $isEngine
                    ? (Illuminate\Support\Facades\Route::has('engine.edit') ? route('engine.edit', $r->id) : '#')
                    : (Illuminate\Support\Facades\Route::has('seat.edit') ? route('seat.edit', $r->id) : '#');

                // waktu (WITA)
                $inspectionText = $r->inspection_date
                    ? \Carbon\Carbon::parse($r->inspection_date)->timezone('Asia/Makassar')->format('Y-m-d H:i')
                    : '-';

                $submittedText = $r->submitted_at
                    ? \Carbon\Carbon::parse($r->submitted_at)->timezone('Asia/Makassar')->format('Y-m-d H:i')
                    : '-';

                $searchBlob = strtolower(
                    ($r->component ?? '') . ' ' .
                    ($r->customer ?? '') . ' ' .
                    ($r->make ?? '') . ' ' .
                    ($r->model ?? '') . ' ' .
                    ($r->sn ?? '')
                );
            @endphp

            <div class="mon-card report-card"
                 data-id="{{ $r->id }}"
                 data-type="{{ $r->type }}"
                 data-customer="{{ $r->customer }}"
                 data-search="{{ $searchBlob }}"
                 data-inspection="{{ $r->inspection_date }}"
                 data-submitted="{{ $r->submitted_at }}">

                {{-- LEFT --}}
                <div class="mon-left">
                    <div class="mon-title-row">
                        <div class="mon-title">
                            <strong>{{ $r->component }}</strong>
                        </div>

                        <span class="type-pill {{ $isEngine ? 'type-engine' : 'type-seat' }}">
                            {{ strtoupper($r->type) }}
                        </span>
                    </div>

                    <div class="mon-meta">
                        <span class="meta-pill">Customer: <b>{{ $r->customer ?? '-' }}</b></span>
                        <span class="meta-pill">Make: <b>{{ $r->make ?? '-' }}</b></span>
                        <span class="meta-pill">Model: <b>{{ $r->model ?? '-' }}</b></span>
                        <span class="meta-pill">SN: <b>{{ $r->sn ?? '-' }}</b></span>

                        <span class="meta-pill meta-inspection">
                            Inspection: <b>{{ $inspectionText }}</b> <span class="wita">(WITA)</span>
                        </span>

                        <span class="meta-pill meta-submitted">
                            Submitted: <b>{{ $submittedText }}</b> <span class="wita">(WITA)</span>
                        </span>
                    </div>
                </div>

                {{-- RIGHT --}}
                <div class="mon-right">

                    <div class="action-top">
                        <a href="{{ $previewRoute }}" target="_blank" rel="noopener noreferrer"
                           class="btn-pill btn-preview">
                            👁️ Preview
                        </a>

                        <a href="{{ $editRoute }}" target="_blank" rel="noopener noreferrer"
                           class="btn-pill btn-edit">
                            ✏️ Edit
                        </a>
                    </div>

                    {{-- APPROVE --}}
                    <form action="{{ route('director.monitoring.approve', $r->id) }}"
                          method="POST" class="panel panel-approve">
                        @csrf

                        <label class="panel-label">Optional Note (Approve)</label>
                        <textarea name="approve_note" class="input textarea"
                                  rows="2"
                                  placeholder="Boleh dikosongkan"></textarea>

                        <button type="submit" class="btn-action btn-approve">
                            ✅ Approve
                        </button>
                    </form>

                    {{-- REJECT --}}
                    <form action="{{ route('director.monitoring.reject', $r->id) }}"
                          method="POST" class="panel panel-reject">
                        @csrf

                        {{-- supaya error reject cuma muncul di card ini --}}
                        <input type="hidden" name="_reject_id" value="{{ $r->id }}">

                        <label class="panel-label">Catatan Revisi (Reject) <span class="req">*</span></label>

                        <textarea name="director_note" class="input textarea"
                                  rows="3"
                                  placeholder="Catatan revisi wajib">{{ old('_reject_id') == $r->id ? old('director_note') : '' }}</textarea>

                        @error('director_note')
                            @if(old('_reject_id') == $r->id)
                                <div class="error-text">{{ $message }}</div>
                            @endif
                        @enderror

                        <button type="submit" class="btn-action btn-reject">
                            ❌ Reject + Note
                        </button>
                    </form>

                </div>
            </div>

            <div class="mon-divider"></div>

        @empty
            <p style="margin:0;">Tidak ada CCR yang menunggu persetujuan.</p>
        @endforelse

    </div>
</div>

{{-- ======================= FILTER/SORT SCRIPT ======================= --}}
<script>
document.addEventListener("DOMContentLoaded", () => {
    const page = document.querySelector('[data-page="director-monitoring"]');
    if (!page) return;

    const searchInput = page.querySelector("#searchInputMon");
    const customerFilter = page.querySelector("#customerFilterMon");
    const typeFilter = page.querySelector("#typeFilterMon");
    const sortSelect = page.querySelector("#sortSelectMon");
    const list = page.querySelector("#reportListMon");

    if (!searchInput || !customerFilter || !typeFilter || !sortSelect || !list) return;

    let cards = Array.from(list.querySelectorAll(".report-card"));

    const toTime = (val) => {
        if (!val) return 0;
        const safe = String(val).trim().replace(" ", "T");
        const t = Date.parse(safe);
        return isNaN(t) ? 0 : t;
    };

    function applyFilters(){
        const q = (searchInput.value || "").toLowerCase().trim();
        const c = (customerFilter.value || "").trim();
        const t = (typeFilter.value || "").trim();

        cards.forEach(card => {
            const blob = (card.dataset.search || "");
            const cust = (card.dataset.customer || "").trim();
            const type = (card.dataset.type || "").trim();

            let show = true;
            if (q && !blob.includes(q)) show = false;
            if (c && cust !== c) show = false;
            if (t && type !== t) show = false;

            card.style.display = show ? "flex" : "none";
            // divider di bawah card mengikuti display card (optional)
            const divider = card.nextElementSibling;
            if (divider && divider.classList.contains('mon-divider')) {
                divider.style.display = show ? "block" : "none";
            }
        });
    }

    function applySort(){
        cards = Array.from(list.querySelectorAll(".report-card"));

        const mode = sortSelect.value;

        const sorted = [...cards].sort((a,b) => {
            const subA = toTime(a.dataset.submitted);
            const subB = toTime(b.dataset.submitted);
            const insA = toTime(a.dataset.inspection);
            const insB = toTime(b.dataset.inspection);

            if (mode === "submitted_newest") return subB - subA;
            if (mode === "submitted_oldest") return subA - subB;
            if (mode === "inspection_newest") return insB - insA;
            if (mode === "inspection_oldest") return insA - insB;
            return 0;
        });

        // Append card + divider pasangan
        sorted.forEach(card => {
            const divider = card.nextElementSibling;
            list.appendChild(card);
            if (divider && divider.classList.contains('mon-divider')) list.appendChild(divider);
        });

        cards = sorted;
        applyFilters();
    }

    searchInput.addEventListener("input", applyFilters);
    customerFilter.addEventListener("change", applyFilters);
    typeFilter.addEventListener("change", applyFilters);
    sortSelect.addEventListener("change", applySort);

    applySort();
});
</script>

{{-- ======================= STYLE ======================= --}}
<style>
/* ========= base ========= */
.btn-back{
    display:inline-block;
    color:white;
    padding:10px 18px;
    border-radius:10px;
    background:#5f656a;
    font-weight:700;
    font-size:14px;
    text-decoration:none;
    transition:.2s;
    box-shadow:0 3px 7px rgba(0,0,0,.15);
    margin-bottom:18px;
}
.btn-back:hover{ background:#2b2d2f; transform:translateY(-2px) }

.header-card{
    background:white;
    padding:22px;
    border-radius:14px;
    margin-bottom:18px;
    box-shadow:0 3px 10px rgba(0,0,0,.07);
}
.header-left{ display:flex; align-items:center; gap:18px }
.header-logo{ width:74px; height:74px; object-fit:contain }
.header-title{ font-size:20px; font-weight:900; margin:0; letter-spacing:.2px }
.header-subtitle{ font-size:14px; color:#555; margin-top:4px }

.accent-line{
    height:4px;
    background:#9F8170;
    border-radius:20px;
    margin-bottom:18px
}

.box{
    background:white;
    padding:22px;
    border-radius:14px;
    margin-bottom:18px;
    box-shadow:0 3px 10px rgba(0,0,0,.07);
}
.input{
    width:100%;
    padding:12px 14px;
    border-radius:12px;
    border:1px solid #d1d5db;
    background:#fafafa;
    font-size:14px;
    outline:none;
}

.input:focus{
    background:#fff;
    border-color: rgba(159,129,112,.55); /* #9F8170 */
    box-shadow:0 0 0 4px rgba(159,129,112,.18);
}


.textarea{ resize:vertical; min-height:58px; }

/* ==== penting biar width input ga bikin nabrak ==== */
*, *::before, *::after { box-sizing: border-box; }

/* ========= filter layout (anti dempet) ========= */
.mon-filter-grid{
    display: grid;

    /* Cari dibuat lebih pendek biar ada jarak dengan Customer */
    grid-template-columns:
        minmax(280px, 520px)   /* Cari */
        minmax(220px, 1fr)     /* Customer */
        minmax(180px, 1fr)     /* Type */
        minmax(220px, 1fr);    /* Sort */

    column-gap: 22px;  /* ✅ jarak kanan-kiri (ini yang kamu mau) */
    row-gap: 16px;     /* jarak atas-bawah */
    align-items: end;
}

/* biar isi grid boleh mengecil dan ga “dorong” sampai nabrak */
.mon-filter-group{ min-width: 0; }

.mon-filter-group label{
    display:block;
    font-weight:800;
    font-size:13px;
    color:#111827;
    margin-bottom:8px;
}

/* tablet */
@media (max-width:1024px){
    .mon-filter-grid{
        grid-template-columns: 1fr 1fr;
        column-gap: 16px;
        row-gap: 14px;
    }
    .mon-filter-search{ grid-column: 1 / -1; } /* search full width */
}

/* mobile */
@media (max-width:600px){
    .mon-filter-grid{
        grid-template-columns: 1fr;
        column-gap: 0;
        row-gap: 12px;
    }
}


/* ========= card layout ========= */
.mon-card{
    display:flex;
    justify-content:space-between;
    gap:18px;
    padding:18px 14px;
    border-radius:16px;
    border:1px solid #eef2f7;
    background: #ffffff;
}
.mon-divider{
    height:1px;
    background:#eef2f7;
    margin:14px 0;
}

.mon-left{ flex:1; min-width:0; }
.mon-right{ width: 520px; max-width: 520px; }

.mon-title-row{
    display:flex;
    align-items:center;
    justify-content:flex-start;
    gap:12px;
    flex-wrap:wrap;
    margin-bottom:10px;
}
.mon-title strong{
    font-size:18px;
    font-weight:900;
    line-height:1.2;
    overflow-wrap:anywhere;
    word-break:break-word;
}

.type-pill{
    display:inline-flex;
    align-items:center;
    padding:6px 12px;
    border-radius:999px;
    font-weight:900;
    font-size:12px;
    letter-spacing:.4px;
    border:2px solid transparent;
}

.type-seat,
.type-engine{
    color:#9F8170;
    background: rgba(159,129,112,.12);
    border-color: rgba(159,129,112,.28);
}

/* meta pills */
.mon-meta{
    display:flex;
    flex-wrap:wrap;
    gap:10px;
}
.meta-pill{
    background:#f3f4f6;
    padding:7px 12px;
    border-radius:999px;
    font-size:13px;
    color:#374151;
}
.meta-pill b{ color:#111827; }

.wita{
    font-weight:900;
    color:#E40505;
    margin-left:6px;
}

/* inspection & submitted special */
.meta-inspection{
    background: rgba(13,110,253,.08);
    border:1px solid rgba(13,110,253,.15);
}
.meta-submitted{
    background: rgba(159,129,112,.10);         /* ✅ sesuai request */
    border:1px solid rgba(159,129,112,.28);
}
.meta-submitted b{ color:#6b4f42; }

/* ========= right panel ========= */
.action-top{
    display:flex;
    justify-content:flex-end;
    gap:12px;
    margin-bottom:12px;
}

.btn-pill{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    gap:8px;
    padding:10px 16px;
    border-radius:999px;
    font-weight:900;
    font-size:14px;
    text-decoration:none;
    color:#fff;
    box-shadow:0 10px 18px rgba(0,0,0,.10);
    transition:.18s;
}
.btn-pill:hover{ transform:translateY(-1px); filter:brightness(.98); }

.btn-preview{ background:#F57C00; }
.btn-edit{ background:#6b7075; }

.panel{
    background:#fff;
    border:1px solid #eef2f7;
    border-radius:16px;
    padding:14px;
    margin-bottom:12px;
    box-shadow:0 10px 25px rgba(0,0,0,.05);
}
.panel-label{
    display:block;
    font-weight:900;
    font-size:13px;
    margin-bottom:8px;
    color:#111827;
}
.req{ color:#E40505; }

.btn-action{
    width:100%;
    margin-top:10px;
    border:none;
    cursor:pointer;
    padding:12px 16px;
    border-radius:999px;
    font-weight:900;
    font-size:15px;
    color:#fff;
    box-shadow:0 18px 35px rgba(0,0,0,.10);
    transition:.18s;
}
.btn-action:hover{ transform:translateY(-1px); }

.btn-approve{ background:#22c55e; }
.btn-reject{ background:#ef4444; }

.error-text{
    margin-top:8px;
    font-weight:800;
    color:#E40505;
    font-size:13px;
}

/* ========= responsive card ========= */
@media (max-width:1024px){
    .mon-card{
        flex-direction:column;
        align-items:stretch;
    }
    .mon-right{
        width:100%;
        max-width:100%;
    }
    .action-top{
        justify-content:flex-start;
    }
}

@media (max-width:600px){
    .btn-pill{ width:100%; }
    .action-top{
        flex-direction:column;
        align-items:stretch;
    }
    .meta-pill{ max-width:100%; overflow-wrap:anywhere; word-break:break-word; }
}

</style>

@endsection
