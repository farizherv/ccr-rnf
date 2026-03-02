{{-- =========================================================
TAB: ITEMS MASTER (SEAT)
File: resources/views/seat/partials/items_worksheet.blade.php
- Master global seat (DB: items_master) + snapshot per report
- User bisa CRUD tanpa edit kode
========================================================= --}}

@php
  $reportObj = $report ?? null;
  $reportId  = $reportObj?->id;
  $userId    = auth()->check() ? (int) auth()->id() : 0;

  $seedRows = (isset($seatItemsRows) && is_array($seatItemsRows)) ? $seatItemsRows : [];

  $oldSeatItemsPayload = old('seat_items_payload');
  if (is_string($oldSeatItemsPayload) && trim($oldSeatItemsPayload) !== '') {
    $oldDecoded = json_decode($oldSeatItemsPayload, true);
    if (is_array($oldDecoded)) $seedRows = $oldDecoded;
  }
  $draftSeedItemsPayload = (isset($draftSeedItemsPayload) && is_array($draftSeedItemsPayload))
    ? $draftSeedItemsPayload
    : [];
  $skipLocalDraftLoad = isset($skipLocalDraftLoad) ? (bool) $skipLocalDraftLoad : false;
  if (!(is_string($oldSeatItemsPayload) && trim($oldSeatItemsPayload) !== '') && !empty($draftSeedItemsPayload)) {
    $seedRows = $draftSeedItemsPayload;
  }

  $storageKey = 'ccr_seat_items_ws_'
      . ($userId ? ('u'.$userId.'_') : 'guest_')
      . ($reportId ? ('r'.$reportId) : 'create')
      . '_' . md5(url()->current());

  $autosaveUrl = $reportId ? route('seat.worksheet.autosave', ['id' => $reportId]) : null;
  $masterSyncUrl = \Illuminate\Support\Facades\Route::has('items_master.seat.sync')
      ? route('items_master.seat.sync')
      : null;
  $photoBaseUrl = rtrim(asset('storage'), '/');
  $maxFileUploads = max(1, (int) ini_get('max_file_uploads'));
@endphp

<div x-show="tab==='items'" x-cloak
     x-data="seatItemsWS({
      reportId: @js($reportId),
      autosaveUrl: @js($autosaveUrl),
      masterSyncUrl: @js($masterSyncUrl),
      csrf: @js(csrf_token()),
      storageKey: @js($storageKey),
      skipLocalDraftLoad: @js($skipLocalDraftLoad),
      photoBaseUrl: @js($photoBaseUrl),
      maxFileUploads: @js($maxFileUploads),
      initialRows: @js($seedRows),
     })"
     x-init="init()"
     @keydown.escape.window="closePreview()"
     class="box si-shell">

  <h3 class="si-title" style="margin-bottom:6px;">Items</h3>
  <p class="si-desc" style="font-size:13px; color:#64748b; margin-bottom:14px;">
    Data master Parts &amp; Labour Worksheet (No, Category, PN, Items, Purchase Price, Sales Price, Photos).
  </p>

  <div class="si-topbar">
    <div class="si-topbar__left">
      <div class="si-status-slot">
        <span class="si-badge" x-text="saveStatus"></span>
      </div>
      <span class="si-small" x-text="autoSaveOn ? 'AutoSave ON' : 'AutoSave OFF'"></span>

      <span class="si-divider">|</span>

      <button type="button" class="si-btn si-btn--primary" @click="addRow()">+ Tambah Baris</button>
      <button type="button" class="si-btn si-btn--danger" @click="removeLastRow()" :disabled="!canRemoveLastRow()">Hapus Terakhir</button>
      <button type="button" class="si-btn" @click="deleteSelectedRows()" :disabled="selectedCount() === 0">Hapus Terpilih</button>
    </div>

    <div class="si-topbar__right">
      <span class="si-small">Current: <b x-text="rows.length"></b></span>
      <span class="si-divider">|</span>
      <span class="si-small">Cell: <b x-text="activeCellLabel()"></b></span>
      <span class="si-divider">|</span>
      <span class="si-small">Max 10 foto / item</span>
    </div>
  </div>

  <div class="si-wrap" @mousedown="clearSelectionIfOutside($event)">
    <table class="si-table">
      <thead>
        <tr>
          <th style="width:76px;">No</th>
          <th style="width:180px;">Category</th>
          <th style="width:220px;">PN</th>
          <th style="min-width:320px;">Items</th>
          <th style="width:170px;">Purchase Price</th>
          <th style="width:170px;">Sales Price</th>
          <th style="min-width:320px;">Photos</th>
        </tr>
      </thead>

      <tbody>
        <template x-for="(r, i) in rows" :key="r.uid">
          <tr :class="r.__selected ? 'is-selected' : ''">
            <td>
              <div class="si-no-cell">
                <button type="button" class="si-no-pick"
                        :class="r.__selected ? 'is-selected' : ''"
                        @focus="setActive(i, 0)"
                        @click="toggleRowSelection(i)"
                        x-text="i + 1"></button>
              </div>
            </td>

            <td>
              <input type="text" class="si-inp"
                     x-model="r.category"
                     @focus="setActive(i, 1)"
                     @input="r.category = cleanText(r.category); markDirty()"
                     placeholder="Category">
            </td>

            <td>
              <input type="text" class="si-inp"
                     x-model="r.pn"
                     @focus="setActive(i, 2)"
                     @input="r.pn = cleanText(r.pn); markDirty()"
                     placeholder="Part Number">
            </td>

            <td>
              <input type="text" class="si-inp"
                     x-model="r.item"
                     @focus="setActive(i, 3)"
                     @input="r.item = cleanText(r.item); markDirty()"
                     placeholder="Items name">
            </td>

            <td>
              <div class="si-money">
                <span class="si-rp">Rp</span>
                <input type="text" class="si-inp si-inp--money"
                       :value="formatDots(r.purchase_price)"
                       @focus="setActive(i, 4)"
                       @input="setMoney(i, 'purchase_price', $event.target.value, $event)"
                       inputmode="numeric"
                       placeholder="0">
              </div>
            </td>

            <td>
              <div class="si-money">
                <span class="si-rp">Rp</span>
                <input type="text" class="si-inp si-inp--money"
                       :value="formatDots(r.sales_price)"
                       @focus="setActive(i, 5)"
                       @input="setMoney(i, 'sales_price', $event.target.value, $event)"
                       inputmode="numeric"
                       placeholder="0">
              </div>
            </td>

            <td>
              <div class="si-photos-cell">
                <div class="si-photos-toolbar">
                  <button type="button" class="si-btn si-btn--small" @click="openFilePicker(r.uid)">+ Tambah Foto</button>
                </div>

                <input type="file"
                       class="si-hidden"
                       accept="image/*"
                       multiple
                       :id="fileInputId(r.uid)"
                       :name="'seat_item_photos[' + r.uid + '][]'"
                       @change="onFilesSelected($event, r.uid)">

                <div class="si-thumbs">
                  <template x-for="p in r.photos" :key="p.id">
                    <div class="si-thumb-wrap">
                      <button type="button" class="si-thumb" @click.stop="openPreview(p.url, r.item || r.pn || 'Photo')">
                        <img :src="p.url" alt="Item photo">
                      </button>
                      <button type="button" class="si-thumb-x" @click.stop="removePhoto(r.uid, p.id)">×</button>
                    </div>
                  </template>
                </div>
              </div>
            </td>
          </tr>
        </template>
      </tbody>
    </table>
  </div>

  <input type="hidden" name="seat_items_payload" :value="jsonPayload()">

  <div class="si-modal" x-show="preview.open" x-transition x-cloak>
    <div class="si-modal__backdrop" @click="closePreview()"></div>
    <button type="button" class="si-modal__x" @click="closePreview()">×</button>
    <img class="si-modal__img" :src="preview.url" :alt="preview.title || 'Preview'">
  </div>
</div>

<style>
  .si-shell{position:relative;}
  .si-topbar{display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap;margin-bottom:12px;}
  .si-topbar__left{display:flex;align-items:center;gap:8px;flex-wrap:wrap;}
  .si-topbar__right{display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-left:auto;}
  .si-status-slot{width:260px;max-width:100%;display:flex;align-items:center;}
  .si-badge{
    width:100%;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    font-weight:900;
    font-size:12px;
    color:#0f172a;
    padding:7px 12px;
    border-radius:999px;
    background:#e2e8f0;
    white-space:nowrap;
    overflow:hidden;
    text-overflow:ellipsis;
  }
  .si-small{font-size:12px;color:#475569;font-weight:700;}
  .si-divider{color:#cbd5e1;font-weight:900;}

  .si-btn{border:1px solid #d1d5db;background:#fff;color:#0f172a;font-weight:800;font-size:12px;padding:8px 12px;border-radius:10px;cursor:pointer;}
  .si-btn:disabled{opacity:.45;cursor:not-allowed;}
  .si-btn:hover:not(:disabled){background:#f8fafc;}
  .si-btn--primary{border-color:#2563eb;background:#2563eb;color:#fff;}
  .si-btn--primary:hover:not(:disabled){background:#1d4ed8;}
  .si-btn--danger{border-color:#dc2626;background:#dc2626;color:#fff;}
  .si-btn--danger:hover:not(:disabled){background:#b91c1c;}
  .si-btn--small{padding:6px 10px;font-size:11px;border-radius:8px;}

  .si-wrap{border:1px solid #e2e8f0;border-radius:14px;overflow:auto;background:#fff;}
  .si-table{width:100%;min-width:1300px;border-collapse:separate;border-spacing:0;}
  .si-table thead th{position:sticky;top:0;z-index:1;background:#0b0f15;color:#fff;font-weight:900;font-size:12px;padding:10px 10px;border-right:1px solid #1f2937;border-bottom:1px solid #1f2937;white-space:nowrap;}
  .si-table thead th:last-child{border-right:none;}
  .si-table tbody td{border-right:1px solid #e5e7eb;border-bottom:1px solid #e5e7eb;padding:8px;background:#fff;vertical-align:top;}
  .si-table tbody td:last-child{border-right:none;}
  .si-table tbody tr.is-selected td{background:#eff6ff;}

  .si-inp{width:100%;height:36px;border:1px solid #dbe2ea;border-radius:10px;padding:0 10px;font-size:13px;color:#111827;background:#fff;}
  .si-inp:focus{outline:none;border-color:#2563eb;box-shadow:0 0 0 3px rgba(37,99,235,.15);}
  .si-inp--center{text-align:center;}
  .si-inp--money{text-align:right;padding-right:10px;padding-left:28px;}

  .si-no-cell{display:flex;align-items:center;justify-content:center;}
  .si-no-pick{width:34px;min-width:34px;height:34px;border:1px solid #dbe2ea;background:#fff;border-radius:9px;font-weight:900;font-size:12px;color:#1f2937;cursor:pointer;}
  .si-no-pick.is-selected{background:#2563eb;color:#fff;border-color:#2563eb;}

  .si-money{position:relative;}
  .si-rp{position:absolute;left:9px;top:50%;transform:translateY(-50%);font-size:11px;font-weight:900;color:#475569;pointer-events:none;}

  .si-hidden{display:none;}
  .si-photos-cell{display:flex;flex-direction:column;gap:8px;}
  .si-photos-toolbar{display:flex;align-items:center;justify-content:space-between;gap:8px;}
  .si-thumbs{display:flex;flex-wrap:wrap;gap:8px;}
  .si-thumb-wrap{position:relative;}
  .si-thumb{width:64px;height:64px;border:1px solid #dbe2ea;border-radius:8px;overflow:hidden;padding:0;background:#fff;cursor:pointer;}
  .si-thumb img{width:100%;height:100%;object-fit:cover;display:block;}
  .si-thumb-x{position:absolute;top:-6px;right:-6px;width:20px;height:20px;border:none;border-radius:999px;background:#dc2626;color:#fff;font-size:14px;line-height:20px;padding:0;cursor:pointer;font-weight:900;}

  .si-modal{position:fixed;inset:0;z-index:95000;display:flex;align-items:center;justify-content:center;padding:20px;}
  .si-modal__backdrop{position:absolute;inset:0;background:rgba(2,6,23,.65);}
  .si-modal__x{position:absolute;top:20px;right:20px;z-index:2;width:36px;height:36px;border:none;border-radius:999px;background:#0f172a;color:#fff;font-size:24px;line-height:36px;cursor:pointer;box-shadow:0 8px 20px rgba(0,0,0,.35);}
  .si-modal__img{position:relative;z-index:1;max-width:min(95vw, 1100px);max-height:90vh;object-fit:contain;border-radius:10px;box-shadow:0 20px 50px rgba(2,6,23,.45);background:transparent;}

  @media (max-width: 900px){
    .si-topbar__left{width:100%;}
    .si-status-slot{width:100%;}
    .si-topbar__right{width:100%;margin-left:0;}
  }
</style>

<script>
document.addEventListener('alpine:init', () => {
  Alpine.data('seatItemsWS', (cfg) => ({
    reportId: cfg.reportId || null,
    autosaveUrl: cfg.autosaveUrl || '',
    masterSyncUrl: cfg.masterSyncUrl || '',
    storageKey: cfg.storageKey || ('ccr_seat_items_ws_' + window.location.pathname),
    skipLocalDraftLoad: !!cfg.skipLocalDraftLoad,
    photoBaseUrl: cfg.photoBaseUrl || '',
    csrf: cfg.csrf || (document.querySelector('meta[name=csrf-token]')?.content || ''),
    maxFileUploads: Number.isFinite(Number(cfg.maxFileUploads)) ? Math.max(1, Number(cfg.maxFileUploads)) : 20,
    maxPhotosPerItem: 10,
    maxPhotoBytes: 8 * 1024 * 1024,
    maxSyncPayloadBytes: 8 * 1024 * 1024,
    maxDraftPayloadBytes: 3 * 1024 * 1024,

    rows: [],
    autoSaveOn: true,
    saveStatus: 'Auto-saved --:--:--',
    dirty: false,
    _saveTimer: null,
    _masterSyncTimer: null,
    _masterSyncSeq: 0,
    _dirtyWorkTimer: null,
    _masterSyncInFlight: false,
    _masterSyncQueued: false,
    _autosaveInFlight: false,
    _autosaveQueued: false,
    _lastMasterFingerprint: '',
    _lastReportFingerprint: '',

    active: { row: 0, col: 0 },

    preview: { open: false, url: '', title: '' },

    init() {
      const seed = Array.isArray(cfg.initialRows) ? cfg.initialRows : [];
      this.rows = seed.map((r, i) => this.normalizeRow(r, i));

      if (!this.reportId && this.rows.length === 0 && !this.skipLocalDraftLoad) {
        const draft = this.loadDraft();
        if (draft && Array.isArray(draft.rows) && draft.rows.length) {
          this.rows = draft.rows.map((r, i) => this.normalizeRow(r, i));
          this.saveStatus = draft.ts
            ? ('Auto-saved ' + this.formatTime(new Date(draft.ts)))
            : this.saveStatus;
        }
      }

      if (!this.rows.length) this.rows = [this.makeEmptyRow(0)];

      this.syncAllFileInputs();
      this.dispatchRowsChanged();

      window.addEventListener('beforeunload', () => this.saveDraftOnly());

      this._onForceSave = () => {
        this.flushPendingSave(true);
      };
      window.addEventListener('ccr:seat-force-save', this._onForceSave);

      this.bindClearOnSubmit();
    },

    uid() {
      return 'si_' + Date.now().toString(36) + '_' + Math.random().toString(36).slice(2, 8);
    },

    fileInputId(uid) {
      return 'si_file_' + uid;
    },

    activeCellLabel() {
      const cols = ['A','B','C','D','E','F','G'];
      const c = cols[this.active.col] || 'A';
      return c + String((this.active.row || 0) + 1);
    },

    setActive(row, col) {
      this.active = { row, col };
    },

    onlyDigits(v) {
      return String(v ?? '').replace(/[^\d]/g, '');
    },

    cleanText(v) {
      return String(v ?? '')
        .replace(/\u00A0/g, ' ')
        .replace(/[ \t]{2,}/g, ' ')
        .trim();
    },

    formatDots(v) {
      const d = this.onlyDigits(v);
      if (!d) return '';
      return d.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    },

    formatTime(dt) {
      const pad = (n) => String(n).padStart(2, '0');
      return pad(dt.getHours()) + ':' + pad(dt.getMinutes()) + ':' + pad(dt.getSeconds());
    },

    hashString(input) {
      const text = String(input ?? '');
      let hash = 2166136261;
      for (let i = 0; i < text.length; i++) {
        hash ^= text.charCodeAt(i);
        hash += (hash << 1) + (hash << 4) + (hash << 7) + (hash << 8) + (hash << 24);
      }
      return (hash >>> 0).toString(16);
    },

    jsonByteSize(data) {
      let json = '';
      try {
        json = JSON.stringify(data);
      } catch (e) {
        json = '[]';
      }
      if (window.TextEncoder) {
        return new TextEncoder().encode(json).length;
      }
      return json.length;
    },

    safeJsonStringify(data, fallback = '[]') {
      try {
        const text = JSON.stringify(data);
        return (typeof text === 'string') ? text : fallback;
      } catch (e) {
        return fallback;
      }
    },

    normalizePath(path) {
      let p = String(path || '').trim();
      if (!p) return '';

      if (/^https?:\/\//i.test(p)) return p;

      p = p.replace(/^\/+/, '');
      p = p.replace(/^storage\//, '');
      p = p.replace(/^public\//, '');

      if (p.includes('..')) return '';
      return p;
    },

    urlFromPath(path) {
      const p = this.normalizePath(path);
      if (!p) return '';
      if (/^https?:\/\//i.test(p)) return p;
      return (this.photoBaseUrl ? (this.photoBaseUrl + '/' + p) : p);
    },

    makePhotoFromPath(path, idx = 0) {
      const cleanPath = this.normalizePath(path);
      const url = this.urlFromPath(cleanPath);
      if (!url) return null;
      return {
        id: 'ph_' + Date.now().toString(36) + '_' + idx + '_' + Math.random().toString(36).slice(2, 6),
        kind: 'existing',
        path: cleanPath,
        url,
        file: null,
      };
    },

    makeEmptyRow(idx = 0) {
      return {
        uid: this.uid(),
        no: String(idx + 1),
        category: '',
        pn: '',
        item: '',
        purchase_price: '',
        sales_price: '',
        photos: [],
        __selected: false,
      };
    },

    normalizeRow(raw, idx = 0) {
      const r = (raw && typeof raw === 'object') ? raw : {};
      const uid = String(r.uid || r.__uid || '').trim() || this.uid();

      const photoPathsRaw =
        Array.isArray(r.photo_paths) ? r.photo_paths
        : (Array.isArray(r.photos) ? r.photos : []);

      const photos = [];
      photoPathsRaw.forEach((p, i) => {
        if (typeof p === 'string') {
          const ph = this.makePhotoFromPath(p, i);
          if (ph) photos.push(ph);
          return;
        }
        if (p && typeof p === 'object') {
          const ph = this.makePhotoFromPath(p.path || p.url || '', i);
          if (ph) photos.push(ph);
        }
      });

      return {
        uid,
        no: this.onlyDigits(r.no ?? String(idx + 1)),
        category: this.cleanText(r.category || ''),
        pn: this.cleanText(r.pn || ''),
        item: this.cleanText(r.item || r.items || ''),
        purchase_price: this.onlyDigits(r.purchase_price || ''),
        sales_price: this.onlyDigits(r.sales_price || ''),
        photos,
        __selected: false,
      };
    },

    selectedCount() {
      return this.rows.filter(r => !!r.__selected).length;
    },

    toggleRowSelection(index) {
      const r = this.rows[index];
      if (!r) return;
      r.__selected = !r.__selected;
    },

    clearSelectionIfOutside(e) {
      if (e.target.closest('.si-no-pick')) return;
      if (e.target.closest('.si-thumb-x')) return;
    },

    addRow() {
      this.rows.push(this.makeEmptyRow(this.rows.length));
      this.markDirty();
    },

    rowHasValue(row) {
      if (!row || typeof row !== 'object') return false;
      return !!(
        this.cleanText(row.category || '') ||
        this.cleanText(row.pn || '') ||
        this.cleanText(row.item || '') ||
        this.onlyDigits(row.purchase_price || '') ||
        this.onlyDigits(row.sales_price || '') ||
        ((row.photos || []).length > 0)
      );
    },

    canRemoveLastRow() {
      if (this.rows.length <= 1) return false;
      const last = this.rows[this.rows.length - 1];
      return !this.rowHasValue(last);
    },

    removeLastRow() {
      if (this.rows.length <= 1) return;
      if (!this.canRemoveLastRow()) {
        alert('Baris terakhir ada data. Hapus lewat tombol Hapus Terpilih.');
        return;
      }
      const r = this.rows[this.rows.length - 1];
      if (r) {
        r.photos.forEach(p => {
          if (p.kind === 'new' && p.url) {
            try { URL.revokeObjectURL(p.url); } catch (e) {}
          }
        });
      }
      this.rows.pop();
      this.markDirty();
    },

    deleteSelectedRows() {
      this.rows
        .filter(r => !!r.__selected)
        .forEach((r) => {
          (r.photos || []).forEach((p) => {
            if (p.kind === 'new' && p.url) {
              try { URL.revokeObjectURL(p.url); } catch (e) {}
            }
          });
        });

      const filtered = this.rows.filter(r => !r.__selected);
      if (!filtered.length) {
        this.rows = [this.makeEmptyRow(0)];
      } else {
        this.rows = filtered;
      }
      this.rows.forEach(r => { r.__selected = false; });
      this.markDirty();
    },

    setMoney(index, key, raw, ev = null) {
      const r = this.rows[index];
      if (!r) return;
      const digits = this.onlyDigits(raw);
      r[key] = digits;
      this.markDirty();

      const el = ev && ev.target ? ev.target : null;
      if (el) el.value = this.formatDots(digits);
    },

    openFilePicker(uid) {
      const el = document.getElementById(this.fileInputId(uid));
      if (el) el.click();
    },

    onFilesSelected(ev, uid) {
      const files = Array.from(ev.target?.files || []).filter(f => (f.type || '').startsWith('image/'));
      const row = this.rows.find(r => r.uid === uid);
      if (!row) return;

      const MAX = this.maxPhotosPerItem;
      for (const f of files) {
        if ((f.size || 0) > this.maxPhotoBytes) {
          alert('Ukuran foto maksimal 8 MB per file.');
          continue;
        }
        const pending = this.newUploadCount();
        if (pending >= this.maxFileUploads) {
          alert(
            'Jumlah foto baru menunggu upload sudah mencapai batas server (' + this.maxFileUploads + ').\\n' +
            'Tunggu autosave selesai dulu, lalu lanjut tambah foto.'
          );
          break;
        }
        if ((row.photos || []).length >= MAX) {
          alert('Maksimal ' + MAX + ' foto per item.');
          break;
        }
        row.photos.push({
          id: 'nph_' + Date.now().toString(36) + '_' + Math.random().toString(36).slice(2, 7),
          kind: 'new',
          path: '',
          url: URL.createObjectURL(f),
          file: f,
        });
      }

      this.syncFileInput(uid);
      this.markDirty();
    },

    syncFileInput(uid) {
      const row = this.rows.find(r => r.uid === uid);
      const input = document.getElementById(this.fileInputId(uid));
      if (!row || !input) return;

      const dt = new DataTransfer();
      (row.photos || []).forEach((p) => {
        if (p.kind === 'new' && p.file) dt.items.add(p.file);
      });
      input.files = dt.files;
    },

    syncAllFileInputs() {
      this.rows.forEach((r) => {
        const hasNew = (r.photos || []).some((p) => p.kind === 'new' && p.file);
        if (hasNew) {
          this.syncFileInput(r.uid);
        }
      });
    },

    pendingUploadSummary() {
      let count = 0;
      let bytes = 0;
      this.rows.forEach((r) => {
        (r.photos || []).forEach((p) => {
          if (p.kind === 'new' && p.file) {
            count += 1;
            bytes += Number(p.file.size || 0);
          }
        });
      });
      return { count, bytes };
    },

    newUploadCount() {
      return this.pendingUploadSummary().count;
    },

    hasPendingNewPhotos() {
      return this.newUploadCount() > 0;
    },

    removePhoto(uid, photoId) {
      const row = this.rows.find(r => r.uid === uid);
      if (!row) return;

      const idx = row.photos.findIndex(p => p.id === photoId);
      if (idx === -1) return;

      const removed = row.photos[idx];
      if (removed && removed.kind === 'new' && removed.url) {
        try { URL.revokeObjectURL(removed.url); } catch (e) {}
      }

      row.photos.splice(idx, 1);
      this.syncFileInput(uid);
      this.markDirty();
    },

    openPreview(url, title = '') {
      if (!url) return;
      this.preview.open = true;
      this.preview.url = url;
      this.preview.title = title || 'Photo';
    },

    closePreview() {
      this.preview.open = false;
      this.preview.url = '';
      this.preview.title = '';
    },

    rowsForSave() {
      const out = [];

      this.rows.forEach((r, i) => {
        const existingPaths = (r.photos || [])
          .filter(p => p.kind === 'existing' && p.path)
          .map(p => this.normalizePath(p.path))
          .filter(Boolean);

        const newCount = (r.photos || []).filter(p => p.kind === 'new' && p.file).length;

        const row = {
          uid: String(r.uid || '').trim() || this.uid(),
          no: String(i + 1),
          category: this.cleanText(r.category || ''),
          pn: this.cleanText(r.pn || ''),
          item: this.cleanText(r.item || ''),
          purchase_price: this.onlyDigits(r.purchase_price || ''),
          sales_price: this.onlyDigits(r.sales_price || ''),
          photo_paths: Array.from(new Set(existingPaths)),
          _has_new_photos: newCount > 0 ? '1' : '0',
        };

        const hasValue =
          row.category || row.pn || row.item || row.purchase_price || row.sales_price || row.photo_paths.length || newCount > 0;

        if (hasValue) out.push(row);
      });

      return out;
    },

    rowsForSync() {
      return this.rowsForSave().map((r, i) => ({
        uid: r.uid,
        no: r.no || String(i + 1),
        category: r.category || '',
        pn: r.pn || '',
        item: r.item || '',
        purchase_price: this.onlyDigits(r.purchase_price || ''),
        sales_price: this.onlyDigits(r.sales_price || ''),
      }));
    },

    jsonPayload() {
      return this.safeJsonStringify(this.rowsForSave(), '[]');
    },

    dispatchRowsChanged() {
      const rows = this.rowsForSync();
      window.__ccrSeatItemsMasterRows = rows;
      window.dispatchEvent(new CustomEvent('ccr:seatItemsMasterChanged', {
        detail: { rows, reportId: this.reportId || null, ts: Date.now() }
      }));
    },

    markDirty() {
      this.dirty = true;
      clearTimeout(this._dirtyWorkTimer);
      this._dirtyWorkTimer = null;
      this._dirtyWorkTimer = setTimeout(() => {
        this.dispatchRowsChanged();
        this.saveDraftOnly();
      }, 140);

      if (this.reportId) {
        this.saveStatus = 'Auto-saved (Report) ' + this.formatTime(new Date());
      } else {
        this.saveStatus = 'AutoSave ON';
      }
      this.scheduleMasterSync();
      if (!this.hasPendingNewPhotos()) {
        this.scheduleReportAutosave();
      }
    },

    flushPendingSave(force = false) {
      clearTimeout(this._masterSyncTimer);
      this._masterSyncTimer = null;
      clearTimeout(this._saveTimer);
      this._saveTimer = null;
      clearTimeout(this._dirtyWorkTimer);
      this._dirtyWorkTimer = null;

      if (this.dirty) {
        this.dispatchRowsChanged();
        this.saveDraftOnly();
        if (force && this.masterSyncUrl) {
          this.syncMasterRemote();
        }
        if (force && this.reportId) {
          this.autosaveRemote(true);
        }
      }
    },

    bindClearOnSubmit() {
      const form = this.$el.closest('form');
      if (!form || form.__seatItemsWsBound) return;
      form.__seatItemsWsBound = true;

      form.addEventListener('submit', () => {
        this.flushPendingSave(true);
        try { localStorage.removeItem(this.storageKey); } catch (e) {}
      });
    },

    scheduleMasterSync(delay = 1300) {
      if (!this.autoSaveOn || !this.masterSyncUrl) return;
      if (this._masterSyncInFlight) {
        this._masterSyncQueued = true;
        return;
      }
      clearTimeout(this._masterSyncTimer);
      this._masterSyncTimer = null;
      this._masterSyncTimer = setTimeout(() => {
        this._masterSyncTimer = null;
        this.syncMasterRemote();
      }, delay);
    },

    scheduleReportAutosave(delay = 1700, force = false) {
      if (!this.autoSaveOn || !this.reportId || !this.autosaveUrl) return;
      if (this._autosaveInFlight) {
        this._autosaveQueued = true;
        return;
      }
      clearTimeout(this._saveTimer);
      this._saveTimer = null;
      this._saveTimer = setTimeout(() => {
        this._saveTimer = null;
        this.autosaveRemote(force);
      }, delay);
    },

    appendUploadFiles(formData) {
      let appended = 0;
      this.rows.forEach((r) => {
        (r.photos || []).forEach((p) => {
          if (p.kind === 'new' && p.file) {
            formData.append(`photos[${r.uid}][]`, p.file);
            appended += 1;
          }
        });
      });
      return appended;
    },

    revokeNewObjectUrls() {
      this.rows.forEach((r) => {
        (r.photos || []).forEach((p) => {
          if (p.kind === 'new' && p.url) {
            try { URL.revokeObjectURL(p.url); } catch (e) {}
          }
        });
      });
    },

    replaceRowsFromServer(serverRows) {
      const prevCount = Array.isArray(this.rows) ? this.rows.length : 0;
      this.revokeNewObjectUrls();

      const normalized = (Array.isArray(serverRows) ? serverRows : [])
        .map((r, i) => this.normalizeRow(r, i));

      this.rows = normalized;

      // Pertahankan jumlah baris UI yang sedang dipakai user.
      // Master DB memang hanya simpan row berisi data, jadi row kosong perlu dipad ulang di client.
      const targetCount = Math.max(1, prevCount, this.rows.length);
      while (this.rows.length < targetCount) {
        this.rows.push(this.makeEmptyRow(this.rows.length));
      }

      if (!this.rows.length) this.rows = [this.makeEmptyRow(0)];
      this.syncAllFileInputs();
      this.dispatchRowsChanged();
      this.saveDraftOnly();
    },

    hasPendingMasterWork() {
      return !!this._masterSyncTimer || this._masterSyncInFlight || this._masterSyncQueued;
    },

    async fetchWithTimeout(url, options = {}, timeoutMs = 25000) {
      if (!window.AbortController) {
        return fetch(url, options);
      }

      const ctrl = new AbortController();
      const timer = setTimeout(() => ctrl.abort(), timeoutMs);
      try {
        const merged = Object.assign({}, options, { signal: ctrl.signal });
        return await fetch(url, merged);
      } finally {
        clearTimeout(timer);
      }
    },

    async syncMasterRemote() {
      if (!this.masterSyncUrl || !this.dirty) return;
      if (this._masterSyncInFlight) {
        this._masterSyncQueued = true;
        return;
      }

      const rowsPayload = this.rowsForSave();
      const rowsBytes = this.jsonByteSize(rowsPayload);
      if (rowsBytes > this.maxSyncPayloadBytes) {
        this.saveStatus = 'Payload items terlalu besar';
        return;
      }

      const uploadInfo = this.pendingUploadSummary();
      if (uploadInfo.count > this.maxFileUploads) {
        this.saveStatus = 'Foto menunggu upload melebihi limit server';
        return;
      }

      const payloadJson = this.safeJsonStringify(rowsPayload, '[]');
      const fingerprint = this.hashString(payloadJson);
      const seq = ++this._masterSyncSeq;
      this._masterSyncInFlight = true;
      this._masterSyncQueued = false;

      try {
        const fd = new FormData();
        fd.append('rows_json', payloadJson);
        fd.append('full_sync', '1');
        const appendedCount = this.appendUploadFiles(fd);
        fd.append('expected_upload_count', String(appendedCount));

        const res = await this.fetchWithTimeout(this.masterSyncUrl, {
          method: 'POST',
          headers: {
            'Accept': 'application/json',
            'X-CSRF-TOKEN': this.csrf,
          },
          body: fd,
        }, 35000);

        if (seq !== this._masterSyncSeq) return;

        const json = await res.json().catch(() => ({}));
        if (!res.ok) {
          throw new Error((json && json.message) ? json.message : ('master sync http ' + res.status));
        }
        if (!json || json.ok === false) {
          throw new Error((json && json.message) ? json.message : 'master sync gagal');
        }

        this.replaceRowsFromServer(json.rows || []);
        this.dirty = false;
        this._lastMasterFingerprint = fingerprint;
        this.saveStatus = 'Auto-saved master ' + this.formatTime(new Date());

        // Simpan snapshot report (jaga kompatibilitas report lama).
        if (this.reportId && this.autosaveUrl) {
          this.scheduleReportAutosave(220, true);
        }
      } catch (e) {
        console.warn('seat items master sync failed', e);
        if (e && e.name === 'AbortError') {
          this.saveStatus = 'AutoSave timeout (Master)';
        } else {
          this.saveStatus = 'AutoSave failed (Master)';
        }
      } finally {
        this._masterSyncInFlight = false;
        if (this._masterSyncQueued || this.dirty) {
          this._masterSyncQueued = false;
          this.scheduleMasterSync(260);
        }
      }
    },

    saveDraftOnly() {
      try {
        const rows = this.rowsForSave();
        const bytes = this.jsonByteSize(rows);
        if (bytes > this.maxDraftPayloadBytes) {
          return;
        }

        const payload = {
          rows,
          ts: Date.now(),
        };
        localStorage.setItem(this.storageKey, JSON.stringify(payload));
        this.emitCreateDraft(payload);

        if (!this.reportId) {
          this.saveStatus = 'Auto-saved ' + this.formatTime(new Date(payload.ts));
        }
      } catch (e) {
        console.warn('seat items draft save failed', e);
      }
    },

    emitCreateDraft(payload = null) {
      if (this.reportId) return;
      const submitMap = (window.__ccrCreateSubmitInProgress && typeof window.__ccrCreateSubmitInProgress === 'object')
        ? window.__ccrCreateSubmitInProgress
        : {};
      if (submitMap.seat) return;
      const p = payload && typeof payload === 'object'
        ? payload
        : { rows: this.rowsForSave(), ts: Date.now() };
      try {
        window.dispatchEvent(new CustomEvent('ccr:create-draft-section', {
          detail: {
            type: 'seat',
            section: 'items',
            payload: p,
          },
        }));
      } catch (e) {}
    },

    loadDraft() {
      try {
        const raw = localStorage.getItem(this.storageKey);
        if (!raw) return null;
        const obj = JSON.parse(raw);
        return (obj && typeof obj === 'object') ? obj : null;
      } catch (e) {
        return null;
      }
    },

    async autosaveRemote(force = false) {
      if (!this.reportId || !this.autosaveUrl) return;
      if (!force && !this.dirty) return;
      if (!force && this.hasPendingNewPhotos()) return;
      if (this._autosaveInFlight) {
        this._autosaveQueued = true;
        return;
      }

      const rowsPayload = this.rowsForSave();
      const rowsBytes = this.jsonByteSize(rowsPayload);
      if (rowsBytes > this.maxSyncPayloadBytes) {
        this.saveStatus = 'Payload report terlalu besar';
        return;
      }

      const rowsFingerprint = this.hashString(this.safeJsonStringify(rowsPayload, '[]'));
      if (!force && this._lastReportFingerprint === rowsFingerprint) {
        return;
      }

      this._autosaveInFlight = true;
      this._autosaveQueued = false;

      try {
        const partsRev = this.currentPartsPayloadRev();
        const payloadTs = Date.now();
        const res = await this.fetchWithTimeout(this.autosaveUrl, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': this.csrf,
          },
          body: JSON.stringify({
            parts_payload_rev: partsRev,
            seat_items_payload: {
              rows: rowsPayload,
              ts: payloadTs,
              parts_payload_rev: partsRev,
            },
          }),
        }, 20000);

        const json = await res.json().catch(() => ({}));
        if (!res.ok) {
          throw new Error((json && json.message) ? json.message : ('autosave http ' + res.status));
        }

        if (json && json.stale && json.stale.parts) {
          this.saveStatus = 'Data stale, refresh dulu';
          return;
        }

        if (json && typeof json === 'object' && Number.isFinite(Number(json.parts_payload_rev))) {
          this.updatePartsPayloadRev(Number(json.parts_payload_rev || 0));
        }

        if (!this.hasPendingMasterWork()) {
          this.dirty = false;
        }
        this._lastReportFingerprint = rowsFingerprint;
        this.saveStatus = 'Auto-saved (Report) ' + this.formatTime(new Date());
      } catch (e) {
        console.warn('seat items autosave failed', e);
        if (e && e.name === 'AbortError') {
          this.saveStatus = 'AutoSave timeout (Report)';
        } else {
          this.saveStatus = 'AutoSave failed (Report)';
        }
      } finally {
        this._autosaveInFlight = false;
        if (this._autosaveQueued || (!force && this.dirty && !this.hasPendingNewPhotos())) {
          this._autosaveQueued = false;
          this.scheduleReportAutosave(320, force);
        }
      }
    },

    currentPartsPayloadRev() {
      const scope = (this.$el && this.$el.closest('form')) ? this.$el.closest('form') : document;
      const input = scope ? scope.querySelector('input[name="parts_payload_rev"]') : null;
      const value = Number(input ? input.value : 0);
      if (!Number.isFinite(value) || value < 0) return 0;
      return Math.floor(value);
    },

    updatePartsPayloadRev(nextRev) {
      const scope = (this.$el && this.$el.closest('form')) ? this.$el.closest('form') : document;
      const input = scope ? scope.querySelector('input[name="parts_payload_rev"]') : null;
      if (!input) return;
      input.value = String(Math.max(0, Number(nextRev || 0)));
    },
  }));
});
</script>
