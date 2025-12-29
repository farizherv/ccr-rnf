@extends('layout')

@section('content')

{{-- ======================= BACK BUTTON ======================= --}}
<a href="{{ route('ccr.manage.menu') }}" class="btn-back">← Kembali ke menu Edit CCR</a>

{{-- ======================= HEADER ======================= --}}
<div class="header-card">
    <div class="header-left">
        <img src="{{ asset('rnf-logo.png') }}" class="header-logo">
        <div>
            <h1 class="header-title">MANAGE CCR – ENGINE</h1>
            <p class="header-subtitle">Pilih laporan CCR Engine untuk dilihat atau diedit.</p>
        </div>
    </div>
</div>

<div class="accent-line"></div>

{{-- ======================= WRAPPER (FILTER + BULK + LIST SATU SCOPE) ======================= --}}
<div data-page="manage-engine">

    {{-- ======================= FILTER BOX ======================= --}}
    <div class="box">
        <h3 style="margin-bottom:18px;">Daftar CCR Engine</h3>

        @php
            $customers = $reports->pluck('customer')->filter()->unique()->values();
        @endphp

        <div class="filter-row">

            {{-- SEARCH --}}
            <div class="filter-group filter-large">
                <label for="searchInputEngine">Cari</label>
                <input id="searchInputEngine" type="text" class="input search-input"
                       placeholder="Cari component, customer, make, model, SN...">
            </div>

            {{-- FILTER CUSTOMER --}}
            <div class="filter-group filter-small">
                <label for="customerFilterEngine">Filter Customer</label>
                <select id="customerFilterEngine" class="input">
                    <option value="">Semua customer</option>
                    @foreach($customers as $c)
                        <option value="{{ $c }}">{{ $c }}</option>
                    @endforeach
                </select>
            </div>

            {{-- SORT BY --}}
            <div class="filter-group filter-small">
                <label for="sortSelectEngine">Sort By</label>
                <select id="sortSelectEngine" class="input">
                    <option value="newest">Newest</option>
                    <option value="oldest">Oldest</option>
                    <option value="updated">Recently Updated</option>
                </select>
            </div>

        </div>
    </div>

    {{-- ======================= BULK + LIST (SATU SCOPE) ======================= --}}
    <div
        x-data="engineBulk()"
        x-init="window.__engineSyncSelectAll = () => syncSelectAll();"
    >

        {{-- BULK DELETE --}}
        <form x-show="selectedReports.length > 0"
            action="{{ route('ccr.engine.trashMultiple') }}"
            method="POST"
            class="bulk-bar">
            @csrf
            <template x-for="id in selectedReports" :key="id">
                <input type="hidden" name="ids[]" :value="id">
            </template>

            <button type="submit" class="btn-premium pdf-btn"
                    onclick="return confirm('Pindahkan ke Sampah? Data akan terhapus permanen otomatis setelah 7 hari.')">
                🗑️ Hapus Terpilih (<span x-text="selectedReports.length"></span>)
            </button>
        </form>

        {{-- LIST --}}
        <div class="box" id="reportListEngine" x-ref="list">

            {{-- ✅ SELECT ALL --}}
            <div class="select-all-row">
                <input
                    type="checkbox"
                    id="selectAllEngine"
                    x-ref="selectAll"
                    class="select-checkbox select-all-checkbox"
                    @change="toggleSelectAll($event)"
                >
                <label for="selectAllEngine" class="select-all-label">
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

                                <span class="time-pill">
                                    <span class="time-text">
                                        {{ $r->inspection_date ? \Carbon\Carbon::parse($r->inspection_date)->timezone('Asia/Makassar')->format('H:i') : '--:--' }}
                                    </span>
                                    <span class="time-wita">(WITA)</span>
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="report-actions">
                        <a href="{{ route('engine.preview', $r->id) }}" class="btn-premium lihat-btn">👁️ Lihat</a>
                        <a href="{{ route('engine.edit', $r->id) }}" class="btn-premium edit-btn">✏️ Edit</a>

                        <a href="{{ route('engine.export.word', $r->id) }}" class="btn-premium word-btn">
                            <img src="/icons/word.svg" class="icon-btn"> Word
                        </a>

                        {{-- ✅ ACTION: Submit / In Review / Re-submit --}}
                        @if(in_array($status, ['waiting','in_review']))
                            <span class="btn-premium btn-inreview">⏳ In Review</span>
                        @elseif(in_array($status, ['draft','rejected']))
                            <form action="{{ route('engine.submit', $r->id) }}" method="POST" class="submit-form">
                                @csrf
                                <button type="submit" class="btn-premium btn-submit"
                                        onclick="return confirm('Kirim CCR Engine ini untuk dicek?')">
                                    📤 Submit
                                </button>
                            </form>
                        @elseif($status === 'approved')
                            <form action="{{ route('engine.submit', $r->id) }}" method="POST" class="submit-form">
                                @csrf
                                <input type="hidden" name="resubmit" value="1">
                                <button type="submit" class="btn-premium btn-resubmit"
                                        onclick="return confirm('CCR ini sudah Approved. Yakin mau Re-submit CCR Engine ini lagi?')">
                                    📨 Re-submit
                                </button>
                            </form>
                        @endif
                    </div>

                </div>
            @empty
                <p>Data CCR Engine masih kosong.</p>
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
                const page = document.querySelector('[data-page="manage-engine"]');
                if (!page) return;

                const searchInput    = page.querySelector("#searchInputEngine");
                const customerFilter = page.querySelector("#customerFilterEngine");
                const sortSelect     = page.querySelector("#sortSelectEngine");
                const list           = page.querySelector("#reportListEngine");

                if (!searchInput || !customerFilter || !sortSelect || !list) {
                    console.warn("CCR Engine: elemen filter/list tidak lengkap.");
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

                    if (window.__engineSyncSelectAll) window.__engineSyncSelectAll();
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

            function engineBulk(){
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

{{-- ======================= STYLE ======================= --}}
<style>
/* ===================== BACK BUTTON ===================== */
.btn-back{
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
.btn-back:hover{ background:#2b2d2f; transform:translateY(-2px) }

/* ===================== HEADER ===================== */
.header-card{
    background:white;
    padding:22px;
    border-radius:14px;
    margin-bottom:20px;
    box-shadow:0 3px 10px rgba(0,0,0,.07);
}
.header-left{ display:flex; align-items:center; gap:18px }
.header-logo{ width:80px; height:80px; object-fit:contain }
.header-title{ font-size:20px; font-weight:800; margin:0 }
.header-subtitle{ font-size:14px; color:#555; margin-top:4px }
.accent-line{
    height:4px;
    background:#0D6EFD;
    border-radius:20px;
    margin-bottom:18px
}

/* ===================== BOX ===================== */
.box{
    background:white;
    padding:22px;
    border-radius:14px;
    margin-bottom:22px;
    box-shadow:0 3px 10px rgba(0,0,0,.07);
}

/* ===================== FILTER ===================== */
.filter-row{
    display:flex;
    align-items:flex-end;
    gap:20px;
    flex-wrap:nowrap;
    width:100%;
}
.filter-large{ flex:1; margin-right:30px; }
.filter-small{ flex:0 0 240px; }
.input{
    width:100%;
    padding:12px 14px;
    border-radius:10px;
    border:1px solid #ccc;
    background:#fafafa;
    font-size:14px;
}
@media (max-width:1024px){
    .filter-row{flex-wrap:wrap;gap:18px}
    .filter-large{flex:1 0 100%;margin-right:0;padding-right:30px;box-sizing:border-box}
    .filter-small{flex:1}
}
@media (max-width:600px){
    .filter-row{flex-direction:column;align-items:flex-start;gap:16px}
    .filter-large{width:100%;padding-right:30px;box-sizing:border-box}
    .filter-small{width:100%}
}

/* ===================== SELECT ALL ROW ===================== */
.select-all-row{
    display:flex;
    align-items:center;
    gap:12px;
    padding:12px 4px;
}
.select-all-label{
    cursor:pointer;
    user-select:none;
}
.select-divider{
    height:1px;
    background:#eee;
    margin:6px 0 6px;
}

/* ===================== REPORT CARD ===================== */
.report-card{
    display:flex;
    justify-content:space-between;
    align-items:center;
    padding:18px 14px;
    border-bottom:1px solid #eee;
    gap:18px;
}
.report-left{
    display:flex;
    align-items:center;
    gap:16px;
    flex:1;
}
.select-checkbox{
    width:20px;
    height:20px;
    accent-color:#C62828;
    cursor:pointer;
}
.report-main{ display:flex; flex-direction:column; gap:6px }
.report-title{ font-size:16px; font-weight:700 }
.report-meta{
    font-size:13px;
    color:#555;
    display:flex;
    flex-wrap:wrap;
    gap:8px;
}
.report-meta span{
    background:#f5f5f5;
    padding:4px 8px;
    border-radius:999px;
}
.time-pill{ background:#eee; padding:6px 14px; border-radius:999px }
.time-text,.time-wita{ font-weight:700; color:#E40505 }
.time-wita{ margin-left:-6px }
.note-pill{
    background:#fff0f0 !important;
    color:#b91c1c !important;
    border:1px solid rgba(185,28,28,.25);
}


/* ===================== ACTION BUTTONS ===================== */
.report-actions{
    display:flex;
    gap:16px;
    align-items:center;
    border-left:2px solid #eee;
    padding-left:18px;
}

/* penting: rapihin submit (form/button) biar ga ada spacing aneh */
.report-actions form{ margin:0; }

.btn-premium{
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
.btn-premium:hover{ transform:translateY(-2px); box-shadow:0 4px 10px rgba(0,0,0,.15) }
.edit-btn{ background:#6b7075 }
.word-btn{ background:#185ABD }
.lihat-btn{ background:#F57C00 }
.lihat-btn:hover{ background:#d96b00 }
.icon-btn{ width:18px; height:18px }

/* =========================
   STATUS BADGE (Draft/In Review/Approved/Rejected)
   ========================= */
.report-title{
  display:flex;
  align-items:center;
  gap:12px;
}

.status-pill{
  display:inline-flex;
  align-items:center;
  gap:8px;
  padding:6px 12px;
  border-radius:999px;
  background:#f3f4f6;
  font-weight:700;
  font-size:12px;
}

.status-dot{
  width:10px;
  height:10px;
  border-radius:999px;
  display:inline-block;
}

.dot-draft{ background:#9ca3af; }
.dot-waiting{ background:#f59e0b; }
.dot-approved{ background:#22c55e; }
.dot-rejected{ background:#ef4444; }

/* =========================
   SUBMIT BUTTON (warna saja)
   ========================= */
.btn-premium.btn-submit{
    background:#9F8170 !important; /* ✅ warna submit baru */
}
.btn-premium.btn-submit:hover{
    filter:brightness(0.95);
}

/* ===================== SELECTED CARD ===================== */
.report-card.selected{
    background:#fff5f5;
    border:2px solid rgba(228,5,5,.35);
    box-shadow:0 0 14px rgba(228,5,5,.25);
    border-radius:14px;
}

/* ===================== BULK DELETE BUTTON ===================== */
.btn-premium.pdf-btn,
button.btn-premium.pdf-btn{
    background:#C62828 !important;
    color:#fff !important;
    opacity:1 !important;
    cursor:pointer !important;
}
.btn-premium.pdf-btn:hover{ background:#a81f1f !important; }
.btn-premium.pdf-btn:disabled,
button.btn-premium.pdf-btn:disabled{
    background:#C62828 !important;
    color:#fff !important;
    opacity:1 !important;
    cursor:pointer !important;
}

/* ===================== TABLET (rapi 2 kolom & semua sama ukuran) ===================== */
@media (max-width:1024px){
  /* biar area action punya ruang & nggak nabrak */
  .report-actions{
    border-left:none;
    padding-left:0;
    display:grid;
    grid-template-columns:repeat(2, minmax(0, 1fr));
    gap:16px;                 /* ✅ tambah jarak biar nggak dempet */
    width:100%;
  }

  /* ✅ ini KUNCI anti “tumpuk/overlap” */
  .report-actions > a,
  .report-actions > form{
    min-width:0;              /* biar bisa shrink dalam grid */
    width:100%;
  }

  /* form submit harus jadi item grid normal */
  .report-actions > form{
    display:block !important;
    margin:0 !important;
  }

  /* semua tombol full di cell masing-masing */
  .report-actions a.btn-premium,
  .report-actions form .btn-premium{
    width:100% !important;
    justify-content:center;
    box-sizing:border-box;
  }
}

/* ===================== MOBILE CARD (Submit harus ikut full width) ===================== */
@media (max-width:600px){
    .report-card{ flex-direction:column; align-items:flex-start; gap:14px }
    .report-actions{
        border-left:none;
        padding-left:0;
        width:100%;
        flex-wrap:wrap;
        gap:10px;
        display:flex; /* tetap stack via width 100% */
    }

    /* ✅ sebelumnya cuma a, sekarang button+form juga */
    .report-actions a,
    .report-actions form,
    .report-actions button{
        width:100%;
        justify-content:center;
    }

    /* override inline style form */
    .report-actions form{ display:block !important; }
}

{{-- ======================= STYLE tambahan (tempel di style yang sudah ada) ======================= --}}
/* ===================== NOTE BUTTON (LIST) ===================== */
.status-note-btn{
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
.status-note-btn:hover{ transform:translateY(-1px); }
.status-note-btn .note-ico{ font-size:16px; line-height:1; }
.status-note-btn .note-txt{ font-size:14px; }

/* rejected (merah) */
.status-note-btn-rejected{
    border-color: rgba(239,68,68,.35);
    background: rgba(239,68,68,.12);
    color:#ef4444;
}

/* approved (hijau) */
.status-note-btn-approved{
    border-color: rgba(34,197,94,.35);
    background: rgba(34,197,94,.12);
    color:#22c55e;
}

/* ===================== NOTE MODAL ===================== */
.note-modal{
    position:fixed;
    inset:0;
    display:none;
    z-index:9999;
}
.note-modal.is-open{ display:block; }

/* ✅ backdrop blur lebih ringan + tidak gelap */
.note-modal-backdrop{
    position:absolute;
    inset:0;
    background: rgba(255,255,255,.08);
    backdrop-filter: blur(2px);
    -webkit-backdrop-filter: blur(2px);
}

/* modal card */
.note-modal-card{
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

/* approved mode */
.note-modal-card[data-note-type="approved"]{
    --accent:#22c55e;
    --accent-bg: rgba(34,197,94,.10);
}

/* header ada warna “dalam” */
.note-modal-header{
    display:flex;
    align-items:center;
    justify-content:space-between;
    padding:14px 16px;
    background: var(--accent-bg);
    border-bottom:1px solid rgba(0,0,0,.06);
}

/* pill di kiri (Rejected Note / Approved Note) => kecilin */
.pill-note{
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

.note-modal-close{
    width:40px;
    height:40px;
    border-radius:12px;
    border:1px solid rgba(0,0,0,.10);
    background:#fff;
    cursor:pointer;
    font-size:18px;
}

.note-modal-body{
    padding:18px 18px 24px;
    min-height:120px;
}
.note-text{
    font-size:16px;
    font-weight:700;
    color:#111827;
    line-height:1.5;
    word-break:break-word;
}

.note-modal-footer{
    padding:14px 16px;
    display:flex;
    justify-content:flex-end;
    border-top:1px solid rgba(0,0,0,.06);
}

/* OK button abu-abu */
.note-ok-btn{
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

.note-ok-btn:hover{
    background:#2b2d2f;
    transform:translateY(-2px);
}

/* =========================================================
   ✅ FIX WARNA TOMBOL ACTION (KETIMPA .btn-premium)
   ========================================================= */

/* IN REVIEW => abu seperti contoh (teks hitam) */
.btn-premium.btn-inreview,
.btn-inreview{
    background:#d1d5db !important;      /* abu */
    border:2px solid #d1d5db !important;
    color:#111827 !important;           /* hitam */
    box-shadow:0 6px 14px rgba(0,0,0,.10) !important;
    font-weight:900 !important;
    pointer-events:none;                /* biar tidak bisa diklik */
    opacity:1 !important;
}

/* RE-SUBMIT => putih, teks hitam, border #9F8170 */
.btn-premium.btn-resubmit,
.btn-resubmit{
    background:#fff !important;
    border:2px solid #9F8170 !important;
    color:#111827 !important;
    box-shadow:0 6px 14px rgba(0,0,0,.10) !important;
    font-weight:900 !important;
}
.btn-premium.btn-resubmit:hover,
.btn-resubmit:hover{
    transform:translateY(-1px);
    filter:brightness(.99);
}

/* ✅ KECILKAN ukuran tombol kanan (Submit / In Review / Re-submit) */
.report-actions .btn-premium.btn-submit,
.report-actions .btn-premium.btn-inreview,
.report-actions .btn-premium.btn-resubmit{
    padding: 8px 14px !important;   /* lebih kecil dari 10px 18px */
    font-size: 13px !important;
    border-radius: 11px !important;

    min-width: 150px !important;    /* turunin dari 170px (coba 140-155) */
    justify-content: center;
    box-sizing: border-box;
}

/* =========================================================
   ✅ RESPONSIVE + OVERFLOW FIX (SCOPED) — PALING BAWAH
   Hanya berlaku untuk halaman manage-engine dan manage-seat
   ========================================================= */

[data-page="manage-engine"] .report-left,
[data-page="manage-engine"] .report-main{
  min-width:0; /* kunci anti overflow pada flex item */
}

/* Judul + status + note boleh wrap */
[data-page="manage-engine"] .report-title{
  display:flex;
  flex-wrap:wrap;
  align-items:center;
  gap:10px;
  font-size:16px;
  font-weight:700;
}

/* Nama component panjang -> turun baris & bisa pecah kata */
[data-page="manage-engine"] .report-title > strong{
  flex:1 1 100%;          /* ambil 1 baris penuh dulu */
  min-width:0;
  overflow-wrap:anywhere; /* pecah string panjang tanpa spasi */
  word-break:break-word;
  line-height:1.2;
}

/* Status & Note tetap pill tapi tidak keluar card */
[data-page="manage-engine"] .status-pill,
[data-page="manage-engine"] .status-note-btn{
  flex:0 0 auto;
  max-width:100%;
  white-space:nowrap;
}

/* Meta pills aman kalau value panjang (model/sn) */
[data-page="manage-engine"] .report-meta span,
[data-page="manage-engine"] .report-meta span b{
  max-width:100%;
  overflow-wrap:anywhere;
  word-break:break-word;
}

/* ===================== TABLET (<=1024px) ===================== */
@media (max-width:1024px){
  [data-page="manage-engine"] .report-card{
    flex-direction:column;
    align-items:flex-start;
    gap:14px;
  }

  [data-page="manage-engine"] .report-left{ width:100%; }

  [data-page="manage-engine"] .report-actions{
    width:100%;
    border-left:none;
    padding-left:0;
    display:grid;
    grid-template-columns:repeat(2, minmax(0, 1fr));
    gap:14px;
  }

  [data-page="manage-engine"] .report-actions > a,
  [data-page="manage-engine"] .report-actions > form,
  [data-page="manage-engine"] .report-actions > span{
    width:100%;
    min-width:0;
  }

  [data-page="manage-engine"] .report-actions .btn-premium{
    width:100% !important;
    justify-content:center;
    box-sizing:border-box;
  }

  /* override min-width desktop submit/inreview/resubmit */
  [data-page="manage-engine"] .report-actions .btn-premium.btn-submit,
  [data-page="manage-engine"] .report-actions .btn-premium.btn-inreview,
  [data-page="manage-engine"] .report-actions .btn-premium.btn-resubmit{
    min-width:0 !important;
  }
}

/* ===================== MOBILE (<=600px) ===================== */
@media (max-width:600px){
  [data-page="manage-engine"] .report-card{
    flex-direction:column;
    align-items:flex-start;
    gap:14px;
  }

  [data-page="manage-engine"] .report-left{ width:100%; }

  [data-page="manage-engine"] .report-actions{
    width:100%;
    border-left:none;
    padding-left:0;
    display:flex;
    flex-direction:column;
    gap:12px;
  }

  [data-page="manage-engine"] .report-actions > a,
  [data-page="manage-engine"] .report-actions > form,
  [data-page="manage-engine"] .report-actions > span{
    width:100%;
    min-width:0;
  }

  [data-page="manage-engine"] .report-actions .btn-premium{
    width:100% !important;
    justify-content:center;
  }

  /* biar status/note rapih di layar kecil */
  [data-page="manage-engine"] .report-title{ gap:8px; }

  [data-page="manage-engine"] .status-pill{
    padding:6px 10px;
    font-size:12px;
  }

  [data-page="manage-engine"] .status-note-btn{
    padding:7px 12px;
  }
}

</style>

@endsection
