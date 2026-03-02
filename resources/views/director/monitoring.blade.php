@extends('layout')

@section('content')

@php
    $filters = array_merge([
        'q' => '',
        'customer' => '',
        'type' => '',
        'sort' => 'submitted_newest',
    ], $filters ?? []);

    $stats = array_merge([
        'pending_total' => 0,
        'pending_engine' => 0,
        'pending_seat' => 0,
    ], $stats ?? []);
@endphp

<div class="dm-page" data-page="director-monitoring">

    <div class="top-toolbar">
        <a href="{{ route('ccr.index') }}" class="btn-back">← Kembali ke beranda CCR</a>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @if(session('warning'))
        <div class="alert alert-warning">{{ session('warning') }}</div>
    @endif

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-label">Total Waiting</div>
            <div class="stat-value">{{ (int) $stats['pending_total'] }}</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Engine Waiting</div>
            <div class="stat-value">{{ (int) $stats['pending_engine'] }}</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Seat Waiting</div>
            <div class="stat-value">{{ (int) $stats['pending_seat'] }}</div>
        </div>
    </div>

    <form method="GET" action="{{ route('director.monitoring') }}" class="box filter-box">
        <div class="filter-head">
            <h3 class="section-title">Daftar CCR Engine & Seat</h3>
        </div>

        <div class="mon-filter-grid">
            <div class="mon-filter-group mon-filter-search">
                <label for="qInputMon">Cari</label>
                <input
                    id="qInputMon"
                    type="text"
                    class="input"
                    name="q"
                    value="{{ $filters['q'] }}"
                    placeholder="Cari component, customer, make, model, SN..."
                >
            </div>

            <div class="mon-filter-group">
                <label for="customerFilterMon">Filter Customer</label>
                <select id="customerFilterMon" class="input" name="customer">
                    <option value="">Semua customer</option>
                    @foreach(($customers ?? collect()) as $c)
                        <option value="{{ $c }}" {{ $filters['customer'] === $c ? 'selected' : '' }}>{{ $c }}</option>
                    @endforeach
                </select>
            </div>

            <div class="mon-filter-group">
                <label for="typeFilterMon">Filter Type</label>
                <select id="typeFilterMon" class="input" name="type">
                    <option value="" {{ $filters['type'] === '' ? 'selected' : '' }}>Semua</option>
                    <option value="engine" {{ $filters['type'] === 'engine' ? 'selected' : '' }}>Engine</option>
                    <option value="seat" {{ $filters['type'] === 'seat' ? 'selected' : '' }}>Seat</option>
                </select>
            </div>

            <div class="mon-filter-group">
                <label for="sortSelectMon">Sort By</label>
                <select id="sortSelectMon" class="input" name="sort">
                    <option value="submitted_newest" {{ $filters['sort'] === 'submitted_newest' ? 'selected' : '' }}>Newest (Submitted)</option>
                    <option value="submitted_oldest" {{ $filters['sort'] === 'submitted_oldest' ? 'selected' : '' }}>Oldest (Submitted)</option>
                    <option value="inspection_newest" {{ $filters['sort'] === 'inspection_newest' ? 'selected' : '' }}>Newest (Inspection)</option>
                    <option value="inspection_oldest" {{ $filters['sort'] === 'inspection_oldest' ? 'selected' : '' }}>Oldest (Inspection)</option>
                </select>
            </div>
        </div>
    </form>

    <div class="box report-list-box" id="reportListMon">
        <div class="list-head">
            <div class="list-head-title">List Waiting Review</div>
            <div class="list-head-count">
                <span class="count-number">{{ $reports->count() }}</span>
                <span class="count-text">data tampil</span>
            </div>
        </div>

        @if($reports->count() > 0)
            <div class="cards-grid">
                @foreach($reports as $r)
                    @php
                        $isEngine = ($r->type === 'engine');

                        $previewRoute = $isEngine
                            ? (\Illuminate\Support\Facades\Route::has('engine.preview') ? route('engine.preview', $r->id) : '#')
                            : (\Illuminate\Support\Facades\Route::has('seat.preview') ? route('seat.preview', $r->id) : '#');

                        $editRoute = $isEngine
                            ? (\Illuminate\Support\Facades\Route::has('engine.edit') ? route('engine.edit', $r->id) : '#')
                            : (\Illuminate\Support\Facades\Route::has('seat.edit') ? route('seat.edit', $r->id) : '#');

                        $statusText = ($r->approval_status === 'in_review') ? 'In Review' : 'Waiting';
                        $isOpenTarget = ((int) ($openId ?? 0) === (int) $r->id);
                    @endphp

                    <article id="r-{{ $r->id }}" class="job-card {{ $isOpenTarget ? 'is-target' : '' }}">
                        <div class="job-head-row">
                            <div class="job-brand-block">
                                <div class="job-head-text">
                                    <h4 class="job-title">{{ $r->component }}</h4>
                                    <div class="job-company">{{ $r->customer ?? '-' }}</div>
                                </div>
                            </div>

                            <div class="job-head-right">
                                <span class="type-pill {{ $isEngine ? 'type-engine' : 'type-seat' }}">{{ strtoupper($r->type) }}</span>
                                <span class="status-pill">{{ $statusText }}</span>
                            </div>
                        </div>

                        <div class="job-tags">
                            <span class="tag-pill">Make: <b>{{ $r->make ?? '-' }}</b></span>
                            <span class="tag-pill">Model: <b>{{ $r->model ?? '-' }}</b></span>
                            <span class="tag-pill">SN: <b>{{ $r->sn ?? '-' }}</b></span>
                        </div>

                        <div class="card-actions">
                            <a href="{{ $previewRoute }}" target="_blank" rel="noopener noreferrer" class="btn-outline-action">Preview</a>
                            <a href="{{ $editRoute }}" target="_blank" rel="noopener noreferrer" class="btn-outline-action">Edit</a>
                        </div>

                        <div class="review-stack">
                            <form action="{{ route('director.monitoring.approve', $r->id) }}" method="POST" class="review-panel js-once-submit">
                                @csrf
                                <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                                <label class="panel-label">Optional Note (Approve)</label>
                                <textarea name="approve_note" class="input textarea" rows="2" maxlength="2000" placeholder="Boleh dikosongkan"></textarea>
                                <button type="submit" class="btn-action btn-approve">Approve</button>
                            </form>

                            <form action="{{ route('director.monitoring.reject', $r->id) }}" method="POST" class="review-panel js-once-submit">
                                @csrf
                                <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                                <input type="hidden" name="_reject_id" value="{{ $r->id }}">
                                <label class="panel-label">Revision Note (Reject) <span class="req">*</span></label>
                                <textarea
                                    name="director_note"
                                    class="input textarea"
                                    rows="3"
                                    maxlength="2000"
                                    placeholder="Catatan revisi wajib"
                                >{{ old('_reject_id') == $r->id ? old('director_note') : '' }}</textarea>

                                @error('director_note')
                                    @if(old('_reject_id') == $r->id)
                                        <div class="error-text">{{ $message }}</div>
                                    @endif
                                @enderror

                                <button type="submit" class="btn-action btn-reject">Reject + Note</button>
                            </form>
                        </div>
                    </article>
                @endforeach
            </div>
        @else
            <p class="empty-state">Tidak ada CCR yang menunggu review saat ini.</p>
        @endif

        @if(method_exists($reports, 'links'))
            <div class="list-pagination">
                {{ $reports->links() }}
            </div>
        @endif
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const page = document.querySelector('.dm-page[data-page="director-monitoring"]');
    if (!page) return;

    const openTarget = page.querySelector('.job-card.is-target');
    if (openTarget) {
        openTarget.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    const filterForm = page.querySelector('form.filter-box');
    if (filterForm) {
        filterForm.querySelectorAll('select').forEach((select) => {
            select.addEventListener('change', () => filterForm.submit());
        });

        const qInput = filterForm.querySelector('input[name="q"]');
        if (qInput) {
            qInput.addEventListener('change', () => filterForm.submit());
            qInput.addEventListener('keydown', (event) => {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    filterForm.submit();
                }
            });
        }
    }

    page.querySelectorAll('form.js-once-submit').forEach((form) => {
        form.addEventListener('submit', () => {
            const btn = form.querySelector('button[type="submit"]');
            if (!btn) return;

            btn.disabled = true;
            btn.dataset.originalText = btn.textContent || '';
            btn.textContent = 'Memproses...';
        }, { once: true });
    });
});
</script>

<style>
.dm-page,
.dm-page *,
.dm-page *::before,
.dm-page *::after {
    box-sizing: border-box;
}

.dm-page .top-toolbar {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 14px;
}

.dm-page .btn-back {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    text-decoration: none;
    border-radius: 12px;
    font-weight: 900;
    padding: 10px 18px;
    background: #5f656a;
    color: #fff;
    box-shadow: 0 10px 18px rgba(0, 0, 0, .10);
    transition: .18s;
}

.dm-page .btn-back:hover {
    background: #2f3336;
    transform: translateY(-1px);
}

.dm-page .alert {
    border-radius: 12px;
    padding: 10px 14px;
    font-size: 14px;
    font-weight: 800;
    margin-bottom: 12px;
}

.dm-page .alert-success {
    background: rgba(34, 197, 94, .12);
    border: 1px solid rgba(34, 197, 94, .35);
    color: #166534;
}

.dm-page .alert-warning {
    background: rgba(245, 158, 11, .12);
    border: 1px solid rgba(245, 158, 11, .30);
    color: #92400e;
}

.dm-page .stats-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(160px, 1fr));
    gap: 10px;
    margin-bottom: 14px;
}

.dm-page .stat-card {
    background: #fff;
    border: 1px solid #dbe5f3;
    border-radius: 12px;
    box-shadow: 0 8px 22px rgba(15, 23, 42, .05);
    padding: 10px 12px;
    min-height: 76px;
    display: flex;
    flex-direction: column;
    justify-content: center;
}

.dm-page .stat-label {
    color: #5f6e8a;
    font-size: 11px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: .04em;
}

.dm-page .stat-value {
    margin-top: 4px;
    font-size: 20px;
    font-weight: 900;
    color: #0f1b3a;
    line-height: 1;
}

.dm-page .box {
    background: #f8fbff;
    border: 1px solid #dbe5f3;
    border-radius: 16px;
    padding: 16px;
    box-shadow: 0 10px 28px rgba(15, 23, 42, .05);
    margin-bottom: 14px;
}

.dm-page .filter-box {
    background: #fff;
}

.dm-page .filter-head {
    margin-bottom: 12px;
}

.dm-page .section-title {
    margin: 0;
    font-size: 18px;
    font-weight: 800;
    color: #0f1b3a;
}

.dm-page .mon-filter-grid {
    display: grid;
    grid-template-columns: minmax(260px, 2fr) repeat(3, minmax(180px, 1fr));
    column-gap: 12px;
    row-gap: 12px;
    align-items: end;
}

.dm-page .mon-filter-group {
    min-width: 0;
}

.dm-page .mon-filter-group label {
    display: block;
    font-weight: 800;
    font-size: 13px;
    color: #1e293b;
    margin-bottom: 6px;
}

.dm-page .input {
    width: 100%;
    min-height: 44px;
    padding: 10px 12px;
    border-radius: 10px;
    border: 1px solid #cfd9e8;
    background: #fff;
    font-size: 14px;
    color: #0f172a;
    outline: none;
    transition: border-color .18s ease, box-shadow .18s ease;
}

.dm-page .input:focus {
    border-color: #2f65d8;
    box-shadow: 0 0 0 3px rgba(47, 101, 216, .15);
}

.dm-page .textarea {
    resize: vertical;
    min-height: 64px;
}

.dm-page .list-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding-bottom: 12px;
    border-bottom: 1px solid #e2e8f0;
    margin-bottom: 12px;
}

.dm-page .list-head-title {
    font-size: 14px;
    font-weight: 800;
    letter-spacing: .08em;
    text-transform: uppercase;
    color: #0f1b3a;
}

.dm-page .list-head-count {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: #eff3fb;
    border: 1px solid #d6e0ef;
    border-radius: 999px;
    padding: 6px 12px;
}

.dm-page .list-head-count .count-number {
    font-weight: 1000;
    color: #0f1b3a;
}

.dm-page .list-head-count .count-text {
    font-size: 12px;
    font-weight: 800;
    color: #5f6e8a;
}

.dm-page .cards-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(280px, 1fr));
    gap: 14px;
}

.dm-page .job-card {
    background: #fff;
    border: 1px solid #e5ebf5;
    border-radius: 22px;
    box-shadow: 0 18px 30px rgba(15, 23, 42, .08);
    padding: 12px;
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.dm-page .job-card.is-target {
    border-color: rgba(31, 111, 229, .45);
    box-shadow: 0 0 0 3px rgba(31, 111, 229, .10);
}

.dm-page .job-head-row {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 98px;
    align-items: start;
    column-gap: 8px;
}

.dm-page .job-brand-block {
    display: flex;
    min-width: 0;
}

.dm-page .job-head-text {
    min-width: 0;
    display: flex;
    flex-direction: column;
}

.dm-page .job-company {
    margin-top: 12px;
    font-size: 12px;
    color: #5f6e8a;
    font-weight: 700;
}

.dm-page .job-title {
    margin: 0;
    font-size: 18px;
    line-height: 1.2;
    font-weight: 700;
    color: #111827;
    overflow-wrap: anywhere;
    word-break: break-word;
}

.dm-page .job-head-right {
    width: 98px;
    min-width: 98px;
    display: grid;
    grid-template-columns: 1fr;
    gap: 6px;
    justify-items: end;
    align-content: start;
}

.dm-page .type-pill,
.dm-page .status-pill {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 92px;
    min-width: 92px;
    padding: 4px 8px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 800;
    border: 1px solid transparent;
    line-height: 1.1;
}

.dm-page .type-engine,
.dm-page .type-seat {
    color: #9f8170;
    background: rgba(159, 129, 112, .12);
    border-color: rgba(159, 129, 112, .28);
}

.dm-page .status-pill {
    color: #854d0e;
    background: rgba(245, 158, 11, .14);
    border-color: rgba(245, 158, 11, .24);
}

.dm-page .job-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin-top: 28px;
}

.dm-page .tag-pill {
    background: #f3f4f6;
    border: 1px solid #e5e7eb;
    border-radius: 999px;
    font-size: 11px;
    color: #374151;
    padding: 4px 8px;
}

.dm-page .tag-pill b {
    color: #111827;
}

.dm-page .card-actions {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 8px;
}

.dm-page .btn-outline-action {
    min-height: 40px;
    border-radius: 10px;
    border: 2px solid #bcc6d7;
    background: #f8fafc;
    color: #111827;
    text-decoration: none;
    font-size: 13px;
    font-weight: 800;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: .16s;
}

.dm-page .btn-outline-action:hover {
    background: #f1f5f9;
    border-color: #aeb8c9;
}

.dm-page .review-stack {
    display: grid;
    grid-template-columns: 1fr;
    gap: 8px;
}

.dm-page .review-panel {
    background: #fff;
    border: 1px solid #eef2f7;
    border-radius: 12px;
    padding: 10px;
}

.dm-page .panel-label {
    display: block;
    font-weight: 800;
    font-size: 13px;
    margin-bottom: 7px;
    color: #111827;
}

.dm-page .req {
    color: #E40505;
}

.dm-page .btn-action {
    width: 100%;
    margin-top: 8px;
    border: none;
    cursor: pointer;
    min-height: 38px;
    padding: 8px 12px;
    border-radius: 9px;
    font-size: 14px;
    font-weight: 800;
    color: #fff;
    transition: .16s;
}

.dm-page .btn-action:disabled {
    opacity: .7;
    cursor: not-allowed;
}

.dm-page .btn-approve {
    background: #22c55e;
}

.dm-page .btn-approve:hover {
    background: #16a34a;
}

.dm-page .btn-reject {
    background: #ef4444;
}

.dm-page .btn-reject:hover {
    background: #dc2626;
}

.dm-page .error-text {
    margin-top: 6px;
    font-weight: 700;
    color: #E40505;
    font-size: 13px;
}

.dm-page .empty-state {
    margin: 0;
    border: 1px dashed #cbd5e1;
    border-radius: 12px;
    background: #fff;
    color: #64748b;
    font-size: 14px;
    font-weight: 800;
    padding: 14px;
    text-align: center;
}

.dm-page .list-pagination {
    margin-top: 12px;
}

@media (max-width:1400px) {
    .dm-page .cards-grid {
        grid-template-columns: repeat(2, minmax(280px, 1fr));
    }
}

@media (max-width:1200px) {
    .dm-page .stats-grid {
        grid-template-columns: repeat(2, minmax(120px, 1fr));
    }

    .dm-page .mon-filter-grid {
        grid-template-columns: repeat(2, minmax(180px, 1fr));
    }

    .dm-page .mon-filter-search {
        grid-column: 1 / -1;
    }
}

@media (max-width:860px) {
    .dm-page .cards-grid {
        grid-template-columns: 1fr;
    }

    .dm-page .job-title {
        font-size: 17px;
    }
}

@media (max-width:700px) {
    .dm-page .stats-grid {
        grid-template-columns: 1fr;
    }

    .dm-page .mon-filter-grid {
        grid-template-columns: 1fr;
    }

    .dm-page .job-head-row {
        grid-template-columns: minmax(0, 1fr) 90px;
    }

    .dm-page .job-head-right {
        width: 90px;
        min-width: 90px;
    }

    .dm-page .type-pill,
    .dm-page .status-pill {
        width: 84px;
        min-width: 84px;
        font-size: 10px;
    }
}
</style>

@endsection
