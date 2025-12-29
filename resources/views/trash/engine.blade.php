@extends('layout')
@section('content')

<div class="trash-page" data-page="trash-engine">

  <a href="{{ route('trash.menu') }}" class="btn-back">← Kembali ke MENU Trash & Restore</a>

  <div class="header-card">
    <div class="header-left">
      <img src="{{ asset('rnf-logo.png') }}" class="header-logo" alt="RNF Logo">
      <div>
        <h1 class="header-title">TRASH & RESTORE – ENGINE</h1>
        <p class="header-subtitle">Restore atau hapus permanen data CCR Engine.</p>
      </div>
    </div>
  </div>

  <div class="accent-line-dark"></div>

  {{-- ======================= FILTER BOX (LIVE) ======================= --}}
  <div class="box">
    <h3 style="margin-bottom:18px;">Daftar CCR Engine (Trash)</h3>

    <div class="filter-row">
      {{-- SEARCH --}}
      <div class="filter-group filter-large">
        <label for="trashSearch">Cari</label>
        <input id="trashSearch" type="text" class="input"
               placeholder="Cari component, customer, make, model, SN...">
      </div>

      {{-- FILTER CUSTOMER --}}
      <div class="filter-group filter-small">
        <label for="trashCustomer">Filter Customer</label>
        <select id="trashCustomer" class="input">
          <option value="">Semua customer</option>
          @foreach($customers as $c)
            <option value="{{ $c }}">{{ $c }}</option>
          @endforeach
        </select>
      </div>

      {{-- SORT --}}
      <div class="filter-group filter-small">
        <label for="trashSort">Sort By</label>
        <select id="trashSort" class="input">
          <option value="newest">Newest</option>
          <option value="oldest">Oldest</option>
        </select>
      </div>
    </div>
  </div>

  {{-- ======================= BULK + LIST (SATU SCOPE) ======================= --}}
  <div
      x-data="trashEngineBulk()"
      x-init="window.__trashEngineSyncSelectAll = () => syncSelectAll();"
  >

    {{-- ✅ BULK BAR: RESTORE + HAPUS PERMANEN --}}
    <div x-show="selectedReports.length > 0" x-cloak class="bulk-bar">

      {{-- BULK RESTORE --}}
      <form action="{{ route('trash.engine.restoreMultiple') }}" method="POST" class="bulk-form">
        @csrf
        <template x-for="id in selectedReports" :key="'re-'+id">
          <input type="hidden" name="ids[]" :value="id">
        </template>

        <button type="submit" class="btn-bulk bulk-restore">
          ♻️ Restore Terpilih (<span x-text="selectedReports.length"></span>)
        </button>
      </form>

      {{-- BULK FORCE DELETE --}}
      <form action="{{ route('trash.engine.forceMultiple') }}" method="POST" class="bulk-form"
            onsubmit="return confirm('Hapus permanen semua yang terpilih? Foto & item akan ikut terhapus.')">
        @csrf
        @method('DELETE')

        <template x-for="id in selectedReports" :key="'del-'+id">
          <input type="hidden" name="ids[]" :value="id">
        </template>

        <button type="submit" class="btn-bulk bulk-delete">
          🗑️ Hapus Permanen Terpilih (<span x-text="selectedReports.length"></span>)
        </button>
      </form>

    </div>

    {{-- ======================= LIST ======================= --}}
    <div class="box" id="trashList" x-ref="list">

      {{-- ✅ SELECT ALL --}}
      <div class="select-all-row">
        <input
          id="selectAllTrashEngine"
          type="checkbox"
          x-ref="selectAll"
          class="select-checkbox select-all-checkbox"
          @change="toggleSelectAll($event)"
        >
        <label for="selectAllTrashEngine" class="select-all-label">Select All</label>
      </div>

      <div class="divider"></div>

      {{-- Empty message (akan di-hide/show oleh JS) --}}
      <p id="trashEmpty" class="empty-text" style="display:none;">Trash Engine masih kosong.</p>

      @foreach($reports as $r)
        <div class="report-card"
             data-id="{{ $r->id }}"
             data-search="{{ strtolower(($r->component ?? '').' '.($r->customer ?? '').' '.($r->make ?? '').' '.($r->model ?? '').' '.($r->sn ?? '')) }}"
             data-customer="{{ $r->customer ?? '' }}"
             data-deleted="{{ optional($r->deleted_at)->format('Y-m-d H:i:s') }}">

          <div class="report-left">
            <input type="checkbox"
                   class="select-checkbox row-checkbox"
                   @change="toggleOne({{ $r->id }}, $event)">

            <div class="report-main">
              <div class="report-title"><strong>{{ $r->component ?? '-' }}</strong></div>
              <div class="report-meta">
                <span>Customer: <b>{{ $r->customer ?? '-' }}</b></span>
                <span>Make: <b>{{ $r->make ?? '-' }}</b></span>
                <span>Model: <b>{{ $r->model ?? '-' }}</b></span>
                <span>SN: <b>{{ $r->sn ?? '-' }}</b></span>
                <span>Dihapus: <b>{{ optional($r->deleted_at)->format('Y-m-d H:i') }}</b></span>
              </div>
            </div>
          </div>

          <div class="report-actions">
            <form action="{{ route('trash.engine.restore', $r->id) }}" method="POST">
              @csrf
              <button class="btn-premium restore-btn" type="submit">♻️ Restore</button>
            </form>

            <form action="{{ route('trash.engine.force', $r->id) }}" method="POST"
                  onsubmit="return confirm('Hapus permanen? Foto & item akan ikut terhapus.')">
              @csrf
              @method('DELETE')
              <button class="btn-premium delete-btn" type="submit">🗑️ Hapus Permanen</button>
            </form>
          </div>

        </div>
      @endforeach
    </div>

  </div>


  {{-- ======================= SCRIPT (FILTER + SORT + EMPTY + SYNC SELECTALL) ======================= --}}
  <script>
    document.addEventListener("DOMContentLoaded", () => {
      const page = document.querySelector('[data-page="trash-engine"]');
      if (!page) return;

      const searchInput = page.querySelector("#trashSearch");
      const customerSel = page.querySelector("#trashCustomer");
      const sortSel     = page.querySelector("#trashSort");
      const list        = page.querySelector("#trashList");
      const emptyMsg    = page.querySelector("#trashEmpty");

      const cards = () => Array.from(list.querySelectorAll(".report-card"));

      const toTime = (val) => {
        if (!val) return 0;
        const safe = String(val).trim().replace(" ", "T");
        const t = Date.parse(safe);
        return isNaN(t) ? 0 : t;
      };

      function updateEmptyState() {
        const visibleCount = cards().filter(c => c.style.display !== "none").length;
        emptyMsg.style.display = (visibleCount === 0) ? "block" : "none";
      }

      function applyFilters() {
        const q = (searchInput.value || "").toLowerCase().trim();
        const c = (customerSel.value || "").trim();

        cards().forEach(card => {
          const search = (card.dataset.search || "");
          const cust   = (card.dataset.customer || "").trim();

          let show = true;
          if (q && !search.includes(q)) show = false;
          if (c && cust !== c) show = false;

          card.style.display = show ? "flex" : "none";
        });

        updateEmptyState();

        // ✅ sync selectAll state setelah filter
        if (window.__trashEngineSyncSelectAll) window.__trashEngineSyncSelectAll();
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

        sorted.forEach(card => list.appendChild(card));
        applyFilters();
      }

      searchInput.addEventListener("input", applyFilters);
      customerSel.addEventListener("change", applyFilters);
      sortSel.addEventListener("change", applySort);

      // init
      applySort();
    });

    // ✅ Alpine bulk
    function trashEngineBulk(){
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


<style>
/* ===================== KHUSUS TRASH PAGE ===================== */
[x-cloak]{ display:none !important; }

.trash-page .btn-back{
  display:inline-block;color:white;padding:8px 18px;border-radius:8px;background:#5f656a;
  font-weight:600;font-size:14px;text-decoration:none;transition:.2s;
  box-shadow:0 3px 7px rgba(0,0,0,.15);margin-bottom:18px
}
.trash-page .btn-back:hover{background:#2b2d2f;transform:translateY(-2px)}

.trash-page .header-card{
  background:white;padding:22px;border-radius:14px;margin-bottom:20px;
  box-shadow:0 3px 10px rgba(0,0,0,.07)
}
.trash-page .header-left{display:flex;align-items:center;gap:18px}
.trash-page .header-logo{width:80px;height:80px;object-fit:contain}
.trash-page .header-title{font-size:20px;font-weight:800;margin:0}
.trash-page .header-subtitle{font-size:14px;color:#555;margin-top:4px}

.trash-page .accent-line-dark{
  height:4px;background:#2b2d2f;border-radius:20px;margin-bottom:18px
}

.trash-page .box{
  background:white;padding:22px;border-radius:14px;margin-bottom:22px;
  box-shadow:0 3px 10px rgba(0,0,0,.07)
}

/* ===================== FILTER ===================== */
.filter-row{display:flex;align-items:flex-end;gap:20px;flex-wrap:nowrap;width:100%}
.filter-large{flex:1;margin-right:30px}
.filter-small{flex:0 0 240px}
.input{
  width:100%;padding:12px 14px;border-radius:10px;border:1px solid #ccc;
  background:#fafafa;font-size:14px
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

/* ===================== BULK BAR ===================== */
.bulk-bar{
  display:flex;
  gap:14px;
  align-items:center;
  margin-bottom:18px;
  padding:14px;
  border-radius:14px;
  background:white;
  box-shadow:0 3px 10px rgba(0,0,0,.07);
}
.bulk-form{ margin:0; }
.btn-bulk{
  display:inline-flex;
  align-items:center;
  gap:10px;
  padding:12px 18px;
  border-radius:12px;
  font-weight:800;
  border:0;
  cursor:pointer;
  color:white;
  box-shadow:0 3px 7px rgba(0,0,0,.15);
  transition:.2s;
}
.btn-bulk:hover{ transform:translateY(-2px); }
.bulk-restore{ background:#0D6EFD; }
.bulk-delete{ background:#C62828; }

@media (max-width:600px){
  .bulk-bar{flex-direction:column;align-items:stretch}
  .btn-bulk{width:100%;justify-content:center}
}

/* ===================== SELECT ALL ===================== */
.select-all-row{display:flex;align-items:center;gap:12px;padding:6px 2px}
.select-all-label{font-weight:700;cursor:pointer;user-select:none}
.divider{height:1px;background:#eee;margin:10px 0 6px}
.empty-text{margin:12px 0;color:#444}

/* ===================== CARD LIST ===================== */
.report-card{
  display:flex;justify-content:space-between;align-items:center;
  padding:18px 14px;border-bottom:1px solid #eee;gap:18px
}
.report-left{display:flex;align-items:center;gap:16px;flex:1}
.select-checkbox{width:20px;height:20px;accent-color:#2b2d2f;cursor:pointer}

.report-main{display:flex;flex-direction:column;gap:6px}
.report-title{font-size:16px;font-weight:700}
.report-meta{font-size:13px;color:#555;display:flex;flex-wrap:wrap;gap:8px}
.report-meta span{background:#f5f5f5;padding:4px 8px;border-radius:999px}

.report-actions{
  display:flex;gap:12px;align-items:center;border-left:2px solid #eee;padding-left:18px
}
.btn-premium{
  display:inline-flex;align-items:center;gap:8px;padding:10px 14px;border-radius:12px;
  font-size:14px;font-weight:700;color:white;border:0;cursor:pointer;
  box-shadow:0 3px 6px rgba(0,0,0,.1);transition:.2s
}
.btn-premium:hover{transform:translateY(-2px)}
.restore-btn{background:#0D6EFD}
.delete-btn{background:#C62828}

/* selected */
.report-card.selected{
  background:#fff5f5;
  border:2px solid rgba(228,5,5,.25);
  box-shadow:0 0 14px rgba(228,5,5,.18);
  border-radius:14px
}

/* mobile */
@media (max-width:600px){
  .report-card{flex-direction:column;align-items:flex-start;gap:14px}
  .report-actions{border-left:none;padding-left:0;width:100%;flex-wrap:wrap;gap:10px}
  .report-actions button{width:100%;justify-content:center}
}
</style>

@endsection
