@extends('layout')

@section('content')

{{-- ✅ WRAP SELURUH HALAMAN (BIAR CSS SCOPED & TIDAK NIMPA TOPBAR/NAV) --}}
<div data-page="manage-seat">

    {{-- ======================= BACK BUTTON ======================= --}}
    <a href="{{ route('ccr.manage.menu') }}" class="btn-back">← Kembali ke menu Edit CCR</a>

    {{-- ======================= HEADER ======================= --}}
    <div class="header-card">
        <div class="header-left">
            <img src="{{ asset('rnf-logo.png') }}" class="header-logo" width="110" height="110" alt="RNF Logo">
            <div>
                <h1 class="header-title">MANAGE CCR – SEAT</h1>
                <p class="header-subtitle">Pilih laporan CCR Seat untuk dilihat atau diedit.</p>
            </div>
        </div>
    </div>

    <div class="accent-line"></div>

    {{-- ======================= FILTER BOX ======================= --}}
    <div class="box">
        <h3 style="margin-bottom:18px;">Daftar CCR Seat</h3>

        @php
            $customers = $reports->pluck('customer')->filter()->unique()->values();
        @endphp

        <div class="filter-row">

            {{-- SEARCH --}}
            <div class="filter-group filter-large">
                <label for="searchInputSeat">Cari</label>
                <input id="searchInputSeat" type="text" class="input search-input"
                       placeholder="Cari component, customer, make, model, SN...">
            </div>

            {{-- FILTER CUSTOMER --}}
            <div class="filter-group filter-small">
                <label for="customerFilterSeat">Filter Customer</label>
                <select id="customerFilterSeat" class="input">
                    <option value="">Semua customer</option>
                    @foreach($customers as $c)
                        <option value="{{ $c }}">{{ $c }}</option>
                    @endforeach
                </select>
            </div>

            {{-- SORT BY --}}
            <div class="filter-group filter-small">
                <label for="sortSelectSeat">Sort By</label>
                <select id="sortSelectSeat" class="input">
                    <option value="newest">Newest</option>
                    <option value="oldest">Oldest</option>
                    <option value="updated">Recently Updated</option>
                </select>
            </div>

        </div>
    </div>

    {{-- ======================= BULK + LIST (SATU SCOPE) ======================= --}}
    <div
        x-data="seatBulk()"
        x-init="window.__seatSyncSelectAll = () => syncSelectAll();"
    >

        {{-- BULK DELETE --}}
        <form x-show="selectedReports.length > 0"
              action="{{ route('ccr.seat.trashMultiple') }}"
              method="POST"
              class="bulk-bar">
            @csrf
            <template x-for="id in selectedReports" :key="id">
                <input type="hidden" name="ids[]" :value="id">
            </template>
            <button type="submit"
                    class="bulk-btn bulk-btn-danger"
                    onclick="return confirm('Pindahkan ke Sampah? Data akan terhapus permanen otomatis setelah 7 hari.')">
                🗑️ Hapus Terpilih (<span x-text="selectedReports.length"></span>)
            </button>
        </form>

        {{-- LIST --}}
        <div class="box" id="reportListSeat" x-ref="list">

            {{-- ✅ SELECT ALL --}}
            <div class="select-all-row">
                <input
                    type="checkbox"
                    id="selectAllSeat"
                    x-ref="selectAll"
                    class="select-checkbox select-all-checkbox"
                    @change="toggleSelectAll($event)"
                >
                <label for="selectAllSeat" class="select-all-label">
                    <b>Select All</b>
                </label>
            </div>

            <div class="select-divider"></div>

            @forelse($reports as $r)
                @php
                    $status = $r->approval_status ?? 'draft';

                    $badge = [
                        'draft'     => ['text' => 'Draft',     'dot' => 'dot-draft'],
                        'waiting'   => ['text' => 'In review', 'dot' => 'dot-waiting'],
                        'in_review' => ['text' => 'In review', 'dot' => 'dot-waiting'],
                        'approved'  => ['text' => 'Approved',  'dot' => 'dot-approved'],
                        'rejected'  => ['text' => 'Rejected',  'dot' => 'dot-rejected'],
                    ][$status] ?? ['text' => 'Draft', 'dot' => 'dot-draft'];

                    $hasNote = !empty($r->director_note);
                    $notePillText = ($status === 'rejected') ? 'Rejected Note' : 'Approved Note';
                @endphp

                <div class="report-card"
                     data-id="{{ $r->id }}"
                     data-search="{{ strtolower(($r->component ?? '').' '.($r->customer ?? '').' '.($r->make ?? '').' '.($r->model ?? '').' '.($r->sn ?? '')) }}"
                     data-customer="{{ $r->customer }}"
                     data-date="{{ $r->inspection_date }}"
                     data-updated="{{ $r->updated_at }}">

                    <div class="report-left">
                        <input type="checkbox"
                               class="select-checkbox row-checkbox"
                               @change="toggleOne({{ $r->id }}, $event)">

                        <div class="report-main">
                            <div class="report-title">
                                <strong>{{ $r->component }}</strong>

                                {{-- ✅ STATUS BADGE --}}
                                <div class="status-pill">
                                    <span class="status-dot {{ $badge['dot'] }}"></span>
                                    <span class="status-text">{{ $badge['text'] }}</span>
                                </div>

                                {{-- ✅ NOTE BUTTON (muncul utk Rejected/Approved jika ada note) --}}
                                @php
                                    $hasNote = !empty($r->director_note);
                                    $noteType = ($status === 'approved') ? 'approved' : 'rejected';
                                    $notePillText = ($status === 'approved') ? 'Approved Note' : 'Rejected Note';
                                @endphp

                                @if(in_array($status, ['rejected','approved']) && $hasNote)
                                    <button
                                        type="button"
                                        class="status-note-btn {{ $noteType === 'approved' ? 'status-note-btn-approved' : 'status-note-btn-rejected' }}"
                                        data-note="{{ $r->director_note }}"
                                        data-pill="{{ $notePillText }}"
                                        data-type="{{ $noteType }}"
                                        onclick="openDirectorNote(this)"
                                    >
                                        <span class="note-ico">📝</span>
                                        <span class="note-txt">Note</span>
                                    </button>
                                @endif
                            </div>

                            <div class="report-meta">
                                <span>Customer: <b>{{ $r->customer ?? '-' }}</b></span>
                                <span>Make: <b>{{ $r->make ?? '-' }}</b></span>
                                <span>Model: <b>{{ $r->model ?? '-' }}</b></span>
                                <span>SN: <b>{{ $r->sn ?? '-' }}</b></span>
                                <span>Tanggal: <b>{{ $r->inspection_date ? date('Y-m-d', strtotime($r->inspection_date)) : '-' }}</b></span>

                                <div style="display:flex; gap:10px; flex-wrap:wrap;">

                                    <span class="time-pill">
                                        <span class="time-text">
                                            {{ $r->updated_at
                                                ? $r->updated_at->timezone('Asia/Makassar')->format('H:i')
                                                : '--:--'
                                            }}
                                        </span>
                                        <span class="time-wita">(WITA)</span>
                                    </span>

                                </div>

                            </div>
                        </div>
                    </div>

                    <div class="report-actions">
                        <a href="{{ route('seat.preview', $r->id) }}" class="btn-premium lihat-btn">👁️ Lihat</a>
                        <a href="{{ route('seat.edit', $r->id) }}" class="btn-premium edit-btn">✏️ Edit</a>

                        <a href="{{ route('seat.export.word', $r->id) }}" class="btn-premium word-btn">
                            <img src="/icons/word.svg" class="icon-btn"> Word
                        </a>

                        {{-- ✅ ACTION: Submit / In Review / Re-submit --}}
                        @if(in_array($status, ['waiting','in_review']))
                            <span class="btn-premium btn-inreview">⏳ In Review</span>
                        @elseif(in_array($status, ['draft','rejected']))
                            <form action="{{ route('seat.submit', $r->id) }}" method="POST" class="submit-form">
                                @csrf
                                <button type="submit" class="btn-premium btn-submit"
                                        onclick="return confirm('Kirim CCR Seat ini untuk dicek?')">
                                    📤 Submit
                                </button>
                            </form>
                        @elseif($status === 'approved')
                            <form action="{{ route('seat.submit', $r->id) }}" method="POST" class="submit-form">
                                @csrf
                                <input type="hidden" name="resubmit" value="1">
                                <button type="submit" class="btn-premium btn-resubmit"
                                        onclick="return confirm('CCR ini sudah Approved. Yakin mau Re-submit CCR Seat ini lagi?')">
                                    📨 Re-submit
                                </button>
                            </form>
                        @endif
                    </div>

                </div>
            @empty
                <p>Data CCR Seat masih kosong.</p>
            @endforelse

        </div>

        {{-- ======================= MODAL NOTE (1x saja) ======================= --}}
        <div id="directorNoteModal" class="note-modal" aria-hidden="true">
            <div class="note-modal-backdrop" onclick="closeDirectorNote()"></div>

            <div class="note-modal-card" role="dialog" aria-modal="true" aria-labelledby="directorNotePill" data-note-type="rejected">
                <div class="note-modal-header">
                    <span id="directorNotePill" class="pill-note">Rejected Note</span>

                    <button type="button" class="note-modal-close" onclick="closeDirectorNote()" aria-label="Close">✕</button>
                </div>

                <div class="note-modal-body">
                    <div id="directorNoteText" class="note-text"></div>
                </div>

                <div class="note-modal-footer">
                    <button type="button" class="note-ok-btn" onclick="closeDirectorNote()">OK</button>
                </div>
            </div>
        </div>

        <script>
            function openDirectorNote(btn){
                const modal = document.getElementById('directorNoteModal');
                const card  = modal.querySelector('.note-modal-card');
                const pill  = document.getElementById('directorNotePill');
                const text  = document.getElementById('directorNoteText');

                const note = btn?.dataset?.note || '-';
                const type = btn?.dataset?.type || 'rejected';
                const pillText = btn?.dataset?.pill || (type === 'approved' ? 'Approved Note' : 'Rejected Note');

                // aman: tampilkan sebagai text (bukan HTML)
                text.innerText = note;

                // set tipe (buat warna header)
                card.setAttribute('data-note-type', type);
                pill.innerText = pillText;

                modal.classList.add('is-open');
                modal.setAttribute('aria-hidden', 'false');

                document.addEventListener('keydown', escCloseNoteModal);
            }

            function closeDirectorNote(){
                const modal = document.getElementById('directorNoteModal');
                modal.classList.remove('is-open');
                modal.setAttribute('aria-hidden', 'true');
                document.removeEventListener('keydown', escCloseNoteModal);
            }

            function escCloseNoteModal(e){
                if (e.key === 'Escape') closeDirectorNote();
            }
        </script>

        {{-- ======================= SCRIPT (FILTER/SORT) ======================= --}}
        <script>
            document.addEventListener("DOMContentLoaded", () => {
                const page = document.querySelector('[data-page="manage-seat"]');
                if (!page) return;

                const searchInput    = page.querySelector("#searchInputSeat");
                const customerFilter = page.querySelector("#customerFilterSeat");
                const sortSelect     = page.querySelector("#sortSelectSeat");
                const list           = page.querySelector("#reportListSeat");

                if (!searchInput || !customerFilter || !sortSelect || !list) {
                    console.warn("CCR Seat: elemen filter/list tidak lengkap.");
                    return;
                }

                let cards = Array.from(list.querySelectorAll(".report-card"));

                const toTime = (val) => {
                    if (!val) return 0;
                    const safe = String(val).trim().replace(" ", "T");
                    const t = Date.parse(safe);
                    return isNaN(t) ? 0 : t;
                };

                function applyFilters() {
                    const q = (searchInput.value || "").toLowerCase().trim();
                    const c = (customerFilter.value || "").trim();

                    cards.forEach(card => {
                        const search = (card.dataset.search || "");
                        const cust   = (card.dataset.customer || "").trim();

                        let show = true;
                        if (q && !search.includes(q)) show = false;
                        if (c && cust !== c) show = false;

                        card.style.display = show ? "flex" : "none";
                    });

                    if (window.__seatSyncSelectAll) window.__seatSyncSelectAll();
                }

                function applySort() {
                    cards = Array.from(list.querySelectorAll(".report-card"));

                    const mode = sortSelect.value;
                    const sorted = [...cards].sort((a, b) => {
                        if (mode === "newest")  return toTime(b.dataset.date) - toTime(a.dataset.date);
                        if (mode === "oldest")  return toTime(a.dataset.date) - toTime(b.dataset.date);
                        if (mode === "updated") return toTime(b.dataset.updated) - toTime(a.dataset.updated);
                        return 0;
                    });

                    sorted.forEach(card => list.appendChild(card));
                    cards = sorted;

                    applyFilters();
                }

                searchInput.addEventListener("input", applyFilters);
                customerFilter.addEventListener("change", applyFilters);
                sortSelect.addEventListener("change", applySort);

                applySort();
            });

            function seatBulk(){
                return {
                    selectedReports: [],
                    _bulkSync: false,

                    toggleOne(id, evt){
                        const cb = evt.target;
                        const card = cb.closest('.report-card');

                        if (cb.checked) {
                            if (!this.selectedReports.includes(id)) this.selectedReports.push(id);
                            card.classList.add('selected');
                        } else {
                            this.selectedReports = this.selectedReports.filter(x => x !== id);
                            card.classList.remove('selected');
                        }

                        if (!this._bulkSync) this.syncSelectAll();
                    },

                    toggleSelectAll(evt){
                        const checked = evt.target.checked;

                        const visibleCards = Array.from(this.$refs.list.querySelectorAll('.report-card'))
                            .filter(card => card.style.display !== 'none');

                        this._bulkSync = true;

                        visibleCards.forEach(card => {
                            const id = Number(card.dataset.id || 0);
                            const cb = card.querySelector('input.row-checkbox');
                            if (!cb || !id) return;

                            cb.checked = checked;

                            if (checked) {
                                if (!this.selectedReports.includes(id)) this.selectedReports.push(id);
                                card.classList.add('selected');
                            } else {
                                this.selectedReports = this.selectedReports.filter(x => x !== id);
                                card.classList.remove('selected');
                            }
                        });

                        this._bulkSync = false;
                        this.syncSelectAll();
                    },

                    syncSelectAll(){
                        const visibleCards = Array.from(this.$refs.list.querySelectorAll('.report-card'))
                            .filter(card => card.style.display !== 'none');

                        const cbs = visibleCards
                            .map(card => card.querySelector('input.row-checkbox'))
                            .filter(Boolean);

                        const anyChecked = cbs.some(cb => cb.checked);
                        const allChecked = (cbs.length > 0) && cbs.every(cb => cb.checked);

                        this.$refs.selectAll.indeterminate = anyChecked && !allChecked;
                        this.$refs.selectAll.checked = allChecked;
                    }
                }
            }
        </script>

    </div>

    {{-- ======================= STYLE (SCOPED) ======================= --}}
    <style>
    /* ===================== BACK BUTTON ===================== */
    [data-page="manage-seat"] .btn-back{
        display:inline-block;
        color:white;
        padding:8px 18px;
        border-radius:8px;
        background:#5f656a;
        font-weight:600;
        font-size:14px;
        text-decoration:none;
        transition:.2s;
        box-shadow:0 3px 7px rgba(0,0,0,.15);
        margin-bottom:18px;
    }
    [data-page="manage-seat"] .btn-back:hover{ background:#2b2d2f; transform:translateY(-2px) }

    /* ===================== HEADER ===================== */
    [data-page="manage-seat"] .header-card{
        background:white;
        padding:22px;
        border-radius:14px;
        margin-bottom:20px;
        box-shadow:0 3px 10px rgba(0,0,0,.07);
    }
    [data-page="manage-seat"] .header-left{ display:flex; align-items:center; gap:18px }
    [data-page="manage-seat"] .header-logo{ width:80px; height:80px; object-fit:contain }
    [data-page="manage-seat"] .header-title{ font-size:20px; font-weight:800; margin:0 }
    [data-page="manage-seat"] .header-subtitle{ font-size:14px; color:#555; margin-top:4px }
    [data-page="manage-seat"] .accent-line{
        height:4px;
        background:#0D6EFD;
        border-radius:20px;
        margin-bottom:18px
    }

    /* ===================== BOX ===================== */
    [data-page="manage-seat"] .box{
        background:white;
        padding:22px;
        border-radius:14px;
        margin-bottom:22px;
        box-shadow:0 3px 10px rgba(0,0,0,.07);
    }

    /* ===================== FILTER ===================== */
    [data-page="manage-seat"] .filter-row{
        display:flex;
        align-items:flex-end;
        gap:20px;
        flex-wrap:nowrap;
        width:100%;
    }
    [data-page="manage-seat"] .filter-large{ flex:1; margin-right:30px; }
    [data-page="manage-seat"] .filter-small{ flex:0 0 240px; }
    [data-page="manage-seat"] .input{
        width:100%;
        padding:12px 14px;
        border-radius:10px;
        border:1px solid #ccc;
        background:#fafafa;
        font-size:14px;
    }
    @media (max-width:1024px){
        [data-page="manage-seat"] .filter-row{flex-wrap:wrap;gap:18px}
        [data-page="manage-seat"] .filter-large{flex:1 0 100%;margin-right:0;padding-right:30px;box-sizing:border-box}
        [data-page="manage-seat"] .filter-small{flex:1}
    }
    @media (max-width:600px){
        [data-page="manage-seat"] .filter-row{flex-direction:column;align-items:flex-start;gap:16px}
        [data-page="manage-seat"] .filter-large{width:100%;padding-right:30px;box-sizing:border-box}
        [data-page="manage-seat"] .filter-small{width:100%}
    }

    /* ===================== SELECT ALL ROW ===================== */
    [data-page="manage-seat"] .select-all-row{
        display:flex;
        align-items:center;
        gap:12px;
        padding:12px 4px;
    }
    [data-page="manage-seat"] .select-all-label{
        cursor:pointer;
        user-select:none;
    }
    [data-page="manage-seat"] .select-divider{
        height:1px;
        background:#eee;
        margin:6px 0 6px;
    }

    /* ===================== REPORT CARD ===================== */
    [data-page="manage-seat"] .report-card{
        display:flex;
        justify-content:space-between;
        align-items:center;
        padding:18px 14px;
        border-bottom:1px solid #eee;
        gap:18px;
    }
    [data-page="manage-seat"] .report-left{
        display:flex;
        align-items:center;
        gap:16px;
        flex:1;
    }
    [data-page="manage-seat"] .select-checkbox{
        width:20px;
        height:20px;
        accent-color:#C62828;
        cursor:pointer;
    }
    [data-page="manage-seat"] .report-main{ display:flex; flex-direction:column; gap:6px }
    [data-page="manage-seat"] .report-title{ font-size:16px; font-weight:700 }
    [data-page="manage-seat"] .report-meta{
        font-size:13px;
        color:#555;
        display:flex;
        flex-wrap:wrap;
        gap:8px;
    }
    [data-page="manage-seat"] .report-meta span{
        background:#f5f5f5;
        padding:4px 8px;
        border-radius:999px;
    }
    [data-page="manage-seat"] .time-pill{ background:#eee; padding:6px 14px; border-radius:999px }
    [data-page="manage-seat"] .time-text,
    [data-page="manage-seat"] .time-wita{ font-weight:700; color:#E40505 }
    [data-page="manage-seat"] .time-wita{ margin-left:-6px }
    [data-page="manage-seat"] .note-pill{
        background:#fff0f0 !important;
        color:#b91c1c !important;
        border:1px solid rgba(185,28,28,.25);
    }

    /* ===================== ACTION BUTTONS ===================== */
    [data-page="manage-seat"] .report-actions{
        display:flex;
        gap:16px;
        align-items:center;
        border-left:2px solid #eee;
        padding-left:18px;
    }
    [data-page="manage-seat"] .report-actions form{ margin:0; }

    [data-page="manage-seat"] .btn-premium{
        display:inline-flex;
        align-items:center;
        gap:8px;
        padding:10px 18px;
        border-radius:12px;
        font-size:14px;
        font-weight:600;
        color:white;
        text-decoration:none;
        transition:.25s;
        box-shadow:0 3px 6px rgba(0,0,0,.1);
    }
    [data-page="manage-seat"] .btn-premium:hover{ transform:translateY(-2px); box-shadow:0 4px 10px rgba(0,0,0,.15) }
    [data-page="manage-seat"] .edit-btn{ background:#6b7075 }
    [data-page="manage-seat"] .word-btn{ background:#185ABD }
    [data-page="manage-seat"] .lihat-btn{ background:#F57C00 }
    [data-page="manage-seat"] .lihat-btn:hover{ background:#d96b00 }
    [data-page="manage-seat"] .icon-btn{ width:18px; height:18px }

    /* ========================= STATUS BADGE ========================= */
    [data-page="manage-seat"] .report-title{
      display:flex;
      align-items:center;
      gap:12px;
    }

    [data-page="manage-seat"] .status-pill{
      display:inline-flex;
      align-items:center;
      gap:8px;
      padding:6px 12px;
      border-radius:999px;
      background:#f3f4f6;
      font-weight:700;
      font-size:12px;
    }

    [data-page="manage-seat"] .status-dot{
      width:10px;
      height:10px;
      border-radius:999px;
      display:inline-block;
    }

    [data-page="manage-seat"] .dot-draft{ background:#9ca3af; }
    [data-page="manage-seat"] .dot-waiting{ background:#f59e0b; }
    [data-page="manage-seat"] .dot-approved{ background:#22c55e; }
    [data-page="manage-seat"] .dot-rejected{ background:#ef4444; }

    /* ========================= SUBMIT BUTTON ========================= */
    [data-page="manage-seat"] .btn-premium.btn-submit{
        background:#9F8170 !important;
    }
    [data-page="manage-seat"] .btn-premium.btn-submit:hover{
        filter:brightness(0.95);
    }

    /* ===================== SELECTED CARD ===================== */
    [data-page="manage-seat"] .report-card.selected{
        background:#fff5f5;
        border:2px solid rgba(228,5,5,.35);
        box-shadow:0 0 14px rgba(228,5,5,.25);
        border-radius:14px;
    }

    /* ===================== BULK BAR (FLOAT) - STYLE MIRIP VIEW ALL / CLEAR READ ===================== */

    /* kasih ruang bawah biar konten gak ketutup floating */
    [data-page="manage-seat"]{
    padding-bottom: 110px;
    }

    /* wrapper bar (desktop/tablet: center bottom) */
    [data-page="manage-seat"] .bulk-bar{
    position: fixed;
    left: 50%;
    bottom: 22px;
    transform: translateX(-50%);

    z-index: 9999;
    margin: 0;
    padding: 10px;

    background: rgba(255,255,255,.92);
    backdrop-filter: blur(6px);
    -webkit-backdrop-filter: blur(6px);

    border: 1px solid #e5e7eb;
    border-radius: 18px;
    box-shadow: 0 18px 40px rgba(0,0,0,.14);

    /* biar gak melebihi layar */
    width: fit-content;
    max-width: calc(100vw - 24px);
    }

    /* tombol bulk (mirip view all / clear read) */
    [data-page="manage-seat"] .bulk-btn{
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 10px;

    padding: 10px 16px;
    border-radius: 16px;
    border: 1px solid #e5e7eb;

    background: #f1f5f9;   /* default abu */
    color: #0f172a;        /* default hitam */

    font-weight: 1000;
    font-size: 14px;
    line-height: 1;
    white-space: nowrap;

    cursor: pointer;
    transition: .18s;
    }

    [data-page="manage-seat"] .bulk-btn:hover{
    transform: translateY(-1px);
    }

    /* hover soft red (halus) */
    [data-page="manage-seat"] .bulk-btn-danger:hover{
    background: rgba(220,53,69,.10);
    border-color: rgba(220,53,69,.25);
    color: #dc3545;
    }

    [data-page="manage-seat"] .bulk-btn:active{
    transform: translateY(0);
    }

    /* mobile: kecil, center bottom (tidak full width) */
    @media (max-width: 640px){
    /* cukup ruang bawah biar konten gak ketutup */
    [data-page="manage-seat"]{
        padding-bottom: 120px;
    }

    [data-page="manage-seat"] .bulk-bar{
        position: fixed;
        left: 50%;
        right: auto;
        bottom: calc(10px + env(safe-area-inset-bottom));
        transform: translateX(-50%);

        z-index: 9999;
        margin: 0;

        padding: 6px;                 /* ✅ lebih kecil */
        background: rgba(255,255,255,.92);
        backdrop-filter: blur(6px);
        -webkit-backdrop-filter: blur(6px);

        border: 1px solid #e5e7eb;
        border-radius: 14px;          /* ✅ lebih kecil */
        box-shadow: 0 12px 26px rgba(0,0,0,.14);

        width: fit-content;           /* ✅ jangan full width */
        max-width: calc(100vw - 24px);
    }

    [data-page="manage-seat"] .bulk-btn{
        width: auto;                  /* ✅ jangan 100% */
        padding: 8px 12px;            /* ✅ lebih kecil */
        border-radius: 12px;          /* ✅ lebih kecil */
        font-size: 13px;              /* ✅ lebih kecil */
        gap: 8px;
        justify-content: center;
    }
    }


    /* ===================== TABLET (rapi 2 kolom) ===================== */
    @media (max-width:1024px){
    [data-page="manage-seat"] .report-actions{
        border-left:none;
        padding-left:0;
        display:grid;
        grid-template-columns:repeat(2, minmax(0, 1fr));
        gap:16px;
        width:100%;
    }

    [data-page="manage-seat"] .report-actions > a,
    [data-page="manage-seat"] .report-actions > form{
        min-width:0;
        width:100%;
    }

    [data-page="manage-seat"] .report-actions > form{
        display:block !important;
        margin:0 !important;
    }

    [data-page="manage-seat"] .report-actions a.btn-premium,
    [data-page="manage-seat"] .report-actions form .btn-premium{
        width:100% !important;
        justify-content:center;
        box-sizing:border-box;
    }
    }

    /* ===================== MOBILE CARD ===================== */
    @media (max-width:600px){
    [data-page="manage-seat"] .report-card{
        flex-direction:column;
        align-items:flex-start;
        gap:14px;
    }

    [data-page="manage-seat"] .report-actions{
        border-left:none;
        padding-left:0;
        width:100%;
        flex-wrap:wrap;
        gap:10px;
        display:flex;
    }

    [data-page="manage-seat"] .report-actions a,
    [data-page="manage-seat"] .report-actions form,
    [data-page="manage-seat"] .report-actions button{
        width:100%;
        justify-content:center;
    }

    [data-page="manage-seat"] .report-actions form{
        display:block !important;
    }
    }


    /* ===================== NOTE BUTTON (LIST) ===================== */
    [data-page="manage-seat"] .status-note-btn{
        display:inline-flex;
        align-items:center;
        gap:10px;
        padding:8px 14px;
        border-radius:999px;
        border:2px solid #e5e7eb;
        background:#fff;
        font-weight:800;
        cursor:pointer;
        transition:.18s;
        box-shadow:0 2px 8px rgba(0,0,0,.06);
    }
    [data-page="manage-seat"] .status-note-btn:hover{ transform:translateY(-1px); }
    [data-page="manage-seat"] .status-note-btn .note-ico{ font-size:16px; line-height:1; }
    [data-page="manage-seat"] .status-note-btn .note-txt{ font-size:14px; }

    [data-page="manage-seat"] .status-note-btn-rejected{
        border-color: rgba(239,68,68,.35);
        background: rgba(239,68,68,.12);
        color:#ef4444;
    }

    [data-page="manage-seat"] .status-note-btn-approved{
        border-color: rgba(34,197,94,.35);
        background: rgba(34,197,94,.12);
        color:#22c55e;
    }

    /* ===================== NOTE MODAL ===================== */
    [data-page="manage-seat"] .note-modal{
        position:fixed;
        inset:0;
        display:none;
        z-index:9999;
    }
    [data-page="manage-seat"] .note-modal.is-open{ display:block; }

    [data-page="manage-seat"] .note-modal-backdrop{
        position:absolute;
        inset:0;
        background: rgba(255,255,255,.08);
        backdrop-filter: blur(2px);
        -webkit-backdrop-filter: blur(2px);
    }

    [data-page="manage-seat"] .note-modal-card{
        --accent:#ef4444;
        --accent-bg: rgba(239,68,68,.10);

        position:relative;
        width:min(820px, calc(100% - 40px));
        margin:90px auto;
        background:#fff;
        border-radius:18px;
        box-shadow:0 18px 50px rgba(0,0,0,.18);
        overflow:hidden;
        border:1px solid rgba(0,0,0,.06);
    }

    [data-page="manage-seat"] .note-modal-card[data-note-type="approved"]{
        --accent:#22c55e;
        --accent-bg: rgba(34,197,94,.10);
    }

    [data-page="manage-seat"] .note-modal-header{
        display:flex;
        align-items:center;
        justify-content:space-between;
        padding:14px 16px;
        background: var(--accent-bg);
        border-bottom:1px solid rgba(0,0,0,.06);
    }

    [data-page="manage-seat"] .pill-note{
        display:inline-flex;
        align-items:center;
        padding:6px 12px;
        border-radius:999px;
        font-weight:900;
        font-size:13px;
        color: var(--accent);
        border:2px solid color-mix(in srgb, var(--accent) 35%, transparent);
        background: rgba(255,255,255,.80);
    }

    [data-page="manage-seat"] .note-modal-close{
        width:40px;
        height:40px;
        border-radius:12px;
        border:1px solid rgba(0,0,0,.10);
        background:#fff;
        cursor:pointer;
        font-size:18px;
    }

    [data-page="manage-seat"] .note-modal-body{
        padding:18px 18px 24px;
        min-height:120px;
    }
    [data-page="manage-seat"] .note-text{
        font-size:16px;
        font-weight:700;
        color:#111827;
        line-height:1.5;
        word-break:break-word;
    }

    [data-page="manage-seat"] .note-modal-footer{
        padding:14px 16px;
        display:flex;
        justify-content:flex-end;
        border-top:1px solid rgba(0,0,0,.06);
    }

    [data-page="manage-seat"] .note-ok-btn{
        display:inline-block;
        color:#fff;
        padding:8px 18px;
        border-radius:8px;
        background:#5f656a;
        font-weight:600;
        font-size:14px;
        border:none;
        cursor:pointer;
        transition:.2s;
        box-shadow:0 3px 7px rgba(0,0,0,.15);
    }

    [data-page="manage-seat"] .note-ok-btn:hover{
        background:#2b2d2f;
        transform:translateY(-2px);
    }

    /* ===================== FIX WARNA ACTION ===================== */
    [data-page="manage-seat"] .btn-premium.btn-inreview,
    [data-page="manage-seat"] .btn-inreview{
        background:#d1d5db !important;
        border:2px solid #d1d5db !important;
        color:#111827 !important;
        box-shadow:0 6px 14px rgba(0,0,0,.10) !important;
        font-weight:900 !important;
        pointer-events:none;
        opacity:1 !important;
    }

    [data-page="manage-seat"] .btn-premium.btn-resubmit,
    [data-page="manage-seat"] .btn-resubmit{
        background:#fff !important;
        border:2px solid #9F8170 !important;
        color:#111827 !important;
        box-shadow:0 6px 14px rgba(0,0,0,.10) !important;
        font-weight:900 !important;
    }
    [data-page="manage-seat"] .btn-premium.btn-resubmit:hover,
    [data-page="manage-seat"] .btn-resubmit:hover{
        transform:translateY(-1px);
        filter:brightness(.99);
    }

    [data-page="manage-seat"] .report-actions .btn-premium.btn-submit,
    [data-page="manage-seat"] .report-actions .btn-premium.btn-inreview,
    [data-page="manage-seat"] .report-actions .btn-premium.btn-resubmit{
        padding: 8px 14px !important;
        font-size: 13px !important;
        border-radius: 11px !important;
        min-width: 150px !important;
        justify-content: center;
        box-sizing: border-box;
    }

    /* ===================== RESPONSIVE + OVERFLOW FIX ===================== */
    [data-page="manage-seat"] .report-left,
    [data-page="manage-seat"] .report-main{
      min-width:0;
    }

    [data-page="manage-seat"] .report-title{
      display:flex;
      flex-wrap:wrap;
      align-items:center;
      gap:10px;
      font-size:16px;
      font-weight:700;
    }

    [data-page="manage-seat"] .report-title > strong{
      flex:1 1 100%;
      min-width:0;
      overflow-wrap:anywhere;
      word-break:break-word;
      line-height:1.2;
    }

    [data-page="manage-seat"] .status-pill,
    [data-page="manage-seat"] .status-note-btn{
      flex:0 0 auto;
      max-width:100%;
      white-space:nowrap;
    }

    [data-page="manage-seat"] .report-meta span,
    [data-page="manage-seat"] .report-meta span b{
      max-width:100%;
      overflow-wrap:anywhere;
      word-break:break-word;
    }

    @media (max-width:1024px){
      [data-page="manage-seat"] .report-card{
        flex-direction:column;
        align-items:flex-start;
        gap:14px;
      }

      [data-page="manage-seat"] .report-left{ width:100%; }

      [data-page="manage-seat"] .report-actions{
        width:100%;
        border-left:none;
        padding-left:0;
        display:grid;
        grid-template-columns:repeat(2, minmax(0, 1fr));
        gap:14px;
      }

      [data-page="manage-seat"] .report-actions > a,
      [data-page="manage-seat"] .report-actions > form,
      [data-page="manage-seat"] .report-actions > span{
        width:100%;
        min-width:0;
      }

      [data-page="manage-seat"] .report-actions .btn-premium{
        width:100% !important;
        justify-content:center;
        box-sizing:border-box;
      }

      [data-page="manage-seat"] .report-actions .btn-premium.btn-submit,
      [data-page="manage-seat"] .report-actions .btn-premium.btn-inreview,
      [data-page="manage-seat"] .report-actions .btn-premium.btn-resubmit{
        min-width:0 !important;
      }
    }

    @media (max-width:600px){
      [data-page="manage-seat"] .report-card{
        flex-direction:column;
        align-items:flex-start;
        gap:14px;
      }

      [data-page="manage-seat"] .report-left{ width:100%; }

      [data-page="manage-seat"] .report-actions{
        width:100%;
        border-left:none;
        padding-left:0;
        display:flex;
        flex-direction:column;
        gap:12px;
      }

      [data-page="manage-seat"] .report-actions > a,
      [data-page="manage-seat"] .report-actions > form,
      [data-page="manage-seat"] .report-actions > span{
        width:100%;
        min-width:0;
      }

      [data-page="manage-seat"] .report-actions .btn-premium{
        width:100% !important;
        justify-content:center;
      }

      [data-page="manage-seat"] .report-title{ gap:8px; }

      [data-page="manage-seat"] .status-pill{
        padding:6px 10px;
        font-size:12px;
      }

      [data-page="manage-seat"] .status-note-btn{
        padding:7px 12px;
      }
    }
    </style>

</div>
@endsection
