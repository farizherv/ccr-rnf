@extends('layout')
@section('content')

<div data-page="trash-seat">

    <div class="top-toolbar">
        <a href="{{ route('trash.menu') }}" class="btn-back">← Kembali ke MENU Trash & Restore</a>
    </div>

    <div class="header-card">
        <div class="header-left">
            <img src="{{ asset('rnf-logo.png') }}" class="header-logo" width="110" height="110" alt="RNF Logo">
            <div>
                <h1 class="header-title">TRASH & RESTORE - SEAT</h1>
                <p class="header-subtitle">Restore atau hapus permanen data CCR Seat.</p>
            </div>
        </div>
    </div>

    <div class="accent-line"></div>

    @php
        $customers = collect($customers ?? [])->filter()->values();
        $now = now();
        $purgeSoonCutoff = $now->copy()->addDay();
        $totalReports = $reports->count();
        $purgeSoonReports = $reports->filter(fn($row) => $row->purge_at && $row->purge_at->lte($purgeSoonCutoff))->count();
        $expiredReports = $reports->filter(fn($row) => $row->purge_at && $row->purge_at->lt($now))->count();
        $pageVisibleReports = $reports->count();
    @endphp

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-label">Total Trash</div>
            <div class="stat-value">{{ $totalReports }}</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Purge &lt; 24 Jam</div>
            <div class="stat-value">{{ $purgeSoonReports }}</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Lewat Batas</div>
            <div class="stat-value">{{ $expiredReports }}</div>
        </div>
    </div>

    <div class="box filter-box">
        <h3 class="section-title">Daftar CCR Seat (Trash)</h3>

        <div class="filter-row">
            <div class="filter-group filter-large">
                <label for="trashSearch">Cari</label>
                <input id="trashSearch" type="text" class="input search-input"
                       placeholder="Cari component, customer, unit, make, model, SN...">
            </div>

            <div class="filter-group filter-small">
                <label for="trashCustomer">Filter Customer</label>
                <select id="trashCustomer" class="input">
                    <option value="">Semua customer</option>
                    @foreach($customers as $c)
                        <option value="{{ $c }}">{{ $c }}</option>
                    @endforeach
                </select>
            </div>

            <div class="filter-group filter-small">
                <label for="trashSort">Sort By</label>
                <select id="trashSort" class="input">
                    <option value="newest">Newest</option>
                    <option value="oldest">Oldest</option>
                </select>
            </div>
        </div>
    </div>

    <div
        x-data="trashSeatBulk()"
        x-init="window.__trashSeatSyncSelectAll = () => syncSelectAll();"
    >
        <div x-show="selectedReports.length > 0" x-cloak class="bulk-bar">
            <form action="{{ route('trash.seat.restoreMultiple') }}" method="POST" class="bulk-form">
                @csrf
                <template x-for="id in selectedReports" :key="'re-'+id">
                    <input type="hidden" name="ids[]" :value="id">
                </template>

                <button type="submit" class="bulk-btn bulk-btn-restore">
                    ♻ Restore Terpilih (<span x-text="selectedReports.length"></span>)
                </button>
            </form>

            <form action="{{ route('trash.seat.forceMultiple') }}" method="POST" class="bulk-form"
                  onsubmit="return confirm('Hapus permanen semua yang terpilih? Foto & item akan ikut terhapus.')">
                @csrf
                @method('DELETE')

                <template x-for="id in selectedReports" :key="'del-'+id">
                    <input type="hidden" name="ids[]" :value="id">
                </template>

                <button type="submit" class="bulk-btn bulk-btn-danger">
                    🗑 Hapus Permanen (<span x-text="selectedReports.length"></span>)
                </button>
            </form>
        </div>

        <div class="box report-list-box">
            <div class="list-head">
                <div class="list-head-title">List Laporan</div>
                <div class="list-head-tools">
                    <a href="{{ route('ccr.manage.seat') }}" class="btn-list-create">Buka Manage Seat</a>
                    <div class="list-head-count">
                        <span id="resultCountTrashSeat" class="count-number">{{ $pageVisibleReports }}</span>
                        <span class="count-text">data tampil</span>
                    </div>
                </div>
            </div>

            <div class="select-all-row">
                <input
                    id="selectAllTrashSeat"
                    type="checkbox"
                    x-ref="selectAll"
                    class="select-checkbox select-all-checkbox"
                    @change="toggleSelectAll($event)"
                >
                <label for="selectAllTrashSeat" class="select-all-label"><b>Select All</b></label>
            </div>

            <div class="select-divider"></div>

            <div id="trashCards" x-ref="list">
                @forelse($reports as $r)
                    <div class="report-card"
                         data-id="{{ $r->id }}"
                         data-search="{{ strtolower(($r->component ?? '').' '.($r->customer ?? '').' '.($r->unit ?? '').' '.($r->make ?? '').' '.($r->model ?? '').' '.($r->sn ?? '')) }}"
                         data-customer="{{ $r->customer ?? '' }}"
                         data-deleted="{{ optional($r->deleted_at)->format('Y-m-d H:i:s') }}">

                        <div class="report-left">
                            <input type="checkbox"
                                   class="select-checkbox row-checkbox"
                                   @change="toggleOne({{ $r->id }}, $event)">

                            <div class="report-main">
                                <div class="report-title">
                                    <strong>{{ $r->component ?? '-' }}</strong>
                                </div>
                                <div class="report-meta">
                                    <span>Customer: <b>{{ $r->customer ?? '-' }}</b></span>
                                    <span>Unit: <b>{{ $r->unit ?? '-' }}</b></span>
                                    <span>Make: <b>{{ $r->make ?? '-' }}</b></span>
                                    <span>Model: <b>{{ $r->model ?? '-' }}</b></span>
                                    <span>Dihapus: <b>{{ optional($r->deleted_at)->format('Y-m-d H:i') }}</b></span>
                                    <span>Purge At: <b>{{ optional($r->purge_at)->format('Y-m-d H:i') ?? '-' }}</b></span>
                                </div>
                            </div>
                        </div>

                    </div>
                @empty
                @endforelse
            </div>

            <p id="trashEmpty" class="empty-state" style="display:none;">Trash Seat masih kosong.</p>
        </div>
    </div>

    <script>
        document.addEventListener("DOMContentLoaded", () => {
            const page = document.querySelector('[data-page="trash-seat"]');
            if (!page) return;

            const searchInput = page.querySelector("#trashSearch");
            const customerSel = page.querySelector("#trashCustomer");
            const sortSel = page.querySelector("#trashSort");
            const list = page.querySelector("#trashCards");
            const emptyMsg = page.querySelector("#trashEmpty");
            const countEl = page.querySelector("#resultCountTrashSeat");

            if (!searchInput || !customerSel || !sortSel || !list || !emptyMsg || !countEl) return;

            const cards = () => Array.from(list.querySelectorAll(".report-card"));

            const toTime = (val) => {
                if (!val) return 0;
                const safe = String(val).trim().replace(" ", "T");
                const t = Date.parse(safe);
                return Number.isNaN(t) ? 0 : t;
            };

            function updateVisibleCount() {
                const visibleCount = cards().filter((card) => card.style.display !== "none").length;
                emptyMsg.style.display = visibleCount === 0 ? "block" : "none";
                countEl.textContent = String(visibleCount);
            }

            function applyFilters() {
                const q = (searchInput.value || "").toLowerCase().trim();
                const c = (customerSel.value || "").trim();

                cards().forEach((card) => {
                    const search = (card.dataset.search || "");
                    const cust = (card.dataset.customer || "").trim();

                    let show = true;
                    if (q && !search.includes(q)) show = false;
                    if (c && cust !== c) show = false;

                    card.style.display = show ? "flex" : "none";
                });

                updateVisibleCount();

                if (window.__trashSeatSyncSelectAll) {
                    window.__trashSeatSyncSelectAll();
                }
            }

            function applySort() {
                const mode = sortSel.value;

                const sorted = [...cards()].sort((a, b) => {
                    const da = toTime(a.dataset.deleted);
                    const db = toTime(b.dataset.deleted);
                    if (mode === "newest") return db - da;
                    if (mode === "oldest") return da - db;
                    return 0;
                });

                sorted.forEach((card) => list.appendChild(card));
                applyFilters();
            }

            searchInput.addEventListener("input", applyFilters);
            customerSel.addEventListener("change", applyFilters);
            sortSel.addEventListener("change", applySort);

            applySort();
        });

        function trashSeatBulk() {
            return {
                selectedReports: [],
                _bulkSync: false,

                toggleOne(id, evt) {
                    const cb = evt.target;
                    const card = cb.closest('.report-card');

                    if (cb.checked) {
                        if (!this.selectedReports.includes(id)) this.selectedReports.push(id);
                        card.classList.add('selected');
                    } else {
                        this.selectedReports = this.selectedReports.filter((x) => x !== id);
                        card.classList.remove('selected');
                    }

                    if (!this._bulkSync) this.syncSelectAll();
                },

                toggleSelectAll(evt) {
                    const checked = evt.target.checked;

                    const visibleCards = Array.from(this.$refs.list.querySelectorAll('.report-card'))
                        .filter((card) => card.style.display !== 'none');

                    this._bulkSync = true;

                    visibleCards.forEach((card) => {
                        const id = Number(card.dataset.id || 0);
                        const cb = card.querySelector('input.row-checkbox');
                        if (!cb || !id) return;

                        cb.checked = checked;

                        if (checked) {
                            if (!this.selectedReports.includes(id)) this.selectedReports.push(id);
                            card.classList.add('selected');
                        } else {
                            this.selectedReports = this.selectedReports.filter((x) => x !== id);
                            card.classList.remove('selected');
                        }
                    });

                    this._bulkSync = false;
                    this.syncSelectAll();
                },

                syncSelectAll() {
                    const visibleCards = Array.from(this.$refs.list.querySelectorAll('.report-card'))
                        .filter((card) => card.style.display !== 'none');

                    const cbs = visibleCards
                        .map((card) => card.querySelector('input.row-checkbox'))
                        .filter(Boolean);

                    const anyChecked = cbs.some((cb) => cb.checked);
                    const allChecked = cbs.length > 0 && cbs.every((cb) => cb.checked);

                    this.$refs.selectAll.indeterminate = anyChecked && !allChecked;
                    this.$refs.selectAll.checked = allChecked;
                }
            }
        }
    </script>

</div>

<style>
    [x-cloak]{
        display:none !important;
    }

    [data-page="trash-seat"]{
        --trash-accent: #E40505;
        --trash-surface: #ffffff;
        --trash-border: #d7e1ef;
        --trash-text: #0f172a;
        --trash-muted: #5f6d84;
        --trash-shadow: 0 3px 10px rgba(0,0,0,.07);
        padding-bottom:110px;
        font-family:Arial, sans-serif;
        background:#f5f7fb;
        border-radius:14px;
        padding:12px;
    }

    [data-page="trash-seat"] .top-toolbar{
        display:flex;
        align-items:center;
        justify-content:flex-start;
        gap:12px;
        margin-bottom:16px;
        flex-wrap:wrap;
    }
    [data-page="trash-seat"] .btn-back{
        display:inline-flex;
        align-items:center;
        color:#fff;
        padding:9px 18px;
        border-radius:10px;
        background:#5f656a;
        font-weight:700;
        font-size:14px;
        text-decoration:none;
        transition:.2s ease;
        box-shadow:0 6px 16px rgba(0,0,0,.16);
    }
    [data-page="trash-seat"] .btn-back:hover{
        background:#2f3439;
    }

    [data-page="trash-seat"] .header-card{
        background:#ffffff;
        padding:22px;
        border-radius:14px;
        margin-bottom:20px;
        box-shadow:var(--trash-shadow);
        border:1px solid var(--trash-border);
    }
    [data-page="trash-seat"] .header-left{
        display:flex;
        align-items:center;
        gap:18px;
    }
    [data-page="trash-seat"] .header-logo{
        width:80px;
        height:80px;
        object-fit:contain;
    }
    [data-page="trash-seat"] .header-title{
        margin:0;
        font-size:20px;
        line-height:1.1;
        font-weight:800;
        color:var(--trash-text);
    }
    [data-page="trash-seat"] .header-subtitle{
        margin:4px 0 0;
        color:var(--trash-muted);
        font-size:14px;
        font-weight:600;
    }
    [data-page="trash-seat"] .accent-line{
        height:4px;
        background:#E40505;
        border-radius:999px;
        margin-bottom:18px;
    }

    [data-page="trash-seat"] .stats-grid{
        display:grid;
        grid-template-columns:repeat(3, minmax(130px, 1fr));
        gap:10px;
        margin-bottom:16px;
    }
    [data-page="trash-seat"] .stat-card{
        background:#fff;
        border:1px solid var(--trash-border);
        border-radius:10px;
        box-shadow:var(--trash-shadow);
        padding:10px 14px;
        min-height:78px;
        display:flex;
        flex-direction:column;
        justify-content:center;
    }
    [data-page="trash-seat"] .stat-label{
        color:var(--trash-muted);
        font-weight:700;
        font-size:11px;
        text-transform:uppercase;
        letter-spacing:.04em;
    }
    [data-page="trash-seat"] .stat-value{
        margin-top:2px;
        color:var(--trash-text);
        font-weight:800;
        font-size:20px;
        line-height:1;
    }

    [data-page="trash-seat"] .box{
        background:var(--trash-surface);
        padding:22px;
        border-radius:14px;
        margin-bottom:22px;
        box-shadow:var(--trash-shadow);
        border:1px solid var(--trash-border);
    }
    [data-page="trash-seat"] .filter-box{
        background:#ffffff;
    }
    [data-page="trash-seat"] .section-title{
        margin:0 0 16px;
        font-size:18px;
        font-weight:700;
        line-height:1.15;
        color:var(--trash-text);
    }

    [data-page="trash-seat"] .filter-row{
        display:grid;
        grid-template-columns:minmax(0, 1.7fr) repeat(2, minmax(0, 1fr));
        column-gap:24px;
        row-gap:16px;
        align-items:end;
        width:100%;
    }
    [data-page="trash-seat"] .filter-row > *{
        min-width:0;
    }
    [data-page="trash-seat"] .filter-group{
        min-width:0;
        display:flex;
        flex-direction:column;
        gap:8px;
    }
    [data-page="trash-seat"] .filter-group label{
        display:block;
        margin:0;
        color:#1e293b;
        font-weight:700;
        font-size:14px;
    }
    [data-page="trash-seat"] .input{
        width:100%;
        max-width:100%;
        box-sizing:border-box;
        min-height:48px;
        padding:12px 14px;
        border-radius:10px;
        border:1px solid #ccc;
        background:#fff;
        font-size:14px;
        font-family:inherit;
        color:#0f172a;
        transition:border-color .18s ease, box-shadow .18s ease;
    }
    [data-page="trash-seat"] .input:focus{
        outline:none;
        border-color:var(--trash-accent);
        box-shadow:0 0 0 4px rgba(228,5,5,.12);
    }

    [data-page="trash-seat"] .report-list-box{
        padding-top:18px;
    }
    [data-page="trash-seat"] .list-head{
        display:flex;
        align-items:center;
        justify-content:space-between;
        gap:12px;
        margin-bottom:12px;
        flex-wrap:wrap;
    }
    [data-page="trash-seat"] .list-head-tools{
        display:inline-flex;
        align-items:center;
        gap:10px;
        flex-wrap:wrap;
    }
    [data-page="trash-seat"] .btn-list-create{
        display:inline-flex;
        align-items:center;
        justify-content:center;
        width:auto;
        min-width:0;
        min-height:30px;
        padding:5px 10px;
        border-radius:8px;
        background:#fff;
        color:#111;
        text-decoration:none;
        font-size:11px;
        font-weight:700;
        line-height:1;
        border:1px solid #111;
        box-shadow:0 1px 0 rgba(17,17,17,.08);
        transition:transform .18s ease, box-shadow .18s ease, background-color .18s ease, border-color .18s ease, color .18s ease;
    }
    [data-page="trash-seat"] .btn-list-create:hover{
        background:#f8fafc;
        color:#111;
        transform:translateY(-1px) scale(1.01);
        box-shadow:0 4px 10px rgba(228,5,5,.14), 0 0 0 1px rgba(228,5,5,.18);
        text-decoration:none;
    }
    [data-page="trash-seat"] .list-head-title{
        font-size:14px;
        color:#71809b;
        font-weight:800;
        letter-spacing:.08em;
        text-transform:uppercase;
    }
    [data-page="trash-seat"] .list-head-count{
        display:inline-flex;
        align-items:center;
        gap:6px;
        padding:6px 11px;
        border-radius:999px;
        background:#edf2ff;
        border:1px solid #d3def2;
        color:#24344f;
        font-size:12px;
        font-weight:800;
    }
    [data-page="trash-seat"] .list-head-count .count-number{
        font-size:15px;
        line-height:1;
        letter-spacing:-.02em;
    }
    [data-page="trash-seat"] .list-head-count .count-text{
        font-size:12px;
        font-weight:700;
    }

    [data-page="trash-seat"] .select-all-row{
        display:flex;
        align-items:center;
        gap:14px;
        padding:8px 4px 14px;
        flex-wrap:wrap;
    }
    [data-page="trash-seat"] .select-all-label{
        cursor:pointer;
        user-select:none;
        font-size:16px;
        line-height:1;
        color:#be123c;
    }
    [data-page="trash-seat"] .select-divider{
        height:1px;
        background:#e5ebf4;
        margin:2px 0 12px;
    }
    [data-page="trash-seat"] .select-checkbox{
        width:20px;
        height:20px;
        accent-color:#E11D48;
        cursor:pointer;
        flex:0 0 auto;
    }

    [data-page="trash-seat"] .report-card{
        display:flex;
        justify-content:space-between;
        align-items:center;
        gap:18px;
        padding:18px 14px;
        margin-bottom:12px;
        border-radius:14px;
        border:1px solid #e4ebf5;
        background:#fbfcff;
        transition:border-color .16s ease, box-shadow .16s ease, background-color .16s ease;
    }
    [data-page="trash-seat"] .report-card:hover{
        border-color:#cfdbec;
        box-shadow:0 6px 16px rgba(20, 40, 90, .07);
    }
    [data-page="trash-seat"] .report-card.selected{
        background:#fff5f5;
        border-color:rgba(228,5,5,.35);
        box-shadow:0 0 0 3px rgba(228,5,5,.12);
    }

    [data-page="trash-seat"] .report-left{
        display:flex;
        align-items:center;
        gap:16px;
        min-width:0;
        flex:1;
    }
    [data-page="trash-seat"] .report-main{
        display:flex;
        flex-direction:column;
        gap:6px;
        min-width:0;
        width:100%;
    }
    [data-page="trash-seat"] .report-title{
        display:flex;
        flex-wrap:wrap;
        align-items:center;
        gap:10px;
    }
    [data-page="trash-seat"] .report-title > strong{
        font-size:16px;
        line-height:1.2;
        color:var(--trash-text);
        min-width:0;
        overflow-wrap:anywhere;
        word-break:break-word;
    }

    [data-page="trash-seat"] .report-meta{
        display:flex;
        flex-wrap:wrap;
        gap:8px;
    }
    [data-page="trash-seat"] .report-meta span{
        background:#eef2f7;
        border:1px solid #dbe3ef;
        color:#334155;
        padding:4px 10px;
        border-radius:999px;
        font-size:13px;
        line-height:1.25;
        max-width:100%;
        overflow-wrap:anywhere;
        word-break:break-word;
    }
    [data-page="trash-seat"] .report-meta span b{
        color:#1f2937;
    }

    [data-page="trash-seat"] .report-actions{
        display:flex;
        flex-wrap:wrap;
        justify-content:flex-end;
        gap:7px;
        border-left:2px solid #eee;
        padding-left:12px;
        min-width:232px;
        max-width:232px;
        flex:0 0 auto;
        align-items:center;
    }
    [data-page="trash-seat"] .report-actions > form{
        width:110px;
        flex:0 0 110px;
        margin:0;
        display:block;
    }
    [data-page="trash-seat"] .report-actions form .btn-premium{
        border:none;
        cursor:pointer;
        width:100%;
    }

    [data-page="trash-seat"] .btn-premium{
        display:inline-flex;
        align-items:center;
        justify-content:center;
        gap:6px;
        min-width:0;
        width:100%;
        min-height:34px;
        padding:6px 10px;
        border-radius:9px;
        font-size:12px;
        font-weight:600;
        color:#fff;
        text-decoration:none;
        line-height:1;
        box-shadow:0 3px 6px rgba(0,0,0,.1);
        transition:background-color .16s ease, border-color .16s ease, color .16s ease, opacity .16s ease, box-shadow .16s ease;
        border:1px solid rgba(0,0,0,.05);
    }
    [data-page="trash-seat"] .btn-premium:hover{
        text-decoration:none;
        transform:translateY(-1px);
        box-shadow:0 4px 10px rgba(0,0,0,.15);
    }
    [data-page="trash-seat"] .restore-btn{
        background:#0D6EFD;
    }
    [data-page="trash-seat"] .restore-btn:hover{
        background:#0b5ed7;
    }
    [data-page="trash-seat"] .delete-btn{
        background:#dc3545;
    }
    [data-page="trash-seat"] .delete-btn:hover{
        background:#c82333;
    }

    [data-page="trash-seat"] .empty-state{
        margin:14px 4px 2px;
        padding:22px;
        border:1px dashed #c9d7eb;
        border-radius:12px;
        background:#f8fbff;
        color:#4c5d77;
        font-size:14px;
        font-weight:700;
    }

    [data-page="trash-seat"] .bulk-bar{
        position:fixed;
        left:50%;
        bottom:20px;
        transform:translateX(-50%);
        z-index:9999;
        margin:0;
        padding:8px;
        display:flex;
        align-items:center;
        gap:8px;
        background:rgba(255,255,255,.95);
        backdrop-filter:blur(6px);
        -webkit-backdrop-filter:blur(6px);
        border:1px solid #d8e3f2;
        border-radius:16px;
        box-shadow:0 14px 36px rgba(0,0,0,.18);
        width:fit-content;
        max-width:calc(100vw - 24px);
    }
    [data-page="trash-seat"] .bulk-form{
        margin:0;
    }
    [data-page="trash-seat"] .bulk-btn{
        display:inline-flex;
        align-items:center;
        justify-content:center;
        gap:8px;
        padding:10px 14px;
        border-radius:12px;
        border:1px solid #d5dfef;
        background:#f4f8ff;
        color:#0f172a;
        font-weight:900;
        font-size:14px;
        cursor:pointer;
        transition:.18s ease;
    }
    [data-page="trash-seat"] .bulk-btn-restore:hover{
        background:rgba(13,110,253,.12);
        border-color:rgba(13,110,253,.25);
        color:#0b5ed7;
    }
    [data-page="trash-seat"] .bulk-btn-danger:hover{
        background:rgba(220,53,69,.12);
        border-color:rgba(220,53,69,.25);
        color:#dc3545;
    }

    @media (max-width: 1100px){
        [data-page="trash-seat"] .stats-grid{
            grid-template-columns:repeat(3, minmax(0, 1fr));
        }
        [data-page="trash-seat"] .filter-row{
            grid-template-columns:repeat(2, minmax(0, 1fr));
            gap:16px;
        }
        [data-page="trash-seat"] .filter-group.filter-large{
            grid-column:1 / -1;
        }
        [data-page="trash-seat"] .report-card{
            flex-direction:column;
            align-items:stretch;
        }
        [data-page="trash-seat"] .report-actions{
            width:100%;
            max-width:none;
            min-width:0;
            border-left:none;
            border-top:1px dashed #d6e1ee;
            padding-left:0;
            padding-top:12px;
            display:grid;
            grid-template-columns:repeat(2, minmax(0, 110px));
            justify-content:end;
            gap:7px;
        }
    }

    @media (max-width: 1024px){
        [data-page="trash-seat"] .top-toolbar{
            flex-direction:column;
            align-items:stretch;
        }
        [data-page="trash-seat"] .header-card{
            padding:18px;
        }
        [data-page="trash-seat"] .header-left{
            align-items:flex-start;
        }
        [data-page="trash-seat"] .stats-grid{
            grid-template-columns:repeat(2, minmax(0, 1fr));
        }
        [data-page="trash-seat"] .box{
            padding:18px;
        }
        [data-page="trash-seat"] .filter-row{
            grid-template-columns:1fr;
            row-gap:14px;
        }
        [data-page="trash-seat"] .list-head{
            align-items:flex-start;
            gap:10px;
        }
        [data-page="trash-seat"] .list-head-tools{
            width:100%;
            justify-content:space-between;
        }
        [data-page="trash-seat"] .report-card{
            padding:16px;
        }
    }

    @media (max-width: 640px){
        [data-page="trash-seat"]{
            padding-bottom:120px;
        }
        [data-page="trash-seat"] .top-toolbar{
            gap:10px;
        }
        [data-page="trash-seat"] .btn-back{
            min-height:44px;
        }
        [data-page="trash-seat"] .header-left{
            flex-direction:column;
            align-items:flex-start;
            gap:10px;
        }
        [data-page="trash-seat"] .header-logo{
            width:70px;
            height:70px;
        }
        [data-page="trash-seat"] .stats-grid{
            grid-template-columns:1fr;
            gap:12px;
        }
        [data-page="trash-seat"] .stat-card{
            min-height:84px;
            padding:12px 16px;
        }
        [data-page="trash-seat"] .stat-value{
            font-size:24px;
        }
        [data-page="trash-seat"] .list-head{
            flex-direction:column;
            align-items:flex-start;
            gap:8px;
        }
        [data-page="trash-seat"] .list-head-tools{
            width:100%;
            justify-content:space-between;
            gap:8px;
        }
        [data-page="trash-seat"] .btn-list-create{
            min-height:28px;
            font-size:10px;
            padding:5px 8px;
            border-radius:8px;
        }
        [data-page="trash-seat"] .select-all-row{
            align-items:flex-start;
            flex-direction:column;
            gap:8px;
        }
        [data-page="trash-seat"] .report-title > strong{
            font-size:16px;
            flex:1 1 100%;
        }
        [data-page="trash-seat"] .report-meta span{
            font-size:12px;
            padding:4px 9px;
        }
        [data-page="trash-seat"] .report-actions{
            grid-template-columns:repeat(2, minmax(0, 104px));
            justify-content:end;
            gap:7px;
        }
        [data-page="trash-seat"] .btn-premium{
            min-height:34px;
            border-radius:9px;
            padding:6px 9px;
            font-size:12px;
        }
        [data-page="trash-seat"] .bulk-bar{
            bottom:calc(10px + env(safe-area-inset-bottom));
            border-radius:14px;
            padding:6px;
            flex-wrap:wrap;
            justify-content:center;
        }
        [data-page="trash-seat"] .bulk-btn{
            font-size:13px;
            padding:8px 12px;
            border-radius:10px;
        }
    }
</style>

@endsection
