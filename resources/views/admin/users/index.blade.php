@extends('layout')

@section('content')

{{-- BACK BUTTON --}}
<a href="{{ route('ccr.index') }}" class="btn-back">← Kembali</a>

@php
    // Pastikan $users dikirim dari controller: return view(..., compact('users'));
    $users = $users ?? collect();

    // Normalisasi & group by role
    $grouped = $users->groupBy(function($u){
        return strtolower($u->role ?? 'unknown');
    });

    $adminUsers    = $grouped->get('admin', collect());
    $operatorUsers = $grouped->get('operator', collect()); // tampil: PLANNER
    $directorUsers = $grouped->get('director', collect());

    $roleLabel = function($role){
        $role = strtolower($role);
        if ($role === 'operator') return 'PLANNER';
        return strtoupper($role);
    };
@endphp

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

    {{-- ROLE CARDS (1 kolom untuk semua device) --}}
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
                            <td>
                                <span class="role-pill role-admin-pill">ADMIN</span>
                            </td>
                            <td>
                                <a href="{{ route('admin.users.edit', $u->id) }}" class="btn-ghost">Edit</a>
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
                            <td>
                                <span class="role-pill role-operator-pill">PLANNER</span>
                            </td>
                            <td>
                                <a href="{{ route('admin.users.edit', $u->id) }}" class="btn-ghost">Edit</a>
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
                            <td>
                                <span class="role-pill role-director-pill">DIRECTOR</span>
                            </td>
                            <td>
                                <a href="{{ route('admin.users.edit', $u->id) }}" class="btn-ghost">Edit</a>
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


<style>
/* BACK */
.btn-back{
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
.btn-back:hover{ background:#2b2d2f; transform:translateY(-1px); }

/* PAGE */
.page-card{
    background:#fff;
    border-radius:18px;
    padding:22px;
    box-shadow:0 14px 35px rgba(0,0,0,.08);
}
.page-head{
    display:flex;
    align-items:flex-start;
    justify-content:space-between;
    gap:14px;
    padding-bottom:14px;
    border-bottom:1px solid #eef2f7;
    margin-bottom:18px;
}
.page-title{
    margin:0;
    font-size:30px;
    font-weight:1000;
    letter-spacing:.2px;
}
.page-subtitle{
    margin:6px 0 0 0;
    color:#6b7280;
    font-size:14px;
    font-weight:700;
}

/* BUTTON */
.btn-primary{
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
.btn-primary:hover{ transform:translateY(-1px); filter:brightness(.98); }

.btn-ghost{
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
}
.btn-ghost:hover{ transform:translateY(-1px); filter:brightness(.98); }

/* ROLE STACK: 1 kolom untuk semua device */
.roles-stack{
    display:flex;
    flex-direction:column;
    gap:16px;
}

/* ROLE CARD */
.role-card{
    background:#ffffff;
    border:1px solid #eef2f7;
    border-radius:18px;
    padding:16px;
    box-shadow:0 12px 28px rgba(0,0,0,.06);
}
.role-admin{ border-top:6px solid rgba(13,110,253,.85); }
.role-operator{ border-top:6px solid rgba(228,5,5,.85); }        /* #E40505 */
.role-director{ border-top:6px solid rgba(159,129,112,.95); }     /* #9F8170 */

.role-head{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:12px;
    margin-bottom:12px;
}
.role-head-left{
    display:flex;
    align-items:center;
    gap:10px;
    flex-wrap:wrap;
}
.role-title{
    font-weight:1000;
    letter-spacing:.6px;
    font-size:16px;
    color:#111827;
}
.count-pill{
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
.role-pill{
    display:inline-flex;
    align-items:center;
    padding:8px 12px;
    border-radius:999px;
    font-weight:1000;
    font-size:12px;
    letter-spacing:.4px;
    border:1px solid transparent;
}

/* Admin */
.role-admin-pill{
    background: rgba(13,110,253,.10);
    border-color: rgba(13,110,253,.22);
    color:#0D6EFD;
}

/* Planner (operator) */
.role-operator-pill{
    background: rgba(228,5,5,.10);
    border-color: rgba(228,5,5,.22);
    color:#E40505;
}

/* Director */
.role-director-pill{
    background: rgba(159,129,112,.14);
    border-color: rgba(159,129,112,.28);
    color:#9F8170;
}

/* TABLE */
.table-wrap{
    border:1px solid #eef2f7;
    border-radius:14px;
    overflow:auto;
    background:#fbfdff;
}
.table{
    width:100%;
    border-collapse:separate;
    border-spacing:0;
    min-width:720px; /* aman kalau user banyak & layar kecil */
}
.table thead th{
    background:#f8fafc;
    text-align:left;
    padding:14px 16px;
    font-weight:1000;
    font-size:14px;
    color:#111827;
    border-bottom:1px solid #eef2f7;
}
.table tbody td{
    padding:14px 16px;
    border-bottom:1px solid #f1f5f9;
    vertical-align:middle;
    font-size:14px;
    color:#111827;
}
.table tbody tr:hover td{ background:#ffffff; }
.td-strong{ font-weight:1000; }
.empty-row{
    padding:18px !important;
    color:#6b7280 !important;
    font-weight:800;
}

/* Responsive header */
@media (max-width:700px){
    .page-head{
        flex-direction:column;
        align-items:stretch;
    }
    .btn-primary{ width:100%; }
}

*{ box-sizing:border-box; }

.page-card{
  overflow:hidden;      /* cegah elemen “nyelonong” keluar */
  width:100%;
  max-width:100%;
}

.page-head{
  width:100%;
}

@media (max-width:700px){
  .btn-primary{
    width:100%;
    max-width:100%;
  }
}

</style>


@endsection
