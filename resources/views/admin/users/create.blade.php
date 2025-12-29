@extends('layout')

@section('content')

<a href="{{ route('admin.users.index') }}" class="btn-back">← Kembali ke User Management</a>

<div class="page-card" data-page="user-create">
    <div class="page-head">
        <div>
            <h1 class="page-title">Tambah User</h1>
            <p class="page-subtitle">Buat akun baru untuk Admin / Director / Planner.</p>
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

    <form action="{{ route('admin.users.store') }}" method="POST" class="form">
        @csrf

        {{-- ✅ GRID: desktop 2 kolom, tablet/mobile 1 kolom --}}
        <div class="form-grid">

            {{-- ROW 1: Nama + Role (sejajar) --}}
            <div class="field">
                <label class="label">Nama</label>
                <input type="text" name="name" class="input"
                    value="{{ old('name') }}"
                    placeholder="Nama" required>
            </div>

            <div class="field">
                <label class="label">Role</label>
                <select name="role" class="input" required>
                    <option value="admin" {{ old('role')=='admin' ? 'selected' : '' }}>Admin</option>
                    <option value="director" {{ old('role')=='director' ? 'selected' : '' }}>Director</option>
                    <option value="operator" {{ old('role')=='operator' ? 'selected' : '' }}>Planner</option>
                </select>
                <div class="hint">Catatan: “Planner” disimpan sebagai role <b>operator</b> di sistem.</div>
            </div>

            {{-- ROW 2: Username (full bawah) --}}
            <div class="field span-2">
                <label class="label">Username</label>
                <input type="text" name="username" class="input"
                    value="{{ old('username') }}"
                    placeholder="username" required>
                <div class="hint">Catatan: jangan pakai username yang sama.</div>

            </div>

            {{-- Password (full) --}}
            <div class="field span-2">
                <label class="label">Password</label>
                <input type="text" name="password" class="input"
                    value="{{ old('password') }}"
                    placeholder="password" required>
                <div class="hint">Saran: pakai yang gampang diingat tapi jangan terlalu umum.</div>
            </div>

        </div>


        <div class="form-actions">
            <button type="submit" class="btn-save">Simpan</button>
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
    font-weight:1100;
    font-size:14px;
    text-decoration:none;
    transition:.18s;
    box-shadow:0 10px 24px rgba(0,0,0,.12);
    margin-bottom:18px;
}
.btn-back:hover{ background:#2b2d2f; transform:translateY(-1px); }

/* PAGE CARD (✅ FULL WIDTH di desktop, nggak sempit di tengah) */
.page-card{
    background:#fff;
    border-radius:18px;
    padding:24px;
    box-shadow:0 16px 40px rgba(0,0,0,.10);
    width:100%;
    max-width:none;   /* ✅ hapus max-width biar nggak “narrow” */
    margin:0;         /* ✅ jangan auto-center */
    overflow:hidden;
}

.page-head{
    display:flex;
    align-items:flex-end;
    justify-content:space-between;
    gap:14px;
    padding-bottom:16px;
    border-bottom:1px solid #eef2f7;
    margin-bottom:18px;
}

.page-title{
    margin:0;
    font-size:44px;
    font-weight:1200;
    letter-spacing:.2px;
}
.page-subtitle{
    margin:10px 0 0 0;
    color:#6b7280;
    font-size:16px;
    font-weight:900;
}

/* ALERT */
.alert-error{
    background: rgba(228,5,5,.08);
    border:1px solid rgba(228,5,5,.22);
    color:#7a1010;
    border-radius:16px;
    padding:16px;
    margin-bottom:18px;
}
.alert-title{ font-weight:1200; margin-bottom:8px; font-size:16px; }
.alert-list{ margin:0; padding-left:20px; font-weight:900; }

/* FORM */
.form{ width:100%; }

.form-grid{
    display:grid;
    grid-template-columns: 1fr 1fr; /* ✅ desktop 2 kolom */
    gap:16px;
}

.span-2{ grid-column: 1 / -1; }

.field{ display:flex; flex-direction:column; gap:8px; }
.label{ font-weight:1200; font-size:16px; color:#111827; }

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
    font-size:14px;
    color:#6b7280;
    font-weight:800;
}

.form-actions{
    margin-top:18px;
    display:flex;
    justify-content:flex-end;
}

/* BUTTON */
.btn-save{
    border:none;
    cursor:pointer;
    padding:14px 26px;
    border-radius:16px;
    font-weight:1200;
    font-size:18px;
    color:#fff;
    background:#0D6EFD;
    box-shadow:0 18px 35px rgba(13,110,253,.22);
    transition:.18s;
    min-width:220px;
}
.btn-save:hover{ transform:translateY(-1px); filter:brightness(.98); }

/* RESPONSIVE: tablet/mobile jadi 1 kolom + tombol full */
@media (max-width:1024px){
    .form-grid{ grid-template-columns: 1fr; }
    .span-2{ grid-column:auto; }
    .form-actions{ justify-content:stretch; }
    .btn-save{ width:100%; min-width:0; }
}
@media (max-width:600px){
    .page-card{ padding:18px; border-radius:16px; }
    .page-title{ font-size:36px; }
}
</style>

@endsection
