@extends('layout')

@section('content')

{{-- BACK BUTTON --}}
<a href="{{ route('admin.users.index') }}" class="btn-back">← Kembali ke User Management</a>

<div class="page-card" data-page="user-edit">
    <div class="page-head">
        <div>
            <h1 class="page-title">Edit User: {{ $user->username }}</h1>
            <p class="page-subtitle">Ubah data akun.</p>
        </div>
    </div>

    {{-- ERROR BOX --}}
    @if ($errors->any())
        <div class="alert-error">
            <div class="alert-title">Periksa kembali input:</div>
            <ul class="alert-list">
                @foreach ($errors->all() as $e)
                    <li>{{ $e }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('admin.users.update', $user->id) }}" class="form">
        @csrf
        @method('PUT')

        {{-- ROW 1: Nama + Role (sejajar di desktop) --}}
        <div class="grid-2">
            <div class="field">
                <label class="label">Nama</label>
                <input class="input" name="name"
                       value="{{ old('name', $user->name) }}"
                       placeholder="Nama" required>
            </div>

            <div class="field">
                <label class="label">Role</label>
                <select class="input" name="role" required>
                    <option value="admin" @selected(old('role', $user->role) === 'admin')>Admin</option>
                    <option value="director" @selected(old('role', $user->role) === 'director')>Director</option>
                    <option value="operator" @selected(old('role', $user->role) === 'operator')>Planner</option>
                </select>
                <div class="hint">Catatan: “Planner” disimpan sebagai role <b>operator</b> di sistem.</div>
            </div>
        </div>

        {{-- ROW 2: Username (di bawah) --}}
        <div class="field">
            <label class="label">Username</label>
            <input class="input" name="username"
                   value="{{ old('username', $user->username) }}"
                   placeholder="username" required>
            <div class="hint">Catatan: jangan pakai username yang sama.</div>

        </div>

        <div class="divider"></div>

        {{-- Password (simple, tanpa confirm) --}}
        <div class="field">
            <label class="label">Password Baru</label>
            <input class="input" type="text" name="password"
                   value="{{ old('password') }}"
                   placeholder="password (kosongkan jika tidak diganti)">
            <div class="hint">Saran: pakai password yang gampang diingat tapi jangan terlalu umum.</div>
        </div>

        <div class="actions">
            <button class="btn-save" type="submit">Update</button>
        </div>
    </form>
</div>

<style>
*{ box-sizing:border-box; }

/* BACK */
.btn-back{
    display:inline-block;
    color:#fff;
    padding:12px 18px;
    border-radius:14px;
    background:#5f656a;
    font-weight:1000;
    font-size:14px;
    text-decoration:none;
    transition:.18s;
    box-shadow:0 10px 24px rgba(0,0,0,.12);
    margin-bottom:18px;
}
.btn-back:hover{ background:#2b2d2f; transform:translateY(-1px); }

/* CARD */
.page-card{
    background:#fff;
    border-radius:18px;
    padding:22px;
    box-shadow:0 16px 40px rgba(0,0,0,.10);
    width:100%;
    max-width:980px;   /* ✅ lebih lega di desktop */
    margin:0 auto;
    overflow:hidden;
}
.page-head{ margin-bottom:10px; }
.page-title{ margin:0; font-size:42px; font-weight:1100; letter-spacing:.2px; }
.page-subtitle{ margin:10px 0 0 0; color:#6b7280; font-size:16px; font-weight:800; }

/* ALERT */
.alert-error{
    background: rgba(228,5,5,.08);
    border:1px solid rgba(228,5,5,.22);
    color:#7a1010;
    border-radius:16px;
    padding:16px;
    margin:16px 0 18px 0;
}
.alert-title{ font-weight:1000; margin-bottom:8px; font-size:16px; }
.alert-list{ margin:0; padding-left:20px; font-weight:800; }

/* FORM */
.form{ display:flex; flex-direction:column; gap:16px; }
.field{ display:flex; flex-direction:column; gap:8px; }
.label{ font-weight:1000; font-size:16px; color:#111827; }

.input{
    width:100%;
    padding:14px 16px;
    border-radius:16px;
    border:1px solid #d1d5db;
    background:#fafafa;
    font-size:16px;
    outline:none;
}
.input:focus{
    background:#fff;
    border-color: rgba(13,110,253,.45);
    box-shadow:0 0 0 5px rgba(13,110,253,.12);
}

.hint{
    font-size:13px;
    color:#6b7280;
    font-weight:800;
    line-height:1.35;
}

.divider{
    height:1px;
    background:#eef2f7;
    margin:4px 0;
}

/* GRID desktop */
.grid-2{
    display:grid;
    grid-template-columns: 1fr 1fr;
    gap:16px;
    align-items:start;
}

/* ACTIONS */
.actions{
    display:flex;
    justify-content:flex-end;
    margin-top:4px;
}
.btn-save{
    border:none;
    cursor:pointer;
    padding:14px 22px;
    border-radius:16px;
    font-weight:1100;
    font-size:18px;
    color:#fff;
    background:#0D6EFD;
    box-shadow:0 18px 35px rgba(13,110,253,.22);
    transition:.18s;
    min-width:180px;
}
.btn-save:hover{ transform:translateY(-1px); filter:brightness(.98); }

/* Responsive */
@media (max-width:900px){
    .page-card{ max-width:760px; }
    .grid-2{ grid-template-columns:1fr; }
    .actions{ justify-content:stretch; }
    .btn-save{ width:100%; min-width:auto; }
}
@media (max-width:600px){
    .page-card{ padding:18px; border-radius:16px; }
    .page-title{ font-size:34px; }
    .page-subtitle{ font-size:15px; }
}
</style>

@endsection
