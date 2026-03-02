@extends('layout')

@section('content')

@php
    $users = $users ?? collect();

    $grouped = $users->groupBy(function($u){
        return $u->role instanceof \App\Enums\UserRole ? $u->role->value : strtolower($u->role ?? 'unknown');
    });

    $adminUsers    = $grouped->get('admin', collect());
    $operatorUsers = $grouped->get('operator', collect()); // tampil: PLANNER
    $directorUsers = $grouped->get('director', collect());

    $myRoleRaw = auth()->user()->role;
    $myRole = $myRoleRaw instanceof \App\Enums\UserRole ? $myRoleRaw->value : strtolower(trim((string) $myRoleRaw));
    $myId   = (int) auth()->id();

    $totalUsers = (int) $users->count();

    $roleSections = [
        [
            'key' => 'admin',
            'title' => 'ADMIN',
            'users' => $adminUsers,
            'sectionClass' => 'role-admin',
            'pillClass' => 'role-admin-pill',
            'emptyText' => 'Belum ada akun admin.',
        ],
        [
            'key' => 'operator',
            'title' => 'PLANNER',
            'users' => $operatorUsers,
            'sectionClass' => 'role-operator',
            'pillClass' => 'role-operator-pill',
            'emptyText' => 'Belum ada akun planner.',
        ],
        [
            'key' => 'director',
            'title' => 'DIRECTOR',
            'users' => $directorUsers,
            'sectionClass' => 'role-director',
            'pillClass' => 'role-director-pill',
            'emptyText' => 'Belum ada akun director.',
        ],
    ];
@endphp

<div data-page="manage-users">

    <div class="top-toolbar">
        <a href="{{ route('ccr.index') }}" class="btn-back">← Kembali ke beranda CCR</a>
    </div>

    <div class="box report-list-box">
        <div class="list-head">
            <div class="list-head-title">List Akun</div>
            <div class="list-head-tools">
                <a href="{{ route('admin.activity-log') }}" class="btn-list-create">📋 Activity Log</a>
                <a href="{{ route('admin.notifications.index') }}" class="btn-list-create">Setting Notifikasi</a>
                <a href="{{ route('admin.users.create') }}" class="btn-list-create">+ Tambah User</a>
                <div class="list-head-count">
                    <span id="resultCountUsers" class="count-number">{{ $totalUsers }}</span>
                    <span class="count-text">akun tampil</span>
                </div>
            </div>
        </div>

        <div class="roles-stack" id="roleSectionsUsers">
            @foreach($roleSections as $section)
                <section
                    class="role-group {{ $section['sectionClass'] }}"
                    data-role-group="{{ $section['key'] }}"
                >
                    <div class="role-head">
                        <div class="role-head-left">
                            <span class="role-pill {{ $section['pillClass'] }}">{{ $section['title'] }}</span>
                            <span class="count-pill">{{ $section['users']->count() }} akun</span>
                        </div>
                    </div>

                    <div class="user-list" data-user-list>
                        @forelse($section['users'] as $u)
                            <article
                                class="user-card"
                                data-role="{{ $section['key'] }}"
                            >
                                <div class="user-main">
                                    <div class="user-title">
                                        <strong>{{ $u->username }}</strong>
                                    </div>

                                    <div class="user-meta">
                                        <span>Nama: <b>{{ $u->name }}</b></span>
                                    </div>
                                </div>

                                <div class="user-actions">
                                    @if($section['key'] === 'director' && $myRole === 'admin')
                                        <button
                                            type="button"
                                            class="btn-action btn-edit"
                                            onclick="window.dispatchEvent(new CustomEvent('locked',{detail:{msg:'you cannot access this'}}));"
                                        >
                                            Edit
                                        </button>

                                        <button
                                            type="button"
                                            class="btn-action btn-delete"
                                            onclick="window.dispatchEvent(new CustomEvent('locked',{detail:{msg:'you cannot access this'}}));"
                                        >
                                            Hapus
                                        </button>
                                    @else
                                        <a href="{{ route('admin.users.edit', $u->id) }}" class="btn-action btn-edit">Edit</a>

                                        @if((int)$u->id === $myId)
                                            <button
                                                type="button"
                                                class="btn-action btn-delete"
                                                disabled
                                                title="Tidak bisa hapus akun sendiri"
                                            >
                                                Hapus
                                            </button>
                                        @else
                                            <form
                                                action="{{ route('admin.users.destroy', $u->id) }}"
                                                method="POST"
                                                onsubmit="return confirm('Yakin hapus user ini? Tindakan ini tidak bisa dibatalkan.')"
                                            >
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn-action btn-delete">Hapus</button>
                                            </form>
                                        @endif
                                    @endif
                                </div>
                            </article>
                        @empty
                            <p class="empty-state empty-state-server">{{ $section['emptyText'] }}</p>
                        @endforelse

                    </div>
                </section>
            @endforeach
        </div>

    </div>
</div>

<style>
[data-page="manage-users"]{
    width:100%;
    max-width:100%;
    overflow-x:hidden;
}

[data-page="manage-users"] *,
[data-page="manage-users"] *::before,
[data-page="manage-users"] *::after{
    box-sizing:border-box;
}

[data-page="manage-users"] .top-toolbar{
    display:flex;
    align-items:center;
    justify-content:flex-start;
    gap:12px;
    flex-wrap:wrap;
    margin-bottom:18px;
}

[data-page="manage-users"] .btn-back{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    text-decoration:none;
    border-radius:12px;
    font-weight:900;
    padding:10px 18px;
    background:#5f656a;
    color:#fff;
    box-shadow:0 10px 18px rgba(0,0,0,.10);
    transition:.18s;
}
[data-page="manage-users"] .btn-back:hover{
    background:#2f3336;
    transform:translateY(-1px);
}

[data-page="manage-users"] .box{
    background:#f8fbff;
    border:1px solid #dbe5f3;
    border-radius:18px;
    padding:18px;
    box-shadow:0 10px 28px rgba(15,23,42,.05);
}

[data-page="manage-users"] .report-list-box{
    margin-bottom:10px;
}

[data-page="manage-users"] .list-head{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:12px;
    padding-bottom:12px;
    border-bottom:1px solid #e2e8f0;
    margin-bottom:14px;
}

[data-page="manage-users"] .list-head-tools{
    display:inline-flex;
    align-items:center;
    gap:10px;
    flex-wrap:wrap;
}

[data-page="manage-users"] .list-head-title{
    font-size:14px;
    font-weight:800;
    letter-spacing:.08em;
    text-transform:uppercase;
    color:#0f1b3a;
}

[data-page="manage-users"] .list-head-count{
    display:inline-flex;
    align-items:center;
    gap:8px;
    background:#eff3fb;
    border:1px solid #d6e0ef;
    border-radius:999px;
    padding:6px 12px;
}

[data-page="manage-users"] .list-head-count .count-number{
    font-weight:1000;
    color:#0f1b3a;
}

[data-page="manage-users"] .list-head-count .count-text{
    font-size:12px;
    font-weight:800;
    color:#5f6e8a;
}

[data-page="manage-users"] .btn-list-create{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    text-decoration:none;
    border-radius:8px;
    border:1px solid #111;
    background:#fff;
    color:#111;
    font-weight:700;
    font-size:13px;
    min-height:36px;
    padding:7px 14px;
    box-shadow:0 1px 0 rgba(17,17,17,.08);
    transition:.18s;
    white-space:nowrap;
}

[data-page="manage-users"] .btn-list-create:hover{
    background:#f8fafc;
    transform:translateY(-1px) scale(1.01);
}

[data-page="manage-users"] .roles-stack{
    display:flex;
    flex-direction:column;
    gap:14px;
}

[data-page="manage-users"] .role-group{
    background:#fff;
    border:1px solid #dbe5f3;
    border-radius:18px;
    padding:14px;
    box-shadow:0 8px 22px rgba(15,23,42,.05);
}

[data-page="manage-users"] .role-head{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:12px;
    margin-bottom:10px;
}

[data-page="manage-users"] .role-head-left{
    display:flex;
    align-items:center;
    gap:10px;
    flex-wrap:wrap;
}

[data-page="manage-users"] .count-pill{
    display:inline-flex;
    align-items:center;
    border-radius:999px;
    background:#f3f4f6;
    color:#374151;
    padding:6px 10px;
    font-size:12px;
    font-weight:900;
}

[data-page="manage-users"] .role-pill{
    display:inline-flex;
    align-items:center;
    border-radius:999px;
    border:1px solid transparent;
    padding:6px 12px;
    font-size:12px;
    font-weight:1000;
    letter-spacing:.3px;
    line-height:1;
}

[data-page="manage-users"] .role-admin-pill{
    background:rgba(13,110,253,.10);
    border-color:rgba(13,110,253,.22);
    color:#0d6efd;
}

[data-page="manage-users"] .role-operator-pill{
    background:rgba(228,5,5,.10);
    border-color:rgba(228,5,5,.22);
    color:#e40505;
}

[data-page="manage-users"] .role-director-pill{
    background:rgba(159,129,112,.14);
    border-color:rgba(159,129,112,.28);
    color:#9f8170;
}
[data-page="manage-users"] .user-list{
    display:flex;
    flex-direction:column;
    gap:10px;
}

[data-page="manage-users"] .user-card{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:16px;
    border:1px solid #dbe5f3;
    border-radius:14px;
    background:#f9fbff;
    padding:12px;
}

[data-page="manage-users"] .user-main{
    min-width:0;
    display:flex;
    flex-direction:column;
    gap:7px;
}

[data-page="manage-users"] .user-title{
    display:flex;
    align-items:center;
    gap:8px;
    flex-wrap:wrap;
}

[data-page="manage-users"] .user-title strong{
    font-size:16px;
    font-weight:800;
    color:#0f172a;
    line-height:1.2;
}

[data-page="manage-users"] .user-meta{
    display:flex;
    align-items:center;
    flex-wrap:wrap;
    gap:8px;
}

[data-page="manage-users"] .user-meta span{
    display:inline-flex;
    align-items:center;
    padding:4px 10px;
    border-radius:999px;
    border:1px solid #d5dcea;
    background:#f1f5fb;
    color:#334155;
    font-size:13px;
    font-weight:700;
}

[data-page="manage-users"] .user-actions{
    flex:0 0 auto;
    display:flex;
    align-items:center;
    gap:10px;
    padding-left:12px;
    border-left:2px solid #e5e7eb;
    min-width:fit-content;
}

[data-page="manage-users"] .user-actions form{
    margin:0;
}

[data-page="manage-users"] .btn-action{
    min-width:110px;
    height:34px;
    border-radius:9px;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    font-size:12px;
    font-weight:600;
    text-decoration:none;
    cursor:pointer;
    transition:.18s;
    border:1px solid rgba(0,0,0,.05);
    padding:6px 10px;
    line-height:1;
    box-shadow:0 3px 6px rgba(0,0,0,.1);
}

[data-page="manage-users"] .btn-action:hover{
    box-shadow:0 4px 10px rgba(0,0,0,.15);
}

[data-page="manage-users"] .btn-edit{
    background:#f8fafc;
    border:2px solid #b8c2d3;
    color:#111c33;
    box-shadow:none;
}

[data-page="manage-users"] .btn-edit:hover{
    background:#f1f5f9;
    border-color:#b8c2cf;
    transform:none;
    box-shadow:none;
}

[data-page="manage-users"] .btn-delete{
    background:#e53935;
    color:#fff;
    box-shadow:0 3px 8px rgba(229,57,53,.25);
}

[data-page="manage-users"] .btn-delete:hover{
    background:#d32f2f;
}

[data-page="manage-users"] .btn-delete:disabled{
    opacity:.55;
    cursor:not-allowed;
    transform:none;
}

[data-page="manage-users"] .empty-state{
    margin:0;
    border:1px dashed #cbd5e1;
    border-radius:12px;
    background:#fff;
    color:#64748b;
    font-size:15px;
    font-weight:800;
    padding:14px;
    text-align:center;
}

@media (max-width:1200px){
    [data-page="manage-users"] .user-card{
        flex-direction:column;
        align-items:flex-start;
    }

    [data-page="manage-users"] .user-actions{
        width:100%;
        border-left:0;
        border-top:4px solid #e5e7eb;
        padding-left:0;
        padding-top:12px;
        justify-content:flex-start;
    }
}

@media (max-width:768px){
    [data-page="manage-users"] .list-head-title{ font-size:13px; }

    [data-page="manage-users"] .btn-action{
        min-width:100px;
        height:34px;
        border-radius:9px;
        font-size:12px;
    }

    [data-page="manage-users"] .user-title strong{
        font-size:15px;
    }

    [data-page="manage-users"] .user-meta span{
        font-size:13px;
    }
}
</style>

@endsection
