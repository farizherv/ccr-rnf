@extends('layout')

@section('content')

{{-- ✅ WRAP SELURUH HALAMAN (BIAR CSS SCOPED & TIDAK NIMPA TOPBAR/NAV) --}}
<div data-page="manage-engine">

    {{-- ======================= TOP TOOLBAR ======================= --}}
    <div class="top-toolbar">
        <a href="{{ route('ccr.index') }}" class="btn-back">← Kembali ke beranda CCR</a>
    </div>

    {{-- ======================= HEADER ======================= --}}
    <div class="header-card">
        <div class="header-left">
            <img src="{{ asset('rnf-logo.png') }}" class="header-logo" width="110" height="110" alt="RNF Logo">
            <div>
                <h1 class="header-title">MANAGE CCR – ENGINE</h1>
                <p class="header-subtitle">Pilih laporan CCR Engine untuk dilihat atau diedit.</p>
            </div>
        </div>
    </div>

    <div class="accent-line"></div>

    @php
        $customers = collect($customers ?? [])->filter()->values();
        $statusStats = is_array($statusStats ?? null) ? $statusStats : [];
        $totalReports = (int) ($statusStats['total'] ?? $reports->count());
        $draftReports = (int) ($statusStats['draft'] ?? $reports->filter(fn($x) => (($x->approval_status ?? 'draft') === 'draft'))->count());
        $reviewReports = (int) ($statusStats['review'] ?? $reports->filter(fn($x) => in_array(($x->approval_status ?? 'draft'), ['waiting', 'in_review']))->count());
        $approvedReports = (int) ($statusStats['approved'] ?? $reports->filter(fn($x) => (($x->approval_status ?? 'draft') === 'approved'))->count());
        $rejectedReports = (int) ($statusStats['rejected'] ?? $reports->filter(fn($x) => (($x->approval_status ?? 'draft') === 'rejected'))->count());
        $pageVisibleReports = $reports->count();

        $draftUserId = auth()->check() ? (int) auth()->id() : 0;
        $draftUserPrefix = $draftUserId ? ('u' . $draftUserId . '_') : 'guest_';
        $draftUserToken = $draftUserId ? (string) $draftUserId : 'guest';
        $engineCreateUrl = route('engine.create');
        $engineCreateHash = md5($engineCreateUrl);
        $engineDraftLocalKeys = [
            'ccr_parts_ws_' . $draftUserPrefix . 'create_' . $engineCreateHash,
            'ccr_detail_ws_' . $draftUserPrefix . 'create_' . $engineCreateHash,
        ];
        $engineDraftLocalPrefixes = [
            'ccr_parts_ws_' . $draftUserPrefix . 'create_',
            'ccr_detail_ws_' . $draftUserPrefix . 'create_',
        ];
        $enginePhotoDraftKey = 'engine:create:ccr:u:' . $draftUserToken;
        $engineDraftClientKeyStorage = 'ccr:create:server-key:u:' . $draftUserToken . ':type:engine';
        $draftListUrl = route('ccr.drafts.index');
        $draftDeleteUrlTpl = route('ccr.drafts.destroy', ['id' => '__DRAFT_ID__']);
        $clearCreateDraft = session('clear_create_draft');
    @endphp

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-label">Total CCR</div>
            <div class="stat-value">{{ $totalReports }}</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Draft</div>
            <div class="stat-value">{{ $draftReports }}</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">In Review</div>
            <div class="stat-value">{{ $reviewReports }}</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Approved</div>
            <div class="stat-value">{{ $approvedReports }}</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Rejected</div>
            <div class="stat-value">{{ $rejectedReports }}</div>
        </div>
    </div>

    {{-- ======================= FILTER BOX ======================= --}}
    <div class="box filter-box">
        <h3 class="section-title">Daftar CCR Engine</h3>

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

            {{-- FILTER STATUS --}}
            <div class="filter-group filter-small">
                <label for="statusFilterEngine">Status</label>
                <select id="statusFilterEngine" class="input">
                    <option value="">Semua status</option>
                    <option value="draft">Draft ({{ $draftReports }})</option>
                    <option value="waiting">In Review ({{ $reviewReports }})</option>
                    <option value="approved">Approved ({{ $approvedReports }})</option>
                    <option value="rejected">Rejected ({{ $rejectedReports }})</option>
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
            <button type="submit"
                    class="bulk-btn bulk-btn-danger"
                    onclick="return confirm('Pindahkan ke Sampah? Data akan terhapus permanen otomatis setelah 7 hari.')">
                🗑️ Hapus Terpilih (<span x-text="selectedReports.length"></span>)
            </button>
        </form>

        {{-- LIST --}}
        <div class="box report-list-box" id="reportListEngine" x-ref="list">
            <div class="list-head">
                <div class="list-head-title">List Laporan</div>
                <div class="list-head-tools">
                    <a href="{{ $engineCreateUrl }}"
                       class="btn-list-create js-draft-entry"
                       data-draft-target="engine"
                       data-target-url="{{ $engineCreateUrl }}">
                        + Buat CCR Engine
                    </a>
                    <div class="list-head-count">
                        <span id="resultCountEngine" class="count-number">{{ $pageVisibleReports }}</span>
                        <span class="count-text">data tampil</span>
                    </div>
                </div>
            </div>

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
                        'draft'     => ['text' => 'DRAFT',    'pill' => 'status-pill-draft'],
                        'waiting'   => ['text' => 'WAITING',  'pill' => 'status-pill-waiting'],
                        'in_review' => ['text' => 'WAITING',  'pill' => 'status-pill-waiting'],
                        'approved'  => ['text' => 'APPROVED', 'pill' => 'status-pill-approved'],
                        'rejected'  => ['text' => 'REJECTED', 'pill' => 'status-pill-rejected'],
                    ][$status] ?? ['text' => 'DRAFT', 'pill' => 'status-pill-draft'];

                    $hasNote = !empty($r->director_note);
                    $notePillText = ($status === 'rejected') ? 'Rejected Note' : 'Approved Note';
                @endphp

                <div class="report-card"
                     data-id="{{ $r->id }}"
                     data-search="{{ strtolower(($r->component ?? '').' '.($r->customer ?? '').' '.($r->make ?? '').' '.($r->model ?? '').' '.($r->sn ?? '')) }}"
                     data-customer="{{ $r->customer }}"
                     data-status="{{ in_array($status, ['waiting','in_review']) ? 'waiting' : $status }}"
                     data-created="{{ $r->created_at }}"
                     data-date="{{ $r->inspection_date }}"
                     data-updated="{{ $r->updated_at }}">

                    <div class="report-left">
                        <input type="checkbox"
                               class="select-checkbox row-checkbox"
                               @change="toggleOne({{ $r->id }}, $event)">

                        <div class="report-main">
                            <div class="report-title">
                                <strong>{{ $r->component }}</strong>

                                {{-- ✅ STATUS BADGE (tidak tampil untuk waiting/in_review) --}}
                                @if(!in_array($status, ['waiting','in_review']))
                                <div class="status-pill {{ $badge['pill'] }}">
                                    <span class="status-text">{{ $badge['text'] }}</span>
                                </div>
                                @endif

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

                                <div class="time-wrap">
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
                        <a href="{{ route('engine.edit', $r->id) }}" class="btn-premium edit-btn">Edit</a>

                        <a href="{{ route('engine.export.word', $r->id) }}"
                           class="btn-premium word-btn js-word-download"
                           data-download-word-url="{{ route('engine.export.word', $r->id) }}"
                           data-download-parts-url="{{ route('engine.export.parts_labour', $r->id) }}"
                           data-component="{{ $r->component ?? 'CCR Engine' }}">
                            Download
                        </a>

                        {{-- ✅ ACTION: Submit / In Review / Re-submit --}}
                        @if(in_array($status, ['waiting','in_review']))
                            <span class="btn-premium btn-inreview" aria-disabled="true" tabindex="-1">Waiting</span>
                        @elseif(in_array($status, ['draft','rejected']))
                            <form action="{{ route('engine.submit', $r->id) }}" method="POST" class="submit-form">
                                @csrf
                                <button type="submit" class="btn-premium btn-submit"
                                        onclick="return confirm('Kirim CCR Engine ini untuk dicek?')">
                                    Submit
                                </button>
                            </form>
                        @elseif($status === 'approved')
                            <form action="{{ route('engine.submit', $r->id) }}" method="POST" class="submit-form">
                                @csrf
                                <input type="hidden" name="resubmit" value="1">
                                <button type="submit" class="btn-premium btn-resubmit"
                                        onclick="return confirm('CCR ini sudah Approved. Yakin mau Re-submit CCR Engine ini lagi?')">
                                    Re-submit
                                </button>
                            </form>
                        @endif
                    </div>

                </div>
            @empty
                <p class="empty-state">Belum ada data CCR Engine.</p>
            @endforelse

            <p class="empty-state empty-state-filtered" id="noResultEngine" style="display:none;">
                Tidak ada laporan yang cocok dengan filter saat ini.
            </p>

        </div>

        @if(method_exists($reports, 'links'))
            <div class="list-pagination">
                {{ $reports->links() }}
            </div>
        @endif

        <div id="engine-draft-choice-modal" class="draft-modal" aria-hidden="true">
            <div class="draft-modal__backdrop" data-draft-close></div>
            <div class="draft-modal__panel" role="dialog" aria-modal="true" aria-labelledby="engine-draft-modal-title">
                <h3 id="engine-draft-modal-title" class="draft-modal__title">Draft Ditemukan</h3>
                <p class="draft-modal__desc" id="engine-draft-modal-desc"></p>
                <div class="draft-modal__list" id="engine-draft-modal-list"></div>
                <div class="draft-modal__empty" id="engine-draft-modal-empty" style="display:none;">
                    Belum ada draft.
                </div>

                <div class="draft-modal__actions">
                    <button type="button" id="engine-draft-new-btn" class="draft-btn draft-btn--primary">Buat Baru</button>
                    <button type="button" id="engine-draft-cancel-btn" class="draft-btn draft-btn--ghost">Batal</button>
                </div>
            </div>
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

        {{-- ======================= MODAL DOWNLOAD LIST ======================= --}}
        <div id="engineDownloadModal" class="export-modal" aria-hidden="true">
            <div class="export-modal-backdrop" data-export-close></div>
            <div class="export-modal-card" role="dialog" aria-modal="true" aria-labelledby="engineDownloadModalTitle">
                <h4 id="engineDownloadModalTitle" class="export-modal-title">Download File</h4>
                <p id="engineDownloadModalText" class="export-modal-text">
                    Pilih file yang ingin diunduh.
                </p>
                <div class="export-modal-list">
                    <a href="#" class="export-list-item export-list-item-word" id="engineDownloadWordLink">
                        <img src="/icons/word.svg" class="export-list-icon-word" alt="Word">
                        <span>Download Word CCR</span>
                    </a>
                    <a href="#" class="export-list-item export-list-item-excel" id="engineDownloadPartsLink">
                        <img src="/icons/excel.svg" class="export-list-icon-excel" alt="Excel">
                        <span>Download Excel Parts & Labour + Detail</span>
                    </a>
                </div>
                <div class="export-modal-actions">
                    <button type="button" class="export-btn export-btn-ghost" id="engineDownloadCancel">Batal</button>
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

        <script>
            (() => {
                const page = document.querySelector('[data-page="manage-engine"]');
                if (!page) return;

                const modal = page.querySelector('#engineDownloadModal');
                const textEl = page.querySelector('#engineDownloadModalText');
                const wordEl = page.querySelector('#engineDownloadWordLink');
                const partsEl = page.querySelector('#engineDownloadPartsLink');
                const cancelEl = page.querySelector('#engineDownloadCancel');
                if (!modal || !textEl || !wordEl || !partsEl || !cancelEl) return;

                const downloadOptions = [wordEl, partsEl];
                let isDownloading = false;

                function setOptionHref(element, href) {
                    const safeHref = String(href || '').trim();
                    if (safeHref) {
                        element.setAttribute('href', safeHref);
                        element.classList.remove('is-disabled');
                        element.removeAttribute('aria-disabled');
                        return;
                    }

                    element.setAttribute('href', '#');
                    element.classList.add('is-disabled');
                    element.setAttribute('aria-disabled', 'true');
                }

                function openDownloadModal(link) {
                    const wordHref = String(link?.dataset?.downloadWordUrl || link?.getAttribute('href') || '').trim();
                    const partsHref = String(link?.dataset?.downloadPartsUrl || '').trim();
                    const component = String(link?.dataset?.component || 'CCR Engine').trim();
                    if (!wordHref && !partsHref) return;

                    textEl.textContent = 'Pilih file yang ingin diunduh untuk "' + component + '". Opsi Excel sudah berisi 2 sheet: Parts & Labour Worksheet dan Detail.';
                    setOptionHref(wordEl, wordHref);
                    setOptionHref(partsEl, partsHref);
                    setBusyState(false);

                    modal.classList.add('is-open');
                    modal.setAttribute('aria-hidden', 'false');
                }

                function setBusyState(state) {
                    isDownloading = !!state;
                    downloadOptions.forEach((option) => option.classList.toggle('is-busy', isDownloading));
                    cancelEl.disabled = isDownloading;
                }

                function closeDownloadModal(force = false) {
                    if (isDownloading && !force) {
                        return;
                    }
                    modal.classList.remove('is-open');
                    modal.setAttribute('aria-hidden', 'true');
                    downloadOptions.forEach((option) => setOptionHref(option, ''));
                    setBusyState(false);
                }

                page.querySelectorAll('.js-word-download').forEach((link) => {
                    link.addEventListener('click', (event) => {
                        event.preventDefault();
                        openDownloadModal(link);
                    });
                });

                modal.querySelectorAll('[data-export-close]').forEach((el) => {
                    el.addEventListener('click', closeDownloadModal);
                });
                cancelEl.addEventListener('click', closeDownloadModal);

                downloadOptions.forEach((option) => {
                    option.addEventListener('click', (event) => {
                        if (option.classList.contains('is-disabled')) {
                            event.preventDefault();
                            return;
                        }
                        if (isDownloading) {
                            event.preventDefault();
                            return;
                        }
                        setBusyState(true);
                        window.setTimeout(() => {
                            closeDownloadModal(true);
                        }, 1200);
                    });
                });

                document.addEventListener('keydown', (event) => {
                    if (event.key === 'Escape' && modal.classList.contains('is-open')) {
                        closeDownloadModal();
                    }
                });
            })();
        </script>

        <script>
            (() => {
                const DRAFT_MAX_AGE_MS = 1000 * 60 * 60 * 24 * 45;
                const page = document.querySelector('[data-page="manage-engine"]');
                if (!page) return;

                const config = {
                    engine: {
                        label: 'CCR ENGINE',
                        localKeys: @json($engineDraftLocalKeys),
                        localPrefixes: @json($engineDraftLocalPrefixes),
                        detectKeys: [],
                        detectPrefixes: [],
                        url: @json($engineCreateUrl),
                        clientKeyStorage: @json($engineDraftClientKeyStorage),
                        indexed: {
                            dbName: 'seatCreateDraftDb',
                            storeName: 'seatCreatePhotoDrafts',
                            key: @json($enginePhotoDraftKey),
                        },
                    },
                };

                const draftApi = {
                    listUrl: @json($draftListUrl),
                    deleteUrlTpl: @json($draftDeleteUrlTpl),
                    csrf: @json(csrf_token()),
                };
                const clearCreateDraft = @json($clearCreateDraft);

                const modal = page.querySelector('#engine-draft-choice-modal');
                if (!modal) return;

                const titleEl = page.querySelector('#engine-draft-modal-title');
                const descEl = page.querySelector('#engine-draft-modal-desc');
                const listEl = page.querySelector('#engine-draft-modal-list');
                const emptyEl = page.querySelector('#engine-draft-modal-empty');
                const btnNew = page.querySelector('#engine-draft-new-btn');
                const btnCancel = page.querySelector('#engine-draft-cancel-btn');

                const state = { kind: null, url: '', entries: [] };

                function formatDateTime(ts) {
                    if (!ts || Number.isNaN(Number(ts))) return '-';
                    const d = new Date(Number(ts));
                    if (Number.isNaN(d.getTime())) return '-';
                    return d.toLocaleString('id-ID', {
                        day: '2-digit', month: '2-digit', year: 'numeric',
                        hour: '2-digit', minute: '2-digit', second: '2-digit',
                        hour12: false,
                    });
                }

                function extractDraftNameFromPayload(payload) {
                    if (!payload || typeof payload !== 'object') return '';
                    const fields = (payload.fields && typeof payload.fields === 'object') ? payload.fields : null;
                    const meta = (payload.meta && typeof payload.meta === 'object') ? payload.meta : null;
                    const candidates = [
                        fields ? fields.component : '',
                        fields ? fields.unit : '',
                        fields ? fields.wo_pr : '',
                        meta ? meta.component : '',
                        meta ? meta.unit : '',
                        meta ? meta.wo_pr : '',
                    ];
                    for (const c of candidates) {
                        const text = String(c || '').trim();
                        if (text) return text;
                    }
                    return '';
                }

                function buildDeleteUrl(draftId) {
                    const token = '__DRAFT_ID__';
                    const id = encodeURIComponent(String(draftId || '').trim());
                    return String(draftApi.deleteUrlTpl || '').replace(token, id);
                }

                function renderDraftEntries() {
                    if (!listEl || !emptyEl) return;
                    listEl.innerHTML = '';

                    const entries = Array.isArray(state.entries) ? state.entries : [];
                    if (!entries.length) {
                        emptyEl.style.display = '';
                        return;
                    }
                    emptyEl.style.display = 'none';

                    entries.forEach((entry, index) => {
                        const row = document.createElement('div');
                        row.className = 'draft-row';

                        const openBtn = document.createElement('button');
                        openBtn.type = 'button';
                        openBtn.className = 'draft-row__open';

                        const nameEl = document.createElement('div');
                        nameEl.className = 'draft-row__name';
                        nameEl.textContent = String(entry.name || '-');

                        const metaEl = document.createElement('div');
                        metaEl.className = 'draft-row__meta';
                        const sourceText = entry.source === 'server' ? 'Server Draft' : 'Draft Lokal';
                        metaEl.textContent = sourceText + ' | ' + formatDateTime(entry.ts || 0);

                        openBtn.appendChild(nameEl);
                        openBtn.appendChild(metaEl);
                        openBtn.addEventListener('click', () => openDraftEntry(index));

                        const deleteBtn = document.createElement('button');
                        deleteBtn.type = 'button';
                        deleteBtn.className = 'draft-row__delete';
                        deleteBtn.textContent = 'Hapus';
                        deleteBtn.addEventListener('click', () => deleteDraftEntry(index));

                        row.appendChild(openBtn);
                        row.appendChild(deleteBtn);
                        listEl.appendChild(row);
                    });
                }

                function openModal(kind, entries) {
                    const cfg = config[kind];
                    state.kind = kind;
                    state.url = (cfg && cfg.url) ? cfg.url : '';
                    state.entries = Array.isArray(entries) ? entries : [];

                    titleEl.textContent = 'Draft ' + ((cfg && cfg.label) ? cfg.label : 'CCR') + ' ditemukan';
                    descEl.textContent = 'Klik draft untuk membuka, atau pilih Buat Baru.';
                    renderDraftEntries();

                    modal.classList.add('is-open');
                    modal.setAttribute('aria-hidden', 'false');
                }

                function closeModal() {
                    modal.classList.remove('is-open');
                    modal.setAttribute('aria-hidden', 'true');
                    state.kind = null;
                    state.url = '';
                    state.entries = [];
                }

                function readLocalDraftTs(key) {
                    if (!key) return null;
                    let raw = null;
                    try { raw = localStorage.getItem(key); } catch (e) { return null; }
                    if (!raw) return null;

                    let ts = 0;
                    let parsedPayload = null;
                    try {
                        parsedPayload = JSON.parse(raw);
                        const parsedTs = (parsedPayload && typeof parsedPayload === 'object')
                            ? (parsedPayload.ts || parsedPayload.saved_at || 0)
                            : 0;
                        ts = Number(parsedTs);
                    } catch (e) {
                        ts = 0;
                        parsedPayload = null;
                    }

                    if (ts > 0 && (Date.now() - ts) > DRAFT_MAX_AGE_MS) {
                        try { localStorage.removeItem(key); } catch (e) {}
                        return null;
                    }

                    return { key, ts: ts > 0 ? ts : 0, payload: parsedPayload };
                }

                function collectLocalDraftKeys(cfg, mode = 'all') {
                    const exactSource = mode === 'detect'
                        ? (Array.isArray(cfg.detectKeys) ? cfg.detectKeys : cfg.localKeys)
                        : cfg.localKeys;
                    const prefixSource = mode === 'detect'
                        ? (Array.isArray(cfg.detectPrefixes) ? cfg.detectPrefixes : cfg.localPrefixes)
                        : cfg.localPrefixes;

                    const exactKeys = Array.isArray(exactSource) ? exactSource.slice() : [];
                    const prefixes = Array.isArray(prefixSource) ? prefixSource : [];
                    if (!prefixes.length) return Array.from(new Set(exactKeys));

                    try {
                        for (let i = 0; i < localStorage.length; i++) {
                            const key = String(localStorage.key(i) || '');
                            if (!key) continue;
                            const matched = prefixes.some((prefix) => {
                                const p = String(prefix || '');
                                return p && key.indexOf(p) === 0;
                            });
                            if (matched) exactKeys.push(key);
                        }
                    } catch (e) {}

                    return Array.from(new Set(exactKeys));
                }

                function readIndexedDraftTs(indexedCfg) {
                    return new Promise((resolve) => {
                        if (!indexedCfg || !window.indexedDB || !indexedCfg.key) {
                            resolve(null);
                            return;
                        }
                        let openReq;
                        try {
                            openReq = window.indexedDB.open(indexedCfg.dbName, 1);
                        } catch (e) {
                            resolve(null);
                            return;
                        }
                        openReq.onerror = () => resolve(null);
                        openReq.onsuccess = () => {
                            const db = openReq.result;
                            let tx;
                            try {
                                tx = db.transaction(indexedCfg.storeName, 'readonly');
                            } catch (e) {
                                resolve(null);
                                return;
                            }
                            const store = tx.objectStore(indexedCfg.storeName);
                            const req = store.get(indexedCfg.key);
                            req.onerror = () => resolve(null);
                            req.onsuccess = () => {
                                const rec = req.result;
                                if (!rec || typeof rec !== 'object') {
                                    resolve(null);
                                    return;
                                }
                                const ts = Number(rec.ts || 0);
                                if (ts > 0 && (Date.now() - ts) > DRAFT_MAX_AGE_MS) {
                                    resolve(null);
                                    return;
                                }
                                resolve({ key: indexedCfg.key, ts: ts > 0 ? ts : 0 });
                            };
                        };
                    });
                }

                function clearIndexedDraft(indexedCfg) {
                    return new Promise((resolve) => {
                        if (!indexedCfg || !window.indexedDB || !indexedCfg.key) {
                            resolve();
                            return;
                        }
                        let openReq;
                        try {
                            openReq = window.indexedDB.open(indexedCfg.dbName, 1);
                        } catch (e) {
                            resolve();
                            return;
                        }
                        openReq.onerror = () => resolve();
                        openReq.onsuccess = () => {
                            const db = openReq.result;
                            let tx;
                            try {
                                tx = db.transaction(indexedCfg.storeName, 'readwrite');
                            } catch (e) {
                                resolve();
                                return;
                            }
                            const store = tx.objectStore(indexedCfg.storeName);
                            const req = store.delete(indexedCfg.key);
                            req.onerror = () => resolve();
                            req.onsuccess = () => resolve();
                        };
                    });
                }

                async function detectLocalDraft(kind) {
                    const cfg = config[kind];
                    if (!cfg) return null;

                    const localHits = collectLocalDraftKeys(cfg, 'detect')
                        .map((k) => readLocalDraftTs(k))
                        .filter(Boolean);

                    const indexedHit = await readIndexedDraftTs(cfg.indexed);
                    if (indexedHit) localHits.push(indexedHit);
                    if (!localHits.length) return null;

                    let latestHit = null;
                    for (const hit of localHits) {
                        if (!latestHit || Number(hit.ts || 0) > Number(latestHit.ts || 0)) latestHit = hit;
                    }

                    const latestTs = localHits.reduce((max, item) => {
                        const ts = Number(item.ts || 0);
                        return ts > max ? ts : max;
                    }, 0);

                    const latestPayload = latestHit && latestHit.payload ? latestHit.payload : null;
                    const draftName = extractDraftNameFromPayload(latestPayload);
                    if (!draftName) return null;

                    return {
                        id: 'local:' + kind,
                        source: 'local',
                        kind,
                        name: draftName,
                        ts: latestTs,
                    };
                }

                async function fetchServerDrafts(kind) {
                    const type = kind === 'engine' ? 'engine' : 'seat';
                    const baseUrl = String(draftApi.listUrl || '').trim();
                    if (!baseUrl) return [];

                    const connector = baseUrl.includes('?') ? '&' : '?';
                    const url = baseUrl + connector + 'type=' + encodeURIComponent(type);

                    try {
                        const res = await fetch(url, {
                            method: 'GET',
                            headers: { 'Accept': 'application/json' },
                        });
                        if (!res.ok) return [];

                        const json = await res.json().catch(() => ({}));
                        const rows = (json && Array.isArray(json.drafts)) ? json.drafts : [];
                        return rows.map((row) => {
                            const tsRaw = row.last_saved_at || row.updated_at || '';
                            const ts = Date.parse(String(tsRaw || ''));
                            return {
                                id: String(row.id || ''),
                                source: 'server',
                                kind,
                                name: String(row.draft_name || '').trim() || 'ENGINE Draft',
                                ts: Number.isFinite(ts) ? ts : 0,
                            };
                        }).filter((row) => row.id !== '');
                    } catch (e) {
                        return [];
                    }
                }

                async function detectDraftEntries(kind) {
                    const [serverEntries, localEntry] = await Promise.all([
                        fetchServerDrafts(kind),
                        detectLocalDraft(kind),
                    ]);

                    const merged = [];
                    if (localEntry) merged.push(localEntry);
                    merged.push(...serverEntries);

                    const seen = new Set();
                    const unique = [];
                    for (const entry of merged) {
                        const key = (entry.source || '') + ':' + (entry.id || '');
                        if (seen.has(key)) continue;
                        seen.add(key);
                        unique.push(entry);
                    }

                    unique.sort((a, b) => Number(b.ts || 0) - Number(a.ts || 0));
                    return unique;
                }

                async function clearServerDrafts(kind) {
                    const entries = await fetchServerDrafts(kind);
                    const serverRows = entries.filter((e) => e && e.source === 'server' && e.id);
                    for (const row of serverRows) {
                        const deleteUrl = buildDeleteUrl(row.id);
                        if (!deleteUrl) continue;
                        try {
                            await fetch(deleteUrl, {
                                method: 'DELETE',
                                headers: {
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': String(draftApi.csrf || ''),
                                },
                            });
                        } catch (e) {}
                    }
                }

                async function clearDraft(kind, opts = {}) {
                    const cfg = config[kind];
                    if (!cfg) return;

                    const localKeys = collectLocalDraftKeys(cfg);
                    for (const key of localKeys) {
                        try { localStorage.removeItem(key); } catch (e) {}
                    }
                    if (cfg.clientKeyStorage) {
                        try { localStorage.removeItem(String(cfg.clientKeyStorage)); } catch (e) {}
                    }
                    await clearIndexedDraft(cfg.indexed);
                    if (opts && opts.server) await clearServerDrafts(kind);
                }

                if (typeof clearCreateDraft === 'string' && clearCreateDraft === 'engine') {
                    clearDraft('engine', { server: true }).catch(() => {});
                }

                function openDraftEntry(index) {
                    const entry = state.entries[index];
                    if (!entry) return;
                    if (entry.source === 'server') {
                        const url = state.url + '?draft=' + encodeURIComponent(String(entry.id || ''));
                        window.location.href = url;
                        return;
                    }
                    window.location.href = state.url;
                }

                async function deleteDraftEntry(index) {
                    const entry = state.entries[index];
                    if (!entry || !state.kind) return;

                    if (entry.source === 'local') {
                        await clearDraft(state.kind, { server: false });
                    } else {
                        const deleteUrl = buildDeleteUrl(entry.id);
                        try {
                            const res = await fetch(deleteUrl, {
                                method: 'DELETE',
                                headers: {
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': String(draftApi.csrf || ''),
                                },
                            });
                            if (!res.ok) return;
                        } catch (e) {
                            return;
                        }
                        // Also clear IndexedDB photos + localStorage client key
                        await clearDraft(state.kind, { server: false });
                    }
                    state.entries.splice(index, 1);
                    renderDraftEntries();
                }

                async function onCreateClick(event) {
                    const card = event.currentTarget;
                    const kind = card.dataset.draftTarget || '';
                    const cfg = config[kind];
                    if (!cfg) return;

                    event.preventDefault();
                    const entries = await detectDraftEntries(kind);
                    if (!entries.length) {
                        window.location.href = cfg.url;
                        return;
                    }
                    openModal(kind, entries);
                }

                page.querySelectorAll('.js-draft-entry').forEach((el) => {
                    el.addEventListener('click', onCreateClick);
                });

                if (btnCancel) btnCancel.addEventListener('click', closeModal);
                modal.querySelectorAll('[data-draft-close]').forEach((el) => {
                    el.addEventListener('click', closeModal);
                });

                if (btnNew) btnNew.addEventListener('click', async () => {
                    const kind = state.kind;
                    const url = state.url;
                    if (!kind || !url) return;

                    btnNew.disabled = true;
                    btnNew.textContent = 'Menyiapkan...';
                    try {
                        await clearDraft(kind, { server: true });
                    } finally {
                        btnNew.disabled = false;
                        btnNew.textContent = 'Buat Baru';
                        window.location.href = url;
                    }
                });

                document.addEventListener('keydown', (event) => {
                    if (event.key === 'Escape' && modal.classList.contains('is-open')) {
                        closeModal();
                    }
                });
            })();
        </script>

        {{-- ======================= SCRIPT (FILTER/SORT) ======================= --}}
        <script>
            document.addEventListener("DOMContentLoaded", () => {
                const page = document.querySelector('[data-page="manage-engine"]');
                if (!page) return;

                const searchInput    = page.querySelector("#searchInputEngine");
                const customerFilter = page.querySelector("#customerFilterEngine");
                const sortSelect     = page.querySelector("#sortSelectEngine");
                const statusFilter   = page.querySelector("#statusFilterEngine");
                const list           = page.querySelector("#reportListEngine");
                const resultCountEl  = page.querySelector("#resultCountEngine");
                const noResultEl     = page.querySelector("#noResultEngine");

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

                function syncVisibleCounter() {
                    const visibleCount = cards.filter((card) => card.style.display !== "none").length;
                    if (resultCountEl) resultCountEl.textContent = String(visibleCount);
                    if (noResultEl) noResultEl.style.display = (visibleCount === 0 && cards.length > 0) ? "block" : "none";
                }

                function applyFilters() {
                    const q = (searchInput.value || "").toLowerCase().trim();
                    const c = (customerFilter.value || "").trim();
                    const s = (statusFilter?.value || "").trim();

                    cards.forEach(card => {
                        const search = (card.dataset.search || "");
                        const cust   = (card.dataset.customer || "").trim();
                        const stat   = (card.dataset.status || "").trim();

                        let show = true;
                        if (q && !search.includes(q)) show = false;
                        if (c && cust !== c) show = false;
                        if (s && stat !== s) show = false;

                        card.style.display = show ? "flex" : "none";
                    });

                    syncVisibleCounter();
                    if (window.__engineSyncSelectAll) window.__engineSyncSelectAll();
                }

                function applySort() {
                    cards = Array.from(list.querySelectorAll(".report-card"));

                    const mode = sortSelect.value;
                    const sorted = [...cards].sort((a, b) => {
                        if (mode === "newest")  return toTime(b.dataset.created || b.dataset.date) - toTime(a.dataset.created || a.dataset.date);
                        if (mode === "oldest")  return toTime(a.dataset.created || a.dataset.date) - toTime(b.dataset.created || b.dataset.date);
                        if (mode === "updated") return toTime(b.dataset.updated || b.dataset.created || b.dataset.date) - toTime(a.dataset.updated || a.dataset.created || a.dataset.date);
                        return 0;
                    });

                    sorted.forEach(card => list.appendChild(card));
                    cards = sorted;

                    applyFilters();
                }

                searchInput.addEventListener("input", applyFilters);
                customerFilter.addEventListener("change", applyFilters);
                sortSelect.addEventListener("change", applySort);
                if (statusFilter) statusFilter.addEventListener("change", applyFilters);

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

    {{-- ======================= STYLE (SCOPED) ======================= --}}
    <style>
    [data-page="manage-engine"]{
        --engine-accent: #E40505;
        --engine-red: #E11D48;
        --engine-red-soft: rgba(225,29,72,.14);
        --engine-surface: #ffffff;
        --engine-muted-surface: #f6f9ff;
        --engine-border: #d7e1ef;
        --engine-text: #0f172a;
        --engine-muted: #5f6d84;
        --engine-shadow: 0 3px 10px rgba(0,0,0,.07);
        padding-bottom: 110px;
        font-family: Arial, sans-serif;
        background:#f5f7fb;
        border-radius:14px;
        padding:12px;
    }

    [data-page="manage-engine"] .top-toolbar{
        display:flex;
        align-items:center;
        justify-content:flex-start;
        gap:12px;
        margin-bottom:16px;
        flex-wrap:wrap;
    }
    [data-page="manage-engine"] .btn-back{
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
    [data-page="manage-engine"] .btn-back:hover{
        background:#2f3439;
    }
    [data-page="manage-engine"] .top-toolbar-actions{
        display:flex;
        align-items:center;
        gap:10px;
        flex-wrap:wrap;
    }
    [data-page="manage-engine"] .btn-top{
        display:inline-flex;
        align-items:center;
        justify-content:center;
        gap:8px;
        border-radius:10px;
        padding:9px 14px;
        text-decoration:none;
        font-size:14px;
        font-weight:700;
        border:1px solid transparent;
        transition:.2s ease;
    }
    [data-page="manage-engine"] .btn-top-primary{
        color:#fff;
        background:#0D6EFD;
        box-shadow:0 6px 14px rgba(13,110,253,.24);
    }
    [data-page="manage-engine"] .btn-top-primary:hover{
        background:#0b5ed7;
    }

    [data-page="manage-engine"] .header-card{
        background:#ffffff;
        padding:22px;
        border-radius:14px;
        margin-bottom:20px;
        box-shadow:var(--engine-shadow);
        border:1px solid var(--engine-border);
    }
    [data-page="manage-engine"] .header-left{
        display:flex;
        align-items:center;
        gap:18px;
    }
    [data-page="manage-engine"] .header-logo{
        width:80px;
        height:80px;
        object-fit:contain;
    }
    [data-page="manage-engine"] .header-title{
        margin:0;
        font-size:20px;
        line-height:1.1;
        font-weight:800;
        color:var(--engine-text);
    }
    [data-page="manage-engine"] .header-subtitle{
        margin:4px 0 0;
        color:var(--engine-muted);
        font-size:14px;
        font-weight:600;
    }
    [data-page="manage-engine"] .accent-line{
        height:4px;
        background:#E40505;
        border-radius:999px;
        margin-bottom:18px;
    }

    [data-page="manage-engine"] .stats-grid{
        display:grid;
        grid-template-columns:repeat(5, minmax(130px, 1fr));
        gap:10px;
        margin-bottom:16px;
    }
    [data-page="manage-engine"] .stat-card{
        background:#fff;
        border:1px solid var(--engine-border);
        border-radius:10px;
        box-shadow:var(--engine-shadow);
        padding:10px 14px;
        min-height:78px;
        display:flex;
        flex-direction:column;
        justify-content:center;
    }
    [data-page="manage-engine"] .stat-label{
        color:var(--engine-muted);
        font-weight:700;
        font-size:11px;
        text-transform:uppercase;
        letter-spacing:.04em;
    }
    [data-page="manage-engine"] .stat-value{
        margin-top:2px;
        color:var(--engine-text);
        font-weight:800;
        font-size:20px;
        line-height:1;
    }

    [data-page="manage-engine"] .box{
        background:var(--engine-surface);
        padding:22px;
        border-radius:14px;
        margin-bottom:22px;
        box-shadow:var(--engine-shadow);
        border:1px solid var(--engine-border);
    }
    [data-page="manage-engine"] .filter-box{
        background:#ffffff;
    }
    [data-page="manage-engine"] .section-title{
        margin:0 0 16px;
        font-size:18px;
        font-weight:700;
        line-height:1.15;
        color:var(--engine-text);
    }

    [data-page="manage-engine"] .filter-row{
        display:grid;
        grid-template-columns:minmax(0, 1.7fr) repeat(3, minmax(0, 1fr));
        column-gap:24px;
        row-gap:16px;
        align-items:end;
        width:100%;
    }
    [data-page="manage-engine"] .filter-row > *{
        min-width:0;
    }
    [data-page="manage-engine"] .filter-group{
        min-width:0;
        display:flex;
        flex-direction:column;
        gap:8px;
    }
    [data-page="manage-engine"] .filter-group.filter-large{
        padding-right:0;
    }
    [data-page="manage-engine"] .filter-group.filter-small{
        padding-left:0;
    }
    [data-page="manage-engine"] .filter-group label{
        display:block;
        margin:0;
        color:#1e293b;
        font-weight:700;
        font-size:14px;
    }
    [data-page="manage-engine"] .input{
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
    [data-page="manage-engine"] .input:focus{
        outline:none;
        border-color:var(--engine-accent);
        box-shadow:0 0 0 4px rgba(228,5,5,.12);
    }

    [data-page="manage-engine"] .report-list-box{
        padding-top:18px;
    }
    [data-page="manage-engine"] .list-pagination{
        margin-top:12px;
    }
    [data-page="manage-engine"] .list-head{
        display:flex;
        align-items:center;
        justify-content:space-between;
        gap:12px;
        margin-bottom:12px;
        flex-wrap:wrap;
    }
    [data-page="manage-engine"] .list-head-tools{
        display:inline-flex;
        align-items:center;
        gap:10px;
        flex-wrap:wrap;
    }
    [data-page="manage-engine"] .btn-list-create{
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
        transition:
            transform .18s ease,
            box-shadow .18s ease,
            background-color .18s ease,
            border-color .18s ease,
            color .18s ease,
            filter .22s ease;
    }
    [data-page="manage-engine"] .btn-list-create:hover{
        background:#f8fafc;
        color:#111;
        transform:translateY(-1px) scale(1.01);
        filter:saturate(1.01);
        box-shadow:
            0 4px 10px rgba(228,5,5,.14),
            0 0 0 1px rgba(228,5,5,.18);
        text-decoration:none;
    }
    [data-page="manage-engine"] .btn-list-create:active{
        transform:translateY(0) scale(1);
        filter:none;
        box-shadow:0 2px 5px rgba(228,5,5,.12);
    }
    [data-page="manage-engine"] .btn-list-create:focus-visible{
        outline:none;
        border-color:#E40505;
        box-shadow:
            0 0 0 2px rgba(228,5,5,.14),
            0 4px 10px rgba(228,5,5,.14);
    }
    @media (prefers-reduced-motion: reduce){
        [data-page="manage-engine"] .btn-list-create{
            transition:none;
        }
        [data-page="manage-engine"] .btn-list-create:hover,
        [data-page="manage-engine"] .btn-list-create:active{
            transform:none;
        }
    }
    [data-page="manage-engine"] .list-head-title{
        font-size:14px;
        color:#71809b;
        font-weight:800;
        letter-spacing:.08em;
        text-transform:uppercase;
    }
    [data-page="manage-engine"] .list-head-count{
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
        margin-right:0;
    }
    [data-page="manage-engine"] .list-head-count .count-number{
        font-size:15px;
        line-height:1;
        letter-spacing:-.02em;
    }
    [data-page="manage-engine"] .list-head-count .count-text{
        font-size:12px;
        font-weight:700;
    }
    [data-page="manage-engine"] .select-all-row{
        display:flex;
        align-items:center;
        gap:14px;
        padding:8px 4px 14px;
        flex-wrap:wrap;
    }
    [data-page="manage-engine"] .select-all-label{
        cursor:pointer;
        user-select:none;
        font-size:16px;
        line-height:1;
        color:#be123c;
    }
    [data-page="manage-engine"] .select-all-label b{
        font-weight:800;
    }
    [data-page="manage-engine"] .select-all-hint{
        color:var(--engine-muted);
        font-size:13px;
        font-weight:600;
    }
    [data-page="manage-engine"] .select-divider{
        height:1px;
        background:#e5ebf4;
        margin:2px 0 12px;
    }

    [data-page="manage-engine"] .select-checkbox{
        width:20px;
        height:20px;
        accent-color:#E11D48;
        cursor:pointer;
        flex:0 0 auto;
    }

    [data-page="manage-engine"] .report-card{
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
    [data-page="manage-engine"] .report-card:hover{
        border-color:#cfdbec;
        box-shadow:0 6px 16px rgba(20, 40, 90, .07);
    }
    [data-page="manage-engine"] .report-card.selected{
        background:#fff5f5;
        border-color:rgba(228,5,5,.35);
        box-shadow:0 0 0 3px rgba(228,5,5,.12);
    }

    [data-page="manage-engine"] .report-left{
        display:flex;
        align-items:center;
        gap:16px;
        min-width:0;
        flex:1;
    }
    [data-page="manage-engine"] .report-main{
        display:flex;
        flex-direction:column;
        gap:6px;
        min-width:0;
        width:100%;
    }
    [data-page="manage-engine"] .report-title{
        display:flex;
        flex-wrap:wrap;
        align-items:center;
        gap:10px;
    }
    [data-page="manage-engine"] .report-title > strong{
        font-size:16px;
        line-height:1.2;
        color:var(--engine-text);
        min-width:0;
        overflow-wrap:anywhere;
        word-break:break-word;
    }

    [data-page="manage-engine"] .status-pill{
        display:inline-flex;
        align-items:center;
        justify-content:center;
        padding:4px 10px;
        border-radius:999px;
        border:1.5px solid transparent;
        background:#f8fafc;
        font-weight:800;
        font-size:10px;
        letter-spacing:.5px;
        text-transform:uppercase;
        line-height:1;
    }
    [data-page="manage-engine"] .status-text{
        line-height:1;
    }
    [data-page="manage-engine"] .status-pill-draft{
        color:#64748b;
        background:rgba(100,116,139,.10);
        border-color:rgba(100,116,139,.35);
    }
    [data-page="manage-engine"] .status-pill-waiting{
        color:#de9b2d;
        background:rgba(222,155,45,.14);
        border-color:rgba(245,207,72,.78);
    }
    [data-page="manage-engine"] .status-pill-approved{
        color:#43c06a;
        background:rgba(67,192,106,.14);
        border-color:rgba(129,236,168,.72);
    }
    [data-page="manage-engine"] .status-pill-rejected{
        color:#ff6d5a;
        background:rgba(255,109,90,.12);
        border-color:rgba(255,167,155,.78);
    }

    [data-page="manage-engine"] .status-note-btn{
        display:inline-flex;
        align-items:center;
        gap:4px;
        padding:3px 8px;
        border-radius:999px;
        border:1px solid #e5e7eb;
        background:#fff;
        font-weight:700;
        font-size:10px;
        cursor:pointer;
        transition:.18s ease;
    }
    [data-page="manage-engine"] .status-note-btn:hover{ filter:brightness(.98); }
    [data-page="manage-engine"] .status-note-btn-rejected{
        border-color: rgba(239,68,68,.35);
        background: rgba(239,68,68,.10);
        color:#ef4444;
    }
    [data-page="manage-engine"] .status-note-btn-approved{
        border-color: rgba(34,197,94,.35);
        background: rgba(34,197,94,.10);
        color:#22c55e;
    }

    [data-page="manage-engine"] .report-meta{
        display:flex;
        flex-wrap:wrap;
        gap:8px;
    }
    [data-page="manage-engine"] .report-meta span{
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
    [data-page="manage-engine"] .report-meta span b{
        color:#1f2937;
    }
    [data-page="manage-engine"] .time-wrap{
        display:flex;
        gap:8px;
        flex-wrap:wrap;
    }
    [data-page="manage-engine"] .time-pill{
        background:#fff1f2;
        border:1px solid #fecdd3;
        border-radius:999px;
        padding:4px 10px;
    }
    [data-page="manage-engine"] .time-text,
    [data-page="manage-engine"] .time-wita{
        font-weight:900;
        color:#dc2626;
    }
    [data-page="manage-engine"] .time-wita{
        margin-left:4px;
    }

    [data-page="manage-engine"] .report-actions{
        display:flex;
        flex-wrap:wrap;
        justify-content:flex-end;
        gap:7px;
        border-left:2px solid #eee;
        padding-left:12px;
        min-width:342px;
        max-width:420px;
        flex:0 0 auto;
        align-items:center;
    }
    [data-page="manage-engine"] .report-actions > a,
    [data-page="manage-engine"] .report-actions > span,
    [data-page="manage-engine"] .report-actions > form{
        width:110px;
        flex:0 0 110px;
    }
    [data-page="manage-engine"] .report-actions form{
        margin:0;
        display:block;
        width:100%;
        flex:0 0 110px;
    }
    [data-page="manage-engine"] .report-actions form .btn-premium{
        border:none;
        cursor:pointer;
        width:100%;
    }

    [data-page="manage-engine"] .btn-premium{
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
    [data-page="manage-engine"] .btn-premium:hover{
        text-decoration:none;
        transform:translateY(-1px);
        box-shadow:0 4px 10px rgba(0,0,0,.15);
    }
    [data-page="manage-engine"] .lihat-btn{ background:#f57c00; }
    [data-page="manage-engine"] .lihat-btn:hover{ background:#e57200; }
    [data-page="manage-engine"] .edit-btn,
    [data-page="manage-engine"] .word-btn{
        background:#f8fafc;
        color:#111827;
        border:2px solid #c4ccd8;
        box-shadow:none;
    }
    [data-page="manage-engine"] .edit-btn:hover,
    [data-page="manage-engine"] .word-btn:hover{
        background:#f1f5f9;
        color:#0f172a;
        border-color:#b8c2cf;
        transform:none;
        box-shadow:none;
    }
    [data-page="manage-engine"] .btn-submit{ background:#9f8170; }
    [data-page="manage-engine"] .btn-submit:hover{ background:#8f7465; }
    [data-page="manage-engine"] .btn-submit,
    [data-page="manage-engine"] .btn-inreview,
    [data-page="manage-engine"] .btn-resubmit{
        min-height:34px;
        height:34px;
        padding:6px 10px;
        width:100%;
        border-radius:9px;
        box-sizing:border-box;
        font-size:12px;
        font-weight:600;
        line-height:1;
    }
    [data-page="manage-engine"] .btn-resubmit{
        background:#fff !important;
        color:#6c4d3f !important;
        border:1px solid #9f8170 !important;
    }
    [data-page="manage-engine"] .btn-resubmit:hover{
        background:#f8f2ee !important;
        color:#6c4d3f !important;
    }
    [data-page="manage-engine"] .btn-inreview{
        background:#d7dce5 !important;
        color:#586478 !important;
        border:1px solid #c4ccd9 !important;
        min-width:0;
        width:100%;
        pointer-events:auto;
        user-select:none;
        cursor:not-allowed !important;
        box-shadow:none !important;
        transform:none !important;
        opacity:1;
    }
    [data-page="manage-engine"] .btn-inreview:hover{
        background:#d7dce5 !important;
        color:#586478 !important;
        box-shadow:none !important;
        transform:none !important;
        cursor:not-allowed !important;
    }
    [data-page="manage-engine"] .btn-inreview:focus,
    [data-page="manage-engine"] .btn-inreview:focus-visible,
    [data-page="manage-engine"] .btn-inreview:active{
        outline:none !important;
        box-shadow:none !important;
        transform:none !important;
    }
    [data-page="manage-engine"] .icon-btn{
        width:16px;
        height:16px;
    }

    [data-page="manage-engine"] .empty-state{
        margin:14px 4px 2px;
        padding:22px;
        border:1px dashed #c9d7eb;
        border-radius:12px;
        background:#f8fbff;
        color:#4c5d77;
        font-size:14px;
        font-weight:700;
    }

    [data-page="manage-engine"] .bulk-bar{
        position:fixed;
        left:50%;
        bottom:20px;
        transform:translateX(-50%);
        z-index:9999;
        margin:0;
        padding:8px;
        background:rgba(255,255,255,.95);
        backdrop-filter:blur(6px);
        -webkit-backdrop-filter:blur(6px);
        border:1px solid #d8e3f2;
        border-radius:16px;
        box-shadow:0 14px 36px rgba(0,0,0,.18);
        width:fit-content;
        max-width:calc(100vw - 24px);
    }
    [data-page="manage-engine"] .bulk-btn{
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
    [data-page="manage-engine"] .bulk-btn-danger:hover{
        background:rgba(220,53,69,.12);
        border-color:rgba(220,53,69,.25);
        color:#dc3545;
    }

    [data-page="manage-engine"] .draft-modal{
        position:fixed;
        inset:0;
        z-index:1200;
        display:none;
    }
    [data-page="manage-engine"] .draft-modal.is-open{
        display:block;
    }
    [data-page="manage-engine"] .draft-modal__backdrop{
        position:absolute;
        inset:0;
        background:rgba(2,6,23,.58);
    }
    [data-page="manage-engine"] .draft-modal__panel{
        position:relative;
        width:min(520px, calc(100vw - 28px));
        margin:10vh auto 0;
        background:#fff;
        border-radius:16px;
        border:1px solid #dbe2ea;
        box-shadow:0 24px 60px rgba(2,6,23,.25);
        padding:18px 18px 16px;
    }
    [data-page="manage-engine"] .draft-modal__title{
        margin:0;
        font-size:22px;
        font-weight:900;
        color:#0f172a;
    }
    [data-page="manage-engine"] .draft-modal__desc{
        margin:8px 0 0;
        font-size:14px;
        line-height:1.45;
        color:#475569;
    }
    [data-page="manage-engine"] .draft-modal__list{
        margin-top:12px;
        max-height:320px;
        overflow:auto;
        display:grid;
        gap:8px;
    }
    [data-page="manage-engine"] .draft-modal__empty{
        margin-top:12px;
        border:1px dashed #cbd5e1;
        background:#f8fafc;
        color:#475569;
        border-radius:10px;
        padding:10px 12px;
        font-size:13px;
        font-weight:800;
    }
    [data-page="manage-engine"] .draft-row{
        border:1px solid #dbe2ea;
        background:#fff;
        border-radius:12px;
        padding:8px;
        display:flex;
        gap:8px;
        align-items:stretch;
    }
    [data-page="manage-engine"] .draft-row__open{
        flex:1;
        border:1px solid #dbe2ea;
        border-radius:10px;
        background:#f8fafc;
        padding:10px 12px;
        text-align:left;
        cursor:pointer;
    }
    [data-page="manage-engine"] .draft-row__open:hover{
        border-color:#93c5fd;
        background:#eff6ff;
    }
    [data-page="manage-engine"] .draft-row__name{
        font-size:14px;
        font-weight:900;
        color:#0f172a;
    }
    [data-page="manage-engine"] .draft-row__meta{
        margin-top:4px;
        font-size:12px;
        font-weight:700;
        color:#475569;
    }
    [data-page="manage-engine"] .draft-row__delete{
        border:1px solid #fecaca;
        background:#fff1f2;
        color:#b91c1c;
        border-radius:10px;
        min-width:84px;
        font-size:12px;
        font-weight:800;
        cursor:pointer;
    }
    [data-page="manage-engine"] .draft-row__delete:hover{
        background:#ffe4e6;
    }
    [data-page="manage-engine"] .draft-modal__actions{
        margin-top:14px;
        display:flex;
        justify-content:flex-end;
        gap:10px;
        flex-wrap:wrap;
    }
    [data-page="manage-engine"] .draft-btn{
        border:1px solid #dbe2ea;
        border-radius:10px;
        padding:9px 14px;
        font-size:13px;
        font-weight:800;
        cursor:pointer;
        transition:.18s ease;
    }
    [data-page="manage-engine"] .draft-btn:disabled{
        opacity:.65;
        cursor:not-allowed;
    }
    [data-page="manage-engine"] .draft-btn--primary{
        color:#fff;
        background:#2563eb;
        border-color:#2563eb;
    }
    [data-page="manage-engine"] .draft-btn--primary:hover{
        background:#1d4ed8;
    }
    [data-page="manage-engine"] .draft-btn--ghost{
        color:#1f2937;
        background:#fff;
    }
    [data-page="manage-engine"] .draft-btn--ghost:hover{
        background:#f8fafc;
    }

    [data-page="manage-engine"] .note-modal{
        position:fixed;
        inset:0;
        display:none;
        z-index:9999;
    }
    [data-page="manage-engine"] .note-modal.is-open{
        display:block;
    }
    [data-page="manage-engine"] .note-modal-backdrop{
        position:absolute;
        inset:0;
        background:rgba(0,0,0,.20);
        backdrop-filter:blur(2px);
        -webkit-backdrop-filter:blur(2px);
    }
    [data-page="manage-engine"] .note-modal-card{
        --accent:#ef4444;
        --accent-bg: rgba(239,68,68,.12);
        position:relative;
        width:min(780px, calc(100% - 32px));
        margin:90px auto;
        background:#fff;
        border-radius:16px;
        box-shadow:0 20px 60px rgba(2, 6, 23, .25);
        overflow:hidden;
        border:1px solid rgba(0,0,0,.08);
    }
    [data-page="manage-engine"] .note-modal-card[data-note-type="approved"]{
        --accent:#22c55e;
        --accent-bg: rgba(34,197,94,.12);
    }
    [data-page="manage-engine"] .note-modal-header{
        display:flex;
        align-items:center;
        justify-content:space-between;
        padding:12px 14px;
        background:var(--accent-bg);
        border-bottom:1px solid rgba(0,0,0,.07);
    }
    [data-page="manage-engine"] .pill-note{
        display:inline-flex;
        align-items:center;
        padding:6px 12px;
        border-radius:999px;
        font-weight:900;
        font-size:13px;
        color:var(--accent);
        border:2px solid color-mix(in srgb, var(--accent) 38%, transparent);
        background:rgba(255,255,255,.88);
    }
    [data-page="manage-engine"] .note-modal-close{
        width:36px;
        height:36px;
        border-radius:10px;
        border:1px solid rgba(0,0,0,.10);
        background:#fff;
        cursor:pointer;
        font-size:16px;
    }
    [data-page="manage-engine"] .note-modal-body{
        padding:16px 18px 20px;
        min-height:110px;
    }
    [data-page="manage-engine"] .note-text{
        font-size:16px;
        font-weight:700;
        color:#111827;
        line-height:1.55;
        white-space:pre-wrap;
        word-break:break-word;
    }
    [data-page="manage-engine"] .note-modal-footer{
        padding:12px 14px;
        display:flex;
        justify-content:flex-end;
        border-top:1px solid rgba(0,0,0,.07);
    }
    [data-page="manage-engine"] .note-ok-btn{
        display:inline-block;
        color:#fff;
        padding:8px 18px;
        border-radius:9px;
        background:#2f3439;
        font-weight:700;
        font-size:14px;
        border:none;
        cursor:pointer;
        transition:.2s ease;
        box-shadow:0 8px 20px rgba(0,0,0,.15);
    }
    [data-page="manage-engine"] .note-ok-btn:hover{
        background:#1f2326;
    }

    [data-page="manage-engine"] .export-modal{
        position:fixed;
        inset:0;
        display:none;
        z-index:9999;
    }
    [data-page="manage-engine"] .export-modal.is-open{
        display:block;
    }
    [data-page="manage-engine"] .export-modal-backdrop{
        position:absolute;
        inset:0;
        background:rgba(0,0,0,.20);
        backdrop-filter:blur(2px);
        -webkit-backdrop-filter:blur(2px);
    }
    [data-page="manage-engine"] .export-modal-card{
        position:relative;
        width:min(520px, calc(100% - 32px));
        margin:120px auto;
        background:#fff;
        border-radius:16px;
        box-shadow:0 20px 60px rgba(2, 6, 23, .25);
        overflow:hidden;
        border:1px solid rgba(0,0,0,.08);
        padding:18px;
    }
    [data-page="manage-engine"] .export-modal-title{
        margin:0;
        font-size:22px;
        font-weight:900;
        color:#0f172a;
    }
    [data-page="manage-engine"] .export-modal-text{
        margin:10px 0 0;
        font-size:14px;
        line-height:1.5;
        color:#475569;
        word-break:break-word;
    }
    [data-page="manage-engine"] .export-modal-list{
        margin-top:14px;
        display:flex;
        flex-direction:column;
        gap:10px;
    }
    [data-page="manage-engine"] .export-list-item{
        display:flex;
        align-items:center;
        gap:10px;
        width:100%;
        box-sizing:border-box;
        min-width:0;
        padding:10px 12px;
        border-radius:11px;
        border:1px solid #d4deec;
        background:#f8fbff;
        color:#0f172a;
        font-size:14px;
        font-weight:800;
        text-decoration:none;
        transition:background-color .16s ease, border-color .16s ease, color .16s ease;
    }
    [data-page="manage-engine"] .export-list-item:hover{
        background:#eef5ff;
        border-color:#c4d6f5;
        color:#0b244a;
        text-decoration:none;
    }
    [data-page="manage-engine"] .export-list-item.is-disabled{
        opacity:.55;
        pointer-events:none;
    }
    [data-page="manage-engine"] .export-list-item.is-busy{
        opacity:.6;
        pointer-events:none;
        filter:saturate(.75);
    }
    [data-page="manage-engine"] .export-list-icon-word{
        width:18px;
        height:18px;
        object-fit:contain;
        flex:0 0 18px;
    }
    [data-page="manage-engine"] .export-list-icon-excel{
        width:18px;
        height:18px;
        object-fit:contain;
        flex:0 0 18px;
    }
    [data-page="manage-engine"] .export-list-item > span:last-child{
        min-width:0;
        overflow-wrap:anywhere;
        word-break:break-word;
    }
    [data-page="manage-engine"] .export-modal-actions{
        margin-top:14px;
        display:flex;
        justify-content:flex-end;
        gap:10px;
        flex-wrap:wrap;
    }
    [data-page="manage-engine"] .export-btn{
        display:inline-flex;
        align-items:center;
        justify-content:center;
        min-width:132px;
        padding:10px 14px;
        border-radius:10px;
        font-size:14px;
        font-weight:800;
        text-decoration:none;
        border:1px solid #d1d5db;
        cursor:pointer;
        transition:.18s ease;
    }
    [data-page="manage-engine"] .export-btn:disabled{
        opacity:.55;
        cursor:not-allowed;
    }
    [data-page="manage-engine"] .export-btn-ghost{
        background:#fff;
        color:#1f2937;
    }
    [data-page="manage-engine"] .export-btn-ghost:hover{
        background:#f8fafc;
    }
    [data-page="manage-engine"] .export-btn-primary{
        background:#185abd;
        border-color:#185abd;
        color:#fff;
    }
    [data-page="manage-engine"] .export-btn-primary:hover{
        background:#154ea4;
    }

    @media (max-width: 1100px){
        [data-page="manage-engine"] .stats-grid{
            grid-template-columns:repeat(3, minmax(0, 1fr));
        }
        [data-page="manage-engine"] .filter-row{
            grid-template-columns:repeat(2, minmax(0, 1fr));
            gap:16px;
        }
        [data-page="manage-engine"] .filter-group.filter-large{
            grid-column:1 / -1;
            padding-right:0;
        }
        [data-page="manage-engine"] .filter-group.filter-small{
            padding-left:0;
        }
        [data-page="manage-engine"] .report-card{
            flex-direction:column;
            align-items:stretch;
        }
        [data-page="manage-engine"] .report-actions{
            display:grid;
            width:100%;
            max-width:none;
            min-width:0;
            border-left:none;
            border-top:1px dashed #d6e1ee;
            padding-left:0;
            padding-top:12px;
            grid-template-columns:repeat(3, minmax(0, 110px));
            justify-content:end;
            gap:7px;
        }
    }

    @media (max-width: 1024px){
        [data-page="manage-engine"] .top-toolbar{
            flex-direction:column;
            align-items:stretch;
        }
        [data-page="manage-engine"] .top-toolbar-actions{
            width:100%;
            display:grid;
            grid-template-columns:1fr;
        }
        [data-page="manage-engine"] .btn-top{
            width:100%;
        }
        [data-page="manage-engine"] .header-card{
            padding:18px;
        }
        [data-page="manage-engine"] .header-left{
            align-items:flex-start;
        }
        [data-page="manage-engine"] .header-title{
            font-size:20px;
        }
        [data-page="manage-engine"] .header-subtitle{
            font-size:14px;
        }
        [data-page="manage-engine"] .stats-grid{
            grid-template-columns:repeat(2, minmax(0, 1fr));
        }
        [data-page="manage-engine"] .box{
            padding:18px;
        }
        [data-page="manage-engine"] .section-title{
            font-size:18px;
        }
        [data-page="manage-engine"] .filter-row{
            grid-template-columns:1fr;
            row-gap:14px;
        }
        [data-page="manage-engine"] .filter-group.filter-small{
            padding-left:0;
        }
        [data-page="manage-engine"] .list-head{
            align-items:flex-start;
            gap:10px;
        }
        [data-page="manage-engine"] .list-head-tools{
            width:100%;
            justify-content:space-between;
        }
        [data-page="manage-engine"] .list-head-count{
            margin-right:0;
        }
        [data-page="manage-engine"] .select-all-row{
            gap:12px;
        }
        [data-page="manage-engine"] .select-all-hint{
            flex:1 1 100%;
            margin-left:34px;
        }
        [data-page="manage-engine"] .report-card{
            padding:16px;
        }
        [data-page="manage-engine"] .report-actions{
            display:grid;
            grid-template-columns:repeat(2, minmax(0, 110px));
            justify-content:end;
            gap:7px;
        }
        [data-page="manage-engine"] .report-actions > a,
        [data-page="manage-engine"] .report-actions > form,
        [data-page="manage-engine"] .report-actions > span{
            width:100%;
        }
        [data-page="manage-engine"] .report-actions .btn-premium{
            width:100%;
            min-width:0;
        }
    }

    @media (max-width: 640px){
        [data-page="manage-engine"]{
            padding-bottom:120px;
        }
        [data-page="manage-engine"] .top-toolbar{
            gap:10px;
        }
        [data-page="manage-engine"] .btn-back,
        [data-page="manage-engine"] .btn-top{
            min-height:44px;
        }
        [data-page="manage-engine"] .top-toolbar-actions{
            grid-template-columns:1fr;
        }
        [data-page="manage-engine"] .header-left{
            flex-direction:column;
            align-items:flex-start;
            gap:10px;
        }
        [data-page="manage-engine"] .header-logo{
            width:70px;
            height:70px;
        }
        [data-page="manage-engine"] .stats-grid{
            grid-template-columns:1fr;
            gap:12px;
        }
        [data-page="manage-engine"] .stat-card{
            min-height:84px;
            padding:12px 16px;
        }
        [data-page="manage-engine"] .stat-value{
            font-size:24px;
        }
        [data-page="manage-engine"] .list-head{
            flex-direction:column;
            align-items:flex-start;
            gap:8px;
        }
        [data-page="manage-engine"] .list-head-tools{
            width:100%;
            justify-content:space-between;
            gap:8px;
        }
        [data-page="manage-engine"] .btn-list-create{
            min-height:28px;
            font-size:10px;
            padding:5px 8px;
            border-radius:8px;
        }
        [data-page="manage-engine"] .list-head-count{
            padding:6px 10px;
            font-size:12px;
        }
        [data-page="manage-engine"] .list-head-count .count-number{
            font-size:14px;
        }
        [data-page="manage-engine"] .list-head-count .count-text{
            font-size:12px;
        }
        [data-page="manage-engine"] .select-all-row{
            align-items:flex-start;
            flex-direction:column;
            gap:8px;
        }
        [data-page="manage-engine"] .select-all-hint{
            margin-left:0;
        }
        [data-page="manage-engine"] .select-checkbox{
            width:20px;
            height:20px;
        }
        [data-page="manage-engine"] .report-title > strong{
            font-size:16px;
            flex:1 1 100%;
        }
        [data-page="manage-engine"] .report-meta{
            gap:7px;
        }
        [data-page="manage-engine"] .report-meta span{
            font-size:12px;
            padding:4px 9px;
        }
        [data-page="manage-engine"] .report-actions{
            grid-template-columns:repeat(2, minmax(0, 104px));
            justify-content:end;
            gap:7px;
        }
        [data-page="manage-engine"] .btn-premium{
            min-height:34px;
            border-radius:9px;
            padding:6px 9px;
            font-size:12px;
        }
        [data-page="manage-engine"] .bulk-bar{
            bottom:calc(10px + env(safe-area-inset-bottom));
            border-radius:14px;
            padding:6px;
        }
        [data-page="manage-engine"] .bulk-btn{
            font-size:13px;
            padding:8px 12px;
            border-radius:10px;
        }
    }
    </style>

</div>
@endsection
