@extends('layout')

@section('content')

<div data-page="activity-log">

    <div class="top-toolbar">
        <a href="{{ route('ccr.index') }}" class="btn-back">← Kembali ke beranda CCR</a>
    </div>

    <div class="box report-list-box">
        <div class="list-head">
            <div class="list-head-title">Activity Log</div>
            <div class="list-head-tools">
                <div class="list-head-count">
                    <span class="count-text">Retention: 90 hari</span>
                </div>
            </div>
        </div>

        {{-- FILTER BAR --}}
        <form method="GET" action="{{ route('admin.activity-log') }}" class="filter-bar">
            <select name="action" class="filter-select">
                <option value="">Semua Aksi</option>
                @foreach($actions as $a)
                    <option value="{{ $a }}" {{ request('action') === $a ? 'selected' : '' }}>
                        {{ ucfirst(str_replace('_', ' ', $a)) }}
                    </option>
                @endforeach
            </select>

            <select name="user_id" class="filter-select">
                <option value="">Semua User</option>
                @foreach($users as $u)
                    <option value="{{ $u->user_id }}" {{ (int) request('user_id') === (int) $u->user_id ? 'selected' : '' }}>
                        {{ $u->user_name }}
                    </option>
                @endforeach
            </select>

            <input type="date" name="from" value="{{ request('from') }}" class="filter-input" placeholder="Dari">
            <input type="date" name="to" value="{{ request('to') }}" class="filter-input" placeholder="Sampai">
            <input type="text" name="search" value="{{ request('search') }}" class="filter-input filter-search" placeholder="Cari komponen / user...">

            <button type="submit" class="btn-filter">Filter</button>
            @if(request()->hasAny(['action','user_id','from','to','search']))
                <a href="{{ route('admin.activity-log') }}" class="btn-filter-reset">Reset</a>
            @endif
        </form>

        {{-- LOG TABLE --}}
        <div class="log-table-wrap">
            <table class="log-table">
                <thead>
                    <tr>
                        <th>Waktu</th>
                        <th>User</th>
                        <th>Aksi</th>
                        <th>Detail</th>
                        <th>IP</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($logs as $log)
                        <tr>
                            <td class="td-time">{{ $log->created_at->format('d M Y H:i') }}</td>
                            <td class="td-user">{{ $log->user_name ?? '-' }}</td>
                            <td class="td-action">
                                <span class="action-badge {{ $log->action_color }}">
                                    {{ $log->action_label }}
                                </span>
                            </td>
                            <td class="td-detail">
                                @php $meta = $log->meta ?? []; @endphp
                                @if(!empty($meta['type']))
                                    <span class="meta-type">{{ strtoupper($meta['type']) }}</span>
                                @endif
                                @if(!empty($meta['component']))
                                    {{ $meta['component'] }}
                                @endif
                                @if($log->subject_id)
                                    <span class="meta-id">#{{ $log->subject_id }}</span>
                                @endif
                            </td>
                            <td class="td-ip">{{ $log->ip_address ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="td-empty">Belum ada activity log.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="pagination-wrap">
            {{ $logs->links() }}
        </div>
    </div>
</div>

<style>
[data-page="activity-log"]{width:100%;max-width:100%;overflow-x:hidden}
[data-page="activity-log"] *,[data-page="activity-log"] *::before,[data-page="activity-log"] *::after{box-sizing:border-box}

[data-page="activity-log"] .top-toolbar{display:flex;align-items:center;gap:12px;margin-bottom:18px}
[data-page="activity-log"] .btn-back{display:inline-flex;align-items:center;text-decoration:none;border-radius:12px;font-weight:900;padding:10px 18px;background:#5f656a;color:#fff;box-shadow:0 10px 18px rgba(0,0,0,.10);transition:.18s}
[data-page="activity-log"] .btn-back:hover{background:#2f3336;transform:translateY(-1px)}

[data-page="activity-log"] .box{background:#f8fbff;border:1px solid #dbe5f3;border-radius:18px;padding:18px;box-shadow:0 10px 28px rgba(15,23,42,.05)}
[data-page="activity-log"] .list-head{display:flex;align-items:center;justify-content:space-between;gap:12px;padding-bottom:12px;border-bottom:1px solid #e2e8f0;margin-bottom:14px}
[data-page="activity-log"] .list-head-title{font-size:14px;font-weight:800;letter-spacing:.08em;text-transform:uppercase;color:#0f1b3a}
[data-page="activity-log"] .list-head-count{display:inline-flex;align-items:center;gap:8px;background:#eff3fb;border:1px solid #d6e0ef;border-radius:999px;padding:6px 12px}
[data-page="activity-log"] .count-text{font-size:12px;font-weight:800;color:#5f6e8a}

[data-page="activity-log"] .filter-bar{display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:14px}
[data-page="activity-log"] .filter-select,[data-page="activity-log"] .filter-input{height:36px;border:1px solid #d1d5db;border-radius:8px;padding:0 10px;font-size:13px;font-weight:600;background:#fff;color:#111}
[data-page="activity-log"] .filter-search{min-width:180px}
[data-page="activity-log"] .btn-filter{height:36px;padding:0 16px;border:1px solid #111;border-radius:8px;background:#111;color:#fff;font-weight:700;font-size:13px;cursor:pointer;transition:.18s}
[data-page="activity-log"] .btn-filter:hover{opacity:.85}
[data-page="activity-log"] .btn-filter-reset{height:36px;padding:0 14px;border:1px solid #d1d5db;border-radius:8px;background:#fff;color:#666;font-weight:700;font-size:13px;text-decoration:none;display:inline-flex;align-items:center}

[data-page="activity-log"] .log-table-wrap{overflow-x:auto;border:1px solid #dbe5f3;border-radius:12px}
[data-page="activity-log"] .log-table{width:100%;border-collapse:collapse;font-size:13px}
[data-page="activity-log"] .log-table thead{background:#f1f5f9}
[data-page="activity-log"] .log-table th{text-align:left;padding:10px 12px;font-weight:800;color:#374151;font-size:12px;text-transform:uppercase;letter-spacing:.05em;border-bottom:2px solid #e2e8f0}
[data-page="activity-log"] .log-table td{padding:10px 12px;border-bottom:1px solid #f1f5f9;color:#1e293b;vertical-align:middle}
[data-page="activity-log"] .log-table tbody tr:hover{background:#f8fafc}

[data-page="activity-log"] .td-time{font-weight:600;white-space:nowrap;color:#64748b;font-size:12px}
[data-page="activity-log"] .td-user{font-weight:700}
[data-page="activity-log"] .td-ip{font-family:monospace;font-size:12px;color:#94a3b8}
[data-page="activity-log"] .td-empty{text-align:center;color:#94a3b8;font-weight:700;padding:30px}

[data-page="activity-log"] .action-badge{display:inline-flex;align-items:center;padding:4px 10px;border-radius:999px;font-size:11px;font-weight:800;letter-spacing:.02em;white-space:nowrap}
[data-page="activity-log"] .bg-green-100{background:#dcfce7}.text-green-800{color:#166534}
[data-page="activity-log"] .bg-blue-100{background:#dbeafe}.text-blue-800{color:#1e40af}
[data-page="activity-log"] .bg-yellow-100{background:#fef9c3}.text-yellow-800{color:#854d0e}
[data-page="activity-log"] .bg-emerald-100{background:#d1fae5}.text-emerald-800{color:#065f46}
[data-page="activity-log"] .bg-red-100{background:#fee2e2}.text-red-700{color:#b91c1c}.text-red-800{color:#991b1b}
[data-page="activity-log"] .bg-purple-100{background:#f3e8ff}.text-purple-800{color:#6b21a8}
[data-page="activity-log"] .bg-gray-100{background:#f3f4f6}.text-gray-800{color:#1f2937}

[data-page="activity-log"] .meta-type{display:inline-flex;padding:2px 6px;border-radius:4px;font-size:10px;font-weight:900;background:#e0e7ff;color:#3730a3;margin-right:4px}
[data-page="activity-log"] .meta-id{color:#94a3b8;font-size:12px;margin-left:4px}

[data-page="activity-log"] .pagination-wrap{margin-top:14px;display:flex;justify-content:center}

@media (max-width:768px){
    [data-page="activity-log"] .filter-bar{flex-direction:column;align-items:stretch}
    [data-page="activity-log"] .filter-select,[data-page="activity-log"] .filter-input{width:100%}
}
</style>

@endsection
