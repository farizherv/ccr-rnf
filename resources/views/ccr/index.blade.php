@extends('layout')

@section('content')

{{-- ======================= HEADER ======================= --}}
<div class="header-card">
    <div class="header-left">

        {{-- LOGO FIX PROPORSIONAL --}}
        <div class="logo-wrapper">
            <img src="{{ asset('rnf-logo.png') }}" class="header-logo" width="110" height="110" alt="RNF Logo">
        </div>

        <div>
            <h1 class="header-title">COMPONENT CONDITION REPORT SYSTEM</h1>
            <p class="header-subtitle">PT. Rezeki Nadah Fathan</p>
        </div>

    </div>
</div>

<div class="accent-line"></div>

@php
    $draftUserId = auth()->check() ? (int) auth()->id() : 0;
    $draftUserPrefix = $draftUserId ? ('u' . $draftUserId . '_') : 'guest_';
    $draftUserToken = $draftUserId ? (string) $draftUserId : 'guest';

    $engineCreateUrl = route('engine.create');
    $engineManageUrl = route('ccr.manage.engine');
    $seatCreateUrl = route('seat.create');
    $seatManageUrl = route('ccr.manage.seat');

    $engineCreateHash = md5($engineCreateUrl);
    $seatCreateHash = md5($seatCreateUrl);

    $engineDraftLocalKeys = [
        'ccr_parts_ws_' . $draftUserPrefix . 'create_' . $engineCreateHash,
        'ccr_detail_ws_' . $draftUserPrefix . 'create_' . $engineCreateHash,
    ];
    $engineDraftLocalPrefixes = [
        'ccr_parts_ws_' . $draftUserPrefix . 'create_',
        'ccr_detail_ws_' . $draftUserPrefix . 'create_',
    ];

    $enginePhotoDraftKey = 'engine:create:ccr:u:' . $draftUserToken;
    $seatCcrDraftKey = 'seat:create:ccr:u:' . $draftUserToken;
    $seatDraftLocalKeys = [
        $seatCcrDraftKey,
        'ccr_parts_ws_' . $draftUserPrefix . 'create_' . $seatCreateHash,
        'ccr_seat_detail_ws_' . $draftUserPrefix . 'create_' . $seatCreateHash,
        'ccr_seat_items_ws_' . $draftUserPrefix . 'create_' . $seatCreateHash,
    ];
    $seatDraftLocalPrefixes = [
        'ccr_parts_ws_' . $draftUserPrefix . 'create_',
        'ccr_seat_detail_ws_' . $draftUserPrefix . 'create_',
        'ccr_seat_items_ws_' . $draftUserPrefix . 'create_',
    ];
    $engineDraftClientKeyStorage = 'ccr:create:server-key:u:' . $draftUserToken . ':type:engine';
    $seatDraftClientKeyStorage = 'ccr:create:server-key:u:' . $draftUserToken . ':type:seat';
    $draftListUrl = route('ccr.drafts.index');
    $draftDeleteUrlTpl = route('ccr.drafts.destroy', ['id' => '__DRAFT_ID__']);
    $clearCreateDraft = session('clear_create_draft');
@endphp

{{-- ======================= MENU UTAMA (EDIT + TRASH) ======================= --}}
<div class="home-menu-grid">

    {{-- EDIT CCR ENGINE --}}
    <a href="{{ route('ccr.manage.engine') }}" class="home-menu-card home-menu-card--edit">
        <span class="home-menu-arrow" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                <path d="M5 12h14"></path>
                <path d="m13 6 6 6-6 6"></path>
            </svg>
        </span>
        <div class="home-menu-icon">🛠️</div>
        <h2>EDIT CCR ENGINE</h2>
        <p>Lihat & ubah semua data laporan CCR Engine.</p>
    </a>

    {{-- EDIT CCR OPERATOR SEAT --}}
    <a href="{{ route('ccr.manage.seat') }}" class="home-menu-card home-menu-card--edit">
        <span class="home-menu-arrow" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                <path d="M5 12h14"></path>
                <path d="m13 6 6 6-6 6"></path>
            </svg>
        </span>
        <div class="home-menu-icon">💺</div>
        <h2>EDIT CCR OPERATOR SEAT</h2>
        <p>Lihat & ubah semua data laporan CCR Operator Seat.</p>
    </a>

    {{-- TRASH & RESTORE --}}
    <a href="{{ route('trash.menu') }}" class="home-menu-card home-menu-card--trash">
        <span class="home-menu-arrow" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                <path d="M5 12h14"></path>
                <path d="m13 6 6 6-6 6"></path>
            </svg>
        </span>
        <div class="home-menu-icon">🗑️</div>
        <h2>TRASH & RESTORE</h2>
        <p>Lihat data yang dihapus, restore, atau hapus permanen.</p>
    </a>

</div>

<div id="draft-choice-modal" class="draft-modal" aria-hidden="true">
    <div class="draft-modal__backdrop" data-draft-close></div>
    <div class="draft-modal__panel" role="dialog" aria-modal="true" aria-labelledby="draft-modal-title">
        <h3 id="draft-modal-title" class="draft-modal__title">Draft Ditemukan</h3>
        <p class="draft-modal__desc" id="draft-modal-desc"></p>
        <div class="draft-modal__list" id="draft-modal-list"></div>
        <div class="draft-modal__empty" id="draft-modal-empty" style="display:none;">
            Belum ada draft.
        </div>

        <div class="draft-modal__actions">
            <button type="button" id="draft-new-btn" class="draft-btn draft-btn--primary">Buat Baru</button>
            <button type="button" id="draft-cancel-btn" class="draft-btn draft-btn--ghost">Batal</button>
        </div>
    </div>
</div>

<script>
(() => {
    const DRAFT_MAX_AGE_MS = 1000 * 60 * 60 * 24 * 45;

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
        seat: {
            label: 'CCR OPERATOR SEAT',
            localKeys: @json($seatDraftLocalKeys),
            localPrefixes: @json($seatDraftLocalPrefixes),
            detectKeys: @json([$seatCcrDraftKey]),
            detectPrefixes: [],
            url: @json($seatCreateUrl),
            clientKeyStorage: @json($seatDraftClientKeyStorage),
            indexed: {
                dbName: 'seatCreateDraftDb',
                storeName: 'seatCreatePhotoDrafts',
                key: @json($seatCcrDraftKey),
            },
        },
    };
    const draftApi = {
        listUrl: @json($draftListUrl),
        deleteUrlTpl: @json($draftDeleteUrlTpl),
        csrf: @json(csrf_token()),
    };
    const clearCreateDraft = @json($clearCreateDraft);

    const modal = document.getElementById('draft-choice-modal');
    if (!modal) return;

    const titleEl = document.getElementById('draft-modal-title');
    const descEl = document.getElementById('draft-modal-desc');
    const listEl = document.getElementById('draft-modal-list');
    const emptyEl = document.getElementById('draft-modal-empty');
    const btnNew = document.getElementById('draft-new-btn');
    const btnCancel = document.getElementById('draft-cancel-btn');

    const state = {
        kind: null,
        url: '',
        entries: [],
    };

    function formatDateTime(ts) {
        if (!ts || Number.isNaN(Number(ts))) return '-';
        const d = new Date(Number(ts));
        if (Number.isNaN(d.getTime())) return '-';
        return d.toLocaleString('id-ID', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
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

    function localDraftLabel(kind) {
        return kind === 'engine' ? 'Draft Lokal Engine' : 'Draft Lokal Seat';
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
            openBtn.dataset.index = String(index);

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
        try {
            raw = localStorage.getItem(key);
        } catch (e) {
            return null;
        }
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

        return {
            key,
            ts: ts > 0 ? ts : 0,
            payload: parsedPayload,
        };
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
            if (!latestHit || Number(hit.ts || 0) > Number(latestHit.ts || 0)) {
                latestHit = hit;
            }
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
                    name: String(row.draft_name || '').trim() || (kind === 'engine' ? 'ENGINE Draft' : 'SEAT Draft'),
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

        const normalizeName = (name) => String(name || '').trim().toLowerCase();

        // Dedupe visual untuk server entries lama yang terduplikasi karena bug sebelumnya.
        const dedupedServer = [];
        const serverSeen = new Set();
        for (const entry of unique) {
            if (entry.source !== 'server') {
                dedupedServer.push(entry);
                continue;
            }
            const secBucket = Math.floor(Number(entry.ts || 0) / 1000);
            const fp = normalizeName(entry.name) + '|' + secBucket;
            if (serverSeen.has(fp)) continue;
            serverSeen.add(fp);
            dedupedServer.push(entry);
        }

        // Kompres duplikat server yang nama sama dan timestamp berdekatan.
        const compactedServer = [];
        for (const entry of dedupedServer) {
            if (entry.source !== 'server') {
                compactedServer.push(entry);
                continue;
            }
            const sameBucketIdx = compactedServer.findIndex((row) => {
                if (row.source !== 'server') return false;
                if (normalizeName(row.name) !== normalizeName(entry.name)) return false;
                return Math.abs(Number(row.ts || 0) - Number(entry.ts || 0)) <= (1000 * 60 * 10);
            });
            if (sameBucketIdx === -1) {
                compactedServer.push(entry);
            }
        }

        // Jika ada server draft yang sama, jangan tampilkan local draft duplikat.
        const serverEntriesOnly = compactedServer.filter((e) => e.source === 'server');
        const finalRows = compactedServer.filter((entry) => {
            if (entry.source !== 'local') return true;
            const localName = normalizeName(entry.name);
            const localTs = Number(entry.ts || 0);
            return !serverEntriesOnly.some((srv) => {
                const sameName = normalizeName(srv.name) === localName;
                const closeTs = Math.abs(Number(srv.ts || 0) - localTs) <= (1000 * 60 * 2);
                return sameName && closeTs;
            });
        });

        return finalRows;
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
        if (opts && opts.server) {
            await clearServerDrafts(kind);
        }
    }

    if (typeof clearCreateDraft === 'string' && clearCreateDraft && config[clearCreateDraft]) {
        clearDraft(clearCreateDraft, { server: true }).catch(() => {});
    }

    function openDraftEntry(index) {
        const entry = state.entries[index];
        if (!entry) return;

        if (entry.source === 'server') {
            const url = state.url + '?draft=' + encodeURIComponent(String(entry.id || ''));
            window.location.href = url;
            return;
        }

        if (entry.source === 'local') {
            window.location.href = state.url;
        }
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
        }

        state.entries.splice(index, 1);
        renderDraftEntries();
    }

    async function onCardClick(event) {
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

    document.querySelectorAll('.js-draft-entry').forEach((el) => {
        el.addEventListener('click', onCardClick);
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



{{-- ======================= STYLE FINAL (LOGO FIX + UI RAPIH) ======================= --}}
<style>
    .home-menu-grid,
    .home-menu-grid *,
    .header-card,
    .header-card * {
        box-sizing: border-box;
    }

    /* HEADER */
    .header-card {
        background: white;
        padding: 22px;
        border-radius: 14px;
        margin-bottom: 20px;
        box-shadow: 0 3px 10px rgba(0,0,0,0.07);
    }

    .header-left {
        display: flex;
        align-items: center;
        gap: 18px;
    }

    .logo-wrapper {
        padding: 0;
    }

    /* LOGO FINAL — tidak gepeng + proporsional */
    .header-logo {
        width: 115px;
        height: auto;
        object-fit: contain;
        display: block;
    }

    .header-title {
        font-size: 20px;
        font-weight: 800;
        margin: 0;
    }

    .header-subtitle {
        margin: 0;
        margin-top: 4px;
        font-size: 14px;
        color: #555;
    }

    .accent-line {
        width: 100%;
        height: 4px;
        background: #E40505;
        border-radius: 20px;
        margin-bottom: 22px;
    }


    /* ======================= HOME MENU CARDS ======================= */
    .home-menu-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 22px;
        align-items: start;
    }

    .home-menu-card {
        position: relative;
        display: flex;
        flex-direction: column;
        justify-content: flex-start;
        width: auto;
        max-width: 100%;
        min-height: 0;
        height: auto;
        padding: 22px 58px 22px 22px;
        border-radius: 18px;
        text-decoration: none;
        color: #0f172a;
        border: 1px solid #d9e1ee;
        background: linear-gradient(180deg, #ffffff 0%, #fbfcff 100%);
        box-shadow: 0 8px 18px rgba(15, 23, 42, 0.06);
        transition: transform .2s ease, border-color .2s ease, box-shadow .2s ease, background-color .2s ease;
        overflow: hidden;
    }

    .home-menu-card:hover {
        transform: translateY(-2px);
        border-color: rgba(228, 5, 5, 0.30);
        box-shadow: 0 12px 24px rgba(15, 23, 42, 0.12);
        background: #ffffff;
    }

    .home-menu-icon {
        width: 56px;
        height: 56px;
        display: grid;
        place-items: center;
        border-radius: 14px;
        border: 1px solid #d8e2f1;
        background: #eef3fb;
        font-size: 31px;
        margin-bottom: 14px;
        flex: 0 0 auto;
    }

    .home-menu-arrow {
        position: absolute;
        right: 16px;
        top: 50%;
        width: 34px;
        height: 34px;
        border-radius: 999px;
        border: 1px solid #d8e2f1;
        background: #f8fafd;
        color: #8d9ab0;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        opacity: 0;
        transform: translate(6px, -50%);
        transition: opacity .2s ease, transform .2s ease, border-color .2s ease, color .2s ease, background-color .2s ease;
    }

    .home-menu-arrow svg {
        width: 16px;
        height: 16px;
    }

    .home-menu-card:hover .home-menu-arrow {
        opacity: 1;
        transform: translate(0, -50%);
        color: #111827;
        border-color: rgba(228, 5, 5, 0.32);
        background: #fff;
    }

    .home-menu-card--edit .home-menu-icon {
        background: #eef4ff;
        border-color: #d8e5ff;
    }

    .home-menu-card--trash .home-menu-icon {
        background: #f3f4f6;
        border-color: #e2e8f0;
    }

    .home-menu-card h2 {
        margin: 0 0 8px;
        font-size: 20px;
        font-size: clamp(18px, 1.2vw, 22px);
        font-weight: 800;
        letter-spacing: .2px;
        line-height: 1.2;
        color: #060b18;
        max-width: calc(100% - 30px);
    }

    .home-menu-card p {
        margin: 0;
        color: #4b5563;
        font-size: 14px;
        font-size: clamp(13px, 0.85vw, 15px);
        line-height: 1.45;
        max-width: calc(100% - 30px);
    }

    /* DESKTOP KECIL / TABLET LANDSCAPE */
    @media(max-width:1280px){
        .home-menu-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    /* TABLET & MOBILE */
    @media(max-width:900px){
        .home-menu-grid {
            grid-template-columns: 1fr;
            gap: 18px;
        }
        .home-menu-card {
            min-height: 0;
            padding: 20px 52px 20px 20px;
        }
        .home-menu-icon {
            width: 50px;
            height: 50px;
            font-size: 28px;
        }
        .home-menu-card h2 {
            font-size: 18px;
            max-width: 100%;
        }
        .home-menu-card p {
            font-size: 13px;
            max-width: 100%;
        }
        .home-menu-arrow {
            right: 14px;
            width: 30px;
            height: 30px;
            opacity: .82;
            transform: translate(0, -50%);
        }
        .header-left{
            flex-direction: column;
            align-items: flex-start;
            gap: 12px;
        }
        .header-logo {
            width: 90px;
            height: auto;
        }
        .header-title { font-size: 18px; }
        .header-subtitle { font-size: 13px; }
    }

    @media (hover: none), (max-width: 900px) {
        .home-menu-card {
            padding-right: 20px;
        }
        .home-menu-arrow {
            display: none;
        }
    }

    .draft-modal{
        position:fixed;
        inset:0;
        z-index:1200;
        display:none;
    }
    .draft-modal.is-open{
        display:block;
    }
    .draft-modal__backdrop{
        position:absolute;
        inset:0;
        background:rgba(2,6,23,.58);
    }
    .draft-modal__panel{
        position:relative;
        width:min(520px, calc(100vw - 28px));
        margin:10vh auto 0;
        background:#fff;
        border-radius:16px;
        border:1px solid #dbe2ea;
        box-shadow:0 24px 60px rgba(2,6,23,.25);
        padding:18px 18px 16px;
    }
    .draft-modal__title{
        margin:0;
        font-size:22px;
        font-weight:900;
        color:#0f172a;
    }
    .draft-modal__desc{
        margin:8px 0 0;
        font-size:14px;
        line-height:1.45;
        color:#475569;
    }
    .draft-modal__list{
        margin-top:12px;
        max-height:320px;
        overflow:auto;
        display:grid;
        gap:8px;
    }
    .draft-modal__empty{
        margin-top:12px;
        border:1px dashed #cbd5e1;
        background:#f8fafc;
        color:#475569;
        border-radius:10px;
        padding:10px 12px;
        font-size:13px;
        font-weight:800;
    }
    .draft-row{
        border:1px solid #dbe2ea;
        background:#fff;
        border-radius:12px;
        padding:8px;
        display:flex;
        gap:8px;
        align-items:stretch;
    }
    .draft-row__open{
        flex:1;
        border:1px solid #dbe2ea;
        border-radius:10px;
        background:#f8fafc;
        padding:10px 12px;
        text-align:left;
        cursor:pointer;
    }
    .draft-row__open:hover{
        border-color:#93c5fd;
        background:#eff6ff;
    }
    .draft-row__name{
        font-size:14px;
        font-weight:900;
        color:#0f172a;
    }
    .draft-row__meta{
        margin-top:4px;
        font-size:12px;
        font-weight:700;
        color:#475569;
    }
    .draft-row__delete{
        border:1px solid #fecaca;
        background:#fff1f2;
        color:#b91c1c;
        border-radius:10px;
        min-width:84px;
        font-size:12px;
        font-weight:900;
        cursor:pointer;
    }
    .draft-row__delete:hover{
        background:#fee2e2;
    }
    .draft-modal__actions{
        margin-top:16px;
        display:flex;
        gap:10px;
        flex-wrap:wrap;
        justify-content:flex-end;
    }
    .draft-btn{
        border:1px solid #d1d5db;
        border-radius:10px;
        padding:10px 14px;
        font-size:14px;
        font-weight:800;
        cursor:pointer;
        background:#fff;
        color:#0f172a;
        min-width:120px;
    }
    .draft-btn:disabled{
        opacity:.65;
        cursor:not-allowed;
    }
    .draft-btn--primary{
        background:#2563eb;
        border-color:#2563eb;
        color:#fff;
    }
    .draft-btn--ghost{
        background:#fff;
    }

</style>

@endsection
