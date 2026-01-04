@extends('layout')

@section('content')

@php
    $users = $users ?? collect();

    $grouped = $users->groupBy(function($u){
        return strtolower($u->role ?? 'unknown');
    });

    $adminUsers    = $grouped->get('admin', collect());
    $operatorUsers = $grouped->get('operator', collect()); // tampil: PLANNER
    $directorUsers = $grouped->get('director', collect());

    $myRole = strtolower(trim((string) auth()->user()->role));
    $myId   = (int) auth()->id();
@endphp

<div class="um-page">

    {{-- BACK BUTTON --}}
    <a href="{{ route('ccr.index') }}" class="btn-back">← Kembali</a>

    {{-- PAGE CARD --}}
    <div class="page-card">
        <div class="page-head">
            <div>
                <h1 class="page-title">User Management</h1>
                <p class="page-subtitle">Kelola akun Admin / Director / Planner.</p>
            </div>

            <a href="{{ route('admin.users.create') }}" class="btn-primary">
                + Tambah User
            </a>
        </div>

        {{-- ROLE CARDS --}}
        <div class="roles-stack">

            {{-- ADMIN CARD --}}
            <section class="role-card role-admin">
                <div class="role-head">
                    <div class="role-head-left">
                        <div class="role-title">ADMIN</div>
                        <span class="count-pill">{{ $adminUsers->count() }} akun</span>
                    </div>

                    <span class="role-pill role-admin-pill">ADMIN</span>
                </div>

                <div class="table-wrap">
                    <table class="table">
                        <thead>
                        <tr>
                            <th style="width:28%;">Username</th>
                            <th style="width:28%;">Nama</th>
                            <th style="width:22%;">Role</th>
                            <th style="width:22%;">Aksi</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($adminUsers as $u)
                            <tr>
                                <td class="td-strong">{{ $u->username }}</td>
                                <td>{{ $u->name }}</td>
                                <td><span class="role-pill role-admin-pill">ADMIN</span></td>
                                <td>
                                    <div class="aksi-wrap">
                                        <a href="{{ route('admin.users.edit', $u->id) }}" class="btn-ghost">Edit</a>

                                        {{-- ✅ tidak boleh hapus akun sendiri --}}
                                        @if((int)$u->id === $myId)
                                            <button type="button" class="btn-danger" disabled title="Tidak bisa hapus akun sendiri">Hapus</button>
                                        @else
                                            <form action="{{ route('admin.users.destroy', $u->id) }}"
                                                  method="POST"
                                                  onsubmit="return confirm('Yakin hapus user ini? Tindakan ini tidak bisa dibatalkan.')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn-danger">Hapus</button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="empty-row">Belum ada akun admin.</td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            {{-- PLANNER CARD (role operator) --}}
            <section class="role-card role-operator">
                <div class="role-head">
                    <div class="role-head-left">
                        <div class="role-title">PLANNER</div>
                        <span class="count-pill">{{ $operatorUsers->count() }} akun</span>
                    </div>

                    <span class="role-pill role-operator-pill">PLANNER</span>
                </div>

                <div class="table-wrap">
                    <table class="table">
                        <thead>
                        <tr>
                            <th style="width:28%;">Username</th>
                            <th style="width:28%;">Nama</th>
                            <th style="width:22%;">Role</th>
                            <th style="width:22%;">Aksi</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($operatorUsers as $u)
                            <tr>
                                <td class="td-strong">{{ $u->username }}</td>
                                <td>{{ $u->name }}</td>
                                <td><span class="role-pill role-operator-pill">PLANNER</span></td>
                                <td>
                                    <div class="aksi-wrap">
                                        <a href="{{ route('admin.users.edit', $u->id) }}" class="btn-ghost">Edit</a>

                                        {{-- ✅ tidak boleh hapus akun sendiri --}}
                                        @if((int)$u->id === $myId)
                                            <button type="button" class="btn-danger" disabled title="Tidak bisa hapus akun sendiri">Hapus</button>
                                        @else
                                            <form action="{{ route('admin.users.destroy', $u->id) }}"
                                                  method="POST"
                                                  onsubmit="return confirm('Yakin hapus user ini? Tindakan ini tidak bisa dibatalkan.')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn-danger">Hapus</button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="empty-row">Belum ada akun planner.</td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            {{-- DIRECTOR CARD --}}
            <section class="role-card role-director">
                <div class="role-head">
                    <div class="role-head-left">
                        <div class="role-title">DIRECTOR</div>
                        <span class="count-pill">{{ $directorUsers->count() }} akun</span>
                    </div>

                    <span class="role-pill role-director-pill">DIRECTOR</span>
                </div>

                <div class="table-wrap">
                    <table class="table">
                        <thead>
                        <tr>
                            <th style="width:28%;">Username</th>
                            <th style="width:28%;">Nama</th>
                            <th style="width:22%;">Role</th>
                            <th style="width:22%;">Aksi</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($directorUsers as $u)
                            <tr>
                                <td class="td-strong">{{ $u->username }}</td>
                                <td>{{ $u->name }}</td>
                                <td><span class="role-pill role-director-pill">DIRECTOR</span></td>
                                <td>
                                    <div class="aksi-wrap">
                                        {{-- ✅ ADMIN tidak boleh edit/delete director: popup global --}}
                                        @if($myRole === 'admin')
                                            <button type="button" class="btn-ghost"
                                                onclick="window.dispatchEvent(new CustomEvent('locked',{detail:{msg:'you cannot access this'}}));">
                                                Edit
                                            </button>
                                            <button type="button" class="btn-danger"
                                                onclick="window.dispatchEvent(new CustomEvent('locked',{detail:{msg:'you cannot access this'}}));">
                                                Hapus
                                            </button>
                                        @else
                                            <a href="{{ route('admin.users.edit', $u->id) }}" class="btn-ghost">Edit</a>

                                            {{-- ✅ tidak boleh hapus akun sendiri --}}
                                            @if((int)$u->id === $myId)
                                                <button type="button" class="btn-danger" disabled title="Tidak bisa hapus akun sendiri">Hapus</button>
                                            @else
                                                <form action="{{ route('admin.users.destroy', $u->id) }}"
                                                      method="POST"
                                                      onsubmit="return confirm('Yakin hapus user ini? Tindakan ini tidak bisa dibatalkan.')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn-danger">Hapus</button>
                                                </form>
                                            @endif
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="empty-row">Belum ada akun director.</td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

        </div>
    </div>

</div>

<style>
/* ===================== USER MANAGEMENT (SCOPED) ===================== */
.um-page *,
.um-page *::before,
.um-page *::after{ box-sizing:border-box; }

/* BACK */
.um-page .btn-back{
    display:inline-block;
    color:white;
    padding:10px 18px;
    border-radius:12px;
    background:#5f656a;
    font-weight:900;
    font-size:14px;
    text-decoration:none;
    transition:.18s;
    box-shadow:0 8px 18px rgba(0,0,0,.12);
    margin-bottom:18px;
}
.um-page .btn-back:hover{ background:#2b2d2f; transform:translateY(-1px); }

/* PAGE */
.um-page .page-card{
    background:#fff;
    border-radius:18px;
    padding:22px;
    box-shadow:0 14px 35px rgba(0,0,0,.08);
    overflow:hidden;
    width:100%;
    max-width:100%;
}
.um-page .page-head{
    display:flex;
    align-items:flex-start;
    justify-content:space-between;
    gap:14px;
    padding-bottom:14px;
    border-bottom:1px solid #eef2f7;
    margin-bottom:18px;
    width:100%;
}
.um-page .page-title{
    margin:0;
    font-size:30px;
    font-weight:1000;
    letter-spacing:.2px;
}
.um-page .page-subtitle{
    margin:6px 0 0 0;
    color:#6b7280;
    font-size:14px;
    font-weight:700;
}

/* BUTTON */
.um-page .btn-primary{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    padding:12px 16px;
    border-radius:14px;
    background:#0D6EFD;
    color:#fff;
    font-weight:1000;
    text-decoration:none;
    box-shadow:0 14px 24px rgba(13,110,253,.20);
    transition:.18s;
    white-space:nowrap;
}
.um-page .btn-primary:hover{ transform:translateY(-1px); filter:brightness(.98); }

.um-page .btn-ghost{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    padding:10px 14px;
    border-radius:12px;
    background:#6b7075;
    color:#fff;
    font-weight:1000;
    text-decoration:none;
    box-shadow:0 10px 18px rgba(0,0,0,.12);
    transition:.18s;

    border:0;
    cursor:pointer;
    appearance:none;
}
.um-page .btn-ghost:hover{ transform:translateY(-1px); filter:brightness(.98); }

/* ✅ DELETE BUTTON */
.um-page .btn-danger{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    padding:10px 14px;
    border-radius:12px;
    background:#C62828;
    color:#fff;
    font-weight:1000;
    text-decoration:none;
    box-shadow:0 10px 18px rgba(0,0,0,.12);
    transition:.18s;

    border:0;
    cursor:pointer;
    appearance:none;
}
.um-page .btn-danger:hover{ transform:translateY(-1px); filter:brightness(.98); }
.um-page .btn-danger:disabled{
    opacity:.55;
    cursor:not-allowed;
    transform:none;
}

/* ✅ ACTION WRAP (Edit + Hapus sejajar) */
.um-page .aksi-wrap{
    display:flex;
    justify-content:flex-end;
    align-items:center;
    gap:10px;
    flex-wrap:wrap;
}
.um-page .aksi-wrap form{ margin:0; }

/* ROLE STACK */
.um-page .roles-stack{
    display:flex;
    flex-direction:column;
    gap:16px;
}

/* ROLE CARD */
.um-page .role-card{
    background:#ffffff;
    border:1px solid #eef2f7;
    border-radius:18px;
    padding:16px;
    box-shadow:0 12px 28px rgba(0,0,0,.06);
}
.um-page .role-admin{ border-top:6px solid rgba(13,110,253,.85); }
.um-page .role-operator{ border-top:6px solid rgba(228,5,5,.85); }
.um-page .role-director{ border-top:6px solid rgba(159,129,112,.95); }

.um-page .role-head{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:12px;
    margin-bottom:12px;
}
.um-page .role-head-left{
    display:flex;
    align-items:center;
    gap:10px;
    flex-wrap:wrap;
}
.um-page .role-title{
    font-weight:1000;
    letter-spacing:.6px;
    font-size:16px;
    color:#111827;
}
.um-page .count-pill{
    display:inline-flex;
    align-items:center;
    padding:6px 10px;
    border-radius:999px;
    background:#f3f4f6;
    color:#374151;
    font-weight:900;
    font-size:12px;
}

/* ROLE PILL */
.um-page .role-pill{
    display:inline-flex;
    align-items:center;
    padding:8px 12px;
    border-radius:999px;
    font-weight:1000;
    font-size:12px;
    letter-spacing:.4px;
    border:1px solid transparent;
}
.um-page .role-admin-pill{
    background: rgba(13,110,253,.10);
    border-color: rgba(13,110,253,.22);
    color:#0D6EFD;
}
.um-page .role-operator-pill{
    background: rgba(228,5,5,.10);
    border-color: rgba(228,5,5,.22);
    color:#E40505;
}
.um-page .role-director-pill{
    background: rgba(159,129,112,.14);
    border-color: rgba(159,129,112,.28);
    color:#9F8170;
}

/* TABLE */
.um-page .table-wrap{
    border:1px solid #eef2f7;
    border-radius:14px;
    overflow:auto;
    background:#fbfdff;
}
.um-page .table{
    width:100%;
    border-collapse:separate;
    border-spacing:0;
    min-width:720px;
}
.um-page .table thead th{
    background:#f8fafc;
    text-align:left;
    padding:14px 16px;
    font-weight:1000;
    font-size:14px;
    color:#111827;
    border-bottom:1px solid #eef2f7;
}
.um-page .table tbody td{
    padding:14px 16px;
    border-bottom:1px solid #f1f5f9;
    vertical-align:middle;
    font-size:14px;
    color:#111827;
}
.um-page .table tbody tr:hover td{ background:#ffffff; }
.um-page .td-strong{ font-weight:1000; }
.um-page .empty-row{
    padding:18px !important;
    color:#6b7280 !important;
    font-weight:800;
}

/* Responsive header */
@media (max-width:700px){
    .um-page .page-head{
        flex-direction:column;
        align-items:stretch;
    }
    .um-page .btn-primary{
        width:100%;
        max-width:100%;
    }
}
</style>

@endsection
